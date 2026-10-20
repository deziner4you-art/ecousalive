<?php

/**
 * Service-level workflow state.
 *
 * The legacy task columns are retained for compatibility with old reports,
 * but all new workflow decisions for a service use this model.  A product can
 * therefore have Info and A+ (and future services) without one phase
 * overwriting the other phase's status.
 */
class TaskService
{
    public const INFO = 'info';
    public const APLUS = 'aplus';
    public const AI = 'ai';

    public static function label(string $code): string
    {
        return $code === self::APLUS ? 'A+ Banners' : ($code === self::INFO ? 'Infographics' : ucfirst($code));
    }

    public static function ensure(int $taskId, string $code, array $values = []): int
    {
        $order = $code === self::APLUS ? 2 : ($code === self::INFO ? 1 : 0);
        $stmt = db()->prepare("INSERT INTO eco_task_services
            (task_id, service_code, service_order, phase_status, content_status, worker_id)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
        $stmt->execute([
            $taskId,
            $code,
            $order,
            $values['phase_status'] ?? 'Pending',
            $values['content_status'] ?? 'Pending',
            $values['worker_id'] ?? null,
        ]);
        return (int)db()->lastInsertId();
    }

    public static function get(int $taskId, string $code): ?array
    {
        $stmt = db()->prepare("SELECT * FROM eco_task_services WHERE task_id=? AND service_code=? LIMIT 1");
        $stmt->execute([$taskId, $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function activeCode(int $taskId): ?string
    {
        $stmt = db()->prepare("SELECT service_code FROM eco_task_services
            WHERE task_id=? AND phase_status NOT IN ('Done','Cancelled')
            ORDER BY CASE WHEN phase_status IN ('Revision','Working','Paused','In QA','SEO Review') THEN 100 ELSE 0 END DESC,
                     CASE service_code WHEN 'ai' THEN 3 WHEN 'aplus' THEN 2 ELSE 1 END DESC, id DESC LIMIT 1");
        $stmt->execute([$taskId]);
        $code = $stmt->fetchColumn();
        return $code ? (string)$code : null;
    }

    public static function state(int $taskId): array
    {
        $stmt = db()->prepare("SELECT * FROM eco_task_services WHERE task_id=? ORDER BY service_order ASC, id ASC");
        $stmt->execute([$taskId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [
            'ai_phase_status' => null,
            'ai_content_status' => null,
            'ai_worker_id' => null,
            'info_phase_status' => null,
            'aplus_phase_status' => null,
            'info_content_status' => null,
            'aplus_content_status' => null,
            'info_worker_id' => null,
            'aplus_worker_id' => null,
            'info_media_link' => null,
            'aplus_media_link' => null,
            'aplus_seo_doc_link' => null,
            'info_completed' => 0,
            'aplus_completed' => 0,
            'active_service_code' => null,
            'active_service_phase_status' => null,
            'active_service_content_status' => null,
        ];
        foreach ($rows as $row) {
            $prefix = $row['service_code'] === self::APLUS ? 'aplus' : ($row['service_code'] === self::INFO ? 'info' : ($row['service_code'] === self::AI ? 'ai' : null));
            if (!$prefix) continue;
            $out[$prefix . '_phase_status'] = $row['phase_status'];
            $out[$prefix . '_content_status'] = $row['content_status'];
            $out[$prefix . '_worker_id'] = $row['worker_id'];
            $out[$prefix . '_completed'] = $row['phase_status'] === 'Done' ? 1 : 0;
            $out[$prefix . '_media_link'] = $row['media_link'];
            if ($prefix === 'aplus') $out['aplus_seo_doc_link'] = $row['document_link'];
            if ($row['phase_status'] !== 'Done' && $row['phase_status'] !== 'Cancelled') {
                $activeOrder = $row['service_code'] === self::AI ? 3 : ($row['service_code'] === self::APLUS ? 2 : 1);
                if (in_array($row['phase_status'], ['Revision','Working','Paused','In QA','SEO Review'], true)) $activeOrder += 100;
                if ($out['active_service_code'] === null || $activeOrder >= ($out['_active_order'] ?? 0)) {
                    $out['active_service_code'] = $row['service_code'];
                    $out['active_service_phase_status'] = $row['phase_status'];
                    $out['active_service_content_status'] = $row['content_status'];
                    $out['_active_order'] = $activeOrder;
                }
            }
        }
        unset($out['_active_order']);
        return $out;
    }

    public static function setContent(int $taskId, string $code, string $status, ?int $actorId = null): void
    {
        self::ensure($taskId, $code);
        db()->prepare("UPDATE eco_task_services SET content_status=?, updated_at=NOW() WHERE task_id=? AND service_code=?")
            ->execute([$status, $taskId, $code]);
        self::event($taskId, $code, 'content_' . strtolower($status), $actorId);
    }

    public static function setPhase(int $taskId, string $code, string $phase, array $values = []): void
    {
        self::ensure($taskId, $code);
        $old = self::get($taskId, $code);
        $sets = ['phase_status=?', 'updated_at=NOW()'];
        $params = [$phase];
        if ($phase === 'Working') $sets[] = 'work_started_at=COALESCE(work_started_at,NOW())';
        if ($phase === 'Paused') $sets[] = 'work_paused_at=NOW()';
        if ($phase === 'In QA') $sets[] = 'work_completed_at=NOW()';
        if ($phase === 'Done') $sets[] = 'completed_at=NOW()';
        foreach (['worker_id','media_link','document_link','qa_submitted_by','qa_submitted_at','completed_by','work_total_seconds'] as $key) {
            if (array_key_exists($key, $values)) { $sets[] = $key . '=?'; $params[] = $values[$key]; }
        }
        $params[] = $taskId;
        $params[] = $code;
        db()->prepare("UPDATE eco_task_services SET " . implode(', ', $sets) . " WHERE task_id=? AND service_code=?")
            ->execute($params);
        if (!$old || ($old['phase_status'] ?? null) !== $phase) {
            self::event($taskId, $code, 'phase_' . strtolower(str_replace(' ', '_', $phase)), $values['actor_id'] ?? null, $old['phase_status'] ?? null, $phase);
        }
    }

    public static function event(int $taskId, string $code, string $event, ?int $actorId = null, ?string $from = null, ?string $to = null, ?string $notes = null): void
    {
        db()->prepare("INSERT INTO eco_task_service_events
            (task_id, service_code, event_type, from_status, to_status, actor_id, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())")
            ->execute([$taskId, $code, $event, $from, $to, $actorId, $notes]);
    }
}
