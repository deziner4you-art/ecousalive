<?php

/*
=====================================================
ADMIN CONTROLLER
User management, CSV import, permissions
=====================================================
*/

class AdminController {

    public function getUsers(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        json_success(['data' => User::getAll()]);
    }

    public function createUser(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role     = trim($_POST['role']     ?? '');

        if(!$username || !$password || !$role) json_error('All fields required');

        $allowed = ['worker','eco_client','qa','seo_manager','eco_listing','administrator','ai_work'];
        if(!in_array($role, $allowed)) json_error('Invalid role');

        User::create($username, $password, $role);
        json_success();
    }

    public function deleteUser(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $id = intval($_POST['id'] ?? 0);
        if(!$id) json_error('Invalid ID');
        User::delete($id);
        json_success();
    }

    public function updateUser(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $id       = intval($_POST['id']       ?? 0);
        $username = trim($_POST['username']   ?? '');
        $role     = trim($_POST['role']       ?? '');
        $password = trim($_POST['password']   ?? '');

        if(!$id || !$username || !$role) json_error('Missing fields');

        $allowed = ['worker','eco_client','qa','seo_manager','eco_listing','administrator','ai_work'];
        if(!in_array($role, $allowed)) json_error('Invalid role');

        User::update($id, $username, $role, $password);
        json_success();
    }

    public function importCSV(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();

        if(!empty($_FILES['csv']['tmp_name'])){
            User::importCSV($_FILES['csv']['tmp_name']);
        }

        json_success();
    }

    public function getPermissions(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();

        $result = User::getPermissions();
        json_success($result);
    }

    public function savePermissions(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $role     = trim($_POST['role']     ?? '');
        $settings = trim($_POST['settings'] ?? '{}');

        $result = User::savePermissions($role, $settings);
        if($result['ok']) json_success(); else json_error($result['message'] ?? 'Error');
    }

    /* ── Module Permissions (RBAC) ───────────────── */

    public function getUserPermissions(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();

        $users       = User::getAll();
        $roleFilters = User::getPermissions();
        $userFilters = User::getUserPermissions();
        $roleModules = ModulePermission::getAll();
        $userModules = ModulePermission::getUserAll();

        $effective = [];
        foreach($users as $u){
            $uid = (int)$u['id'];
            $effective[$uid] = [
                'filters' => User::getEffectiveFiltersForUser($u),
                'modules' => ModulePermission::getForUser($u),
            ];
        }

        json_success([
            'users'        => $users,
            'role_filters' => $roleFilters['data'] ?? [],
            'user_filters' => $userFilters,
            'role_modules' => $roleModules,
            'user_modules' => $userModules,
            'effective'    => $effective,
            'modules'      => ModulePermission::MODULES,
            'labels'       => ModulePermission::MODULE_LABELS,
        ]);
    }

    public function saveUserPermissions(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $userId  = intval($_POST['user_id'] ?? 0);
        $filters = json_decode($_POST['filters'] ?? '[]', true);
        $modules = json_decode($_POST['modules'] ?? '{}', true);

        if($userId <= 0) json_error('Invalid user');
        if(!is_array($filters)) json_error('Invalid filter permissions');
        if(!is_array($modules)) json_error('Invalid module permissions');

        $result = User::saveUserPermissions($userId, json_encode(['filters' => $filters]));
        if(!$result['ok']) json_error($result['message'] ?? 'Error');

        $allowedModules = array_keys(ModulePermission::MODULES);
        foreach($modules as $module => $perms){
            if(!in_array($module, $allowedModules)) continue;
            if(is_array($perms)) ModulePermission::saveForUser($userId, $module, $perms);
        }

        json_success();
    }

    public function resetUserPermissions(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $userId = intval($_POST['user_id'] ?? 0);
        if($userId <= 0) json_error('Invalid user');

        db()->prepare("DELETE FROM eco_user_permissions WHERE user_id=?")->execute([$userId]);
        db()->prepare("DELETE FROM eco_user_module_permissions WHERE user_id=?")->execute([$userId]);

        json_success();
    }

    public function getModulePermissions(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        json_success([
            'data'    => ModulePermission::getAll(),
            'modules' => ModulePermission::MODULES,
            'labels'  => ModulePermission::MODULE_LABELS,
        ]);
    }

    public function saveModulePermissions(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $role   = trim($_POST['role']   ?? '');
        $module = trim($_POST['module'] ?? '');
        $perms  = json_decode($_POST['perms'] ?? '{}', true);

        $allowed_roles   = ['worker','eco_client','qa','eco_listing','seo_manager','ai_work'];
        $allowed_modules = array_keys(ModulePermission::MODULES);

        if(!in_array($role, $allowed_roles))   json_error('Invalid role');
        if(!in_array($module, $allowed_modules)) json_error('Invalid module');
        if(!is_array($perms)) json_error('Invalid permissions data');

        ModulePermission::save($role, $module, $perms);
        json_success();
    }

    public function getDashboardStats(): void {
        AuthMiddleware::requireAuth();
        
        $u = current_user();
        $isAdmin = ($u['role'] === 'administrator' || $u['username'] === 'ilyaeco');

        $db = db();

        $period     = $_GET['period'] ?? 'all';
        $start_date = $_GET['start_date'] ?? '';
        $end_date   = $_GET['end_date'] ?? '';

        // Build SQL conditions using Expense helper
        $invPaidCond      = Expense::getDateRangeSQL($period, $start_date, $end_date, 'paid_at');
        $invPendingCond   = Expense::getDateRangeSQL($period, $start_date, $end_date, 'created_at');
        $payPaidCond      = Expense::getDateRangeSQL($period, $start_date, $end_date, 'paid_at');
        $payGeneratedCond = Expense::getDateRangeSQL($period, $start_date, $end_date, 'created_at');
        $penCond          = Expense::getDateRangeSQL($period, $start_date, $end_date, 'created_at');
        $expCond          = Expense::getDateRangeSQL($period, $start_date, $end_date, 'expense_date');

        if ($isAdmin) {
            // 1. Finance Stats
            // Invoices — paid filtered by paid_at, pending by created_at
            $invPaid    = floatval($db->query("SELECT COALESCE(SUM(total_amount), 0) FROM eco_invoices WHERE status = 'Paid'    AND {$invPaidCond}")->fetchColumn());
            $invPending = floatval($db->query("SELECT COALESCE(SUM(total_amount), 0) FROM eco_invoices WHERE status = 'Pending' AND {$invPendingCond}")->fetchColumn());

            // Payslips — paid filtered by paid_at, generated by created_at
            $payPaid      = floatval($db->query("SELECT COALESCE(SUM(total_amount), 0) FROM eco_payslips WHERE status = 'Paid'      AND {$payPaidCond}")->fetchColumn());
            $payGenerated = floatval($db->query("SELECT COALESCE(SUM(total_amount), 0) FROM eco_payslips WHERE status = 'Generated' AND {$payGeneratedCond}")->fetchColumn());

            // Total worker ledger paid (not date-filtered for overall liability calculation)
            $totalWorkerPaid = floatval($db->query("SELECT COALESCE(SUM(amount), 0) FROM eco_worker_ledger")->fetchColumn());
            // Total worker earned (all payslips generated/paid, not date-filtered for overall liability calculation)
            $totalWorkerEarned = floatval($db->query("SELECT COALESCE(SUM(total_amount), 0) FROM eco_payslips")->fetchColumn());
            $workerBalances = round($totalWorkerEarned - $totalWorkerPaid, 2);

            // Fines / Penalties
            $finesDeducted = floatval($db->query("SELECT COALESCE(SUM(penalty_amount), 0) FROM eco_penalties WHERE status = 'Deducted' AND {$penCond}")->fetchColumn());
            $finesPending = floatval($db->query("SELECT COALESCE(SUM(penalty_amount), 0) FROM eco_penalties WHERE status = 'Pending' AND {$penCond}")->fetchColumn());

            // Expenses
            $expenseTotals = Expense::getPeriodTotals($period, $start_date, $end_date);
            $expensesList = Expense::getFiltered($period, $start_date, $end_date);
        } else {
            $invPaid = 0; $invPending = 0; $payPaid = 0; $payGenerated = 0;
            $workerBalances = 0; $finesDeducted = 0; $finesPending = 0;
            $expenseTotals = ['office' => 0, 'utility' => 0, 'other' => 0, 'total' => 0];
            $expensesList = [];
        }

        // 2. Project Pipeline Stats
        $taskCondActivity  = Expense::getDateRangeSQL($period, $start_date, $end_date, 'last_activity_at');
        $taskCondCompleted = Expense::getDateRangeSQL($period, $start_date, $end_date, 'work_completed_at');
        $taskCondPublished = Expense::getDateRangeSQL($period, $start_date, $end_date, 'published_at');
        $taskCondApproved  = Expense::getDateRangeSQL($period, $start_date, $end_date, 'content_approved_at');
        $taskCondUpdated   = Expense::getDateRangeSQL($period, $start_date, $end_date, 'content_updated_at');
        $taskCondQA        = Expense::getDateRangeSQL($period, $start_date, $end_date, 'qa_submitted_at');

        $writerCond = "(" . Expense::getDateRangeSQL($period, $start_date, $end_date, 'content_approved_at') . " OR " . Expense::getDateRangeSQL($period, $start_date, $end_date, 'content_updated_at') . ")";

        $userFilter = "";
        if (!$isAdmin) {
            $uid = intval($u['id']);
            $urole = $u['role'];
            if ($urole === 'qa') {
                $userFilter = " AND (qa_submitted_by = {$uid} OR qa_user_id = {$uid})";
            } elseif ($urole === 'seo_manager' || $urole === 'd4u_writer') {
                $userFilter = " AND (written_by_user_id = {$uid} OR work_status = 'SEO Review' OR qa_submitted_by = {$uid})";
            } elseif ($urole === 'ai_work') {
                $userFilter = " AND ai_worked_by = {$uid}";
            } else {
                $userFilter = " AND (work_completed_by_worker_id = {$uid} OR assigned_worker_id = {$uid} OR id IN (SELECT task_id FROM eco_tool_assignments WHERE worker_id = {$uid}))";
            }
        }

        // Apply taskCondActivity to the current statuses to respect date filter
        $pipeline = [
            'working'            => intval($db->query("SELECT COUNT(*) FROM wp_eco_aplus_tasks WHERE work_status = 'Working' AND deleted_at IS NULL AND {$taskCondActivity} {$userFilter}")->fetchColumn()),
            'paused'             => intval($db->query("SELECT COUNT(*) FROM wp_eco_aplus_tasks WHERE work_status = 'Paused' AND deleted_at IS NULL AND {$taskCondActivity} {$userFilter}")->fetchColumn()),
            'in_qa'              => intval($db->query("SELECT COUNT(*) FROM wp_eco_aplus_tasks WHERE work_status = 'In QA' AND deleted_at IS NULL AND {$taskCondActivity} {$userFilter}")->fetchColumn()),
            'seo_review'         => intval($db->query("SELECT COUNT(*) FROM wp_eco_aplus_tasks WHERE work_status = 'SEO Review' AND deleted_at IS NULL AND {$taskCondActivity} {$userFilter}")->fetchColumn()),
            'hold'               => intval($db->query("SELECT COUNT(*) FROM wp_eco_aplus_tasks WHERE status = 'Hold' AND deleted_at IS NULL AND {$taskCondActivity} {$userFilter}")->fetchColumn()),
            'changes'            => intval($db->query("SELECT COUNT(*) FROM wp_eco_aplus_tasks WHERE work_status = 'Changes' AND deleted_at IS NULL AND {$taskCondActivity} {$userFilter}")->fetchColumn()),
            'changes_in_content' => intval($db->query("SELECT COUNT(*) FROM wp_eco_aplus_tasks WHERE work_status = 'Changes in Content' AND deleted_at IS NULL AND {$taskCondActivity} {$userFilter}")->fetchColumn()),
            'changing'           => intval($db->query("SELECT COUNT(*) FROM wp_eco_aplus_tasks WHERE work_status = 'Changing' AND deleted_at IS NULL AND {$taskCondActivity} {$userFilter}")->fetchColumn()),
            'ai_work'            => intval($db->query("SELECT COUNT(*) FROM wp_eco_aplus_tasks WHERE status = 'AI Work' AND deleted_at IS NULL AND {$taskCondActivity} {$userFilter}")->fetchColumn()),
            'ai_done'            => intval($db->query("SELECT COUNT(*) FROM wp_eco_aplus_tasks WHERE status = 'AI DONE' AND deleted_at IS NULL AND {$taskCondActivity} {$userFilter}")->fetchColumn()),
        ];

        $pipeline['total']     = intval($db->query("SELECT COUNT(*) FROM wp_eco_aplus_tasks WHERE deleted_at IS NULL AND {$taskCondActivity} {$userFilter}")->fetchColumn());
        $pipeline['pending']   = intval($db->query("SELECT COUNT(*) FROM wp_eco_aplus_tasks WHERE status = 'Pending' AND work_status = 'Pending' AND deleted_at IS NULL AND {$taskCondActivity} {$userFilter}")->fetchColumn());
        $pipeline['approved']  = intval($db->query("SELECT COUNT(*) FROM wp_eco_aplus_tasks WHERE status IN ('Approved','Updated') AND work_status = 'Pending' AND deleted_at IS NULL AND {$taskCondApproved} {$userFilter}")->fetchColumn());
        $pipeline['work_done'] = intval($db->query("SELECT COUNT(*) FROM wp_eco_aplus_tasks WHERE work_status IN ('Work Done', 'Info Done') AND deleted_at IS NULL AND {$taskCondCompleted} {$userFilter}")->fetchColumn());
        $pipeline['published'] = intval($db->query("SELECT COUNT(*) FROM wp_eco_aplus_tasks WHERE published_at IS NOT NULL AND deleted_at IS NULL AND {$taskCondPublished} {$userFilter}")->fetchColumn());

        // 3. Worker Progress & Hours (date-filtered for completed tasks and hours if desired)
        $workerStats = [];
        if ($isAdmin) {
            $workers = $db->query("SELECT id, username, role FROM eco_tool_users WHERE role IN ('worker', 'qa', 'seo_manager', 'eco_listing', 'ai_work') ORDER BY username ASC")->fetchAll();

            foreach($workers as $w){
                $uid = intval($w['id']);
                $role = $w['role'];

                // Active tasks (assigned and work_status not Work Done, Info Done or Hold)
                $activeStmt = $db->prepare("
                    SELECT COUNT(*) FROM eco_tool_assignments a
                    JOIN wp_eco_aplus_tasks t ON t.id = a.task_id
                    WHERE a.worker_id = ? AND t.work_status NOT IN ('Work Done', 'Info Done', 'Hold') AND t.status != 'Hold' AND t.deleted_at IS NULL
                ");
                $activeStmt->execute([$uid]);
                $activeTasks = intval($activeStmt->fetchColumn());

                // Completed tasks (role dependent, within period)
                if($role === 'qa'){
                    $compStmt = $db->prepare("SELECT COUNT(*) FROM wp_eco_aplus_tasks WHERE qa_submitted_by = ? AND work_status IN ('Work Done', 'Info Done') AND deleted_at IS NULL AND {$taskCondQA}");
                } elseif($role === 'seo_manager' || $role === 'd4u_writer'){
                    $compStmt = $db->prepare("SELECT COUNT(*) FROM wp_eco_aplus_tasks WHERE (written_by_user_id = ? OR qa_submitted_by = ?) AND deleted_at IS NULL AND ({$writerCond} OR {$taskCondQA})");
                } elseif($role === 'ai_work'){
                    $compStmt = $db->prepare("SELECT COUNT(*) FROM wp_eco_aplus_tasks WHERE ai_worked_by = ? AND status = 'AI DONE' AND deleted_at IS NULL AND {$writerCond}");
                } else {
                    $compStmt = $db->prepare("SELECT COUNT(*) FROM wp_eco_aplus_tasks WHERE (work_completed_by_worker_id = ? OR info_worker_id = ? OR aplus_worker_id = ?) AND work_status IN ('Work Done', 'Info Done') AND deleted_at IS NULL AND {$taskCondCompleted}");
                    $compStmt->execute([$uid, $uid, $uid]);
                    $completedTasks = intval($compStmt->fetchColumn());
                }
                if($role !== 'worker') {
                    $compStmt->execute([$uid]);
                    $completedTasks = intval($compStmt->fetchColumn());
                }

                // Total working seconds (within period)
                $timeStmt = $db->prepare("
                    SELECT SUM(work_total_seconds) FROM wp_eco_aplus_tasks
                    WHERE deleted_at IS NULL AND {$taskCondActivity} AND (work_completed_by_worker_id = ?
                    OR id IN (SELECT task_id FROM eco_tool_assignments WHERE worker_id = ?))
                ");
                $timeStmt->execute([$uid, $uid]);
                $totalSeconds = intval($timeStmt->fetchColumn());

                // Active usage seconds (silent tracking - all time)
                try {
                    $usageStmt = $db->prepare("SELECT active_seconds FROM eco_user_usage WHERE user_id = ?");
                    $usageStmt->execute([$uid]);
                    $usageSeconds = intval($usageStmt->fetchColumn());
                } catch(Exception $e){
                    $usageSeconds = 0;
                }

                // Only override if no period filter is active, or usage is larger
                if ($period === 'all') {
                    $totalSeconds = max($totalSeconds, $usageSeconds);
                }

                // Get balance via Payslip helper
                $balance = 0.00;
                try {
                    $prog = Payslip::getWorkerProgress($uid);
                    if($prog['ok']){
                        $balance = floatval($prog['balance'] ?? 0);
                    }
                } catch(Exception $e) {}

                $workerStats[] = [
                    'id' => $uid,
                    'username' => $w['username'],
                    'role' => $role,
                    'active_tasks' => $activeTasks,
                    'completed_tasks' => $completedTasks,
                    'work_seconds' => $totalSeconds,
                    'balance' => $balance
                ];
            }
        }

        $is_ilyaeco = ($u['username'] === 'ilyaeco');
        if ($is_ilyaeco) {
            $invPaid = 0;
            $invPending = 0;
            $payPaid = 0;
            $payGenerated = 0;
            $workerBalances = 0;
            $finesDeducted = 0;
            $finesPending = 0;
            $expenseTotals = ['office' => 0, 'utility' => 0, 'other' => 0, 'total' => 0];
            $expensesList = [];
            
            foreach ($workerStats as &$ws) {
                $ws['balance'] = 0.00;
            }
            unset($ws);
        }

        json_success([
            'finances' => [
                'invoices_paid' => $invPaid,
                'invoices_pending' => $invPending,
                'payslips_paid' => $payPaid,
                'payslips_generated' => $payGenerated,
                'worker_balances' => $workerBalances,
                'fines_deducted' => $finesDeducted,
                'fines_pending' => $finesPending,
                'expenses_office' => $expenseTotals['office'],
                'expenses_utility' => $expenseTotals['utility'],
                'expenses_other' => $expenseTotals['other'],
                'expenses_total' => $expenseTotals['total']
            ],
            'pipeline' => $pipeline,
            'workers' => $workerStats,
            'expenses' => $expensesList
        ]);
    }

    public function addExpense(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $title = trim($_POST['title'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $date = trim($_POST['expense_date'] ?? date('Y-m-d'));

        if (!$title) json_error('Title is required');
        if ($amount <= 0) json_error('Amount must be greater than zero');
        if (!in_array($category, ['office', 'utility', 'other'])) json_error('Invalid category');
        if (!$date) json_error('Date is required');

        $ok = Expense::create($title, $amount, $category, $date);
        if ($ok) {
            json_success();
        } else {
            json_error('Failed to create expense');
        }
    }

    public function deleteExpense(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) json_error('Invalid ID');

        $ok = Expense::delete($id);
        if ($ok) {
            json_success();
        } else {
            json_error('Failed to delete expense');
        }
    }

}
