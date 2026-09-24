<?php

/*
=====================================================
TASK MODEL
All task-related SQL queries extracted from monolith
DB schema is NOT changed — same tables/columns
=====================================================
*/

class Task {

    /*
    ──────────────────────────────────────────────────
    getTasksForRole()
    Role-aware task fetch (exact SQL from monolith)
    ──────────────────────────────────────────────────
    */
    public static function getTasksForRole(array $user): array {

        $cols = "
            t.*,
            a.worker_id AS assigned_worker_id,
            q.username AS qa_user_name,
            cw.username AS work_completed_worker_name,
            pu.username AS published_by_name,
            aiw.username AS ai_worked_by_name,
            infow.username AS info_worker_name,
            aplusw.username AS aplus_worker_name,
            t.work_status, t.work_started_at, t.work_completed_at,
            t.work_paused_at, t.work_total_seconds,
            (
                SELECT p.status
                FROM eco_payslips p
                JOIN eco_payslip_items pi ON p.id = pi.payslip_id
                WHERE pi.task_id = t.id
                ORDER BY p.id DESC
                LIMIT 1
            ) AS payslip_status
        ";

        switch($user['role']){

            case 'worker':
                $stmt = db()->prepare("
                SELECT $cols
                FROM wp_eco_aplus_tasks t
                LEFT JOIN eco_tool_assignments a ON a.task_id = t.id
                LEFT JOIN eco_tool_users q  ON q.id  = t.qa_submitted_by
                LEFT JOIN eco_tool_users cw ON cw.id = t.work_completed_by_worker_id
                LEFT JOIN eco_tool_users pu ON pu.id = t.published_by
                LEFT JOIN eco_tool_users aiw ON aiw.id = t.ai_worked_by
                LEFT JOIN eco_tool_users infow ON infow.id = t.info_worker_id
                LEFT JOIN eco_tool_users aplusw ON aplusw.id = t.aplus_worker_id
                WHERE t.deleted_at IS NULL AND t.status != 'Hold'
                  AND (
                      a.worker_id = ?
                      OR t.info_worker_id = ?
                      OR t.aplus_worker_id = ?
                      OR t.work_completed_by_worker_id = ?
                      OR (t.family_code IS NOT NULL AND t.family_code IN (
                          SELECT DISTINCT t2.family_code
                          FROM wp_eco_aplus_tasks t2
                          LEFT JOIN eco_tool_assignments a2 ON a2.task_id = t2.id
                          WHERE t2.family_code IS NOT NULL
                            AND t2.deleted_at IS NULL
                            AND (a2.worker_id = ? OR t2.info_worker_id = ? OR t2.aplus_worker_id = ? OR t2.work_completed_by_worker_id = ?)
                      ))
                  )
                ORDER BY t.id DESC
                ");
                $stmt->execute([$user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id']]);
                break;

            case 'qa':
                $stmt = db()->prepare("
                SELECT $cols
                FROM wp_eco_aplus_tasks t
                LEFT JOIN eco_tool_assignments a ON a.task_id = t.id
                LEFT JOIN eco_tool_users q  ON q.id  = t.qa_submitted_by
                LEFT JOIN eco_tool_users cw ON cw.id = t.work_completed_by_worker_id
                LEFT JOIN eco_tool_users pu ON pu.id = t.published_by
                LEFT JOIN eco_tool_users aiw ON aiw.id = t.ai_worked_by
                LEFT JOIN eco_tool_users infow ON infow.id = t.info_worker_id
                LEFT JOIN eco_tool_users aplusw ON aplusw.id = t.aplus_worker_id
                WHERE t.deleted_at IS NULL AND t.status != 'Hold'
                  AND (
                      t.work_status = 'In QA'
                      OR t.work_status = 'SEO Review'
                      OR t.work_status = 'Info Done'
                      OR t.qa_submitted_by = ?
                      OR (t.family_code IS NOT NULL AND t.family_code IN (
                          SELECT DISTINCT t2.family_code
                          FROM wp_eco_aplus_tasks t2
                          WHERE t2.family_code IS NOT NULL
                            AND t2.deleted_at IS NULL
                            AND (t2.work_status = 'In QA' OR t2.work_status = 'SEO Review' OR t2.work_status = 'Info Done' OR t2.qa_submitted_by = ?)
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
                LEFT JOIN eco_tool_assignments a ON a.task_id = t.id
                LEFT JOIN eco_tool_users q  ON q.id  = t.qa_submitted_by
                LEFT JOIN eco_tool_users cw ON cw.id = t.work_completed_by_worker_id
                LEFT JOIN eco_tool_users pu ON pu.id = t.published_by
                LEFT JOIN eco_tool_users aiw ON aiw.id = t.ai_worked_by
                LEFT JOIN eco_tool_users infow ON infow.id = t.info_worker_id
                LEFT JOIN eco_tool_users aplusw ON aplusw.id = t.aplus_worker_id
                WHERE t.deleted_at IS NULL AND t.status != 'Hold'
                  AND (
                      t.written_by_user_id = ?
                      OR t.status = 'Pending'
                      OR t.work_status = 'SEO Review'
                      OR t.qa_submitted_by = ?
                      OR (t.family_code IS NOT NULL AND t.family_code IN (
                          SELECT DISTINCT t2.family_code
                          FROM wp_eco_aplus_tasks t2
                          WHERE t2.family_code IS NOT NULL
                            AND t2.deleted_at IS NULL
                            AND (
                                t2.written_by_user_id = ?
                                OR t2.status = 'Pending'
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
                $stmt = db()->prepare("
                SELECT $cols
                FROM wp_eco_aplus_tasks t
                LEFT JOIN eco_tool_assignments a ON a.task_id = t.id
                LEFT JOIN eco_tool_users q  ON q.id  = t.qa_submitted_by
                LEFT JOIN eco_tool_users cw ON cw.id = t.work_completed_by_worker_id
                LEFT JOIN eco_tool_users pu ON pu.id = t.published_by
                LEFT JOIN eco_tool_users aiw ON aiw.id = t.ai_worked_by
                LEFT JOIN eco_tool_users infow ON infow.id = t.info_worker_id
                LEFT JOIN eco_tool_users aplusw ON aplusw.id = t.aplus_worker_id
                WHERE t.deleted_at IS NULL AND t.status != 'Hold'
                  AND (
                      a.worker_id = ?
                      OR t.ai_worked_by = ?
                      OR (t.family_code IS NOT NULL AND t.family_code IN (
                          SELECT DISTINCT t2.family_code
                          FROM wp_eco_aplus_tasks t2
                          LEFT JOIN eco_tool_assignments a2 ON a2.task_id = t2.id
                          WHERE t2.family_code IS NOT NULL
                            AND t2.deleted_at IS NULL
                            AND (a2.worker_id = ? OR t2.ai_worked_by = ?)
                      ))
                  )
                ORDER BY t.id DESC
                ");
                $stmt->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
                break;

            case 'eco_listing':
                $stmt = db()->prepare("
                SELECT $cols
                FROM wp_eco_aplus_tasks t
                LEFT JOIN eco_tool_assignments a ON a.task_id = t.id
                LEFT JOIN eco_tool_users q  ON q.id  = t.qa_submitted_by
                LEFT JOIN eco_tool_users cw ON cw.id = t.work_completed_by_worker_id
                LEFT JOIN eco_tool_users pu ON pu.id = t.published_by
                LEFT JOIN eco_tool_users aiw ON aiw.id = t.ai_worked_by
                LEFT JOIN eco_tool_users infow ON infow.id = t.info_worker_id
                LEFT JOIN eco_tool_users aplusw ON aplusw.id = t.aplus_worker_id
                WHERE t.deleted_at IS NULL
                ORDER BY t.is_urgent DESC, t.id DESC
                ");
                $stmt->execute([]);
                break;

            default: /* administrator, eco_client — see everything including Hold, but NOT deleted */
                $stmt = db()->query("
                SELECT $cols
                FROM wp_eco_aplus_tasks t
                LEFT JOIN eco_tool_assignments a ON a.task_id = t.id
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
                // Remove assignment so it goes back to pool for next worker
                db()->prepare("DELETE FROM eco_tool_assignments WHERE task_id=?")->execute([$taskId]);
                return true;
            }
        }

        if($status === 'Changing'){
            db()->prepare("UPDATE wp_eco_aplus_tasks SET work_status='Changing', work_started_at=NOW(), work_paused_at=NULL, last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
            return true;
        }

        if($status === 'Info Work'){
            db()->prepare("UPDATE wp_eco_aplus_tasks SET work_status='Info Work', last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
            return true;
        }

        if($status === 'Working'){
            db()->prepare("UPDATE wp_eco_aplus_tasks SET work_status='Working', work_started_at=NOW(), work_paused_at=NULL, last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
            return true;
        }

        if($status === 'In QA'){

            $row = db()->prepare("SELECT status, product_type, work_started_at, work_paused_at, work_total_seconds, info_worker_id, aplus_worker_id FROM wp_eco_aplus_tasks WHERE id=?");
            $row->execute([$taskId]);
            $t = $row->fetch();

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
                $pt = $t['product_type'] ?? '';
                $st = $t['status'] ?? '';
                if($pt === 'A Plus' || $st === 'A Plus' || ($pt === 'Info + A Plus' && !empty($t['info_worker_id']) && empty($t['aplus_worker_id']))){
                    $extraSql .= ", aplus_worker_id = ?";
                    $extraParams[] = $completedBy;
                } else {
                    $extraSql .= ", info_worker_id = COALESCE(info_worker_id, ?)";
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

        return true;

    }

    /*
    ──────────────────────────────────────────────────
    submitQA()
    ──────────────────────────────────────────────────
    */
    public static function submitQA(int $taskId, string $mediaLink, string $seoLink, array $user): bool {

        $taskRow = db()->prepare("SELECT product_type, status, content_approved_at, content_updated_at, info_worker_id, work_completed_by_worker_id FROM wp_eco_aplus_tasks WHERE id=?");
        $taskRow->execute([$taskId]);
        $task = $taskRow->fetch();

        $isInfoStage = ($task && (
            ($task['product_type'] ?? '') === 'Infographics' ||
            (($task['product_type'] ?? '') === 'Info + A Plus' && in_array($task['status'] ?? '', ['Pending', 'AI DONE', 'Infographics']) && empty($task['content_approved_at']) && empty($task['content_updated_at']))
        ));

        if ($isInfoStage) {
            $workerId = $task['info_worker_id'] ?? null;
            if (!$workerId) {
                $assignRow = db()->prepare("SELECT worker_id FROM eco_tool_assignments WHERE task_id=?");
                $assignRow->execute([$taskId]);
                $assign = $assignRow->fetch();
                $workerId = $assign['worker_id'] ?? $task['work_completed_by_worker_id'] ?? null;
            }

            $stmt = db()->prepare("
                UPDATE wp_eco_aplus_tasks
                SET media_link=?, work_status='Info Done',
                    info_worker_id=COALESCE(info_worker_id, ?),
                    work_completed_by_worker_id=COALESCE(work_completed_by_worker_id, ?),
                    qa_submitted_at=NOW(), qa_submitted_by=?, is_urgent=0, active_revision_type=NULL, last_activity_at=NOW()
                WHERE id=? AND work_status='In QA'
            ");
            $stmt->execute([$mediaLink, $workerId, $workerId, $user['id'], $taskId]);
            return $stmt->rowCount() > 0;
        }

        $fromStatus = ($user['role'] === 'seo_manager') ? 'SEO Review' : 'In QA';

        if($user['role'] === 'seo_manager'){
            $stmt = db()->prepare("
                UPDATE wp_eco_aplus_tasks
                SET media_link=?, seo_doc_link=?, work_status='Work Done',
                    qa_submitted_at=NOW(), seo_submitted_by=?, is_urgent=0, active_revision_type=NULL, last_activity_at=NOW()
                WHERE id=? AND work_status='SEO Review'
            ");
            $stmt->execute([$mediaLink, $seoLink, $user['id'], $taskId]);
        } else {
            $stmt = db()->prepare("
                UPDATE wp_eco_aplus_tasks
                SET media_link=?, seo_doc_link=?, work_status='Work Done',
                    qa_submitted_at=NOW(), qa_submitted_by=?, is_urgent=0, active_revision_type=NULL, last_activity_at=NOW()
                WHERE id=? AND work_status='In QA'
            ");
            $stmt->execute([$mediaLink, $seoLink, $user['id'], $taskId]);
        }

        return $stmt->rowCount() > 0;

    }

    /*
    ──────────────────────────────────────────────────
    sendForSEO()
    ──────────────────────────────────────────────────
    */
    public static function sendForSEO(int $taskId, int $userId): bool {
        $stmt = db()->prepare("UPDATE wp_eco_aplus_tasks SET work_status='SEO Review', qa_submitted_by=?, last_activity_at=NOW() WHERE id=? AND work_status='In QA'");
        $stmt->execute([$userId, $taskId]);
        return $stmt->rowCount() > 0;
    }

    /*
    ──────────────────────────────────────────────────
    saveFinalLinks() — admin override
    ──────────────────────────────────────────────────
    */
    public static function saveFinalLinks(int $taskId, string $mediaLink, string $seoLink): void {
        $cur = db()->prepare("SELECT work_status FROM wp_eco_aplus_tasks WHERE id=?");
        $cur->execute([$taskId]);
        $cRow = $cur->fetch();
        $targetStatus = ($cRow && $cRow['work_status'] === 'Info Done') ? 'Info Done' : 'Work Done';

        db()->prepare("
            UPDATE wp_eco_aplus_tasks
            SET media_link=?, seo_doc_link=?, work_status=?, is_urgent=0, active_revision_type=NULL, last_activity_at=NOW()
            WHERE id=?
        ")->execute([$mediaLink, $seoLink, $targetStatus, $taskId]);
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

            case 'Generated':
                db()->prepare("DELETE FROM eco_tool_assignments WHERE task_id=?")->execute([$taskId]);
                db()->prepare("
                    UPDATE wp_eco_aplus_tasks SET
                        status='Generated', work_status='Pending',
                        work_started_at=NULL, work_completed_at=NULL, work_paused_at=NULL,
                        work_total_seconds=0, media_link=NULL, seo_doc_link=NULL,
                        qa_submitted_at=NULL, qa_submitted_by=NULL, is_urgent=0,
                        content_approved_at=NULL, content_updated_at=NULL, last_activity_at=NOW()
                    WHERE id=?
                ")->execute([$taskId]);
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
                db()->prepare("UPDATE wp_eco_aplus_tasks SET work_status='Info Done', work_completed_at=NOW(), work_completed_by_worker_id=COALESCE(work_completed_by_worker_id, ?), info_worker_id=COALESCE(info_worker_id, ?), is_urgent=0, active_revision_type=NULL, last_activity_at=NOW() WHERE id=?")->execute([$wcwId, $wcwId, $taskId]);
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

    public static function toggleUrgent(int $taskId, int $urgent): void {
        db()->prepare("UPDATE wp_eco_aplus_tasks SET is_urgent=?, last_activity_at=NOW() WHERE id=?")->execute([$urgent, $taskId]);
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
        } elseif ($wRow && $wRow['role'] === 'worker') {
            $tStmt = db()->prepare("SELECT status, product_type, info_worker_id, aplus_worker_id FROM wp_eco_aplus_tasks WHERE id=?");
            $tStmt->execute([$taskId]);
            $tRow = $tStmt->fetch();
            if ($tRow && $tRow['status'] === 'AI DONE') {
                db()->prepare("UPDATE wp_eco_aplus_tasks SET work_status='Pending', last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
            }
            if ($tRow) {
                $pt = $tRow['product_type'] ?? '';
                $st = $tRow['status'] ?? '';
                if ($pt === 'A Plus' || $st === 'A Plus') {
                    db()->prepare("UPDATE wp_eco_aplus_tasks SET aplus_worker_id = COALESCE(aplus_worker_id, ?) WHERE id=?")->execute([$workerId, $taskId]);
                } elseif ($pt === 'Infographics' || empty($tRow['info_worker_id'])) {
                    db()->prepare("UPDATE wp_eco_aplus_tasks SET info_worker_id = COALESCE(info_worker_id, ?) WHERE id=?")->execute([$workerId, $taskId]);
                }
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

        $sql = "UPDATE wp_eco_aplus_tasks SET product_type=?, info_subtasks=?, ai_worked_by=?, info_worker_id=?, aplus_worker_id=?, last_activity_at=NOW() WHERE id=?";
        db()->prepare($sql)->execute([
            $productType,
            $infoSubtasks ?: null,
            $aiWorkerId ?: null,
            $infoWorkerId ?: null,
            $aplusWorkerId ?: null,
            $taskId
        ]);

        if($activeAssign === 'ai' && $aiWorkerId){
            db()->prepare("DELETE FROM eco_tool_assignments WHERE task_id=?")->execute([$taskId]);
            db()->prepare("INSERT INTO eco_tool_assignments (worker_id, task_id) VALUES(?, ?)")->execute([$aiWorkerId, $taskId]);
            db()->prepare("UPDATE wp_eco_aplus_tasks SET status='AI Work', work_status='Pending', last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
        } elseif($activeAssign === 'info' && $infoWorkerId){
            db()->prepare("DELETE FROM eco_tool_assignments WHERE task_id=?")->execute([$taskId]);
            db()->prepare("INSERT INTO eco_tool_assignments (worker_id, task_id) VALUES(?, ?)")->execute([$infoWorkerId, $taskId]);
            if($task['status'] === 'AI Work' || $task['status'] === 'AI DONE'){
                db()->prepare("UPDATE wp_eco_aplus_tasks SET status='Infographics', work_status='Pending', last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
            }
        } elseif($activeAssign === 'aplus' && $aplusWorkerId){
            db()->prepare("DELETE FROM eco_tool_assignments WHERE task_id=?")->execute([$taskId]);
            db()->prepare("INSERT INTO eco_tool_assignments (worker_id, task_id) VALUES(?, ?)")->execute([$aplusWorkerId, $taskId]);
            if(!in_array($task['status'], ['Approved', 'Updated', 'Work Done'])){
                db()->prepare("UPDATE wp_eco_aplus_tasks SET status='A Plus', work_status='Pending', last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
            }
        } elseif($activeAssign === 'start_aplus_content'){
            self::startAplusWorkflow($taskId);
        } elseif($activeAssign === 'none'){
            db()->prepare("DELETE FROM eco_tool_assignments WHERE task_id=?")->execute([$taskId]);
        }

        return ['ok'=>true, 'message'=>'Product services & assignments updated successfully'];
    }

    public static function addProduct(string $productNo, string $title, ?string $infoSubtasks, ?string $productLink = null): int {
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
        db()->prepare("INSERT INTO wp_eco_aplus_tasks (product_no, title, status, work_status, product_type, info_subtasks, product_link) VALUES (?, ?, 'Pending', 'Pending', ?, ?, ?)")
            ->execute([$productNo, $title, $type ?: null, $infoSubtasks ?: null, $productLink ?: null]);
        return (int) db()->lastInsertId();
    }

    public static function startAplusWorkflow(int $taskId): array {
        $stmt = db()->prepare("SELECT * FROM wp_eco_aplus_tasks WHERE id=?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();
        if(!$task) return ['ok'=>false, 'message'=>'Task not found'];

        // Add 'A+ Banners' to info_subtasks if not already present
        $subtasks = [];
        if(!empty($task['info_subtasks'])){
            $subtasks = array_map('trim', explode(',', $task['info_subtasks']));
        }
        if(!in_array('A+ Banners', $subtasks)){
            $subtasks[] = 'A+ Banners';
        }
        $infoSubtasks = implode(',', $subtasks);

        // Delete current assignment so product goes into writer pool for content generation
        // db()->prepare("DELETE FROM eco_tool_assignments WHERE task_id=?")->execute([$taskId]);

        // Preserve existing Infographics content so worker and client can reference it
        $existingContent = $task['content'] ?? '';
        $newContent = '';
        if(!empty($existingContent)){
            $decoded = json_decode($existingContent, true);
            if(is_array($decoded)){
                $decoded['aplus'] = ['b1'=>'', 'b2'=>'', 'b3'=>'', 'b4'=>'', 'extra'=>''];
                $newContent = json_encode($decoded);
            } else {
                $newContent = json_encode([
                    '_format' => 'grid_v2',
                    'info' => [
                        'img1' => $existingContent,
                        'img2' => '',
                        'img3' => '',
                        'img4' => '',
                        'img5' => '',
                        'img6' => '',
                        'extra' => ''
                    ],
                    'aplus' => ['b1'=>'', 'b2'=>'', 'b3'=>'', 'b4'=>'', 'extra'=>'']
                ]);
            }
        }

        // Transition to standard A+ content generation workflow
        db()->prepare("
            UPDATE wp_eco_aplus_tasks
            SET product_type='Info + A Plus',
                info_subtasks=?,
                status='Pending',
                work_status='Pending',
                content=?,
                original_content=?,
                content_approved_at=NULL,
                content_updated_at=NULL,
                seo_doc_link=NULL,
                published_at=NULL,
                published_by=NULL,
                published_link=NULL,
                last_activity_at=NOW()
            WHERE id=?
        ")->execute([$infoSubtasks, $newContent, $newContent, $taskId]);

        return ['ok'=>true, 'message'=>'A+ Banner workflow shuru ho gaya. Product writer content generation ke liye Pending ho gaya hai.'];
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

        if(($role === 'd4u_writer' || $role === 'seo_manager' || $role === 'administrator') && $status === 'Generated'){
            $writerId = ($role === 'd4u_writer' || $role === 'seo_manager') ? $user['id'] : null;
            if($writerId){
                db()->prepare("UPDATE wp_eco_aplus_tasks SET content=?, original_content=?, status=?, work_status='Pending', written_by_user_id=?, last_activity_at=NOW() WHERE id=?")
                    ->execute([$content, $content, $status, $writerId, $id]);
            } else {
                db()->prepare("UPDATE wp_eco_aplus_tasks SET content=?, original_content=?, status=?, work_status='Pending', last_activity_at=NOW() WHERE id=?")
                    ->execute([$content, $content, $status, $id]);
            }
        } elseif($role === 'eco_client' || ($role === 'administrator' && in_array($status, ['Approved','Updated']))){
            if($role === 'eco_client' && $task['status'] !== 'Generated'){
                return ['ok'=>false,'message'=>'Unauthorized: product is not in a stage relevant to your role (must be Generated).'];
            }
            $tsApproved = ($status === 'Approved') ? ',content_approved_at=UTC_TIMESTAMP(),content_updated_at=NULL' : '';
            $tsUpdated  = ($status === 'Updated')  ? ',content_updated_at=UTC_TIMESTAMP()' : '';
            db()->prepare("UPDATE wp_eco_aplus_tasks SET content=?, status=?, last_activity_at=NOW() $tsApproved $tsUpdated WHERE id=?")
                ->execute([$content, $status, $id]);
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
            db()->prepare("DELETE FROM eco_invoice_items WHERE task_id IN ($inQuery)")->execute($ids);
            db()->prepare("DELETE FROM eco_payslip_items WHERE task_id IN ($inQuery)")->execute($ids);
            db()->prepare("DELETE FROM eco_seo_content WHERE task_id IN ($inQuery)")->execute($ids);
            db()->prepare("DELETE FROM wp_eco_aplus_tasks WHERE id IN ($inQuery)")->execute($ids);
        }
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
        db()->prepare("UPDATE wp_eco_aplus_tasks SET product_type=?, last_activity_at=NOW() WHERE id=?")->execute([$type ?: null, $taskId]);
    }

    public static function publishProduct(int $taskId, int $userId, ?string $link): void {
        db()->prepare("UPDATE wp_eco_aplus_tasks SET published_at=NOW(), published_by=?, published_link=?, last_activity_at=NOW() WHERE id=?")
            ->execute([$userId, $link ?: null, $taskId]);
    }

    public static function unpublishProduct(int $taskId): void {
        db()->prepare("UPDATE wp_eco_aplus_tasks SET published_at=NULL, published_by=NULL, last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
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
        if($user['role'] !== 'administrator'){
            $s = db()->prepare("SELECT id FROM eco_tool_assignments WHERE task_id=? AND worker_id=?");
            $s->execute([$taskId, $user['id']]);
            if(!$s->fetch()) return ['ok'=>false,'message'=>'Assigned nahi hai'];
        }
        db()->prepare("UPDATE wp_eco_aplus_tasks SET status='Pending', work_status='Content Pending', last_activity_at=NOW() WHERE id=?")->execute([$taskId]);
        return ['ok'=>true];
    }

    public static function bulkMarkUrgent(array $taskIds): void {
        if (empty($taskIds)) return;
        $inQuery = implode(',', array_fill(0, count($taskIds), '?'));
        db()->prepare("
            UPDATE wp_eco_aplus_tasks
            SET is_urgent = 1, last_activity_at = NOW()
            WHERE id IN ($inQuery)
        ")->execute($taskIds);
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

}
