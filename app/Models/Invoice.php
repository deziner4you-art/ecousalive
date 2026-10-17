<?php

/*
=====================================================
INVOICE MODEL
Invoice creation + management queries
=====================================================
*/

class Invoice {

    public static function getAll(array $user): array {
        if($user['role'] === 'eco_client'){
            $stmt = db()->prepare("
                SELECT i.*, u.username AS client_name,
                (SELECT COUNT(*) FROM eco_invoice_items WHERE invoice_id=i.id) AS item_count
                FROM eco_invoices i
                JOIN eco_tool_users u ON u.id=i.client_id
                WHERE i.client_id=? ORDER BY i.id DESC
            ");
            $stmt->execute([$user['id']]);
        } else {
            $stmt = db()->query("
                SELECT i.*, u.username AS client_name,
                (SELECT COUNT(*) FROM eco_invoice_items WHERE invoice_id=i.id) AS item_count
                FROM eco_invoices i
                JOIN eco_tool_users u ON u.id=i.client_id
                ORDER BY i.id DESC
            ");
        }
        return $stmt->fetchAll();
    }

    public static function getDetail(int $id, array $user): array {
        $inv = db()->prepare("SELECT i.*, u.username AS client_name FROM eco_invoices i JOIN eco_tool_users u ON u.id=i.client_id WHERE i.id=?");
        $inv->execute([$id]);
        $invoice = $inv->fetch();
        if(!$invoice) return ['ok'=>false];
        if($user['role'] === 'eco_client' && $invoice['client_id'] != $user['id']) return ['ok'=>false];
        $items = db()->prepare("SELECT ii.*, t.product_no, t.title FROM eco_invoice_items ii JOIN wp_eco_aplus_tasks t ON t.id=ii.task_id WHERE ii.invoice_id=?");
        $items->execute([$id]);
        return ['ok'=>true,'invoice'=>$invoice,'items'=>$items->fetchAll()];
    }

    public static function getUninvoiced(): array {
        return db()->query("
            SELECT t.id, t.product_no, t.title, t.work_status, t.product_type,
                   t.invoice_status, t.info_invoice_status, t.aplus_invoice_status,
                   t.media_link, t.seo_doc_link, t.aplus_worker_id, t.seo_submitted_by,
                   EXISTS(SELECT 1 FROM eco_task_service_charges sc WHERE sc.task_id=t.id AND sc.service_type='info') AS info_service_charged,
                   EXISTS(SELECT 1 FROM eco_task_service_charges sc WHERE sc.task_id=t.id AND sc.service_type='aplus') AS aplus_service_charged,
                   (SELECT GROUP_CONCAT(DISTINCT sc.invoice_no ORDER BY sc.id DESC SEPARATOR ', ')
                    FROM eco_task_service_charges sc WHERE sc.task_id=t.id AND sc.service_type='info') AS info_invoice_numbers,
                   (SELECT GROUP_CONCAT(DISTINCT sc.invoice_no ORDER BY sc.id DESC SEPARATOR ', ')
                    FROM eco_task_service_charges sc WHERE sc.task_id=t.id AND sc.service_type='aplus') AS aplus_invoice_numbers,
                   t.published_at
            FROM wp_eco_aplus_tasks t
            WHERE t.deleted_at IS NULL AND t.status <> 'No Charge'
            ORDER BY t.id DESC
        ")->fetchAll();
    }

    public static function create(int $clientId, string $notes, array $items): array {
        if(!$clientId || empty($items)) return ['ok'=>false,'message'=>'Client and at least one product required'];

        $db = db();
        $validated = [];
        $seenServices = [];

        try {
            $db->beginTransaction();

            /* Validate against the current database row, not the browser's
               product_type/status values. FOR UPDATE also prevents two
               simultaneous invoices from charging the same service. */
            foreach($items as $it){
                $tid        = intval($it['task_id'] ?? 0);
                $postedPrice = floatval($it['price'] ?? 0);
                $infoPrice  = max(0, floatval($it['info_price'] ?? 0));
                $aplusPrice = max(0, floatval($it['aplus_price'] ?? 0));
                if(!$tid || $postedPrice <= 0) continue;
                if($infoPrice <= 0 && $aplusPrice <= 0) continue;

                $taskStmt = $db->prepare("SELECT * FROM wp_eco_aplus_tasks WHERE id=? AND deleted_at IS NULL FOR UPDATE");
                $taskStmt->execute([$tid]);
                $task = $taskStmt->fetch(PDO::FETCH_ASSOC);
                if(!$task) throw new RuntimeException('Product not found or deleted: #' . $tid);
                if(($task['status'] ?? '') === 'No Charge') {
                    throw new RuntimeException('No Charge product invoice mein add nahi ho sakta: #' . $tid);
                }

                $ptype = trim($task['product_type'] ?? '');
                if($infoPrice > 0){
                    if(!self::canInvoiceInfo($task)){
                        throw new RuntimeException('Info service abhi billable nahi hai: #' . ($task['product_no'] ?? $tid));
                    }
                    self::assertServiceNotCharged($db, $tid, 'info', $task['product_no'] ?? $tid);
                    $serviceKey = $tid . '|info';
                    if(isset($seenServices[$serviceKey])) throw new RuntimeException('Same Info service do baar select hui hai: #' . ($task['product_no'] ?? $tid));
                    $seenServices[$serviceKey] = true;
                }
                if($aplusPrice > 0){
                    if(!self::canInvoiceAplus($task)){
                        throw new RuntimeException('A+ service sirf Work Done + SEO complete hone ke baad billable hai: #' . ($task['product_no'] ?? $tid));
                    }
                    self::assertServiceNotCharged($db, $tid, 'aplus', $task['product_no'] ?? $tid);
                    $serviceKey = $tid . '|aplus';
                    if(isset($seenServices[$serviceKey])) throw new RuntimeException('Same A+ service do baar select hui hai: #' . ($task['product_no'] ?? $tid));
                    $seenServices[$serviceKey] = true;
                }

                $price = ($infoPrice > 0 || $aplusPrice > 0) ? ($infoPrice + $aplusPrice) : $postedPrice;
                if($price <= 0) continue;
                $validated[] = [
                    'id' => $tid,
                    'product_type' => $ptype,
                    'price' => $price,
                    'info_price' => $infoPrice,
                    'aplus_price' => $aplusPrice
                ];
            }

            if(empty($validated)) throw new RuntimeException('No valid items');

            $year      = date('Y');
            $count     = $db->query("SELECT COUNT(*) FROM eco_invoices WHERE YEAR(created_at)=$year")->fetchColumn();
            $invoiceNo = 'INV-'.$year.'-'.str_pad($count+1, 4, '0', STR_PAD_LEFT);
            $shareToken = bin2hex(random_bytes(16));

            $stmt = $db->prepare("INSERT INTO eco_invoices (invoice_no,client_id,status,notes,share_token) VALUES(?,?,?,?,?)");
            $stmt->execute([$invoiceNo, $clientId, 'Pending', $notes, $shareToken]);
            $invoiceId = (int) $db->lastInsertId();

            $total = 0;
            $taskIds = [];
            $iStmt = $db->prepare("INSERT INTO eco_invoice_items (invoice_id,task_id,item_price,info_price,aplus_price) VALUES(?,?,?,?,?)");
            $ledgerStmt = $db->prepare("INSERT INTO eco_task_service_charges (task_id,service_type,invoice_id,invoice_no,amount,invoice_status,charged_at) VALUES(?,?,?,?,?,'Invoiced',NOW())");

            foreach($validated as $it){
                $iStmt->execute([$invoiceId, $it['id'], $it['price'], $it['info_price'], $it['aplus_price']]);
                if($it['info_price'] > 0) $ledgerStmt->execute([$it['id'], 'info', $invoiceId, $invoiceNo, $it['info_price']]);
                if($it['aplus_price'] > 0) $ledgerStmt->execute([$it['id'], 'aplus', $invoiceId, $invoiceNo, $it['aplus_price']]);
                $total += $it['price'];
                $taskIds[] = ['id'=>$it['id'],'product_type'=>$it['product_type'],'info_price'=>$it['info_price'],'aplus_price'=>$it['aplus_price']];
            }

            $db->prepare("UPDATE eco_invoices SET total_amount=? WHERE id=?")->execute([$total, $invoiceId]);
            self::updateTaskInvoiceStatus($taskIds, 'Invoiced');
            $db->commit();
            return ['ok'=>true,'invoice_no'=>$invoiceNo];
        } catch(Throwable $e) {
            if($db->inTransaction()) $db->rollBack();
            return ['ok'=>false,'message'=>$e->getMessage() ?: 'Invoice create failed'];
        }
    }

    public static function markPaid(int $id): void {
        db()->prepare("UPDATE eco_invoices SET status='Paid', paid_at=NOW() WHERE id=?")->execute([$id]);
        db()->prepare("UPDATE eco_task_service_charges SET invoice_status='Paid', paid_at=NOW() WHERE invoice_id=?")
            ->execute([$id]);
        $rows = db()->prepare("SELECT ii.task_id, ii.info_price, ii.aplus_price, t.product_type FROM eco_invoice_items ii JOIN wp_eco_aplus_tasks t ON t.id=ii.task_id WHERE ii.invoice_id=?");
        $rows->execute([$id]);
        self::updateTaskInvoiceStatus($rows->fetchAll(), 'Paid');
    }

    public static function delete(int $id): array {
        $inv = db()->prepare("SELECT id FROM eco_invoices WHERE id=?");
        $inv->execute([$id]);
        if(!$inv->fetch()) return ['ok'=>false,'message'=>'Invoice not found'];

        $rows = db()->prepare("SELECT ii.task_id, ii.info_price, ii.aplus_price, t.product_type FROM eco_invoice_items ii JOIN wp_eco_aplus_tasks t ON t.id=ii.task_id WHERE ii.invoice_id=?");
        $rows->execute([$id]);
        self::updateTaskInvoiceStatus($rows->fetchAll(), null);

        db()->prepare("DELETE FROM eco_invoice_items WHERE invoice_id=?")->execute([$id]);
        db()->prepare("DELETE FROM eco_invoices WHERE id=?")->execute([$id]);
        return ['ok'=>true];
    }

    public static function clearStatus(int $taskId): void {
        db()->prepare("UPDATE wp_eco_aplus_tasks SET invoice_status=NULL, info_invoice_status=NULL, aplus_invoice_status=NULL WHERE id=?")->execute([$taskId]);
    }

    public static function getPublic(int $id, string $token): ?array {
        $inv = db()->prepare("SELECT i.*, u.username AS client_name FROM eco_invoices i JOIN eco_tool_users u ON u.id=i.client_id WHERE i.id=? AND i.share_token=?");
        $inv->execute([$id, $token]);
        $invoice = $inv->fetch();
        if(!$invoice) return null;
        $items = db()->prepare("SELECT ii.item_price, t.product_no, t.title FROM eco_invoice_items ii JOIN wp_eco_aplus_tasks t ON t.id=ii.task_id WHERE ii.invoice_id=?");
        $items->execute([$id]);
        return ['invoice'=>$invoice, 'items'=>$items->fetchAll()];
    }

    /* ── Private helper ──────────────────────────── */
    private static function canInvoiceInfo(array $task): bool {
        $ptype = $task['product_type'] ?? '';
        return in_array($ptype, ['Infographics', 'Info + A Plus'], true)
            && in_array($task['work_status'] ?? '', ['Info Done', 'Work Done'], true);
    }

    private static function canInvoiceAplus(array $task): bool {
        $ptype = $task['product_type'] ?? '';
        return in_array($ptype, ['A+', 'A Plus', 'Info + A Plus'], true)
            && ($task['work_status'] ?? '') === 'Work Done'
            && !empty(trim((string)($task['seo_doc_link'] ?? '')))
            && !empty($task['seo_submitted_by']);
    }

    private static function assertServiceNotCharged(PDO $db, int $taskId, string $serviceType, $productNo): void {
        $stmt = $db->prepare("SELECT invoice_no, invoice_status FROM eco_task_service_charges WHERE task_id=? AND service_type=? LIMIT 1");
        $stmt->execute([$taskId, $serviceType]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row){
            throw new RuntimeException(ucfirst($serviceType) . ' service pehle hi invoice ho chuki hai (' . ($row['invoice_no'] ?? 'old invoice') . '): #' . $productNo);
        }
    }

    private static function updateTaskInvoiceStatus(array $taskIds, ?string $status): void {
        $updInfo  = db()->prepare("UPDATE wp_eco_aplus_tasks SET info_invoice_status=?  WHERE id=?");
        $updAplus = db()->prepare("UPDATE wp_eco_aplus_tasks SET aplus_invoice_status=? WHERE id=?");
        $plainIds = [];

        foreach($taskIds as $ti){
            $ptype = $ti['product_type'] ?? '';
            $tid   = $ti['task_id'] ?? $ti['id'];
            if($ptype === 'Info + A Plus'){
                if(floatval($ti['info_price']  ?? 0) > 0) $updInfo->execute([$status, $tid]);
                if(floatval($ti['aplus_price'] ?? 0) > 0) $updAplus->execute([$status, $tid]);
            } elseif($ptype === 'Infographics'){
                $updInfo->execute([$status, $tid]);
            } elseif($ptype === 'A+'){
                $updAplus->execute([$status, $tid]);
            } else {
                $plainIds[] = $tid;
            }
        }

        if($plainIds){
            $in = implode(',', array_fill(0, count($plainIds), '?'));
            db()->prepare("UPDATE wp_eco_aplus_tasks SET invoice_status=? WHERE id IN ($in)")
               ->execute(array_merge([$status], $plainIds));
        }
    }

}
