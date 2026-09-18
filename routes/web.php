<?php

/*
=====================================================
ROUTE TABLE
action → [ControllerClass, method]
All 55+ API actions mapped here
Backward-compatible with existing JS fetch calls
=====================================================
*/

return [

    /* ── Auth ──────────────────────────────────── */
    'login'            => ['AuthController',   'login'],
    'logout'           => ['AuthController',   'logout'],
    'change_password'  => ['AuthController',   'changePassword'],
    'force_logout_all' => ['AuthController',   'forceLogoutAll'],
    'get_login_logs'   => ['AuthController',   'getLoginLogs'],
    'log_current_session' => ['AuthController', 'logCurrentSession'],
    'refresh_session'  => ['AuthController',   'refreshSession'],

    /* ── Tasks ──────────────────────────────────── */
    'get_tasks'          => ['TaskController', 'getTasks'],
    'bulk_update_tasks'  => ['TaskController', 'bulkUpdateTasks'],
    'assign_product'     => ['TaskController', 'assignProduct'],
    'update_work_status' => ['TaskController', 'updateWorkStatus'],
    'pause_work'         => ['TaskController', 'pauseWork'],
    'resume_work'        => ['TaskController', 'resumeWork'],
    'submit_qa'          => ['TaskController', 'submitQA'],
    'send_for_seo'       => ['TaskController', 'sendForSEO'],
    'save_final_links'   => ['TaskController', 'saveFinalLinks'],
    'force_stage'        => ['TaskController', 'forceStage'],
    'hold_product'       => ['TaskController', 'holdProduct'],
    'toggle_urgent'      => ['TaskController', 'toggleUrgent'],
    'add_product'        => ['TaskController', 'addProduct'],
    'send_for_content'   => ['TaskController', 'sendForContent'],
    'save_task'          => ['TaskController', 'saveTask'],
    'clear_task'         => ['TaskController', 'clearTask'],
    'delete_task'        => ['TaskController', 'deleteTask'],
    'get_deleted_tasks'  => ['TaskController', 'getDeletedTasks'],
    'restore_task'       => ['TaskController', 'restoreTask'],
    'permanent_delete_task' => ['TaskController', 'permanentDeleteTask'],
    'empty_recycle_bin'  => ['TaskController', 'emptyRecycleBin'],
    'add_to_family'      => ['TaskController', 'addToFamily'],
    'remove_from_family' => ['TaskController', 'removeFromFamily'],
    'set_product_type'   => ['TaskController', 'setProductType'],
    'publish_product'    => ['TaskController', 'publishProduct'],
    'unpublish_product'  => ['TaskController', 'unpublishProduct'],
    'request_revision'   => ['TaskController', 'requestRevision'],
    'get_workers'        => ['TaskController', 'getWorkers'],
    'update_product_services' => ['TaskController', 'updateProductServices'],
    'enable_aplus_service' => ['TaskController', 'enableAplusService'],
    'start_aplus_workflow' => ['TaskController', 'startAplusWorkflow'],

    /* ── Invoices ───────────────────────────────── */
    'get_invoices'          => ['InvoiceController', 'getAll'],
    'get_invoice_detail'    => ['InvoiceController', 'getDetail'],
    'get_uninvoiced_tasks'  => ['InvoiceController', 'getUninvoiced'],
    'get_eco_clients'       => ['InvoiceController', 'getClients'],
    'create_invoice'        => ['InvoiceController', 'create'],
    'mark_invoice_paid'     => ['InvoiceController', 'markPaid'],
    'delete_invoice'        => ['InvoiceController', 'delete'],
    'clear_invoice_status'  => ['InvoiceController', 'clearStatus'],
    'public_invoice'        => ['InvoiceController', 'publicView'],

    /* ── Payroll ────────────────────────────────── */
    'get_worker_rates'                  => ['PayrollController', 'getRates'],
    'save_worker_rate'                  => ['PayrollController', 'saveRate'],
    'get_payslip_products'              => ['PayrollController', 'getProducts'],
    'get_worker_products_for_payslip'   => ['PayrollController', 'getWorkerProducts'],
    'generate_payslip'                  => ['PayrollController', 'generate'],
    'get_payslips'                      => ['PayrollController', 'getAll'],
    'get_payslip_detail'                => ['PayrollController', 'getDetail'],
    'mark_payslip_paid'                 => ['PayrollController', 'markPaid'],
    'delete_payslip'                    => ['PayrollController', 'delete'],
    'get_worker_progress'               => ['PayrollController', 'getProgress'],
    'get_all_worker_balances'           => ['PayrollController', 'getAllBalances'],
    'get_worker_account'                => ['PayrollController', 'getAccount'],
    'add_ledger_entry'                  => ['PayrollController', 'addLedger'],
    'delete_ledger_entry'               => ['PayrollController', 'deleteLedger'],
    'get_payroll_workers'               => ['PayrollController', 'getWorkers'],
    'get_penalties'                     => ['PayrollController', 'getPenalties'],
    'process_penalty'                   => ['PayrollController', 'processPenalty'],
    'delete_penalty'                    => ['PayrollController', 'deletePenalty'],

    /* ── Admin ──────────────────────────────────── */
    'get_module_permissions'  => ['AdminController', 'getModulePermissions'],
    'save_module_permissions' => ['AdminController', 'saveModulePermissions'],
    'get_user_permissions'    => ['AdminController', 'getUserPermissions'],
    'save_user_permissions'   => ['AdminController', 'saveUserPermissions'],
    'reset_user_permissions'  => ['AdminController', 'resetUserPermissions'],
    'get_users'        => ['AdminController', 'getUsers'],
    'create_user'      => ['AdminController', 'createUser'],
    'delete_user'      => ['AdminController', 'deleteUser'],
    'update_user'      => ['AdminController', 'updateUser'],
    'import_csv'       => ['AdminController', 'importCSV'],
    'get_permissions'  => ['AdminController', 'getPermissions'],
    'save_permissions' => ['AdminController', 'savePermissions'],
    'get_dashboard_stats' => ['AdminController', 'getDashboardStats'],
    'add_expense'         => ['AdminController', 'addExpense'],
    'delete_expense'      => ['AdminController', 'deleteExpense'],

    /* ── SEO Content ─────────────────────────── */
    'get_seo_content'  => ['SeoController', 'getContent'],
    'save_seo_content' => ['SeoController', 'saveContent'],
    'generate_seo_ai'  => ['SeoController', 'generateAI'],

];
