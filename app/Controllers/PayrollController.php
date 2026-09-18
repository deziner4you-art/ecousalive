<?php

/*
=====================================================
PAYROLL CONTROLLER
Payslips, worker rates, ledger
=====================================================
*/

class PayrollController {

    public function getRates(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        json_success(['data' => Payslip::getWorkerRates()]);
    }

    public function saveRate(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();
        $workerId  = intval($_POST['worker_id']         ?? 0);
        $rateInfo  = floatval($_POST['rate_infographics'] ?? 0);
        $rateAplus = floatval($_POST['rate_aplus']        ?? 0);
        $fine      = floatval($_POST['fine']              ?? 0);
        Payslip::saveWorkerRate($workerId, $rateInfo, $rateAplus, $fine);
        json_success();
    }

    public function getProducts(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        $workerId = intval($_GET['worker_id'] ?? 0);
        $dateFrom = trim($_GET['date_from']   ?? '');
        $dateTo   = trim($_GET['date_to']     ?? '');
        if(!$workerId || !$dateFrom || !$dateTo){ json_success(['data'=>[]]); return; }
        json_success(['data' => Payslip::getProductsForPayslip($workerId, $dateFrom, $dateTo)]);
    }

    public function getWorkerProducts(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        $workerId = intval($_GET['worker_id'] ?? 0);
        $dateFrom = trim($_GET['date_from']   ?? '');
        $dateTo   = trim($_GET['date_to']     ?? '');
        if(!$workerId){ json_success(['data'=>[]]); return; }
        try{
            $data = Payslip::getWorkerProductsFlexible($workerId, $dateFrom, $dateTo);
            json_success(['data' => $data]);
        }catch(Exception $e){
            json_error($e->getMessage());
        }
    }

    public function generate(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $workerId         = intval($_POST['worker_id']          ?? 0);
        $taskIdsJ         = trim($_POST['task_ids']             ?? '');
        $label            = trim($_POST['label']                ?? '');
        $prevD4U          = floatval($_POST['prev_d4u']         ?? 0);
        $advD4U           = floatval($_POST['adv_d4u']          ?? 0);
        $loanD4U          = floatval($_POST['loan_d4u']         ?? 0);
        $penaltyDeducted  = floatval($_POST['penalty_deducted'] ?? 0);

        if(!$workerId || !$taskIdsJ){ json_error('Invalid data'); }

        $taskIds = array_values(array_filter(array_map('intval', json_decode($taskIdsJ, true) ?: [])));
        $result  = Payslip::generate($workerId, $taskIds, $label, $prevD4U, $advD4U, $loanD4U, $penaltyDeducted);
        if($result['ok']){
            json_success(['earned'=>$result['earned'],'total_paid'=>$result['total_paid'],'count'=>$result['count']]);
        } else {
            json_error($result['message'] ?? 'Error');
        }
    }

    public function getAll(): void {
        AuthMiddleware::requireAuth();
        $user = current_user();
        if(!in_array($user['role'],['worker','d4u_writer','qa','seo_manager']) && !is_admin()) json_error('Unauthorized', 403);
        json_success(['data' => Payslip::getAll($user)]);
    }

    public function getDetail(): void {
        AuthMiddleware::requireAuth();
        $user   = current_user();
        $id     = intval($_GET['id'] ?? 0);
        $result = Payslip::getDetail($id, $user);
        if(!$result['ok']) json_error('Not found or unauthorized', 404);
        json_success($result);
    }

    public function markPaid(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();
        $id = intval($_POST['id'] ?? 0);
        Payslip::markPaid($id);
        json_success();
    }

    public function delete(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();
        $id     = intval($_POST['id'] ?? 0);
        $result = Payslip::delete($id);
        if($result['ok']) json_success(); else json_error($result['message'] ?? 'Error');
    }

    public function getProgress(): void {
        AuthMiddleware::requireAuth();
        $user = current_user();
        $workerId = intval($_GET['worker_id'] ?? 0);
        if(!$workerId) {
            $workerId = $user['id'];
        }
        if(!is_admin() && $workerId !== $user['id']) json_error('Unauthorized', 403);
        $result = Payslip::getWorkerProgress($workerId);
        if($result['ok']) json_success($result); else json_error($result['message'] ?? 'Not found');
    }

    public function getAllBalances(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        $monthFilter = trim($_GET['month'] ?? '');
        json_success(['data' => Payslip::getAllWorkerBalances($monthFilter)]);
    }

    public function getAccount(): void {
        AuthMiddleware::requireAuth();
        $user = current_user();
        $workerId = intval($_GET['worker_id'] ?? 0);
        $monthFilter = trim($_GET['month'] ?? '');
        if(!$workerId) {
            $workerId = $user['id'];
        }
        if(!is_admin() && $workerId !== $user['id']) json_error('Unauthorized', 403);
        json_success(Payslip::getWorkerAccount($workerId, $monthFilter));
    }

    public function addLedger(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();
        $result = Payslip::addLedgerEntry(
            intval($_POST['worker_id']         ?? 0),
            trim($_POST['type']                ?? ''),
            floatval($_POST['amount']          ?? 0),
            trim($_POST['notes']               ?? ''),
            trim($_POST['transaction_date']    ?? '')
        );
        if($result['ok']) json_success(); else json_error($result['message'] ?? 'Error');
    }

    public function deleteLedger(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();
        $id = intval($_POST['id'] ?? 0);
        if(!$id) json_error('Invalid ID');
        Payslip::deleteLedgerEntry($id);
        json_success();
    }

    public function getWorkers(): void {
        AuthMiddleware::requireAuth();
        $user = current_user();
        if(!is_admin() && $user['role'] !== 'qa') json_error('Unauthorized', 403);
        json_success(['data' => User::getPayrollWorkers()]);
    }

    public function getPenalties(): void {
        AuthMiddleware::requireAuth();
        $user = current_user();
        $workerFilter = intval($_GET['worker_id'] ?? 0);
        $data = Payslip::getPenalties($user, $workerFilter);
        json_success(['data' => $data]);
    }

    public function processPenalty(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $penaltyId  = intval($_POST['penalty_id']  ?? 0);
        $decision   = trim($_POST['decision']      ?? '');
        $customFine = floatval($_POST['custom_fine'] ?? -1);

        $result = Payslip::processPenalty($penaltyId, $decision, $customFine);
        if($result['ok']){
            json_success();
        } else {
            json_error($result['message'] ?? 'Error');
        }
    }

    public function deletePenalty(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $penaltyId = intval($_POST['penalty_id'] ?? $_POST['id'] ?? 0);
        if(!$penaltyId) {
            json_error('Invalid penalty ID');
        }

        $result = Payslip::deletePenalty($penaltyId);
        if($result['ok']){
            json_success();
        } else {
            json_error($result['message'] ?? 'Error');
        }
    }

}
