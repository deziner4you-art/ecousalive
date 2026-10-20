<?php

/*
=====================================================
TASK MODEL
All task-related SQL queries extracted from monolith
=====================================================
*/

class Task {

    /**
     * Service helpers keep the two workflows separate while retaining the
     * existing task columns for backwards compatibility.
     */
    public static function isAplusService(array $task): bool {
        /* Service-level state is authoritative for products with separate
           Info/A+ phases. This prevents a later generic content status such
           as Approved from changing an A+ task back into an Info task. */
        if (!empty($task['active_service_code'])) {
            return $task['active_service_code'] === TaskService::APLUS;
        }
        if (!empty($task['id'])) {
            try {
                $active = TaskService::activeCode((int)$task['id']);
                if ($active !== null) return $active === TaskService::APLUS;
            } catch (Throwable $e) {}
        }
        $ws = $task['work_status'] ?? '';
        if ($ws === 'Info Done') {
            return false;
        }
        $pt = $task['product_type'] ?? '';
        $st = $task['status'] ?? '';
        if ($pt === 'Infographics') {
            return false;
        }
        if ($ws === 'SEO Review' || ($ws === 'Work Done' && !empty($task['seo_doc_link']) && !empty($task['seo_submitted_by']))) {
            return true;
        }
        if ($st === 'A Plus') {
            return true;
        }
        if ($pt === 'A+' || $pt === 'A Plus') {
            return true;
        }
        return false;
    }

    public static function isInfoService(array $task): bool {
        if (!empty($task['active_service_code'])) {
            return $task['active_service_code'] === TaskService::INFO;
        }
        if (!empty($task['id'])) {
            try {
                $active = TaskService::activeCode((int)$task['id']);
                if ($active !== null) return $active === TaskService::INFO;
            } catch (Throwable $e) {}
        }
        if (($task['status'] ?? '') === 'AI Work' || ($task['status'] ?? '') === 'AI DONE' || ($task['work_status'] ?? '') === 'AI In QA') {
            return false;
        }
        return !self::isAplusService($task);
    }

    public static function isAIService(array $task): bool {
        if (!empty($task['active_service_code'])) {
            return $task['active_service_code'] === TaskService::AI;
        }
        if (!empty($task['id'])) {
            try {
                return TaskService::activeCode((int)$task['id']) === TaskService::AI;
            } catch (Throwable $e) {}
        }
        return ($task['status'] ?? '') === 'AI Work'
            || ($task['status'] ?? '') === 'AI DONE'
            || ($task['work_status'] ?? '') === 'AI In QA';
    }

    public static function activeServiceCode(array $task): string {
        if (!empty($task['active_service_code'])) return (string)$task['active_service_code'];
        if (!empty($task['id'])) {
            try { $active = TaskService::activeCode((int)$task['id']); if ($active) return $active; } catch (Throwable $e) {}
        }
        return self::isAplusService($task) ? TaskService::APLUS : TaskService::INFO;
    }

    private static function activeServiceCodeById(int $taskId): string {
        try { $active = TaskService::activeCode($taskId); if ($active) return $active; } catch (Throwable $e) {}
        $stmt = db()->prepare("SELECT id, product_type, status, work_status, seo_doc_link, seo_submitted_by FROM wp_eco_aplus_tasks WHERE id=?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return self::activeServiceCode($task);
    }

    /*
    ──────────────────────────────────────────────────
    getTasksForRole()
    Role-aware task fetch (exact SQL from monolith)
    ──────────────────────────────────────────────────
    */
    public static function getTasksForRole(array $user): array {

        $cols = "
            t.*,
            v.name AS vendor_name,
            v.color AS vendor_color,
            a.worker_id AS assigned_worker_id,
            q.username AS qa_user_name,
            cw.username AS work_completed_worker_name,
            pu.username AS published_by_name,
            aiw.username AS ai_worked_by_name,
            COALESCE(
                CASE WHEN infow.role = 'worker' THEN infow.username END,
                CASE WHEN cw.role = 'worker' AND (t.product_type = 'Infographics' OR t.product_type = 'Info + A Plus') THEN cw.username END,
                CASE WHEN aw.role = 'worker' AND (t.product_type = 'Infographics' OR (t.product_type = 'Info + A Plus' AND t.status != 'A Plus')) THEN aw.username END
            ) AS info_worker_name,
            COALESCE(
                CASE WHEN aplusw.role = 'worker' THEN aplusw.username END,
                CASE WHEN (t.product_type = 'A Plus' OR t.product_type = 'A+') AND cw.role = 'worker' THEN cw.username END,
                CASE WHEN (t.product_type = 'A Plus' OR t.product_type = 'A+') AND aw.role = 'worker' THEN aw.username END
            ) AS aplus_worker_name,
            t.work_status, t.work_started_at, t.work_completed_at,
            t.work_paused_at, t.work_total_seconds,
            (
                SELECT p.status
                FROM eco_payslips p
                JOIN eco_payslip_items pi ON p.id = pi.payslip_id
                WHERE pi.task_id = t.id
                ORDER BY p.id DESC
                LIMIT 1
            ) AS payslip_status,
            (
                SELECT GROUP_CONCAT(DISTINCT sc.invoice_no ORDER BY sc.id DESC SEPARATOR ', ')
                FROM eco_task_service_charges sc
                WHERE sc.task_id = t.id AND sc.service_type = 'info'
            ) AS info_invoice_numbers,
            (
                SELECT GROUP_CONCAT(DISTINCT sc.invoice_no ORDER BY sc.id DESC SEPARATOR ', ')
                FROM eco_task_service_charges sc
                WHERE sc.task_id = t.id AND sc.service_type = 'aplus'
            ) AS aplus_invoice_numbers,
            (
                SELECT GROUP_CONCAT(DISTINCT i.invoice_no ORDER BY i.id DESC SEPARATOR ', ')
                FROM eco_invoice_items ii
                JOIN eco_invoices i ON i.id = ii.invoice_id
                WHERE ii.task_id = t.id
             ) AS invoice_numbers,
             (
                 SELECT ts.service_code FROM eco_task_services ts
                 WHERE ts.task_id=t.id AND ts.phase_status NOT IN ('Done','Cancelled')
                 ORDER BY CASE WHEN ts.phase_status IN ('Revision','Working','Paused','In QA','SEO Review') THEN 100 ELSE 0 END DESC,
                          CASE ts.service_code WHEN 'ai' THEN 3 WHEN 'aplus' THEN 2 ELSE 1 END DESC, ts.id DESC LIMIT 1
             ) AS active_service_code,
             (SELECT ts.phase_status FROM eco_task_services ts WHERE ts.task_id=t.id AND ts.phase_status NOT IN ('Done','Cancelled') ORDER BY CASE WHEN ts.phase_status IN ('Revision','Working','Paused','In QA','SEO Review') THEN 100 ELSE 0 END DESC, CASE ts.service_code WHEN 'ai' THEN 3 WHEN 'aplus' THEN 2 ELSE 1 END DESC, ts.id DESC LIMIT 1) AS active_service_phase_status,
             (SELECT ts.content_status FROM eco_task_services ts WHERE ts.task_id=t.id AND ts.phase_status NOT IN ('Done','Cancelled') ORDER BY CASE WHEN ts.phase_status IN ('Revision','Working','Paused','In QA','SEO Review') THEN 100 ELSE 0 END DESC, CASE ts.service_code WHEN 'ai' THEN 3 WHEN 'aplus' THEN 2 ELSE 1 END DESC, ts.id DESC LIMIT 1) AS active_service_content_status,
             (SELECT ts.phase_status FROM eco_task_services ts WHERE ts.task_id=t.id AND ts.service_code='info' LIMIT 1) AS info_phase_status,
             (SELECT ts.phase_status FROM eco_task_services ts WHERE ts.task_id=t.id AND ts.service_code='ai' LIMIT 1) AS ai_phase_status,
             (SELECT ts.phase_status FROM eco_task_services ts WHERE ts.task_id=t.id AND ts.service_code='aplus' LIMIT 1) AS aplus_phase_status,
             (SELECT ts.content_status FROM eco_task_services ts WHERE ts.task_id=t.id AND ts.service_code='info' LIMIT 1) AS info_content_status,
             (SELECT ts.content_status FROM eco_task_services ts WHERE ts.task_id=t.id AND ts.service_code='ai' LIMIT 1) AS ai_content_status,
             (SELECT ts.content_status FROM eco_task_services ts WHERE ts.task_id=t.id AND ts.service_code='aplus' LIMIT 1) AS aplus_content_status,
             (SELECT ts.worker_id FROM eco_task_services ts WHERE ts.task_id=t.id AND ts.service_code='ai' LIMIT 1) AS ai_service_worker_id,
             (SELECT ts.media_link FROM eco_task_services ts WHERE ts.task_id=t.id AND ts.service_code='info' LIMIT 1) AS info_media_link,
             (SELECT ts.media_link FROM eco_task_services ts WHERE ts.task_id=t.id AND ts.service_code='aplus' LIMIT 1) AS aplus_media_link,
             (SELECT ts.document_link FROM eco_task_services ts WHERE ts.task_id=t.id AND ts.service_code='aplus' LIMIT 1) AS aplus_seo_doc_link,
             EXISTS(SELECT 1 FROM eco_task_services ts WHERE ts.task_id=t.id AND ts.service_code='info' AND ts.phase_status='Done') AS info_completed,
             EXISTS(SELECT 1 FROM eco_task_services ts WHERE ts.task_id=t.id AND ts.service_code='aplus' AND ts.phase_status='Done') AS aplus_completed
        ";

        $uname = strtolower($user['username'] ?? '');
        if ($user['role'] === 'ai_work' || $uname === 'irfan') {
            $stmt = db()->query("
            SELECT $cols
            FROM wp_eco_aplus_tasks t
            LEFT JOIN eco_vendors v ON v.id = t.vendor_id
            LEFT JOIN eco_tool_assignments a ON a.task_id = t.id
            LEFT JOIN eco_tool_users aw ON aw.id = a.worker_id
            LEFT JOIN eco_tool_users q  ON q.id  = t.qa_submitted_by
            LEFT JOIN eco_tool_users cw ON cw.id = t.work_completed_by_worker_id
            LEFT JOIN eco_tool_users pu ON pu.id = t.published_by
            LEFT JOIN eco_tool_users aiw ON aiw.id = t.ai_worked_by
            LEFT JOIN eco_tool_users infow ON infow.id = t.info_worker_id
            LEFT JOIN eco_tool_users aplusw ON aplusw.id = t.aplus_worker_id
            WHERE t.deleted_at IS NULL AND t.status != 'No Charge'
            ORDER BY t.id DESC
            ");
            return $stmt->fetchAll();
        }

        switch($user['role']){

            case 'worker':
                $stmt = db()->prepare("
                SELECT $cols
                FROM wp_eco_aplus_tasks t
                LEFT JOIN eco_vendors v ON v.id = t.vendor_id
                LEFT JOIN eco_tool_assignments a ON a.task_id = t.id
                LEFT JOIN eco_tool_users aw ON aw.id = a.worker_id
                LEFT JOIN eco_tool_users q  ON q.id  = t.qa_submitted_by
                LEFT JOIN eco_tool_users cw ON cw.id = t.work_completed_by_worker_id
                LEFT JOIN eco_tool_users pu ON pu.id = t.published_by
                LEFT JOIN eco_tool_users aiw ON aiw.id = t.ai_worked_by
                LEFT JOIN eco_tool_users infow ON infow.id = t.info_worker_id
                LEFT JOIN eco_tool_users aplusw ON aplusw.id = t.aplus_worker_id
                WHERE t.deleted_at IS NULL AND t.status NOT IN ('Hold','No Charge')
                  AND (
                      a.worker_id = ?
                      OR t.info_worker_id = ?
                      OR t.aplus_worker_id = ?
                      OR t.work_completed_by_worker_id = ?
                  )
                ORDER BY t.id DESC
                ");
                $stmt->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
                break;

            case 'qa':
                $stmt = db()->prepare("
                SELECT $cols
                FROM wp_eco_aplus_tasks t
                LEFT JOIN eco_vendors v ON v.id = t.vendor_id
                LEFT JOIN eco_tool_assignments a ON a.task_id = t.id
                LEFT JOIN eco_tool_users aw ON aw.id = a.worker_id
                LEFT JOIN eco_tool_users q  ON q.id  = t.qa_submitted_by
                LEFT JOIN eco_tool_users cw ON cw.id = t.work_completed_by_worker_id
                LEFT JOIN eco_tool_users pu ON pu.id = t.published_by
                LEFT JOIN eco_tool_users aiw ON aiw.id = t.ai_worked_by
                LEFT JOIN eco_tool_users infow ON infow.id = t.info_worker_id
                LEFT JOIN eco_tool_users aplusw ON aplusw.id = t.aplus_worker_id
                WHERE t.deleted_at IS NULL AND t.status NOT IN ('Hold','No Charge')
                  AND (
                      t.work_status IN ('In QA','AI In QA')
                      OR t.work_status = 'SEO Review'
                      OR t.work_status = 'Info Done'
                      OR t.qa_submitted_by = ?
                      OR (t.family_code IS NOT NULL AND t.family_code IN (
                          SELECT DISTINCT t2.family_code
                          FROM wp_eco_aplus_tasks t2
                          WHERE t2.family_code IS NOT NULL
                            AND t2.deleted_at IS NULL
                            AND (t2.work_status IN ('In QA','AI In QA') OR t2.work_status = 'SEO Review' OR t2.work_status = 'Info Done' OR t2.qa_submitted_by = ?)
                      ))
                  )
                ORDER BY t.id DESC
                ");
                $stmt->execute([$user['id'], $user['id']]);
                break;

            case 'd4u_writer':
            case 'seo_manager':
                $stmt = db()->prepare("
                SELECT $cols
                FROM wp_eco_aplus_tasks t
                LEFT JOIN eco_vendors v ON v.id = t.vendor_id
                LEFT JOIN eco_tool_assignments a ON a.task_id = t.id
                LEFT JOIN eco_tool_users aw ON aw.id = a.worker_id
                LEFT JOIN eco_tool_users q  ON q.id  = t.qa_submitted_by
                LEFT JOIN eco_tool_users cw ON cw.id = t.work_completed_by_worker_id
                LEFT JOIN eco_tool_users pu ON pu.id = t.published_by
                LEFT JOIN eco_tool_users aiw ON aiw.id = t.ai_worked_by
                LEFT JOIN eco_tool_users infow ON infow.id = t.info_worker_id
                LEFT JOIN eco_tool_users aplusw ON aplusw.id = t.aplus_worker_id
                WHERE t.deleted_at IS NULL AND t.status NOT IN ('Hold','No Charge')
                  AND (
                      t.written_by_user_id = ?
                      OR t.status IN ('Pending', 'A Plus')
                      OR t.work_status = 'SEO Review'
                      OR t.qa_submitted_by = ?
                      OR (t.family_code IS NOT NULL AND t.family_code IN (
                          SELECT DISTINCT t2.family_code
                          FROM wp_eco_aplus_tasks t2
                          WHERE t2.family_code IS NOT NULL
                            AND t2.deleted_at IS NULL AND t2.status NOT IN ('Hold','No Charge')
                            AND (
                                t2.written_by_user_id = ?
                                OR t2.status IN ('Pending', 'A Plus')
                                OR t2.work_status = 'SEO Review'
                                OR t2.qa_submitted_by = ?
                            )
                      ))
                  )
                ORDER BY t.id DESC
                ");
                $stmt->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
                break;

            case 'ai_work':
                $stmt = db()->query("
                SELECT $cols
                FROM wp_eco_aplus_tasks t
                LEFT JOIN eco_vendors v ON v.id = t.vendor_id
                LEFT JOIN eco_tool_assignments a ON a.task_id = t.id
                LEFT JOIN eco_tool_users aw ON aw.id = a.worker_id
                LEFT JOIN eco_tool_users q  ON q.id  = t.qa_submitted_by
                LEFT JOIN eco_tool_users cw ON cw.id = t.work_completed_by_worker_id
                LEFT JOIN eco_tool_users pu ON pu.id = t.published_by
                LEFT JOIN eco_tool_users aiw ON aiw.id = t.ai_worked_by
                LEFT JOIN eco_tool_users infow ON infow.id = t.info_worker_id
                LEFT JOIN eco_tool_users aplusw ON aplusw.id = t.aplus_worker_id
                WHERE t.deleted_at IS NULL AND t.status != 'No Charge'
                ORDER BY t.id DESC
                ");
                break;

            case 'eco_listing':
                $stmt = db()->prepare("
                SELECT $cols
                FROM wp_eco_aplus_tasks t
                LEFT JOIN eco_vendors v ON v.id = t.vendor_id
                LEFT JOIN eco_tool_assignments a ON a.task_id = t.id
                LEFT JOIN eco_tool_users aw ON aw.id = a.worker_id
                LEFT JOIN eco_tool_users q  ON q.id  = t.qa_submitted_by
                LEFT JOIN eco_tool_users cw ON cw.id = t.work_completed_by_worker_id
                LEFT JOIN eco_tool_users pu ON pu.id = t.published_by
                LEFT JOIN eco_tool_users aiw ON aiw.id = t.ai_worked_by
                LEFT JOIN eco_tool_users infow ON infow.id = t.info_worker_id
                LEFT JOIN eco_tool_users aplusw ON aplusw.id = t.aplus_worker_id
                WHERE t.deleted_at IS NULL AND t.status != 'No Charge'
                ORDER BY t.id DESC
                ");
                $stmt->execute([]);
                break;

            default: /* administrator, eco_client — see everything including Hold, but NOT deleted */
                $stmt = db()->query("
                SELECT $cols
                FROM wp_eco_aplus_tasks t
                LEFT JOIN eco_vendors v ON v.id = t.vendor_id
                LEFT JOIN eco_tool_assignments a ON a.task_id = t.id
                LEFT JOIN eco_tool_users aw ON aw.id = a.worker_id
                LEFT JOIN eco_tool_users q  ON q.id  = t.qa_submitted_by
                LEFT JOIN eco_tool_users cw ON cw.id = t.work_completed_by_worker_id
                LEFT JOIN eco_tool_users pu ON pu.id = t.published_by
                LEFT JOIN eco_tool_users aiw ON aiw.id = t.ai_worked_by
                LEFT JOIN eco_tool_users infow ON infow.id = t.info_worker_id
                LEFT JOIN eco_tool_users aplusw ON aplusw.id = t.aplus_worker_id
                WHERE t.deleted_at IS NULL
                ORDER BY t.id DESC
                ");
                break;
        }

        return $stmt->fetchAll();

    }

    /*
    ──────────────────────────────────────────────────
    updateWorkStatus()
    ──────────────────────────────────────────────────
    */
    public static function updateWorkStatus(int $taskId, string $status, array $user): bool {

        $chargeStmt = db()->prepare("SELECT status FROM wp_eco_aplus_tasks WHERE id=?");
        $chargeStmt->execute([$taskId]);
        if ($chargeStmt->fetchColumn() === 'No Charge') return false;

        /* Check assignment for non-admin workers */
        if($user['role'] !== 'administrator'){
            $s = db()->prepare("SELECT id FROM eco_tool_assignments WHERE task_id=? AND worker_id=?");
            $s->execute([$taskId, $user['id']]);
            if(!$s->fetch()) return false;
        }

        if(($user['role'] === 'ai_work' || $user['role'] === 'administrator') && ($status === 'Work Done' || $status === 'AI DONE')){
            $tStmt = db()->prepare("SELECT status, ai_worked_by FROM wp_eco_aplus_tasks WHERE id=?");
            $tStmt->execute([$taskId]);
            $tRow = $tStmt->fetch();

            if($tRow && ($tRow['status'] === 'AI Work' || $user['role'] === 'ai_work' || $status === 'AI DONE')){
                $aiWorkerId = ($user['role'] === 'ai_work') ? $user['id'] : $tRow['ai_worked_by'];
                if(!$aiWorkerId){
                    $s = db()->prepare("SELECT worker_id FROM eco_tool_assignments WHERE task_id=?");
                    $s->execute([$taskId]);
                    $aiWorkerId = $s->fetchColumn() ?: null;
                }
                db()->prepare("UPDATE wp_eco_aplus_tasks SET status='AI DONE', work_status='Pending', work_paused_at=NULL, work_started_at=NULL, ai_worked_by=?, last_activity_at=NOW() WHERE id=?")->execute([$aiWorkerId, $taskId]);
                TaskService::ensure($taskId, TaskService::AI, ['worker_id' => $aiWorkerId]);
                TaskService::setPhase($taskId, TaskService::AI, 'Done', [
                    'worker_id' => $aiWorkerId,
                    'completed_by' => $aiWorkerId,
                    'actor_id' => (int)($user['id'] ?? 0),
                ]);
                // Remove assignment so it goes back to pool for next worker
                db()->prepare("DELETE FROM eco_tool_assignments WHERE task_id=?")->execute([$taskId]);
                return true;
            }
        }

        /* AI workers now send their work to QA.  AI Done is reserved for QA
           approval; the assignment remains active until QA accepts it. */
        if($status === 'AI In QA'){
            if(!in_array($user['role'] ?? '', ['ai_work', 'administrator'], true)) return false;
            $row = db()->prepare("SELECT ai_worked_by, work_started_at, work_paused_at, work_total_seconds FROM wp_eco_aplus_tasks WHERE id=?");
            $row->execute([$taskId]);
            $t = $row->fetch() ?: [];
            $totalSecs = intval($t['work_total_seconds'] ?? 0);
            if(!empty($t['work_started_at']) && empty($t['work_paused_at'])){
                $totalSecs += max(0, time() - strtotime($t['work_started_at']));
            }
            $workerId = (int)($t['ai_worked_by'] ?? $user['id'] ?? 0) ?: null;
            db()->prepare("UPDATE wp_eco_aplus_tasks SET status='AI Work', work_status='AI In QA', work_completed_at=NOW(), work_total_seconds=?, work_completed_by_worker_id=?, qa_submitted_at=NOW(), qa_submitted_by=NULL, last_activity_at=NOW() WHERE id=?")
                ->execute([$totalSecs, $workerId, $taskId]);
            TaskService::ensure($taskId, TaskService::AI, ['worker_id' => $workerId]);
            TaskService::setPhase($taskId, TaskService::AI, 'In QA', [
                'worker_id' => $workerId,
                'completed_by' => $workerId,
                'work_total_seconds' => $totalSecs,
                'actor_id' => (int)($user['id'] ?? 0),
            ]);
            return true;
        }

        if($status === 'Changing'){
            db()->prepare("UPDATE wp_eco_aplus_tasks SET work_status='Changing', work_started_at=NOW(), work_paused_at=NULL, last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
            TaskService::setPhase($taskId, self::activeServiceCodeById($taskId), 'Working', ['actor_id'=>(int)($user['id'] ?? 0)]);
            return true;
        }

        if($status === 'Info Work'){
            db()->prepare("UPDATE wp_eco_aplus_tasks SET work_status='Info Work', last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
            TaskService::setPhase($taskId, TaskService::INFO, 'Working', ['actor_id'=>(int)($user['id'] ?? 0)]);
            return true;
        }

        if($status === 'Working'){
            db()->prepare("UPDATE wp_eco_aplus_tasks SET work_status='Working', work_started_at=NOW(), work_paused_at=NULL, last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
            TaskService::setPhase($taskId, self::activeServiceCodeById($taskId), 'Working', ['actor_id'=>(int)($user['id'] ?? 0)]);
            return true;
        }

        if($status === 'In QA'){

            $row = db()->prepare("SELECT id, status, product_type, work_status, info_subtasks, content_approved_at, content_updated_at, seo_doc_link, work_started_at, work_paused_at, work_total_seconds, info_worker_id, aplus_worker_id FROM wp_eco_aplus_tasks WHERE id=?");
            $row->execute([$taskId]);
            $t = $row->fetch();

            if(self::isAIService($t ?: [])){
                return self::updateWorkStatus($taskId, 'AI In QA', $user);
            }

            $totalSecs = intval($t['work_total_seconds']);
            if($t['work_started_at'] && !$t['work_paused_at']){
                $totalSecs += (time() - strtotime($t['work_started_at']));
            }

            $completedBy = ($user['role'] === 'worker') ? $user['id'] : null;
            if(!$completedBy){
                $aRow = db()->prepare("SELECT worker_id FROM eco_tool_assignments WHERE task_id=?");
                $aRow->execute([$taskId]);
                $aFetch = $aRow->fetch();
                if($aFetch) $completedBy = $aFetch['worker_id'];
            }

            $extraSql = "";
            $extraParams = [];
            if($completedBy){
                if(self::isAplusService($t ?: [])){
                    $extraSql .= ", aplus_worker_id = ?";
                    $extraParams[] = $completedBy;
                } else {
                    $extraSql .= ", info_worker_id = ?";
                    $extraParams[] = $completedBy;
                }
            }

            $sql = "
                UPDATE wp_eco_aplus_tasks
                SET work_status='In QA', work_completed_at=NOW(), work_total_seconds=?,
                    work_completed_by_worker_id=?, last_activity_at=NOW() $extraSql
                WHERE id=?
            ";
            $params = array_merge([$totalSecs, $completedBy], $extraParams, [$taskId]);
            db()->prepare($sql)->execute($params);
            $serviceCode = self::activeServiceCode($t ?: []);
            TaskService::setPhase($taskId, $serviceCode, 'In QA', [
                'worker_id' => $completedBy,
                'completed_by' => $completedBy,
                'work_total_seconds' => $totalSecs,
                'actor_id' => (int)($user['id'] ?? 0),
            ]);
            return true;
        }

        return false;

    }

    /*
    ──────────────────────────────────────────────────
    pauseWork()
    ──────────────────────────────────────────────────
    */
    public static function pauseWork(int $taskId, array $user): array {

        if($user['role'] !== 'administrator'){
            $s = db()->prepare("SELECT id FROM eco_tool_assignments WHERE task_id=? AND worker_id=?");
            $s->execute([$taskId, $user['id']]);
            if(!$s->fetch()) return ['ok' => false];
        }

        $row = db()->prepare("SELECT work_started_at, work_total_seconds FROM wp_eco_aplus_tasks WHERE id=?");
        $row->execute([$taskId]);
        $t = $row->fetch();

        $sessionSecs = 0;
        if($t['work_started_at']){
            $sessionSecs = time() - strtotime($t['work_started_at']);
        }
        $newTotal = intval($t['work_total_seconds']) + $sessionSecs;

        db()->prepare("
            UPDATE wp_eco_aplus_tasks
            SET work_status='Paused', work_paused_at=NOW(), work_total_seconds=?, last_activity_at=NOW()
            WHERE id=?
        ")->execute([$newTotal, $taskId]);
        TaskService::setPhase($taskId, self::activeServiceCodeById($taskId), 'Paused', [
            'work_total_seconds' => $newTotal,
            'actor_id' => (int)($user['id'] ?? 0),
        ]);

        return ['ok' => true, 'total_seconds' => $newTotal];

    }

    /*
    ──────────────────────────────────────────────────
    resumeWork()
    ──────────────────────────────────────────────────
    */
    public static function resumeWork(int $taskId, array $user): bool {

        if($user['role'] !== 'administrator'){
            $s = db()->prepare("SELECT id FROM eco_tool_assignments WHERE task_id=? AND worker_id=?");
            $s->execute([$taskId, $user['id']]);
            if(!$s->fetch()) return false;
        }

        db()->prepare("
            UPDATE wp_eco_aplus_tasks
            SET work_status='Working', work_started_at=NOW(), work_paused_at=NULL, last_activity_at=NOW()
            WHERE id=?
        ")->execute([$taskId]);
        TaskService::setPhase($taskId, self::activeServiceCodeById($taskId), 'Working', ['actor_id'=>(int)($user['id'] ?? 0)]);

        return true;

    }

    /*
    ──────────────────────────────────────────────────
    submitQA()
    ──────────────────────────────────────────────────
    */
    public static function submitQA(int $taskId, string $mediaLink, string $seoLink, array $user): bool {

        $taskRow = db()->prepare("SELECT id, product_type, status, work_status, content_approved_at, content_updated_at, info_subtasks, seo_doc_link, info_worker_id, aplus_worker_id, work_completed_by_worker_id, media_link FROM wp_eco_aplus_tasks WHERE id=?");
        $taskRow->execute([$taskId]);
        $task = $taskRow->fetch();

        /* AI QA has no media-link requirement.  QA approval completes the AI
           service and only then removes the worker assignment, so payroll and
           the next service cannot start before QA accepts the work. */
        if($task && (($task['work_status'] ?? '') === 'AI In QA' || self::isAIService($task))){
            if(($task['work_status'] ?? '') !== 'AI In QA') return false;
            $svc = TaskService::get($taskId, TaskService::AI) ?: [];
            $workerId = (int)($svc['worker_id'] ?? $task['ai_worked_by'] ?? $task['work_completed_by_worker_id'] ?? 0) ?: null;
            $stmt = db()->prepare("UPDATE wp_eco_aplus_tasks
                SET status='AI DONE', work_status='Pending', qa_submitted_at=NOW(), qa_submitted_by=?,
                    active_revision_type=NULL, revision_comment=NULL, last_activity_at=NOW()
                WHERE id=? AND work_status='AI In QA'");
            $stmt->execute([$user['id'], $taskId]);
            if($stmt->rowCount() > 0){
                TaskService::setPhase($taskId, TaskService::AI, 'Done', [
                    'worker_id' => $workerId,
                    'completed_by' => $workerId,
                    'qa_submitted_by' => (int)$user['id'],
                    'qa_submitted_at' => date('Y-m-d H:i:s'),
                    'actor_id' => (int)$user['id'],
                ]);
                db()->prepare("DELETE FROM eco_tool_assignments WHERE task_id=?")->execute([$taskId]);
            }
            return $stmt->rowCount() > 0;
        }

        $isInfoStage = $task && self::isInfoService($task);

        if ($isInfoStage && ($task['work_status'] ?? '') === 'In QA') {
            $workerId = $task['info_worker_id'] ?? null;
            if ($workerId) {
                $chkRole = db()->prepare("SELECT role FROM eco_tool_users WHERE id=?");
                $chkRole->execute([$workerId]);
                $r = $chkRole->fetch();
                if ($r && $r['role'] !== 'worker') {
                    $workerId = null;
                }
            }
            if (!$workerId) {
                $assignRow = db()->prepare("SELECT worker_id FROM eco_tool_assignments WHERE task_id=?");
                $assignRow->execute([$taskId]);
                $assign = $assignRow->fetch();
                $candId = $assign['worker_id'] ?? $task['work_completed_by_worker_id'] ?? null;
                if ($candId) {
                    $chkRole = db()->prepare("SELECT role FROM eco_tool_users WHERE id=?");
                    $chkRole->execute([$candId]);
                    $r = $chkRole->fetch();
                    if ($r && $r['role'] === 'worker') {
                        $workerId = $candId;
                    }
                }
            }

            $stmt = db()->prepare("
                UPDATE wp_eco_aplus_tasks
                SET media_link=?, work_status='Info Done',
                    info_worker_id=COALESCE(?, info_worker_id),
                    work_completed_by_worker_id=COALESCE(?, work_completed_by_worker_id),
                    qa_submitted_at=NOW(), qa_submitted_by=?, is_urgent=0, active_revision_type=NULL, last_activity_at=NOW()
                WHERE id=? AND work_status='In QA'
            ");
            $stmt->execute([$mediaLink, $workerId, $workerId, $user['id'], $taskId]);
            if ($stmt->rowCount() > 0) {
                TaskService::setPhase($taskId, TaskService::INFO, 'Done', [
                    'media_link' => $mediaLink,
                    'qa_submitted_by' => (int)$user['id'],
                    'qa_submitted_at' => date('Y-m-d H:i:s'),
                    'completed_by' => $workerId,
                    'actor_id' => (int)$user['id'],
                ]);
            }
            return $stmt->rowCount() > 0;
        }

        if(($task['work_status'] ?? '') === 'In QA' && self::isAplusService($task)){
            /* A+ QA only records/checks the media link. SEO is a separate step. */
            if($user['role'] === 'seo_manager') return false;
            $stmt = db()->prepare("
                UPDATE wp_eco_aplus_tasks
                SET media_link=?, last_activity_at=NOW()
                WHERE id=? AND work_status='In QA'
            ");
            $stmt->execute([$mediaLink, $taskId]);
            if ($stmt->rowCount() > 0) {
                TaskService::setPhase($taskId, TaskService::APLUS, 'SEO Review', [
                    'media_link' => $mediaLink,
                    'qa_submitted_by' => (int)$user['id'],
                    'qa_submitted_at' => date('Y-m-d H:i:s'),
                    'actor_id' => (int)$user['id'],
                ]);
            }
            return $stmt->rowCount() > 0;
        }

        /* SEO manager or administrator may complete SEO Review. Admin needs
           the same emergency-override ability as the other workflow stages. */
        if(!in_array($user['role'] ?? '', ['seo_manager', 'administrator'], true) || ($task['work_status'] ?? '') !== 'SEO Review' || trim($seoLink) === '') return false;
        $stmt = db()->prepare("
                UPDATE wp_eco_aplus_tasks
                SET media_link=COALESCE(NULLIF(?, ''), media_link), seo_doc_link=?, work_status='Work Done',
                    qa_submitted_at=NOW(), seo_submitted_by=?, is_urgent=0, active_revision_type=NULL, last_activity_at=NOW()
                WHERE id=? AND work_status='SEO Review'
            ");
        $stmt->execute([$mediaLink, $seoLink, $user['id'], $taskId]);
        if ($stmt->rowCount() > 0) {
            TaskService::setPhase($taskId, TaskService::APLUS, 'Done', [
                'media_link' => $mediaLink ?: null,
                'document_link' => $seoLink,
                'qa_submitted_by' => (int)$user['id'],
                'qa_submitted_at' => date('Y-m-d H:i:s'),
                'completed_by' => (int)($task['aplus_worker_id'] ?? $task['work_completed_by_worker_id'] ?? 0) ?: null,
                'actor_id' => (int)$user['id'],
            ]);
        }

        return $stmt->rowCount() > 0;

    }

    /*
    ──────────────────────────────────────────────────
    sendForSEO()
    ──────────────────────────────────────────────────
    */
    public static function sendForSEO(int $taskId, int $userId, string $mediaLink = ''): bool {
        $row = db()->prepare("SELECT product_type, status, work_status, info_subtasks, content_approved_at, content_updated_at, seo_doc_link, media_link FROM wp_eco_aplus_tasks WHERE id=?");
        $row->execute([$taskId]);
        $task = $row->fetch();
        $effectiveMedia = trim($mediaLink) !== '' ? trim($mediaLink) : trim((string)($task['media_link'] ?? ''));
        if(!$task || !self::isAplusService($task) || ($task['work_status'] ?? '') !== 'In QA' || $effectiveMedia === '') return false;
        $stmt = db()->prepare("UPDATE wp_eco_aplus_tasks SET media_link=?, work_status='SEO Review', qa_submitted_by=?, last_activity_at=NOW() WHERE id=? AND work_status='In QA'");
        $stmt->execute([$effectiveMedia, $userId, $taskId]);
        if ($stmt->rowCount() > 0) {
            TaskService::setPhase($taskId, TaskService::APLUS, 'SEO Review', [
                'media_link' => $effectiveMedia,
                'qa_submitted_by' => $userId,
                'qa_submitted_at' => date('Y-m-d H:i:s'),
                'actor_id' => $userId,
            ]);
        }
        return $stmt->rowCount() > 0;
    }

    /*
    ──────────────────────────────────────────────────
    saveFinalLinks() — admin override
    ──────────────────────────────────────────────────
    */
    public static function saveFinalLinks(int $taskId, string $mediaLink, string $seoLink): void {
        $cur = db()->prepare("SELECT product_type, status, work_status, info_subtasks, content_approved_at, content_updated_at, seo_doc_link FROM wp_eco_aplus_tasks WHERE id=?");
        $cur->execute([$taskId]);
        $cRow = $cur->fetch();
        $isInfo = $cRow && self::isInfoService($cRow);
        $targetStatus = $isInfo ? 'Info Done' : 'Work Done';

        db()->prepare("
            UPDATE wp_eco_aplus_tasks
            SET media_link=?, seo_doc_link=COALESCE(NULLIF(?, ''), seo_doc_link), work_status=?, is_urgent=0, active_revision_type=NULL, last_activity_at=NOW()
            WHERE id=?
        ")->execute([$mediaLink, $seoLink, $targetStatus, $taskId]);
        if ($isInfo) {
            TaskService::setPhase($taskId, TaskService::INFO, 'Done', ['media_link'=>$mediaLink, 'actor_id'=>null]);
        } else {
            TaskService::setPhase($taskId, TaskService::APLUS, 'Done', ['media_link'=>$mediaLink, 'document_link'=>$seoLink, 'actor_id'=>null]);
        }
    }

    /*
    ──────────────────────────────────────────────────
    forceStage() — admin force status switch
    ──────────────────────────────────────────────────
    */
    public static function forceStage(int $taskId, string $stage): void {

        switch($stage){
            case 'Pending':
                db()->prepare("DELETE FROM eco_tool_assignments WHERE task_id=?")->execute([$taskId]);
                db()->prepare("
                    UPDATE wp_eco_aplus_tasks SET
                        status='Pending', work_status='Pending', content='',
                        work_started_at=NULL, work_completed_at=NULL, work_paused_at=NULL,
                        work_total_seconds=0, media_link=NULL, seo_doc_link=NULL,
                        qa_submitted_at=NULL, qa_submitted_by=NULL, is_urgent=0,
                        content_approved_at=NULL, content_updated_at=NULL, last_activity_at=NOW()
                    WHERE id=?
                ")->execute([$taskId]);
                break;

            case 'Generate info Content':
            case 'Generate Info Content':
                $curr = db()->prepare("SELECT product_type FROM wp_eco_aplus_tasks WHERE id=?");
                $curr->execute([$taskId]);
                $pType = $curr->fetchColumn();
                $newType = ($pType === 'Info + A Plus') ? 'Info + A Plus' : 'Infographics';

                db()->prepare("
                    UPDATE wp_eco_aplus_tasks SET
                        product_type=?,
                        status='Pending',
                        work_status='Pending',
                        content_approved_at=NULL,
                        content_updated_at=NULL,
                        work_started_at=NULL,
                        work_completed_at=NULL,
                        work_paused_at=NULL,
                        work_total_seconds=0,
                        media_link=NULL,
                        seo_doc_link=NULL,
                        qa_submitted_at=NULL,
                        qa_submitted_by=NULL,
                        is_urgent=0,
                        work_completed_by_worker_id=NULL,
                        active_revision_type=NULL,
                        last_activity_at=NOW()
                    WHERE id=?
                ")->execute([$newType, $taskId]);
                break;

            case 'A Plus':
                /* Preserve the existing Infographics content/history while
                   explicitly reopening the task at the A+ content stage. */
                db()->prepare("UPDATE wp_eco_aplus_tasks SET status='A Plus', work_status='Pending', last_activity_at=NOW() WHERE id=?")
                    ->execute([$taskId]);
                break;

            case 'Generated':
            case 'All Generated':
                db()->prepare("DELETE FROM eco_tool_assignments WHERE task_id=?")->execute([$taskId]);
                db()->prepare("
                    UPDATE wp_eco_aplus_tasks SET
                        status=?, work_status='Pending',
                        work_started_at=NULL, work_completed_at=NULL, work_paused_at=NULL,
                        work_total_seconds=0, media_link=NULL, seo_doc_link=NULL,
                        qa_submitted_at=NULL, qa_submitted_by=NULL, is_urgent=0,
                        content_approved_at=NULL, content_updated_at=NULL, last_activity_at=NOW()
                    WHERE id=?
                ")->execute([$stage, $taskId]);
                break;

            case 'Updated':
                db()->prepare("UPDATE wp_eco_aplus_tasks SET status='Updated', work_status='Pending', content_updated_at=NOW(), last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
                break;

            case 'Approved':
                db()->prepare("
                    UPDATE wp_eco_aplus_tasks SET
                        status='Approved', work_status='Pending',
                        work_started_at=NULL, work_completed_at=NULL, work_paused_at=NULL,
                        work_total_seconds=0, media_link=NULL, seo_doc_link=NULL,
                        qa_submitted_at=NULL, qa_submitted_by=NULL, is_urgent=0, last_activity_at=NOW()
                    WHERE id=?
                ")->execute([$taskId]);
                break;

            case 'Working':
                db()->prepare("UPDATE wp_eco_aplus_tasks SET work_status='Working', work_started_at=NOW(), work_paused_at=NULL, work_completed_at=NULL, last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
                break;

            case 'Paused':
                db()->prepare("UPDATE wp_eco_aplus_tasks SET work_status='Paused', work_paused_at=NOW(), last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
                break;

            case 'In QA':
                db()->prepare("
                    UPDATE wp_eco_aplus_tasks SET
                        work_status='In QA', work_completed_at=NOW(), work_paused_at=NULL,
                        media_link=NULL, seo_doc_link=NULL, qa_submitted_at=NULL, qa_submitted_by=NULL, last_activity_at=NOW()
                    WHERE id=?
                ")->execute([$taskId]);
                break;

            case 'SEO Review':
                db()->prepare("UPDATE wp_eco_aplus_tasks SET work_status='SEO Review', last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
                break;

            case 'Work Done':
                $awRow = db()->prepare("SELECT worker_id FROM eco_tool_assignments WHERE task_id=?");
                $awRow->execute([$taskId]);
                $awFetch = $awRow->fetch();
                $wcwId = $awFetch ? $awFetch['worker_id'] : null;
                db()->prepare("UPDATE wp_eco_aplus_tasks SET work_status='Work Done', work_completed_at=NOW(), work_completed_by_worker_id=?, active_revision_type=NULL, last_activity_at=NOW() WHERE id=?")->execute([$wcwId, $taskId]);
                break;

            case 'Info Done':
                $awRow = db()->prepare("SELECT worker_id FROM eco_tool_assignments WHERE task_id=?");
                $awRow->execute([$taskId]);
                $awFetch = $awRow->fetch();
                $wcwId = $awFetch ? $awFetch['worker_id'] : null;
                $isHumanWorker = false;
                if ($wcwId) {
                    $wRoleStmt = db()->prepare("SELECT role FROM eco_tool_users WHERE id=?");
                    $wRoleStmt->execute([$wcwId]);
                    $r = $wRoleStmt->fetch();
                    $isHumanWorker = ($r && $r['role'] === 'worker');
                }
                $workerUpdateSql = $isHumanWorker ? ", info_worker_id = ?" : "";
                $workerParams = [$wcwId];
                if ($isHumanWorker) $workerParams[] = $wcwId;
                $workerParams[] = $taskId;
                db()->prepare("UPDATE wp_eco_aplus_tasks SET work_status='Info Done', work_completed_at=NOW(), work_completed_by_worker_id=COALESCE(work_completed_by_worker_id, ?) $workerUpdateSql, is_urgent=0, active_revision_type=NULL, last_activity_at=NOW() WHERE id=?")->execute($workerParams);
                break;

            case 'Republish':
                db()->prepare("UPDATE wp_eco_aplus_tasks SET work_status='Republish', last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
                break;

            case 'Changes':
                db()->prepare("UPDATE wp_eco_aplus_tasks SET work_status='Changes', active_revision_type='design', last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
                break;

            case 'Changes in Content':
                db()->prepare("UPDATE wp_eco_aplus_tasks SET work_status='Changes in Content', active_revision_type='content', last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
                break;

            case 'Changing':
                db()->prepare("UPDATE wp_eco_aplus_tasks SET work_status='Changing', work_started_at=NOW(), work_paused_at=NULL, last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
                break;

            case 'AI Work':
                db()->prepare("UPDATE wp_eco_aplus_tasks SET status='AI Work', work_status='Pending', last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
                break;

            case 'AI DONE':
                $s = db()->prepare("SELECT worker_id FROM eco_tool_assignments WHERE task_id=?");
                $s->execute([$taskId]);
                $wId = $s->fetchColumn();
                if ($wId) {
                    $uRole = db()->prepare("SELECT role FROM eco_tool_users WHERE id=?");
                    $uRole->execute([$wId]);
                    if ($uRole->fetchColumn() === 'ai_work') {
                        db()->prepare("UPDATE wp_eco_aplus_tasks SET ai_worked_by=? WHERE id=? AND (ai_worked_by IS NULL OR ai_worked_by=0)")->execute([$wId, $taskId]);
                        db()->prepare("DELETE FROM eco_tool_assignments WHERE task_id=?")->execute([$taskId]);
                    }
                }
                db()->prepare("UPDATE wp_eco_aplus_tasks SET status='AI DONE', work_status='Pending', work_started_at=NULL, work_paused_at=NULL, last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
                break;
        }

        /* Keep the independent service state aligned with admin overrides.
           Legacy columns are still updated above, but must never become the
           only source of truth for a multi-service product. */
        try {
            $serviceCode = ($stage === 'A Plus') ? TaskService::APLUS : self::activeServiceCodeById($taskId);
            TaskService::ensure($taskId, $serviceCode);
            if (in_array($stage, ['Generated','All Generated','Approved','Updated'], true)) {
                TaskService::setContent($taskId, $serviceCode, $stage);
                TaskService::setPhase($taskId, $serviceCode, 'Pending');
            } elseif (in_array($stage, ['Working','Changing'], true)) {
                TaskService::setPhase($taskId, $serviceCode, 'Working');
            } elseif ($stage === 'Paused') {
                TaskService::setPhase($taskId, $serviceCode, 'Paused');
            } elseif ($stage === 'In QA') {
                TaskService::setPhase($taskId, $serviceCode, 'In QA');
            } elseif ($stage === 'SEO Review') {
                TaskService::setPhase($taskId, TaskService::APLUS, 'SEO Review');
            } elseif ($stage === 'Work Done') {
                TaskService::setPhase($taskId, TaskService::APLUS, 'Done');
            } elseif ($stage === 'Info Done') {
                TaskService::setPhase($taskId, TaskService::INFO, 'Done');
            } elseif (in_array($stage, ['Pending','Generate info Content','Generate Info Content'], true)) {
                TaskService::setPhase($taskId, TaskService::INFO, 'Pending');
                TaskService::setContent($taskId, TaskService::INFO, 'Pending');
            }
        } catch (Throwable $e) {
            error_log('service state sync failed: ' . $e->getMessage());
        }

    }

    public static function holdProduct(int $taskId, int $hold): void {
        if($hold){
            $row = db()->prepare("SELECT status, work_status FROM wp_eco_aplus_tasks WHERE id=?");
            $row->execute([$taskId]);
            $t = $row->fetch();
            $prev = json_encode(['status'=>$t['status'],'work_status'=>$t['work_status']]);
            db()->prepare("UPDATE wp_eco_aplus_tasks SET status='Hold', work_status='Hold', hold_prev_status=? WHERE id=?")->execute([$prev, $taskId]);
        } else {
            $row = db()->prepare("SELECT hold_prev_status FROM wp_eco_aplus_tasks WHERE id=?");
            $row->execute([$taskId]);
            $t = $row->fetch();
            $prev = $t ? json_decode($t['hold_prev_status'], true) : null;
            if($prev && isset($prev['status'])){
                db()->prepare("UPDATE wp_eco_aplus_tasks SET status=?, work_status=?, hold_prev_status=NULL WHERE id=?")->execute([$prev['status'], $prev['work_status'], $taskId]);
            } else {
                db()->prepare("UPDATE wp_eco_aplus_tasks SET status='Generated', work_status='Pending', hold_prev_status=NULL WHERE id=?")->execute([$taskId]);
            }
        }
    }

    public static function setNoCharge(int $taskId, int $noCharge, string $reason = '', int $userId = 0): array {
        $row = db()->prepare("SELECT status, work_status, no_charge_prev_status FROM wp_eco_aplus_tasks WHERE id=? AND deleted_at IS NULL");
        $row->execute([$taskId]);
        $task = $row->fetch();
        if (!$task) return ['ok'=>false, 'message'=>'Product not found'];

        if (!$noCharge) {
            if ($task['status'] !== 'No Charge') return ['ok'=>false, 'message'=>'Product is not marked No Charge'];
            $prev = $task['no_charge_prev_status'] ? json_decode($task['no_charge_prev_status'], true) : null;
            $prevStatus = $prev['status'] ?? 'Pending';
            $prevWork = $prev['work_status'] ?? 'Pending';
            db()->prepare("UPDATE wp_eco_aplus_tasks SET status=?, work_status=?, no_charge_prev_status=NULL, no_charge_reason=NULL, no_charge_at=NULL, no_charge_by=NULL, last_activity_at=NOW() WHERE id=?")
               ->execute([$prevStatus, $prevWork, $taskId]);
            return ['ok'=>true];
        }

        if ($task['status'] === 'No Charge') return ['ok'=>true];

        // 1. Agar already Paid invoice mein hai to warn karein
        $paidInv = db()->prepare("
            SELECT i.invoice_no 
            FROM eco_invoice_items ii 
            JOIN eco_invoices i ON i.id = ii.invoice_id 
            WHERE ii.task_id = ? AND i.status = 'Paid'
            LIMIT 1
        ");
        $paidInv->execute([$taskId]);
        $paidInvoiceNo = $paidInv->fetchColumn();
        if ($paidInvoiceNo) {
            return ['ok'=>false, 'message'=>'Product already Paid invoice #' . $paidInvoiceNo . ' mein shamil hai. Pehle invoice ko adjust karein.'];
        }

        // 2. Agar already Paid payslip mein hai to warn karein
        $paidPs = db()->prepare("
            SELECT p.id 
            FROM eco_payslip_items pi 
            JOIN eco_payslips p ON p.id = pi.payslip_id 
            WHERE pi.task_id = ? AND p.status = 'Paid'
            LIMIT 1
        ");
        $paidPs->execute([$taskId]);
        if ($paidPs->fetchColumn()) {
            return ['ok'=>false, 'message'=>'Product already Paid payslip mein shamil hai.'];
        }

        // 3. Unpaid invoice(s) se item remove karein aur invoice ka total amount recalculate karein
        $unpaidInvs = db()->prepare("
            SELECT DISTINCT ii.invoice_id 
            FROM eco_invoice_items ii 
            JOIN eco_invoices i ON i.id = ii.invoice_id 
            WHERE ii.task_id = ? AND i.status != 'Paid'
        ");
        $unpaidInvs->execute([$taskId]);
        $invIds = $unpaidInvs->fetchAll(PDO::FETCH_COLUMN);

        db()->prepare("
            DELETE ii FROM eco_invoice_items ii 
            JOIN eco_invoices i ON i.id = ii.invoice_id 
            WHERE ii.task_id = ? AND i.status != 'Paid'
        ")->execute([$taskId]);

        foreach ($invIds as $invId) {
            $newTot = (float) db()->query("SELECT COALESCE(SUM(item_price), 0) FROM eco_invoice_items WHERE invoice_id=" . intval($invId))->fetchColumn();
            db()->prepare("UPDATE eco_invoices SET total_amount=? WHERE id=?")->execute([$newTot, $invId]);
        }

        // 4. Unpaid task_service_charges remove karein
        try {
            db()->prepare("DELETE FROM eco_task_service_charges WHERE task_id=? AND (invoice_status IS NULL OR invoice_status != 'Paid')")->execute([$taskId]);
        } catch(Throwable $e){}

        // 5. Unpaid payslip items se remove karein
        try {
            db()->prepare("
                DELETE pi FROM eco_payslip_items pi 
                JOIN eco_payslips p ON p.id = pi.payslip_id 
                WHERE pi.task_id = ? AND p.status != 'Paid'
            ")->execute([$taskId]);
        } catch(Throwable $e){}

        $prev = json_encode(['status'=>$task['status'], 'work_status'=>$task['work_status']]);
        $reason = $reason !== '' ? $reason : 'Client ne split products ko single product mein convert kar diya';
        db()->prepare("UPDATE wp_eco_aplus_tasks SET status='No Charge', work_status='No Charge', invoice_status=NULL, info_invoice_status=NULL, aplus_invoice_status=NULL, is_urgent=0, no_charge_prev_status=?, no_charge_reason=?, no_charge_at=NOW(), no_charge_by=?, last_activity_at=NOW() WHERE id=?")
           ->execute([$prev, $reason, $userId ?: null, $taskId]);
        return ['ok'=>true];
    }

    public static function toggleUrgent(int $taskId, int $urgent, int $priority = 1): void {
        $val = $urgent ? max(1, min(3, $priority)) : 0;
        
        // Check if product belongs to a group/family
        $stmt = db()->prepare("SELECT family_code FROM wp_eco_aplus_tasks WHERE id=?");
        $stmt->execute([$taskId]);
        $familyCode = $stmt->fetchColumn();

        if (!empty($familyCode)) {
            // Group ki sabhi products ka urgent & priority same ho jaye ga
            db()->prepare("
                UPDATE wp_eco_aplus_tasks 
                SET is_urgent = ?, last_activity_at = NOW() 
                WHERE family_code = ? AND deleted_at IS NULL
            ")->execute([$val, $familyCode]);
        } else {
            db()->prepare("
                UPDATE wp_eco_aplus_tasks 
                SET is_urgent = ?, last_activity_at = NOW() 
                WHERE id = ?
            ")->execute([$val, $taskId]);
        }
    }

    public static function assignProduct(int $taskId, int $workerId): array {
        db()->prepare("DELETE FROM eco_tool_assignments WHERE task_id=?")->execute([$taskId]);
        db()->prepare("INSERT INTO eco_tool_assignments (worker_id,task_id) VALUES(?,?)")->execute([$workerId, $taskId]);
        $wStmt = db()->prepare("SELECT username, role FROM eco_tool_users WHERE id=?");
        $wStmt->execute([$workerId]);
        $wRow = $wStmt->fetch();

        // Automatically set status to "AI Work" if the worker is "ai_work"
        if ($wRow && $wRow['role'] === 'ai_work') {
            db()->prepare("UPDATE wp_eco_aplus_tasks SET status='AI Work', work_status='Pending', ai_worked_by=?, last_activity_at=NOW() WHERE id=?")->execute([$workerId, $taskId]);
            TaskService::ensure($taskId, TaskService::AI, ['phase_status'=>'Pending', 'worker_id'=>$workerId]);
            db()->prepare("UPDATE eco_task_services SET worker_id=?, phase_status='Pending', updated_at=NOW() WHERE task_id=? AND service_code='ai'")
                ->execute([$workerId, $taskId]);
        } elseif ($wRow && $wRow['role'] === 'worker') {
            $tStmt = db()->prepare("SELECT status, product_type, work_status, info_worker_id, aplus_worker_id FROM wp_eco_aplus_tasks WHERE id=?");
            $tStmt->execute([$taskId]);
            $tRow = $tStmt->fetch();
            if ($tRow && $tRow['status'] === 'AI DONE') {
                db()->prepare("UPDATE wp_eco_aplus_tasks SET work_status='Pending', last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
            }
            if ($tRow) {
                $pt = $tRow['product_type'] ?? '';
                $st = $tRow['status'] ?? '';
                $infoComplete = ($tRow['work_status'] ?? '') === 'Info Done';
                $isInfoPlusAplus = $pt === 'Info + A Plus';
                /* Assigning an A+ worker must not activate A+ on a product
                   whose Infographics phase is still active. */
                if ($pt === 'A Plus' || $pt === 'A+' || ($st === 'A Plus' && (!$isInfoPlusAplus || $infoComplete))) {
                    db()->prepare("UPDATE wp_eco_aplus_tasks SET aplus_worker_id = ?, last_activity_at=NOW() WHERE id=?")->execute([$workerId, $taskId]);
                } else {
                    db()->prepare("UPDATE wp_eco_aplus_tasks SET info_worker_id = ?, last_activity_at=NOW() WHERE id=?")->execute([$workerId, $taskId]);
                }
                try {
                    $serviceCode = TaskService::activeCode($taskId);
                    if (!$serviceCode) {
                        $serviceCode = ($st === 'A Plus' || in_array($pt, ['A+','A Plus'], true)) ? TaskService::APLUS : TaskService::INFO;
                    }
                    TaskService::ensure($taskId, $serviceCode, ['worker_id'=>$workerId]);
                    db()->prepare("UPDATE eco_task_services SET worker_id=?, updated_at=NOW() WHERE task_id=? AND service_code=?")
                        ->execute([$workerId, $taskId, $serviceCode]);
                } catch (Throwable $e) { error_log('service assignment sync failed: ' . $e->getMessage()); }
            }
        }

        return ['worker_id'=>$workerId, 'worker_name'=>$wRow ? $wRow['username'] : ''];
    }

    public static function updateProductServices(int $taskId, array $services, ?int $aiWorkerId, ?int $infoWorkerId, ?int $aplusWorkerId, string $activeAssign = 'unchanged'): array {
        $stmt = db()->prepare("SELECT * FROM wp_eco_aplus_tasks WHERE id=?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();
        if(!$task) return ['ok'=>false, 'message'=>'Task not found'];

        $hasAi    = in_array('AI Work', $services) || in_array('ai_work', $services);
        $hasInfo  = in_array('Infographics', $services) || in_array('infographics', $services);
        $hasAplus = in_array('A+ Banners', $services) || in_array('aplus', $services) || in_array('A Plus', $services);

        // Auto-enable service flags if worker or active stage is specified
        if ($aplusWorkerId || $activeAssign === 'aplus' || $activeAssign === 'start_aplus_content') {
            $hasAplus = true;
        }
        if ($infoWorkerId || $activeAssign === 'info') {
            $hasInfo = true;
        }
        if ($aiWorkerId || $activeAssign === 'ai') {
            $hasAi = true;
        }

        // If product originally had Infographics (via product_type, existing worker, or existing subtasks)
        // and A+ Banners is being added or assigned, ensure Infographics remains preserved so it shifts to 'Info + A Plus'
        $prevSubtasks = array_map('trim', explode(',', $task['info_subtasks'] ?? ''));
        $existingInfoWorker = $task['info_worker_id'] ?? null;
        if ($existingInfoWorker) {
            $chk = db()->prepare("SELECT role FROM eco_tool_users WHERE id=?");
            $chk->execute([$existingInfoWorker]);
            $r = $chk->fetch();
            if ($r && $r['role'] !== 'worker') {
                $existingInfoWorker = null;
            }
        }
        $existingCompWorker = $task['work_completed_by_worker_id'] ?? null;
        if ($existingCompWorker) {
            $chk = db()->prepare("SELECT role FROM eco_tool_users WHERE id=?");
            $chk->execute([$existingCompWorker]);
            $r = $chk->fetch();
            if ($r && $r['role'] !== 'worker') {
                $existingCompWorker = null;
            }
        }
        $hadInfo = ($task['product_type'] === 'Infographics' || $task['product_type'] === 'Info + A Plus' || !empty($existingInfoWorker) || !empty($existingCompWorker) || in_array('Infographics', $prevSubtasks));
        if ($hadInfo && ($hasAplus || $infoWorkerId || in_array('Infographics', $services))) {
            $hasInfo = true;
            if (!$infoWorkerId) {
                $infoWorkerId = $existingInfoWorker ?: $existingCompWorker ?: null;
            }
        }

        $subtasksList = [];
        if($hasAi)    $subtasksList[] = 'AI Work';
        if($hasInfo)  $subtasksList[] = 'Infographics';
        if($hasAplus) $subtasksList[] = 'A+ Banners';
        $infoSubtasks = implode(', ', $subtasksList);

        if($hasInfo && $hasAplus){
            $productType = 'Info + A Plus';
        } elseif($hasInfo){
            $productType = 'Infographics';
        } elseif($hasAplus){
            $productType = 'A Plus';
        } else {
            $productType = 'Infographics';
        }

        // Preserve previous invoice status for Infographics if shifting to Info + A Plus
        $infoInvoiceStatus = $task['info_invoice_status'];
        if(empty($infoInvoiceStatus) && !empty($task['invoice_status'])){
            $infoInvoiceStatus = $task['invoice_status'];
        }

        $sql = "UPDATE wp_eco_aplus_tasks 
                SET product_type=?, info_subtasks=?, ai_worked_by=?, info_worker_id=?, aplus_worker_id=?, 
                    info_invoice_status=COALESCE(info_invoice_status, ?),
                    last_activity_at=NOW() 
                WHERE id=?";
        db()->prepare($sql)->execute([
            $productType,
            $infoSubtasks ?: null,
            $aiWorkerId ?: null,
            $infoWorkerId ?: null,
            $aplusWorkerId ?: null,
            $infoInvoiceStatus ?: null,
            $taskId
        ]);

        if($activeAssign === 'ai' && $aiWorkerId){
            db()->prepare("DELETE FROM eco_tool_assignments WHERE task_id=?")->execute([$taskId]);
            db()->prepare("INSERT INTO eco_tool_assignments (worker_id, task_id) VALUES(?, ?)")->execute([$aiWorkerId, $taskId]);
            db()->prepare("UPDATE wp_eco_aplus_tasks SET status='AI Work', work_status='Pending', last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
            TaskService::ensure($taskId, TaskService::AI, ['phase_status'=>'Pending', 'worker_id'=>$aiWorkerId]);
            db()->prepare("UPDATE eco_task_services SET worker_id=?, phase_status='Pending', updated_at=NOW() WHERE task_id=? AND service_code='ai'")
                ->execute([$aiWorkerId, $taskId]);
        } elseif($activeAssign === 'info' && $infoWorkerId){
            db()->prepare("DELETE FROM eco_tool_assignments WHERE task_id=?")->execute([$taskId]);
            db()->prepare("INSERT INTO eco_tool_assignments (worker_id, task_id) VALUES(?, ?)")->execute([$infoWorkerId, $taskId]);
            if($task['status'] === 'AI Work' || $task['status'] === 'AI DONE'){
                db()->prepare("UPDATE wp_eco_aplus_tasks SET status='Infographics', work_status='Pending', last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
            }
        } elseif($activeAssign === 'aplus' && $aplusWorkerId){
            db()->prepare("DELETE FROM eco_tool_assignments WHERE task_id=?")->execute([$taskId]);
            db()->prepare("INSERT INTO eco_tool_assignments (worker_id, task_id) VALUES(?, ?)")->execute([$aplusWorkerId, $taskId]);
            $infoComplete = ($task['work_status'] ?? '') === 'Info Done';
            $canActivateAplus = !$hasInfo || $infoComplete;
            if($canActivateAplus && !in_array($task['status'], ['Approved', 'Updated', 'Work Done'])){
                db()->prepare("UPDATE wp_eco_aplus_tasks SET status='A Plus', work_status='Pending', last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
            } elseif(!$canActivateAplus){
                db()->prepare("UPDATE wp_eco_aplus_tasks SET last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
            }
        } elseif($activeAssign === 'start_aplus_content'){
            self::startAplusWorkflow($taskId);
        } elseif($activeAssign === 'none'){
            db()->prepare("DELETE FROM eco_tool_assignments WHERE task_id=?")->execute([$taskId]);
        }

        return ['ok'=>true, 'message'=>'Product services & assignments updated successfully'];
    }

    public static function addProduct(string $productNo, string $title, ?string $infoSubtasks, ?string $productLink = null, ?int $vendorId = null): int {
        $type = null;
        if($infoSubtasks && trim($infoSubtasks)){
            $subtasks = array_map('trim', explode(',', $infoSubtasks));
            $hasInfographics = in_array('Infographics', $subtasks);
            $hasAplus = in_array('A+ Banners', $subtasks);
            $hasAiWork = in_array('AI Work', $subtasks);
            if($hasInfographics && $hasAplus){
                $type = 'Info + A Plus';
            } elseif($hasInfographics){
                $type = 'Infographics';
            } elseif($hasAplus){
                $type = 'A+';
            } else {
                $type = 'Infographics';
            }
        }
        if (!$vendorId || $vendorId <= 0) {
            $defVendor = Vendor::getDefault();
            $vendorId = $defVendor ? (int)$defVendor['id'] : null;
        }

        db()->prepare("INSERT INTO wp_eco_aplus_tasks (product_no, title, status, work_status, product_type, info_subtasks, product_link, vendor_id) VALUES (?, ?, 'Pending', 'Pending', ?, ?, ?, ?)")
            ->execute([$productNo, $title, $type ?: null, $infoSubtasks ?: null, $productLink ?: null, $vendorId]);
        return (int) db()->lastInsertId();
    }

    public static function startAplusWorkflow(int $taskId): array {
        try {
            $stmt = db()->prepare("SELECT * FROM wp_eco_aplus_tasks WHERE id=?");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch();
            if(!$task) return ['ok'=>false, 'message'=>'Task not found'];
            if(!self::isInfoService($task) || ($task['work_status'] ?? '') !== 'Info Done'){
                return ['ok'=>false, 'message'=>'A+ Banners sirf Info Done ke baad start ho sakte hain'];
            }

            // Add 'A+ Banners' to info_subtasks if not already present
            $subtasks = [];
            if(!empty($task['info_subtasks'])){
                $subtasks = array_map('trim', explode(',', $task['info_subtasks']));
            }
            if(!in_array('A+ Banners', $subtasks)){
                $subtasks[] = 'A+ Banners';
            }
            $infoSubtasks = implode(',', $subtasks);

            // Preserve existing Infographics content so worker and client can reference it
            $existingContent = $task['content'] ?? '';
            $newContent = '';
            if(!empty($existingContent)){
                $decoded = json_decode($existingContent, true);
                if(is_array($decoded) && isset($decoded['_format']) && $decoded['_format'] === 'grid_v2'){
                    $decoded['aplus'] = ['b1'=>'', 'b2'=>'', 'b3'=>'', 'b4'=>'', 'extra'=>''];
                    $newContent = json_encode($decoded);
                } else {
                    $newContent = $existingContent;
                }
            }

            $aplusWorkerId = !empty($task['aplus_worker_id']) ? (int)$task['aplus_worker_id'] : null;

            // Transition to standard A+ content generation workflow
            db()->prepare("
                UPDATE wp_eco_aplus_tasks
                    SET product_type='Info + A Plus',
                    info_subtasks=?,
                    info_invoice_status=COALESCE(info_invoice_status, invoice_status),
                    status='A Plus',
                    work_status='Pending',
                    content=?,
                    original_content=?,
                    seo_doc_link=NULL,
                    published_at=NULL,
                    published_by=NULL,
                    published_link=NULL,
                    last_activity_at=NOW()
                WHERE id=?
            ")->execute([$infoSubtasks, $newContent, $newContent, $taskId]);

            // Set current assignment to aplus_worker_id if specified, or clear old Info assignment
            db()->prepare("DELETE FROM eco_tool_assignments WHERE task_id=?")->execute([$taskId]);
            if ($aplusWorkerId) {
                db()->prepare("INSERT INTO eco_tool_assignments (worker_id, task_id) VALUES (?, ?)")->execute([$aplusWorkerId, $taskId]);
            }

            /* A+ is now an independent phase. The Info service row remains
               Done forever and the new A+ row becomes the active phase. */
            TaskService::ensure($taskId, TaskService::APLUS, [
                'phase_status' => 'Pending',
                'content_status' => 'Pending',
                'worker_id' => $aplusWorkerId,
            ]);
            db()->prepare("UPDATE eco_task_services SET phase_status='Pending', content_status='Pending', media_link=NULL, document_link=NULL, qa_submitted_by=NULL, qa_submitted_at=NULL, work_started_at=NULL, work_paused_at=NULL, work_completed_at=NULL, work_total_seconds=0, completed_by=NULL, completed_at=NULL, worker_id=? WHERE task_id=? AND service_code='aplus'")
                ->execute([$aplusWorkerId, $taskId]);
            TaskService::event($taskId, TaskService::APLUS, 'started', null, null, 'Pending');

            return ['ok'=>true, 'message'=>'A+ Banner workflow shuru ho gaya. Product writer content generation ke liye Pending ho gaya hai.'];
        } catch (\Throwable $e) {
            error_log('startAplusWorkflow error: ' . $e->getMessage());
            return ['ok'=>false, 'message'=>'Database error: ' . $e->getMessage()];
        }
    }

    public static function saveTask(int $id, string $content, string $status, array $user): array {
        $stmt = db()->prepare("SELECT * FROM wp_eco_aplus_tasks WHERE id=?");
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        if(!$task) return ['ok'=>false,'message'=>'Not found'];

        if(in_array($task['status'],['Approved','Updated']) && $user['role'] !== 'administrator'){
            return ['ok'=>false,'message'=>'Locked'];
        }

        $role = $user['role'];
        $serviceCode = self::activeServiceCode($task);
        if ($serviceCode === TaskService::APLUS) {
            TaskService::ensure($id, TaskService::APLUS, ['worker_id' => $task['aplus_worker_id'] ?? null]);
        } elseif (in_array($task['product_type'] ?? '', ['Infographics', 'Info + A Plus'], true)) {
            TaskService::ensure($id, TaskService::INFO, ['worker_id' => $task['info_worker_id'] ?? null]);
        }

        if(($role === 'd4u_writer' || $role === 'seo_manager' || $role === 'administrator') && in_array($status, ['Generated', 'All Generated'])){
            if(($task['product_type'] ?? '') === 'Info + A Plus'){
                $decoded = json_decode($content, true);
                if(is_array($decoded) && !empty($decoded['info']) && !empty($decoded['aplus'])){
                    $hasInfo = false;
                    foreach(['img1','img2','img3','img4','img5','img6'] as $k){
                        if(!empty(trim($decoded['info'][$k] ?? ''))){ $hasInfo = true; break; }
                    }
                    $hasAplus = false;
                    foreach(['b1','b2','b3','b4'] as $bk){
                        if(!empty(trim($decoded['aplus'][$bk] ?? ''))){ $hasAplus = true; break; }
                    }
                    if($hasInfo && $hasAplus){
                        $status = 'All Generated';
                    }
                }
            }
            $writerId = ($role === 'd4u_writer' || $role === 'seo_manager') ? $user['id'] : null;
            if($writerId){
                db()->prepare("UPDATE wp_eco_aplus_tasks SET content=?, original_content=?, status=?, work_status='Pending', written_by_user_id=?, last_activity_at=NOW() WHERE id=?")
                    ->execute([$content, $content, $status, $writerId, $id]);
            } else {
                db()->prepare("UPDATE wp_eco_aplus_tasks SET content=?, original_content=?, status=?, work_status='Pending', last_activity_at=NOW() WHERE id=?")
                    ->execute([$content, $content, $status, $id]);
            }
            TaskService::setContent($id, $serviceCode, $status, (int)($user['id'] ?? 0));
        } elseif($role === 'eco_client' || ($user['username'] ?? '') === 'ilyaeco' || ($role === 'administrator' && in_array($status, ['Approved','Updated']))){
            if(($role === 'eco_client' || ($user['username'] ?? '') === 'ilyaeco') && !in_array($task['status'], ['Generated', 'All Generated'])){
                return ['ok'=>false,'message'=>'Unauthorized: product is not in a stage relevant to your role (must be Generated or All Generated).'];
            }
            $tsApproved = ($status === 'Approved') ? ',content_approved_at=UTC_TIMESTAMP(),content_updated_at=NULL' : '';
            $tsUpdated  = ($status === 'Updated')  ? ',content_updated_at=UTC_TIMESTAMP()' : '';
            db()->prepare("UPDATE wp_eco_aplus_tasks SET content=?, status=?, last_activity_at=NOW() $tsApproved $tsUpdated WHERE id=?")
                ->execute([$content, $status, $id]);
            TaskService::setContent($id, $serviceCode, $status, (int)($user['id'] ?? 0));
        } else {
            return ['ok'=>false,'message'=>'Unauthorized'];
        }

        return ['ok'=>true];
    }

    public static function clearTask(int $id): void {
        db()->prepare("UPDATE wp_eco_aplus_tasks SET content='', status='Pending' WHERE id=?")->execute([$id]);
    }

    public static function deleteTask(int $id): void {
        db()->prepare("UPDATE wp_eco_aplus_tasks SET deleted_at = NOW() WHERE id=?")->execute([$id]);
    }

    public static function bulkDeleteTasks(array $taskIds): int {
        $taskIds = array_values(array_unique(array_filter(array_map('intval', $taskIds))));
        if (empty($taskIds)) return 0;

        $inQuery = implode(',', array_fill(0, count($taskIds), '?'));
        $stmt = db()->prepare("UPDATE wp_eco_aplus_tasks SET deleted_at = NOW() WHERE id IN ($inQuery) AND deleted_at IS NULL");
        $stmt->execute($taskIds);
        return $stmt->rowCount();
    }

    public static function getDeletedTasks(): array {
        $stmt = db()->query("
            SELECT t.*, 
                   cw.username AS work_completed_worker_name,
                   q.username AS qa_user_name
            FROM wp_eco_aplus_tasks t
            LEFT JOIN eco_tool_users q ON q.id = t.qa_submitted_by
            LEFT JOIN eco_tool_users cw ON cw.id = t.work_completed_by_worker_id
            WHERE t.deleted_at IS NOT NULL
            ORDER BY t.deleted_at DESC
        ");
        return $stmt->fetchAll();
    }

    public static function restoreTask(int $id): void {
        db()->prepare("UPDATE wp_eco_aplus_tasks SET deleted_at = NULL WHERE id=?")->execute([$id]);
    }

    public static function permanentDeleteTask(int $id): void {
        db()->prepare("DELETE FROM eco_tool_assignments WHERE task_id=?")->execute([$id]);
        db()->prepare("DELETE FROM eco_task_service_events WHERE task_id=?")->execute([$id]);
        db()->prepare("DELETE FROM eco_task_services WHERE task_id=?")->execute([$id]);
        db()->prepare("DELETE FROM eco_invoice_items WHERE task_id=?")->execute([$id]);
        db()->prepare("DELETE FROM eco_payslip_items WHERE task_id=?")->execute([$id]);
        db()->prepare("DELETE FROM eco_seo_content WHERE task_id=?")->execute([$id]);
        db()->prepare("DELETE FROM wp_eco_aplus_tasks WHERE id=?")->execute([$id]);
    }

    public static function emptyRecycleBin(): void {
        $stmt = db()->query("SELECT id FROM wp_eco_aplus_tasks WHERE deleted_at IS NOT NULL");
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($ids)) {
            $inQuery = implode(',', array_fill(0, count($ids), '?'));
            db()->prepare("DELETE FROM eco_tool_assignments WHERE task_id IN ($inQuery)")->execute($ids);
            db()->prepare("DELETE FROM eco_task_service_events WHERE task_id IN ($inQuery)")->execute($ids);
            db()->prepare("DELETE FROM eco_task_services WHERE task_id IN ($inQuery)")->execute($ids);
            db()->prepare("DELETE FROM eco_invoice_items WHERE task_id IN ($inQuery)")->execute($ids);
            db()->prepare("DELETE FROM eco_payslip_items WHERE task_id IN ($inQuery)")->execute($ids);
            db()->prepare("DELETE FROM eco_seo_content WHERE task_id IN ($inQuery)")->execute($ids);
            db()->prepare("DELETE FROM wp_eco_aplus_tasks WHERE id IN ($inQuery)")->execute($ids);
        }
    }

    public static function addToFamilyById(int $taskId, int $targetTaskId): array {
        if ($taskId === $targetTaskId) {
            return ['ok' => false, 'message' => 'Cannot group a product with itself'];
        }

        $stmt = db()->prepare("SELECT id, family_code, product_no FROM wp_eco_aplus_tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $taskA = $stmt->fetch();
        if (!$taskA) {
            return ['ok' => false, 'message' => 'Task not found'];
        }

        $stmt = db()->prepare("SELECT id, family_code, product_no FROM wp_eco_aplus_tasks WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$targetTaskId]);
        $taskB = $stmt->fetch();
        if (!$taskB) {
            return ['ok' => false, 'message' => 'Target product not found in database'];
        }

        $famCode = null;
        if (!empty($taskA['family_code'])) {
            $famCode = $taskA['family_code'];
        } elseif (!empty($taskB['family_code'])) {
            $famCode = $taskB['family_code'];
        } else {
            $famCode = 'fam_' . bin2hex(random_bytes(8));
        }

        if (empty($taskA['family_code'])) {
            db()->prepare("UPDATE wp_eco_aplus_tasks SET family_code = ? WHERE id = ?")->execute([$famCode, $taskA['id']]);
        }
        if (!empty($taskB['family_code']) && $taskB['family_code'] !== $famCode) {
            db()->prepare("UPDATE wp_eco_aplus_tasks SET family_code = ? WHERE family_code = ?")->execute([$famCode, $taskB['family_code']]);
        } else {
            db()->prepare("UPDATE wp_eco_aplus_tasks SET family_code = ? WHERE id = ?")->execute([$famCode, $taskB['id']]);
        }

        return ['ok' => true];
    }

    public static function addToFamily(int $taskId, string $targetProductNo): array {
        $targetProductNo = preg_replace('/^#/', '', $targetProductNo);
        $targetProductNo = preg_replace('/\+$/', '', $targetProductNo);
        $targetProductNo = trim($targetProductNo);

        $stmt = db()->prepare("SELECT id, family_code, product_no FROM wp_eco_aplus_tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $taskA = $stmt->fetch();
        if (!$taskA) {
            return ['ok' => false, 'message' => 'Task not found'];
        }

        if (strtolower(trim($taskA['product_no'])) === strtolower(trim($targetProductNo))) {
            return ['ok' => false, 'message' => 'Cannot group a product with itself'];
        }

        $stmt = db()->prepare("SELECT id, family_code FROM wp_eco_aplus_tasks WHERE LOWER(TRIM(product_no)) = LOWER(TRIM(?)) AND deleted_at IS NULL");
        $stmt->execute([$targetProductNo]);
        $taskB = $stmt->fetch();
        if (!$taskB) {
            return ['ok' => false, 'message' => 'Product number not found in database'];
        }

        $famCode = null;
        if (!empty($taskA['family_code'])) {
            $famCode = $taskA['family_code'];
        } elseif (!empty($taskB['family_code'])) {
            $famCode = $taskB['family_code'];
        } else {
            $famCode = 'fam_' . bin2hex(random_bytes(8));
        }

        if (empty($taskA['family_code'])) {
            db()->prepare("UPDATE wp_eco_aplus_tasks SET family_code = ? WHERE id = ?")->execute([$famCode, $taskA['id']]);
        }
        if (!empty($taskB['family_code']) && $taskB['family_code'] !== $famCode) {
            db()->prepare("UPDATE wp_eco_aplus_tasks SET family_code = ? WHERE family_code = ?")->execute([$famCode, $taskB['family_code']]);
        } else {
            db()->prepare("UPDATE wp_eco_aplus_tasks SET family_code = ? WHERE id = ?")->execute([$famCode, $taskB['id']]);
        }

        return ['ok' => true];
    }

    public static function removeFromFamily(int $taskId): void {
        db()->prepare("UPDATE wp_eco_aplus_tasks SET family_code = NULL WHERE id = ?")->execute([$taskId]);
    }

    public static function setProductType(int $taskId, string $type): void {
        if ($type === 'Info + A Plus') {
            db()->prepare("UPDATE wp_eco_aplus_tasks SET product_type=?, info_invoice_status=COALESCE(info_invoice_status, invoice_status), last_activity_at=NOW() WHERE id=?")->execute([$type, $taskId]);
        } else {
            db()->prepare("UPDATE wp_eco_aplus_tasks SET product_type=?, last_activity_at=NOW() WHERE id=?")->execute([$type ?: null, $taskId]);
        }
    }

    public static function publishProduct(int $taskId, int $userId, ?string $link): void {
        db()->prepare("UPDATE wp_eco_aplus_tasks SET published_at=NOW(), published_by=?, published_link=?, last_activity_at=NOW() WHERE id=?")
            ->execute([$userId, $link ?: null, $taskId]);
    }

    public static function unpublishProduct(int $taskId): void {
        db()->prepare("UPDATE wp_eco_aplus_tasks SET published_at=NULL, published_by=NULL, last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
    }

    /** Send a QA-failed service back to the same worker with QA comments. */
    public static function requestQARevision(int $taskId, string $comment, array $qaUser): array {
        $stmt = db()->prepare("SELECT * FROM wp_eco_aplus_tasks WHERE id=? AND deleted_at IS NULL");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$task) return ['ok'=>false, 'message'=>'Task not found'];
        if(!in_array(($task['work_status'] ?? ''), ['In QA','AI In QA'], true)){
            return ['ok'=>false, 'message'=>'Sirf QA stage ke task ko revise kiya ja sakta hai'];
        }
        if(trim($comment) === '') return ['ok'=>false, 'message'=>'QA revision comment required'];

        $serviceCode = ($task['work_status'] ?? '') === 'AI In QA' ? TaskService::AI : null;
        if(!$serviceCode){
            try { $serviceCode = TaskService::activeCode($taskId); } catch(Throwable $e) { $serviceCode = null; }
        }
        if(!in_array($serviceCode, [TaskService::AI, TaskService::INFO, TaskService::APLUS], true)){
            $serviceCode = self::isAplusService($task) ? TaskService::APLUS : TaskService::INFO;
        }

        $service = TaskService::get($taskId, $serviceCode) ?: [];
        $workerId = (int)($service['worker_id'] ?? 0);
        if(!$workerId){
            $workerId = $serviceCode === TaskService::AI
                ? (int)($task['ai_worked_by'] ?? 0)
                : ($serviceCode === TaskService::APLUS ? (int)($task['aplus_worker_id'] ?? 0) : (int)($task['info_worker_id'] ?? 0));
        }
        if(!$workerId) $workerId = (int)($task['work_completed_by_worker_id'] ?? 0);
        if(!$workerId){
            $a = db()->prepare("SELECT worker_id FROM eco_tool_assignments WHERE task_id=? LIMIT 1");
            $a->execute([$taskId]);
            $workerId = (int)($a->fetchColumn() ?: 0);
        }
        if(!$workerId) return ['ok'=>false, 'message'=>'Is service ka original worker record nahi mila'];

        $w = db()->prepare("SELECT role FROM eco_tool_users WHERE id=?");
        $w->execute([$workerId]);
        if(!in_array($w->fetchColumn(), ['worker','ai_work'], true)){
            return ['ok'=>false, 'message'=>'Original worker valid worker account nahi hai'];
        }

        $logs = [];
        try { $logs = json_decode((string)($task['revision_log'] ?? ''), true) ?: []; } catch(Throwable $e) {}
        $logs[] = [
            'type' => 'qa', 'service' => $serviceCode, 'comment' => trim($comment),
            'worker_id' => $workerId, 'qa_user_id' => (int)($qaUser['id'] ?? 0),
            'at' => date('Y-m-d H:i:s'),
        ];

        db()->beginTransaction();
        try {
            $legacyStatus = $serviceCode === TaskService::AI ? 'AI Work' : ($task['status'] ?? 'Pending');
            db()->prepare("UPDATE wp_eco_aplus_tasks SET status=?, work_status='Revision',
                revision_comment=?, active_revision_type='qa', revision_count=COALESCE(revision_count,0)+1,
                revision_log=?, work_started_at=NULL, work_paused_at=NULL, qa_submitted_by=?, last_activity_at=NOW()
                WHERE id=? AND work_status IN ('In QA','AI In QA')")
                ->execute([$legacyStatus, trim($comment), json_encode($logs, JSON_UNESCAPED_UNICODE), (int)($qaUser['id'] ?? 0), $taskId]);

            if($serviceCode === TaskService::AI){
                db()->prepare("UPDATE wp_eco_aplus_tasks SET ai_worked_by=? WHERE id=?")->execute([$workerId, $taskId]);
            } elseif($serviceCode === TaskService::INFO){
                db()->prepare("UPDATE wp_eco_aplus_tasks SET info_worker_id=?, work_completed_by_worker_id=? WHERE id=?")->execute([$workerId, $workerId, $taskId]);
            } else {
                db()->prepare("UPDATE wp_eco_aplus_tasks SET aplus_worker_id=?, work_completed_by_worker_id=? WHERE id=?")->execute([$workerId, $workerId, $taskId]);
            }

            db()->prepare("DELETE FROM eco_tool_assignments WHERE task_id=?")->execute([$taskId]);
            db()->prepare("INSERT INTO eco_tool_assignments (worker_id, task_id) VALUES (?, ?)")->execute([$workerId, $taskId]);

            TaskService::ensure($taskId, $serviceCode, ['worker_id'=>$workerId]);
            TaskService::setPhase($taskId, $serviceCode, 'Revision', [
                'worker_id'=>$workerId,
                'qa_submitted_by'=>(int)($qaUser['id'] ?? 0),
                'actor_id'=>(int)($qaUser['id'] ?? 0),
            ]);
            TaskService::event($taskId, $serviceCode, 'qa_revision_requested', (int)($qaUser['id'] ?? 0), 'In QA', 'Revision', trim($comment));

            db()->commit();
            return ['ok'=>true, 'worker_id'=>$workerId, 'service_code'=>$serviceCode];
        } catch(Throwable $e){
            if(db()->inTransaction()) db()->rollBack();
            error_log('requestQARevision error: ' . $e->getMessage());
            return ['ok'=>false, 'message'=>'Revision request save nahi ho saki'];
        }
    }

    public static function requestRevision(int $taskId, string $revType, string $comment): array {
        $tstmt = db()->prepare("
            SELECT t.id, t.written_by_user_id, t.revision_log, a.worker_id AS assigned_worker_id
            FROM wp_eco_aplus_tasks t
            LEFT JOIN eco_tool_assignments a ON a.task_id=t.id
            WHERE t.id=?
        ");
        $tstmt->execute([$taskId]);
        $task = $tstmt->fetch();
        if(!$task){
            return ['ok' => false, 'message' => 'Task not found'];
        }

        $workerId = 0;
        $newStatus = '';
        if($revType === 'design'){
            $workerId = intval($task['assigned_worker_id']);
            $newStatus = 'Changes';
        } else {
            $workerId = intval($task['written_by_user_id']);
            $newStatus = 'Changes in Content';
        }

        if(!$workerId){
            return ['ok' => false, 'message' => 'No worker/writer assigned to this task for revision.'];
        }

        $rateStmt = db()->prepare("SELECT COALESCE(fine_per_revision,0) FROM eco_worker_rates WHERE worker_id=?");
        $rateStmt->execute([$workerId]);
        $fine = floatval($rateStmt->fetchColumn() ?: 0);

        $logs = json_decode($task['revision_log'] ?: '[]', true) ?: [];
        $logs[] = [
            'type'    => $revType,
            'comment' => $comment ?: '',
            'at'      => date('Y-m-d H:i:s'),
        ];
        $newLog = json_encode($logs, JSON_UNESCAPED_UNICODE);

        db()->beginTransaction();
        try {
            db()->prepare("
                UPDATE wp_eco_aplus_tasks
                SET work_status=?, last_activity_at=NOW(), revision_comment=?, active_revision_type=?,
                    revision_count = revision_count + 1, revision_log=?
                WHERE id=?
            ")->execute([$newStatus, $comment ?: null, $revType, $newLog, $taskId]);

            db()->prepare("
                INSERT INTO eco_penalties (task_id, worker_id, type, penalty_amount, status, notes)
                VALUES (?,?,?,?,'Pending',?)
            ")->execute([$taskId, $workerId, $revType, $fine, $comment ?: null]);

            db()->commit();
            return ['ok' => true];
        } catch(Exception $e) {
            db()->rollBack();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public static function sendForContent(int $taskId, array $user): array {
        if(!in_array($user['role'] ?? '', ['administrator','d4u_writer','seo_manager'], true)){
            return ['ok'=>false,'message'=>'Content generation sirf admin ya content writer ke liye hai'];
        }
        if($user['role'] !== 'administrator'){
            $s = db()->prepare("SELECT id FROM eco_tool_assignments WHERE task_id=? AND worker_id=?");
            $s->execute([$taskId, $user['id']]);
            if(!$s->fetch()) return ['ok'=>false,'message'=>'Assigned nahi hai'];
        }
        db()->prepare("UPDATE wp_eco_aplus_tasks SET status='Pending', work_status='Content Pending', last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
        return ['ok'=>true];
    }

    public static function bulkMarkUrgent(array $taskIds, int $priority = 1): void {
        if (empty($taskIds)) return;
        $val = max(0, min(3, $priority));
        $inQuery = implode(',', array_fill(0, count($taskIds), '?'));
        
        // Find all family_codes for these products
        $stmt = db()->prepare("
            SELECT DISTINCT family_code 
            FROM wp_eco_aplus_tasks 
            WHERE id IN ($inQuery) AND family_code IS NOT NULL AND family_code != ''
        ");
        $stmt->execute($taskIds);
        $families = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Update selected tasks directly
        $updateParams = array_merge([$val], $taskIds);
        db()->prepare("
            UPDATE wp_eco_aplus_tasks
            SET is_urgent = ?, last_activity_at = NOW()
            WHERE id IN ($inQuery)
        ")->execute($updateParams);

        // Also update all sibling products in the same groups
        if (!empty($families)) {
            $famInQuery = implode(',', array_fill(0, count($families), '?'));
            $famParams = array_merge([$val], $families);
            db()->prepare("
                UPDATE wp_eco_aplus_tasks
                SET is_urgent = ?, last_activity_at = NOW()
                WHERE family_code IN ($famInQuery) AND deleted_at IS NULL
            ")->execute($famParams);
        }
    }

    public static function bulkRemoveUrgent(array $taskIds): void {
        if (empty($taskIds)) return;
        self::bulkMarkUrgent($taskIds, 0);
    }

    public static function bulkMarkHold(array $taskIds): void {
        foreach ($taskIds as $taskId) {
            $row = db()->prepare("SELECT status, work_status FROM wp_eco_aplus_tasks WHERE id=?");
            $row->execute([$taskId]);
            $t = $row->fetch();
            if ($t && $t['status'] !== 'Hold') {
                $prev = json_encode(['status' => $t['status'], 'work_status' => $t['work_status']]);
                db()->prepare("
                    UPDATE wp_eco_aplus_tasks
                    SET status = 'Hold', work_status = 'Hold', hold_prev_status = ?, last_activity_at = NOW()
                    WHERE id = ?
                ")->execute([$prev, $taskId]);
            }
        }
    }

    public static function bulkMarkNoCharge(array $taskIds, string $reason = '', int $userId = 0): array {
        $skipped = [];
        try {
            db()->beginTransaction();
            foreach ($taskIds as $taskId) {
                $result = self::setNoCharge((int)$taskId, 1, $reason, $userId);
                if (!$result['ok']) {
                    $skipped[] = '#' . (int)$taskId . ': ' . ($result['message'] ?? 'unknown error');
                }
            }
            if (!empty($skipped)) {
                db()->rollBack();
                return ['updated'=>0, 'skipped'=>$skipped];
            }
            db()->commit();
            return ['updated'=>count($taskIds), 'skipped'=>[]];
        } catch(Exception $e) {
            if (db()->inTransaction()) db()->rollBack();
            return ['updated'=>0, 'skipped'=>['Database error: ' . $e->getMessage()]];
        }
    }

    public static function bulkGroupTasks(array $taskIds): array {
        if (count($taskIds) < 2) {
            return ['ok' => false, 'message' => 'Please select at least 2 products to group.'];
        }

        $inQuery = implode(',', array_fill(0, count($taskIds), '?'));
        $stmt = db()->prepare("SELECT id, product_no, family_code FROM wp_eco_aplus_tasks WHERE id IN ($inQuery) AND deleted_at IS NULL");
        $stmt->execute($taskIds);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($tasks)) {
            return ['ok' => false, 'message' => 'Selected products not found.'];
        }

        // Find existing family code among selected tasks, if any
        $famCode = null;
        $existingCodes = [];
        foreach ($tasks as $t) {
            if (!empty($t['family_code'])) {
                if (!$famCode) {
                    $famCode = $t['family_code'];
                }
                if (!in_array($t['family_code'], $existingCodes)) {
                    $existingCodes[] = $t['family_code'];
                }
            }
        }

        if (!$famCode) {
            $famCode = 'fam_' . bin2hex(random_bytes(8));
        }

        // If multiple distinct family codes exist, merge them all into $famCode
        foreach ($existingCodes as $oldCode) {
            if ($oldCode !== $famCode) {
                db()->prepare("UPDATE wp_eco_aplus_tasks SET family_code = ?, last_activity_at = NOW() WHERE family_code = ?")
                    ->execute([$famCode, $oldCode]);
            }
        }

        // Assign $famCode to all selected tasks
        $params = array_merge([$famCode], $taskIds);
        db()->prepare("UPDATE wp_eco_aplus_tasks SET family_code = ?, last_activity_at = NOW() WHERE id IN ($inQuery)")
            ->execute($params);

        return [
            'ok' => true,
            'family_code' => $famCode,
            'count' => count($tasks),
            'message' => count($tasks) . ' products grouped successfully.'
        ];
    }

    public static function bulkUngroupTasks(array $taskIds): void {
        if (empty($taskIds)) return;
        $inQuery = implode(',', array_fill(0, count($taskIds), '?'));
        db()->prepare("UPDATE wp_eco_aplus_tasks SET family_code = NULL, last_activity_at = NOW() WHERE id IN ($inQuery)")
            ->execute($taskIds);
    }

    public static function renameProduct(int $taskId, string $newProductNo, string $newTitle = '', bool $force = false): array {
        $stmt = db()->prepare("SELECT id, product_no, title, family_code, status, work_status FROM wp_eco_aplus_tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();
        if (!$task) {
            return ['ok' => false, 'message' => 'Product not found'];
        }

        $cleanProductNo = ltrim(trim($newProductNo), '#');
        $cleanProductNo = trim($cleanProductNo);
        $cleanTitle = trim($newTitle);

        if ($cleanProductNo === '') {
            return ['ok' => false, 'message' => 'Product number cannot be empty'];
        }

        // Check if another active product already has this product_no (duplicate warning)
        if (!$force && strtolower(trim($task['product_no'])) !== strtolower($cleanProductNo)) {
            $dupStmt = db()->prepare("
                SELECT id, product_no, title 
                FROM wp_eco_aplus_tasks 
                WHERE LOWER(TRIM(product_no)) = LOWER(?) AND id != ? AND deleted_at IS NULL
                LIMIT 1
            ");
            $dupStmt->execute([$cleanProductNo, $taskId]);
            $dup = $dupStmt->fetch();
            if ($dup) {
                return [
                    'ok' => false,
                    'is_duplicate' => true,
                    'duplicate_product_no' => $dup['product_no'],
                    'duplicate_title' => $dup['title'],
                    'duplicate_id' => $dup['id'],
                    'message' => "Product #{$dup['product_no']} already exists for '{$dup['title']}' (ID: #{$dup['id']}). Agar aap phir bhi yehi number rakhna chahte hain to 'Confirm Rename Anyway' check karein."
                ];
            }
        }

        $titleToSave = ($cleanTitle !== '') ? $cleanTitle : $task['title'];

        // Perform rename: updating ONLY product_no and title
        // Backend id, family_code, work_status, status, workers, history remain 100% UNCHANGED
        $updateStmt = db()->prepare("
            UPDATE wp_eco_aplus_tasks 
            SET product_no = ?, title = ?, last_activity_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$cleanProductNo, $titleToSave, $taskId]);

        return [
            'ok' => true,
            'message' => 'Product renamed successfully!',
            'task_id' => $taskId,
            'old_product_no' => $task['product_no'],
            'new_product_no' => $cleanProductNo,
            'title' => $titleToSave,
            'family_code' => $task['family_code']
        ];
    }

}
