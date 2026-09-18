<?php

/*
=====================================================
INVOICE CONTROLLER
Invoice creation and management
=====================================================
*/

class InvoiceController {

    public function getAll(): void {
        AuthMiddleware::requireAuth();
        $user = current_user();
        if($user['role'] !== 'eco_client' && !is_admin()) json_error('Unauthorized', 403);

        $data = Invoice::getAll($user);
        json_success(['data' => $data]);
    }

    public function getDetail(): void {
        AuthMiddleware::requireAuth();
        $user   = current_user();
        $id     = intval($_GET['id'] ?? 0);
        $result = Invoice::getDetail($id, $user);
        if(!$result['ok']) json_error('Not found or unauthorized', 404);
        json_success($result);
    }

    public function getUninvoiced(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        json_success(['data' => Invoice::getUninvoiced()]);
    }

    public function getClients(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        json_success(['data' => User::getClients()]);
    }

    public function create(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $clientId = intval($_POST['client_id'] ?? 0);
        $notes    = trim($_POST['notes']       ?? '');
        $items    = json_decode($_POST['items'] ?? '[]', true);

        $result = Invoice::create($clientId, $notes, $items);
        if($result['ok']){
            json_success(['invoice_no' => $result['invoice_no']]);
        } else {
            json_error($result['message'] ?? 'Error');
        }
    }

    public function markPaid(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $id = intval($_POST['id'] ?? 0);
        if(!$id) json_error('Invalid ID');
        Invoice::markPaid($id);
        json_success();
    }

    public function delete(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $id     = intval($_POST['id'] ?? 0);
        $result = Invoice::delete($id);
        if($result['ok']) json_success(); else json_error($result['message'] ?? 'Error');
    }

    public function clearStatus(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $taskId = intval($_POST['task_id'] ?? 0);
        if(!$taskId) json_error('Invalid task');
        Invoice::clearStatus($taskId);
        json_success();
    }

    public function publicView(): void {
        /* No auth required */
        $id    = intval($_GET['id']    ?? 0);
        $token = trim($_GET['token']   ?? '');
        if(!$id || !$token){ http_response_code(403); echo 'Invalid link.'; exit; }

        $data = Invoice::getPublic($id, $token);
        if(!$data){ http_response_code(404); echo 'Invoice not found or link expired.'; exit; }

        $inv   = $data['invoice'];
        $items = $data['items'];
        $total = array_sum(array_column($items, 'item_price'));

        $rowsHtml = '';
        foreach($items as $i => $it){
            $rowsHtml .= '<tr><td>'.($i+1).'</td><td>'.htmlspecialchars($it['product_no']).'</td><td>'.htmlspecialchars($it['title']).'</td><td style="text-align:right;">'.number_format((float)$it['item_price'],2).'</td></tr>';
        }
        $statusColor = $inv['status'] === 'Paid' ? '#16a34a' : '#b45309';
        $notesHtml   = $inv['notes'] ? '<div style="margin-top:18px;padding:12px;background:#f8fafc;border-radius:6px;font-size:12px;color:#475569;"><strong>Notes:</strong> '.htmlspecialchars($inv['notes']).'</div>' : '';
        $dateStr     = $inv['created_at'] ? substr($inv['created_at'], 0, 10) : '-';

        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Invoice '.$inv['invoice_no'].'</title>
<style>*{box-sizing:border-box;}body{font-family:Arial,sans-serif;color:#111;background:#fff;margin:0;padding:30px;max-width:900px;margin:auto;}
.hdr{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #e2e8f0;padding-bottom:20px;margin-bottom:24px;flex-wrap:wrap;gap:16px;}
.co{font-size:26px;font-weight:900;letter-spacing:1px;background:linear-gradient(90deg,#e53e3e,#ed8936,#ecc94b,#48bb78,#4299e1,#9f7aea);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.co-sub{font-size:11px;color:#475569;line-height:1.7;margin-top:4px;}
.meta{text-align:right;font-size:13px;color:#475569;line-height:1.9;}
.bill{margin-bottom:20px;font-size:13px;}.bill-lbl{color:#64748b;font-size:11px;margin-bottom:4px;}.bill-name{font-size:15px;font-weight:bold;color:#0f172a;}
table{width:100%;border-collapse:collapse;font-size:13px;}
th{background:#f1f5f9;color:#475569;padding:9px 12px;text-align:left;border-bottom:2px solid #e2e8f0;}
td{padding:9px 12px;border-bottom:1px solid #f1f5f9;}
.tr{font-weight:bold;font-size:14px;background:#f8fafc;border-top:2px solid #e2e8f0;}
.foot{margin-top:24px;text-align:center;font-size:11px;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:14px;}
.np{margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;}
@page{margin:0;}@media print{.np{display:none;}body{padding:18mm 20mm;}html,body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}}
</style></head><body>
<div class="np">
<button onclick="window.print()" style="padding:8px 18px;background:#1e40af;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;">🖨 Print / Save as PDF</button>
</div>
<div class="hdr"><div>
<div class="co">Deziner4you</div>
<div class="co-sub">Canal Road, Lahore &ndash; PAK<br>&#128222; 0092-333-4879073 (WhatsApp) &nbsp;|&nbsp; Skype: ursmani007<br>&#9993; deziner4you@gmail.com &nbsp;|&nbsp; deziner4uae@gmail.com<br>&#127760; deziner4you.com &nbsp;|&nbsp; deziner4u &bull; deziner4you &bull; deziner4uae</div>
</div>
<div class="meta"><strong style="font-size:18px;">INVOICE</strong><br>No: <strong>'.$inv['invoice_no'].'</strong><br>Date: '.$dateStr.'<br>Status: <strong style="color:'.$statusColor.';">'.$inv['status'].'</strong></div></div>
<div class="bill"><div class="bill-lbl">BILLED TO</div><div class="bill-name">'.htmlspecialchars($inv['client_name']).'</div></div>
<table><thead><tr><th>#</th><th>Product No</th><th>Title</th><th style="text-align:right;">Price (USD)</th></tr></thead><tbody>'.$rowsHtml.'</tbody>
<tfoot><tr class="tr"><td colspan="3" style="text-align:right;padding:12px;">Total Amount</td><td style="text-align:right;padding:12px;color:#16a34a;">USD '.number_format($total,2).'</td></tr></tfoot></table>
'.$notesHtml.'
<div class="foot">Deziner4you &mdash; deziner4you.com &mdash; deziner4you@gmail.com</div>
</body></html>';
        exit;
    }

}
