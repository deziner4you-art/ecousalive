<?php

/*
=====================================================
PAYSLIP MODEL
Worker payroll, rates, ledger queries
=====================================================
*/

class Payslip {

    public static function getWorkerRates(): array {
        return db()->query("
            SELECT u.id, u.username, u.role, COALESCE(r.rate_per_product,0) AS rate_per_product, COALESCE(r.fine_per_revision,0) AS fine_per_revision, COALESCE(r.rate_infographics,0) AS rate_infographics, COALESCE(r.rate_aplus,0) AS rate_aplus
            FROM eco_tool_users u
            LEFT JOIN eco_worker_rates r ON r.worker_id=u.id
            WHERE u.role IN ('worker','d4u_writer','qa','seo_manager','ai_work')
            ORDER BY u.username ASC
        ")->fetchAll();
    }

    public static function saveWorkerRate(int $workerId, float $rateInfo, float $rateAplus, float $fine = 0.00): void {
        db()->prepare("INSERT INTO eco_worker_rates (worker_id,rate_infographics,rate_aplus,fine_per_revision) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE rate_infographics=?,rate_aplus=?,fine_per_revision=?")
           ->execute([$workerId,$rateInfo,$rateAplus,$fine,$rateInfo,$rateAplus,$fine]);
    }

    public static function getProductsForPayslip(int $workerId, string $dateFrom, string $dateTo): array {
        $roleRow = db()->prepare("SELECT role FROM eco_tool_users WHERE id=?");
        $roleRow->execute([$workerId]);
        $role = $roleRow->fetchColumn();

        if($role === 'qa'){
            $stmt = db()->prepare("
                SELECT t.id, t.product_no, t.title, inv.invoice_no
                FROM wp_eco_aplus_tasks t
                JOIN eco_invoice_items ii ON ii.task_id=t.id
                JOIN eco_invoices inv ON inv.id=ii.invoice_id AND inv.status='Paid'
                LEFT JOIN (eco_payslip_items pi JOIN eco_payslips p ON p.id = pi.payslip_id AND p.worker_id = ?) ON pi.task_id=t.id
                WHERE t.qa_submitted_by=? AND (t.invoice_status='Paid' OR t.info_invoice_status='Paid' OR t.aplus_invoice_status='Paid') AND t.deleted_at IS NULL
                AND DATE(inv.paid_at) BETWEEN ? AND ? AND pi.id IS NULL
                GROUP BY t.id
                ORDER BY t.id ASC
            ");
            $stmt->execute([$workerId, $workerId, $dateFrom, $dateTo]);
        } elseif($role === 'seo_manager' || $role === 'd4u_writer'){
            $stmt = db()->prepare("
                SELECT t.id, t.product_no, t.title, inv.invoice_no
                FROM wp_eco_aplus_tasks t
                JOIN eco_invoice_items ii ON ii.task_id=t.id
                JOIN eco_invoices inv ON inv.id=ii.invoice_id AND inv.status='Paid'
                LEFT JOIN (eco_payslip_items pi JOIN eco_payslips p ON p.id = pi.payslip_id AND p.worker_id = ?) ON pi.task_id=t.id
                WHERE (t.written_by_user_id=? OR t.seo_submitted_by=?) AND (t.invoice_status='Paid' OR t.info_invoice_status='Paid' OR t.aplus_invoice_status='Paid') AND t.deleted_at IS NULL
                AND DATE(inv.paid_at) BETWEEN ? AND ? AND pi.id IS NULL
                GROUP BY t.id
                ORDER BY t.id ASC
            ");
            $stmt->execute([$workerId, $workerId, $workerId, $dateFrom, $dateTo]);
        } elseif($role === 'ai_work'){
            $stmt = db()->prepare("
                SELECT t.id, t.product_no, t.title, inv.invoice_no
                FROM wp_eco_aplus_tasks t
                JOIN eco_invoice_items ii ON ii.task_id=t.id
                JOIN eco_invoices inv ON inv.id=ii.invoice_id AND inv.status='Paid'
                LEFT JOIN (eco_payslip_items pi JOIN eco_payslips p ON p.id = pi.payslip_id AND p.worker_id = ?) ON pi.task_id=t.id
                WHERE t.ai_worked_by=? AND (t.invoice_status='Paid' OR t.info_invoice_status='Paid' OR t.aplus_invoice_status='Paid') AND t.deleted_at IS NULL
                AND DATE(inv.paid_at) BETWEEN ? AND ? AND pi.id IS NULL
                GROUP BY t.id
                ORDER BY t.id ASC
            ");
            $stmt->execute([$workerId, $workerId, $dateFrom, $dateTo]);
        } else {
            $stmt = db()->prepare("
                SELECT t.id, t.product_no, t.title, inv.invoice_no
                FROM wp_eco_aplus_tasks t
                LEFT JOIN eco_tool_assignments a ON a.task_id=t.id
                JOIN eco_invoice_items ii ON ii.task_id=t.id
                JOIN eco_invoices inv ON inv.id=ii.invoice_id AND inv.status='Paid'
                LEFT JOIN (eco_payslip_items pi JOIN eco_payslips p ON p.id = pi.payslip_id AND p.worker_id = ?) ON pi.task_id=t.id
                WHERE (a.worker_id=? OR t.info_worker_id=? OR t.aplus_worker_id=? OR t.work_completed_by_worker_id=?)
                AND (t.invoice_status='Paid' OR t.info_invoice_status='Paid' OR t.aplus_invoice_status='Paid') AND t.deleted_at IS NULL
                AND DATE(inv.paid_at) BETWEEN ? AND ? AND pi.id IS NULL
                GROUP BY t.id
                ORDER BY t.id ASC
            ");
            $stmt->execute([$workerId, $workerId, $workerId, $workerId, $workerId, $dateFrom, $dateTo]);
        }
        return $stmt->fetchAll();
    }

    public static function getWorkerProductsFlexible(int $workerId, string $dateFrom, string $dateTo): array {
        $roleRow = db()->prepare("SELECT role FROM eco_tool_users WHERE id=?");
        $roleRow->execute([$workerId]);
        $role = $roleRow->fetchColumn();

        $dateCond   = '';
        $dateParams = [];
        if($dateFrom && $dateTo){
            $dateCond   = 'AND DATE(COALESCE(t.work_completed_at, t.last_activity_at)) BETWEEN ? AND ?';
            $dateParams = [$dateFrom, $dateTo];
        }

        $baseCond = "t.deleted_at IS NULL AND t.work_status NOT IN ('Pending','Working','Paused','In QA','SEO Review','Hold') $dateCond AND pi.id IS NULL";
        $sel = "t.id, t.product_no, t.title, t.work_status, t.invoice_status, t.info_invoice_status, t.aplus_invoice_status, t.product_type, t.info_worker_id, t.aplus_worker_id";

        if($role === 'qa'){
            $sql = "SELECT $sel FROM wp_eco_aplus_tasks t LEFT JOIN (eco_payslip_items pi JOIN eco_payslips p ON p.id = pi.payslip_id AND p.worker_id = ?) ON pi.task_id=t.id WHERE t.qa_submitted_by=? AND $baseCond GROUP BY t.id ORDER BY t.id ASC";
            $params = array_merge([$workerId, $workerId], $dateParams);
        } elseif($role === 'seo_manager' || $role === 'd4u_writer'){
            $sql = "SELECT $sel FROM wp_eco_aplus_tasks t LEFT JOIN (eco_payslip_items pi JOIN eco_payslips p ON p.id = pi.payslip_id AND p.worker_id = ?) ON pi.task_id=t.id WHERE (t.written_by_user_id=? OR t.seo_submitted_by=?) AND $baseCond GROUP BY t.id ORDER BY t.id ASC";
            $params = array_merge([$workerId, $workerId, $workerId], $dateParams);
        } elseif($role === 'ai_work'){
            $sql = "SELECT $sel FROM wp_eco_aplus_tasks t LEFT JOIN (eco_payslip_items pi JOIN eco_payslips p ON p.id = pi.payslip_id AND p.worker_id = ?) ON pi.task_id=t.id WHERE t.ai_worked_by=? AND $baseCond GROUP BY t.id ORDER BY t.id ASC";
            $params = array_merge([$workerId, $workerId], $dateParams);
        } else {
            $sql = "SELECT $sel FROM wp_eco_aplus_tasks t
                    LEFT JOIN eco_tool_assignments a ON a.task_id=t.id
                    LEFT JOIN (eco_payslip_items pi JOIN eco_payslips p ON p.id = pi.payslip_id AND p.worker_id = ?) ON pi.task_id=t.id
                    WHERE (a.worker_id=? OR t.info_worker_id=? OR t.aplus_worker_id=? OR t.work_completed_by_worker_id=?)
                    AND $baseCond
                    GROUP BY t.id
                    ORDER BY t.id ASC";
            $params = array_merge([$workerId, $workerId, $workerId, $workerId, $workerId], $dateParams);
        }

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function generate(int $workerId, array $taskIds, string $label, float $prevD4U = 0, float $advD4U = 0, float $loanD4U = 0, float $penaltyDeducted = 0): array {
        if(empty($taskIds)) return ['ok'=>false,'message'=>'Koi product select nahi kiya'];

        $monthLabel = $label ?: date('Y-m');
        $ratesRow = db()->prepare("SELECT COALESCE(rate_infographics,0) AS rate_infographics, COALESCE(rate_aplus,0) AS rate_aplus FROM eco_worker_rates WHERE worker_id=?");
        $ratesRow->execute([$workerId]);
        $ratesRow  = $ratesRow->fetch();
        $rateInfo  = floatval($ratesRow ? $ratesRow['rate_infographics'] : 0);
        $rateAplus = floatval($ratesRow ? $ratesRow['rate_aplus']        : 0);

        try {
            db()->beginTransaction();

            $ps = db()->prepare("INSERT INTO eco_payslips (worker_id,month,total_amount,prev_d4u,adv_d4u,loan_d4u,penalty_deducted,total_paid,status) VALUES(?,?,0,?,?,?,?,0,'Generated')");
            $ps->execute([$workerId, $monthLabel, $prevD4U, $advD4U, $loanD4U, $penaltyDeducted]);
            $payslipId = (int) db()->lastInsertId();

            $pi = db()->prepare("INSERT IGNORE INTO eco_payslip_items (payslip_id,task_id) VALUES(?,?)");
            foreach($taskIds as $tid){ $pi->execute([$payslipId, $tid]); }

            $countStmt = db()->prepare("SELECT COUNT(*) FROM eco_payslip_items WHERE payslip_id=?");
            $countStmt->execute([$payslipId]);
            $inserted = intval($countStmt->fetchColumn());
            if($inserted <= 0){
                db()->rollBack();
                return ['ok'=>false,'message'=>'Koi valid task payslip mein add nahi hua'];
            }

            /* Per-type earned amount */
            $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
            $typeStmt = db()->prepare("SELECT id, product_type, info_worker_id, aplus_worker_id, work_completed_by_worker_id FROM wp_eco_aplus_tasks WHERE id IN ($placeholders)");
            $typeStmt->execute($taskIds);
            $types = $typeStmt->fetchAll();

            $earnedAmt = 0.0;
            foreach($types as $t){
                $pt = $t['product_type'] ?? '';
                if($pt === 'Info + A Plus'){
                    $isInfo  = (!empty($t['info_worker_id']) && (int)$t['info_worker_id'] === $workerId);
                    $isAplus = (!empty($t['aplus_worker_id']) && (int)$t['aplus_worker_id'] === $workerId);
                    if($isInfo && $isAplus){
                        $earnedAmt += ($rateInfo + $rateAplus);
                    } elseif($isInfo && empty($t['aplus_worker_id'])){
                        $earnedAmt += ($rateInfo + $rateAplus);
                    } elseif($isAplus && empty($t['info_worker_id'])){
                        $earnedAmt += ($rateInfo + $rateAplus);
                    } elseif($isInfo){
                        $earnedAmt += $rateInfo;
                    } elseif($isAplus){
                        $earnedAmt += $rateAplus;
                    } else {
                        // If not specifically flagged, fallback to full rate
                        $earnedAmt += ($rateInfo + $rateAplus);
                    }
                } elseif($pt === 'Infographics'){
                    $earnedAmt += $rateInfo;
                } else {
                    $earnedAmt += $rateAplus;
                }
            }

            /* total_paid = actual payout = EARNED − PREV.D4U + ADV + LOAN − PENALTY */
            $totalPaid = $earnedAmt - $prevD4U + $advD4U + $loanD4U - $penaltyDeducted;

            db()->prepare("UPDATE eco_payslips SET total_amount=?, total_paid=? WHERE id=?")->execute([$earnedAmt, $totalPaid, $payslipId]);
            db()->commit();

            return ['ok'=>true,'earned'=>$earnedAmt,'total_paid'=>$totalPaid,'count'=>$inserted];
        } catch(Exception $e) {
            if(db()->inTransaction()) db()->rollBack();
            return ['ok'=>false,'message'=>$e->getMessage()];
        }
    }

    public static function getAll(array $user): array {
        if(in_array($user['role'], ['worker','d4u_writer','qa','seo_manager','ai_work'])){
            $stmt = db()->prepare("
                SELECT p.*, u.username AS worker_name,
                (SELECT COUNT(*) FROM eco_payslip_items WHERE payslip_id=p.id) AS item_count
                FROM eco_payslips p JOIN eco_tool_users u ON u.id=p.worker_id
                WHERE p.worker_id=? ORDER BY p.id DESC
            ");
            $stmt->execute([$user['id']]);
        } else {
            $stmt = db()->query("
                SELECT p.*, u.username AS worker_name,
                (SELECT COUNT(*) FROM eco_payslip_items WHERE payslip_id=p.id) AS item_count
                FROM eco_payslips p JOIN eco_tool_users u ON u.id=p.worker_id
                ORDER BY p.id DESC
            ");
        }
        return $stmt->fetchAll();
    }

    public static function getDetail(int $id, array $user): array {
        $ps = db()->prepare("SELECT p.*, u.username AS worker_name FROM eco_payslips p JOIN eco_tool_users u ON u.id=p.worker_id WHERE p.id=?");
        $ps->execute([$id]);
        $payslip = $ps->fetch();
        if(!$payslip) return ['ok'=>false];
        if(in_array($user['role'],['worker','d4u_writer','qa','seo_manager','ai_work']) && $payslip['worker_id'] != $user['id']) return ['ok'=>false];
        $items = db()->prepare("
            SELECT pi.*, t.product_no, t.title, inv.invoice_no
            FROM eco_payslip_items pi
            JOIN wp_eco_aplus_tasks t ON t.id=pi.task_id
            LEFT JOIN eco_invoice_items ii ON ii.task_id=t.id
            LEFT JOIN eco_invoices inv ON inv.id=ii.invoice_id AND inv.status='Paid'
            WHERE pi.payslip_id=?
        ");
        $items->execute([$id]);
        return ['ok'=>true,'payslip'=>$payslip,'items'=>$items->fetchAll()];
    }

    public static function markPaid(int $id): void {
        $stmt = db()->prepare("SELECT worker_id, total_amount, total_paid, status FROM eco_payslips WHERE id=?");
        $stmt->execute([$id]);
        $payslip = $stmt->fetch();
        if(!$payslip || $payslip['status'] === 'Paid') return;

        db()->prepare("UPDATE eco_payslips SET status='Paid', paid_at=NOW() WHERE id=?")->execute([$id]);

        /* Log actual cash paid out (total_paid = earned − prev_d4u + adv + loan − penalty).
           Fall back to total_amount for old payslips that have total_paid = 0. */
        $cashPaid = floatval($payslip['total_paid']) > 0
            ? floatval($payslip['total_paid'])
            : floatval($payslip['total_amount']);

        db()->prepare("INSERT INTO eco_worker_ledger (worker_id,type,amount,notes,transaction_date) VALUES(?,?,?,?,?)")
            ->execute([
                $payslip['worker_id'],
                'paid',
                $cashPaid,
                'Payslip #' . $id . ' paid',
                date('Y-m-d')
            ]);
    }

    public static function delete(int $id): array {
        try{
            db()->prepare("DELETE FROM eco_payslip_items WHERE payslip_id=?")->execute([$id]);
            db()->prepare("DELETE FROM eco_payslips WHERE id=?")->execute([$id]);
            return ['ok'=>true];
        }catch(Exception $e){
            return ['ok'=>false,'message'=>$e->getMessage()];
        }
    }

    public static function getWorkerProgress(int $workerId): array {
        $w = db()->prepare("SELECT id, username, role FROM eco_tool_users WHERE id=?");
        $w->execute([$workerId]);
        $worker = $w->fetch();
        if(!$worker) return ['ok'=>false,'message'=>'Not found'];
        $role = $worker['role'];

        $whereClause = ($role === 'qa') ? "WHERE qa_submitted_by=?" :
                      (($role === 'd4u_writer' || $role === 'seo_manager') ? "WHERE (written_by_user_id=? OR seo_submitted_by=?)" :
                      (($role === 'ai_work') ? "WHERE ai_worked_by=?" :
                      "FROM wp_eco_aplus_tasks t JOIN eco_tool_assignments a ON a.task_id=t.id AND a.worker_id=?"));

        if($role === 'qa' || $role === 'd4u_writer' || $role === 'seo_manager' || $role === 'ai_work'){
            $fromClause = "FROM wp_eco_aplus_tasks";
        } else {
            $fromClause = "FROM wp_eco_aplus_tasks t JOIN eco_tool_assignments a ON a.task_id=t.id AND a.worker_id=?";
            $whereClause = "WHERE 1";
        }

        /* Simpler approach — use the role-based queries exactly from monolith */
        if($role === 'qa'){
            $statsRow = db()->prepare("
                SELECT COUNT(*) AS total,
                SUM(CASE WHEN work_status='Working' THEN 1 ELSE 0 END) AS working,
                SUM(CASE WHEN work_status='Paused'  THEN 1 ELSE 0 END) AS paused,
                SUM(CASE WHEN work_status='In QA'   THEN 1 ELSE 0 END) AS in_qa,
                SUM(CASE WHEN work_status IN ('Work Done','Info Done') THEN 1 ELSE 0 END) AS work_done,
                SUM(CASE WHEN work_status NOT IN ('Work Done','Info Done','In QA','Working','Paused') THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN (invoice_status='Invoiced' OR info_invoice_status='Invoiced' OR aplus_invoice_status='Invoiced') THEN 1 ELSE 0 END) AS invoiced_count,
                SUM(CASE WHEN (invoice_status='Paid' OR info_invoice_status='Paid' OR aplus_invoice_status='Paid') THEN 1 ELSE 0 END) AS paid_count,
                SUM(CASE WHEN work_status IN ('Work Done','Info Done') AND invoice_status IS NULL AND info_invoice_status IS NULL AND aplus_invoice_status IS NULL THEN 1 ELSE 0 END) AS not_invoiced_count
                FROM wp_eco_aplus_tasks WHERE qa_submitted_by=? AND deleted_at IS NULL
            ");
            $ppStmt = db()->prepare("SELECT COUNT(DISTINCT t.id) FROM wp_eco_aplus_tasks t JOIN eco_invoice_items ii ON ii.task_id=t.id JOIN eco_invoices inv ON inv.id=ii.invoice_id AND inv.status='Paid' LEFT JOIN (eco_payslip_items pi JOIN eco_payslips p ON p.id = pi.payslip_id AND p.worker_id = ?) ON pi.task_id=t.id WHERE t.qa_submitted_by=? AND (t.invoice_status='Paid' OR t.info_invoice_status='Paid' OR t.aplus_invoice_status='Paid') AND pi.id IS NULL AND t.deleted_at IS NULL");
            $ppParams = [$workerId, $workerId];
            $statsParams = [$workerId];
        } elseif($role === 'seo_manager' || $role === 'd4u_writer'){
            $statsRow = db()->prepare("
                SELECT COUNT(*) AS total,
                SUM(CASE WHEN work_status='Working' THEN 1 ELSE 0 END) AS working,
                SUM(CASE WHEN work_status='Paused'  THEN 1 ELSE 0 END) AS paused,
                SUM(CASE WHEN work_status='In QA'   THEN 1 ELSE 0 END) AS in_qa,
                SUM(CASE WHEN work_status IN ('Work Done','Info Done') THEN 1 ELSE 0 END) AS work_done,
                SUM(CASE WHEN work_status NOT IN ('Work Done','Info Done','In QA','Working','Paused') THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN (invoice_status='Invoiced' OR info_invoice_status='Invoiced' OR aplus_invoice_status='Invoiced') THEN 1 ELSE 0 END) AS invoiced_count,
                SUM(CASE WHEN (invoice_status='Paid' OR info_invoice_status='Paid' OR aplus_invoice_status='Paid') THEN 1 ELSE 0 END) AS paid_count,
                SUM(CASE WHEN work_status IN ('Work Done','Info Done') AND invoice_status IS NULL AND info_invoice_status IS NULL AND aplus_invoice_status IS NULL THEN 1 ELSE 0 END) AS not_invoiced_count
                FROM wp_eco_aplus_tasks WHERE (written_by_user_id=? OR seo_submitted_by=?) AND deleted_at IS NULL
            ");
            $ppStmt = db()->prepare("SELECT COUNT(DISTINCT t.id) FROM wp_eco_aplus_tasks t JOIN eco_invoice_items ii ON ii.task_id=t.id JOIN eco_invoices inv ON inv.id=ii.invoice_id AND inv.status='Paid' LEFT JOIN (eco_payslip_items pi JOIN eco_payslips p ON p.id = pi.payslip_id AND p.worker_id = ?) ON pi.task_id=t.id WHERE (t.written_by_user_id=? OR t.seo_submitted_by=?) AND (t.invoice_status='Paid' OR t.info_invoice_status='Paid' OR t.aplus_invoice_status='Paid') AND pi.id IS NULL AND t.deleted_at IS NULL");
            $ppParams = [$workerId, $workerId, $workerId];
            $statsParams = [$workerId, $workerId];
        } else {
            $statsRow = db()->prepare("
                SELECT COUNT(DISTINCT t.id) AS total,
                SUM(CASE WHEN t.work_status='Working' THEN 1 ELSE 0 END) AS working,
                SUM(CASE WHEN t.work_status='Paused'  THEN 1 ELSE 0 END) AS paused,
                SUM(CASE WHEN t.work_status='In QA'   THEN 1 ELSE 0 END) AS in_qa,
                SUM(CASE WHEN t.work_status IN ('Work Done','Info Done') THEN 1 ELSE 0 END) AS work_done,
                SUM(CASE WHEN t.work_status NOT IN ('Work Done','Info Done','In QA','Working','Paused') THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN (t.invoice_status='Invoiced' OR t.info_invoice_status='Invoiced' OR t.aplus_invoice_status='Invoiced') THEN 1 ELSE 0 END) AS invoiced_count,
                SUM(CASE WHEN (t.invoice_status='Paid' OR t.info_invoice_status='Paid' OR t.aplus_invoice_status='Paid') THEN 1 ELSE 0 END) AS paid_count,
                SUM(CASE WHEN t.work_status IN ('Work Done','Info Done') AND t.invoice_status IS NULL AND t.info_invoice_status IS NULL AND t.aplus_invoice_status IS NULL THEN 1 ELSE 0 END) AS not_invoiced_count
                FROM wp_eco_aplus_tasks t
                LEFT JOIN eco_tool_assignments a ON a.task_id=t.id
                WHERE (a.worker_id=? OR t.info_worker_id=? OR t.aplus_worker_id=? OR t.work_completed_by_worker_id=?)
                AND t.deleted_at IS NULL
            ");
            $ppStmt = db()->prepare("
                SELECT COUNT(DISTINCT t.id)
                FROM wp_eco_aplus_tasks t
                LEFT JOIN eco_tool_assignments a ON a.task_id=t.id
                JOIN eco_invoice_items ii ON ii.task_id=t.id
                JOIN eco_invoices inv ON inv.id=ii.invoice_id AND inv.status='Paid'
                LEFT JOIN (eco_payslip_items pi JOIN eco_payslips p ON p.id = pi.payslip_id AND p.worker_id = ?) ON pi.task_id=t.id
                WHERE (a.worker_id=? OR t.info_worker_id=? OR t.aplus_worker_id=? OR t.work_completed_by_worker_id=?)
                AND (t.invoice_status='Paid' OR t.info_invoice_status='Paid' OR t.aplus_invoice_status='Paid')
                AND pi.id IS NULL AND t.deleted_at IS NULL
            ");
            $ppParams = [$workerId, $workerId, $workerId, $workerId, $workerId];
            $statsParams = [$workerId, $workerId, $workerId, $workerId];
        }

        $statsRow->execute($statsParams);
        $stats = $statsRow->fetch();
        $ppStmt->execute($ppParams);
        $pendingPayslip = intval($ppStmt->fetchColumn());

        $earnStmt = db()->prepare("SELECT COALESCE(SUM(total_amount),0) FROM eco_payslips WHERE worker_id=?");
        $earnStmt->execute([$workerId]); $totalEarned = floatval($earnStmt->fetchColumn());

        $paidStmt = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM eco_worker_ledger WHERE worker_id=?");
        $paidStmt->execute([$workerId]); $totalPaid = floatval($paidStmt->fetchColumn());

        $rateStmt = db()->prepare("SELECT COALESCE(rate_per_product,0) FROM eco_worker_rates WHERE worker_id=?");
        $rateStmt->execute([$workerId]); $rate = floatval($rateStmt->fetchColumn());

        return [
            'ok'              => true,
            'worker'          => $worker,
            'stats'           => $stats,
            'pending_payslip' => $pendingPayslip,
            'total_earned'    => $totalEarned,
            'total_paid'      => $totalPaid,
            'balance'         => round($totalEarned - $totalPaid, 2),
            'rate'            => $rate,
            'potential'       => $pendingPayslip * $rate,
        ];
    }

    public static function getAllWorkerBalances(string $monthFilter = ''): array {
        /* Cycle-aware balances for admin header badges.
           PREV. D4U  = last payslip's stored (adv_d4u + loan_d4u)
           current_adv/loan = ledger entries AFTER last payslip date */
        $monthCondPs = "";
        $monthCondLedger = "";
        
        if ($monthFilter) {
            $qMonth = db()->quote($monthFilter);
            
            $dateObj = DateTime::createFromFormat('Y-m', $monthFilter);
            if ($dateObj) {
                $monthName = $dateObj->format('F'); // e.g. June
                $year = $dateObj->format('Y');      // e.g. 2026
                $labelPattern1 = db()->quote("%$monthName $year%");
                $labelPattern2 = db()->quote("%" . substr($monthName, 0, 3) . "%$year%"); // e.g. %Jun%2026%
                
                $monthCondPs = "AND (DATE_FORMAT(p.created_at, '%Y-%m') = $qMonth OR p.month = $qMonth OR p.month LIKE $labelPattern1 OR p.month LIKE $labelPattern2)";
            } else {
                $monthCondPs = "AND DATE_FORMAT(p.created_at, '%Y-%m') = $qMonth";
            }
            
            $monthCondLedger = "AND DATE_FORMAT(COALESCE(l.transaction_date, DATE(l.created_at)), '%Y-%m') = $qMonth";
        }

        $sql = "
            SELECT u.id, u.username, u.role,
            COALESCE((
                SELECT p.prev_d4u
                FROM eco_payslips p WHERE p.worker_id=u.id $monthCondPs ORDER BY p.id DESC LIMIT 1
            ), COALESCE((
                SELECT (p2.adv_d4u + p2.loan_d4u)
                FROM eco_payslips p2 WHERE p2.worker_id=u.id ORDER BY p2.id DESC LIMIT 1
            ), 0)) AS prev_d4u,
            COALESCE((SELECT SUM(l.amount) FROM eco_worker_ledger l
                WHERE l.worker_id=u.id AND l.type='advance' $monthCondLedger
                " . ($monthFilter ? "" : "AND COALESCE(l.transaction_date, DATE(l.created_at)) > COALESCE((SELECT DATE(p2.created_at) FROM eco_payslips p2 WHERE p2.worker_id=u.id ORDER BY p2.id DESC LIMIT 1),'1900-01-01')") . "
            ), 0) AS current_adv,
            COALESCE((SELECT SUM(l.amount) FROM eco_worker_ledger l
                WHERE l.worker_id=u.id AND l.type='loan' $monthCondLedger
                " . ($monthFilter ? "" : "AND COALESCE(l.transaction_date, DATE(l.created_at)) > COALESCE((SELECT DATE(p2.created_at) FROM eco_payslips p2 WHERE p2.worker_id=u.id ORDER BY p2.id DESC LIMIT 1),'1900-01-01')") . "
            ), 0) AS current_loan,
            COALESCE((SELECT total_amount FROM eco_payslips p WHERE p.worker_id=u.id $monthCondPs ORDER BY p.id DESC LIMIT 1), 0) AS last_earned,
            COALESCE((SELECT total_paid   FROM eco_payslips p WHERE p.worker_id=u.id $monthCondPs ORDER BY p.id DESC LIMIT 1), 0) AS last_total_paid
            FROM eco_tool_users u
            WHERE u.role IN ('worker','d4u_writer','qa','seo_manager','ai_work')
            ORDER BY u.username ASC
        ";
        return db()->query($sql)->fetchAll();
    }

    public static function getWorkerAccount(int $workerId, string $monthFilter = ''): array {
        /* Progress stats + pending payslip count */
        $progress       = self::getWorkerProgress($workerId);
        $stats          = $progress['ok'] ? $progress['stats']          : null;
        $pendingPayslip = $progress['ok'] ? intval($progress['pending_payslip'] ?? 0) : 0;
        $rate           = $progress['ok'] ? floatval($progress['rate'] ?? 0) : 0;

        $monthCondPs = "";
        $monthCondLedger = "";
        
        if ($monthFilter) {
            $qMonth = db()->quote($monthFilter);
            $dateObj = DateTime::createFromFormat('Y-m', $monthFilter);
            if ($dateObj) {
                $monthName = $dateObj->format('F');
                $year = $dateObj->format('Y');
                $labelPattern1 = db()->quote("%$monthName $year%");
                $labelPattern2 = db()->quote("%" . substr($monthName, 0, 3) . "%$year%");
                $monthCondPs = "AND (DATE_FORMAT(p.created_at, '%Y-%m') = $qMonth OR p.month = $qMonth OR p.month LIKE $labelPattern1 OR p.month LIKE $labelPattern2)";
            } else {
                $monthCondPs = "AND DATE_FORMAT(p.created_at, '%Y-%m') = $qMonth";
            }
            $monthCondLedger = "AND DATE_FORMAT(COALESCE(transaction_date, DATE(created_at)), '%Y-%m') = $qMonth";
        }

        /* Real-time EARNED = pending unslipped paid products × rate */
        $earned = round($pendingPayslip * $rate, 2);

        /* Absolute Last payslip — PREV. D4U = its stored adv_d4u + loan_d4u */
        $absLpStmt = db()->prepare("SELECT id, created_at, adv_d4u, loan_d4u, total_amount, total_paid FROM eco_payslips p WHERE worker_id=? ORDER BY id DESC LIMIT 1");
        $absLpStmt->execute([$workerId]);
        $absLastPs   = $absLpStmt->fetch();
        $prevD4U     = $absLastPs ? round(floatval($absLastPs['adv_d4u']) + floatval($absLastPs['loan_d4u']), 2) : 0.0;
        $lastDate    = $absLastPs ? $absLastPs['created_at'] : null;

        /* Filtered Last payslip for month-specific stats */
        $lpStmt = db()->prepare("SELECT id, created_at, adv_d4u, loan_d4u, total_amount, total_paid FROM eco_payslips p WHERE worker_id=? $monthCondPs ORDER BY id DESC LIMIT 1");
        $lpStmt->execute([$workerId]);
        $lastPs   = $lpStmt->fetch();

        if ($monthFilter) {
            // If filtering, we just want exactly that month's ledger entries.
            $advQ = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM eco_worker_ledger WHERE worker_id=? AND type='advance' $monthCondLedger");
            $advQ->execute([$workerId]);
            $loanQ = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM eco_worker_ledger WHERE worker_id=? AND type='loan' $monthCondLedger");
            $loanQ->execute([$workerId]);
            $penQ = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM eco_worker_ledger WHERE worker_id=? AND type='penalty' $monthCondLedger");
            $penQ->execute([$workerId]);

            $currentAdv     = round(floatval($advQ->fetchColumn()), 2);
            $currentLoan    = round(floatval($loanQ->fetchColumn()), 2);
            $currentPenalty = round(floatval($penQ->fetchColumn()), 2);

            if ($lastPs) {
                $earned         = floatval($lastPs['total_amount']);
                $actualEarned   = $earned; // Already locked in past
                $projectedTotal = floatval($lastPs['total_paid']);
                $prevD4U        = floatval($lastPs['prev_d4u']); // Show what was ACTUALLY deducted that month
            } else {
                $earned         = 0.0; // If they are looking at a specific month and no payslip exists, don't leak all-time pending!
                $actualEarned   = 0.0;
                $projectedTotal = 0.0;
            }
        } else {
            /* Current cycle debits = ledger entries AFTER last payslip date */
            if($lastDate) {
                $advQ = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM eco_worker_ledger WHERE worker_id=? AND type='advance' AND COALESCE(transaction_date,DATE(created_at)) > DATE(?)");
                $advQ->execute([$workerId, $lastDate]);
                $loanQ = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM eco_worker_ledger WHERE worker_id=? AND type='loan' AND COALESCE(transaction_date,DATE(created_at)) > DATE(?)");
                $loanQ->execute([$workerId, $lastDate]);
                $penQ = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM eco_worker_ledger WHERE worker_id=? AND type='penalty' AND COALESCE(transaction_date,DATE(created_at)) > DATE(?)");
                $penQ->execute([$workerId, $lastDate]);
            } else {
                $advQ = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM eco_worker_ledger WHERE worker_id=? AND type='advance'");
                $advQ->execute([$workerId]);
                $loanQ = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM eco_worker_ledger WHERE worker_id=? AND type='loan'");
                $loanQ->execute([$workerId]);
                $penQ = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM eco_worker_ledger WHERE worker_id=? AND type='penalty'");
                $penQ->execute([$workerId]);
            }
            $currentAdv     = round(floatval($advQ->fetchColumn()), 2);
            $currentLoan    = round(floatval($loanQ->fetchColumn()), 2);
            $currentPenalty = round(floatval($penQ->fetchColumn()), 2);

            $actualEarned    = round($earned - $prevD4U, 2);
            $projectedTotal  = round($earned - $prevD4U - $currentAdv - $currentLoan - $currentPenalty, 2);
        }

        /* Payslips list (with new columns) */
        $ps = db()->prepare("SELECT p.id, p.month, p.total_amount, p.prev_d4u, p.adv_d4u, p.loan_d4u, p.penalty_deducted, p.total_paid, p.status, p.created_at, (SELECT COUNT(*) FROM eco_payslip_items WHERE payslip_id=p.id) AS item_count FROM eco_payslips p WHERE p.worker_id=? $monthCondPs ORDER BY p.id ASC");
        $ps->execute([$workerId]);

        /* All ledger entries for history table */
        $en = db()->prepare("SELECT * FROM eco_worker_ledger WHERE worker_id=? $monthCondLedger ORDER BY COALESCE(transaction_date,DATE(created_at)) ASC, id ASC");
        $en->execute([$workerId]);

        return [
            'prev_d4u'          => $prevD4U,
            'current_adv'       => $currentAdv,
            'current_loan'      => $currentLoan,
            'earned'            => $earned,
            'actual_earned'     => $actualEarned,
            'current_penalty'   => $currentPenalty,
            'projected_total'   => $projectedTotal,
            'rate'              => $rate,
            'pending_payslip'   => $pendingPayslip,
            'last_payslip_date' => $lastDate,
            'stats'             => $stats,
            'payslips'          => $ps->fetchAll(),
            'entries'           => $en->fetchAll(),
        ];
    }

    public static function addLedgerEntry(int $workerId, string $type, float $amount, string $notes, string $date): array {
        if(!$workerId || !in_array($type,['advance','loan','payment','paid','penalty']) || $amount <= 0){
            return ['ok'=>false,'message'=>'Invalid data'];
        }
        if(!$date || !DateTime::createFromFormat('Y-m-d', $date)){
            $date = date('Y-m-d');
        }
        db()->prepare("INSERT INTO eco_worker_ledger (worker_id,type,amount,notes,transaction_date) VALUES(?,?,?,?,?)")
           ->execute([$workerId, $type, $amount, $notes ?: null, $date]);
        return ['ok'=>true];
    }

    public static function deleteLedgerEntry(int $id): void {
        db()->prepare("DELETE FROM eco_worker_ledger WHERE id=?")->execute([$id]);
    }

    public static function getPenalties(array $user, int $workerFilter = 0): array {
        if(is_admin()){
            if($workerFilter > 0){
                $stmt = db()->prepare("SELECT p.*, t.product_no, t.title, u.username AS worker_name FROM eco_penalties p JOIN wp_eco_aplus_tasks t ON t.id=p.task_id JOIN eco_tool_users u ON u.id=p.worker_id WHERE p.worker_id=? ORDER BY p.id DESC");
                $stmt->execute([$workerFilter]);
            } else {
                $stmt = db()->query("SELECT p.*, t.product_no, t.title, u.username AS worker_name FROM eco_penalties p JOIN wp_eco_aplus_tasks t ON t.id=p.task_id JOIN eco_tool_users u ON u.id=p.worker_id ORDER BY p.id DESC");
            }
        } else {
            $stmt = db()->prepare("SELECT p.*, t.product_no, t.title, u.username AS worker_name FROM eco_penalties p JOIN wp_eco_aplus_tasks t ON t.id=p.task_id JOIN eco_tool_users u ON u.id=p.worker_id WHERE p.worker_id=? ORDER BY p.id DESC");
            $stmt->execute([$user['id']]);
        }
        return $stmt->fetchAll();
    }

    public static function processPenalty(int $penaltyId, string $decision, float $customFine): array {
        $stmt = db()->prepare("SELECT * FROM eco_penalties WHERE id=?");
        $stmt->execute([$penaltyId]);
        $p = $stmt->fetch();
        if(!$p) return ['ok'=>false, 'message'=>'Penalty not found'];

        if($decision === 'waive'){
            db()->prepare("UPDATE eco_penalties SET status='Waived' WHERE id=?")->execute([$penaltyId]);
            return ['ok'=>true];
        } elseif($decision === 'deduct') {
            $fine = $customFine >= 0 ? $customFine : floatval($p['penalty_amount']);
            
            $tstmt = db()->prepare("SELECT product_no, title FROM wp_eco_aplus_tasks WHERE id=?");
            $tstmt->execute([$p['task_id']]);
            $task = $tstmt->fetch();
            $prodNo = $task ? $task['product_no'] : $p['task_id'];
            
            $typeLabel = $p['type'] === 'design' ? 'Design' : 'Content';
            $notes = "Fine for revision on product #{$prodNo} (Type: {$typeLabel})";
            
            db()->beginTransaction();
            try {
                db()->prepare("UPDATE eco_penalties SET status='Deducted', penalty_amount=? WHERE id=?")->execute([$fine, $penaltyId]);
                db()->prepare("INSERT INTO eco_worker_ledger (worker_id,type,amount,notes,transaction_date) VALUES(?,?,?,?,?)")
                   ->execute([$p['worker_id'], 'penalty', $fine, $notes, date('Y-m-d')]);
                db()->commit();
                return ['ok'=>true];
            } catch(Exception $e) {
                db()->rollBack();
                return ['ok'=>false, 'message'=>$e->getMessage()];
            }
        }
        return ['ok'=>false, 'message'=>'Invalid decision'];
    }

    public static function deletePenalty(int $penaltyId): array {
        $stmt = db()->prepare("SELECT * FROM eco_penalties WHERE id=?");
        $stmt->execute([$penaltyId]);
        $p = $stmt->fetch();
        if(!$p) return ['ok'=>false, 'message'=>'Penalty not found'];

        db()->beginTransaction();
        try {
            if ($p['status'] === 'Deducted') {
                $tstmt = db()->prepare("SELECT product_no, title FROM wp_eco_aplus_tasks WHERE id=?");
                $tstmt->execute([$p['task_id']]);
                $task = $tstmt->fetch();
                $prodNo = $task ? $task['product_no'] : $p['task_id'];
                
                $typeLabel = $p['type'] === 'design' ? 'Design' : 'Content';
                $notes = "Fine for revision on product #{$prodNo} (Type: {$typeLabel})";

                db()->prepare("DELETE FROM eco_worker_ledger WHERE worker_id = ? AND type = 'penalty' AND notes = ?")
                   ->execute([$p['worker_id'], $notes]);
            }
            db()->prepare("DELETE FROM eco_penalties WHERE id = ?")->execute([$penaltyId]);
            db()->commit();
            return ['ok'=>true];
        } catch(Exception $e) {
            db()->rollBack();
            return ['ok'=>false, 'message'=>$e->getMessage()];
        }
    }

}
