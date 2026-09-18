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
                   t.invoice_status, t.info_invoice_status, t.aplus_invoice_status, t.published_at
            FROM wp_eco_aplus_tasks t
            WHERE t.deleted_at IS NULL
            ORDER BY t.id DESC
        ")->fetchAll();
    }

    public static function create(int $clientId, string $notes, array $items): array {
        if(!$clientId || empty($items)) return ['ok'=>false,'message'=>'Client and at least one product required'];

        $year     = date('Y');
        $count    = db()->query("SELECT COUNT(*) FROM eco_invoices WHERE YEAR(created_at)=$year")->fetchColumn();
        $invoiceNo = 'INV-'.$year.'-'.str_pad($count+1, 4, '0', STR_PAD_LEFT);

        $shareToken = bin2hex(random_bytes(16));
        $stmt = db()->prepare("INSERT INTO eco_invoices (invoice_no,client_id,status,notes,share_token) VALUES(?,?,?,?,?)");
        $stmt->execute([$invoiceNo, $clientId, 'Pending', $notes, $shareToken]);
        $invoiceId = (int) db()->lastInsertId();

        $total = 0;
        $taskIds = [];
        $iStmt = db()->prepare("INSERT IGNORE INTO eco_invoice_items (invoice_id,task_id,item_price,info_price,aplus_price) VALUES(?,?,?,?,?)");

        foreach($items as $it){
            $tid        = intval($it['task_id']    ?? 0);
            $price      = floatval($it['price']      ?? 0);
            $infoPrice  = floatval($it['info_price']  ?? 0);
            $aplusPrice = floatval($it['aplus_price'] ?? 0);
            $ptype      = trim($it['product_type']  ?? '');
            if(!$tid || $price <= 0) continue;
            $iStmt->execute([$invoiceId, $tid, $price, $infoPrice, $aplusPrice]);
            $total += $price;
            $taskIds[] = ['id'=>$tid,'product_type'=>$ptype,'info_price'=>$infoPrice,'aplus_price'=>$aplusPrice];
        }

        if(empty($taskIds)){
            db()->prepare("DELETE FROM eco_invoices WHERE id=?")->execute([$invoiceId]);
            return ['ok'=>false,'message'=>'No valid items'];
        }

        db()->prepare("UPDATE eco_invoices SET total_amount=? WHERE id=?")->execute([$total, $invoiceId]);

        /* Update invoice_status on tasks */
        self::updateTaskInvoiceStatus($taskIds, 'Invoiced');

        return ['ok'=>true,'invoice_no'=>$invoiceNo];
    }

    public static function markPaid(int $id): void {
        db()->prepare("UPDATE eco_invoices SET status='Paid', paid_at=NOW() WHERE id=?")->execute([$id]);
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
