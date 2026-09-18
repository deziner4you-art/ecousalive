<?php

/*
==========================================================
ECO A+ PRO — Front Controller v2.0
Single entry point for all requests

DB NOTE: runMigrations() is intentionally NOT called here.
The database (test or production) already has all required
tables and columns. Migration.php is kept as a reference
tool for fresh deployments only.
==========================================================
*/

define('ROOT', dirname(__DIR__));

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

/* ── Bootstrap ─────────────────────────────────────── */

require_once ROOT.'/config/database.php';
require_once ROOT.'/config/app.php';
require_once ROOT.'/config/roles.php';

require_once ROOT.'/app/Helpers/Database.php';
require_once ROOT.'/app/Helpers/Response.php';
require_once ROOT.'/app/Helpers/Migration.php';
runMigrations();

require_once ROOT.'/app/Middleware/AuthMiddleware.php';
require_once ROOT.'/app/Middleware/RoleMiddleware.php';
require_once ROOT.'/app/Models/User.php';
/* ── Determine action ──────────────────────────────── */

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/* ── Session + force-logout ─────────────────────────── */

AuthMiddleware::handle($action);

/* ── Load Models ─────────────────────────────────────── */

require_once ROOT.'/app/Models/Task.php';
require_once ROOT.'/app/Models/User.php';
require_once ROOT.'/app/Models/Invoice.php';
require_once ROOT.'/app/Models/Payslip.php';
require_once ROOT.'/app/Models/SeoContent.php';
require_once ROOT.'/app/Models/ModulePermission.php';
require_once ROOT.'/app/Models/Expense.php';

/* ── Load Controllers ────────────────────────────────── */

require_once ROOT.'/app/Controllers/AuthController.php';
require_once ROOT.'/app/Controllers/TaskController.php';
require_once ROOT.'/app/Controllers/InvoiceController.php';
require_once ROOT.'/app/Controllers/PayrollController.php';
require_once ROOT.'/app/Controllers/AdminController.php';
require_once ROOT.'/app/Controllers/DashboardController.php';
require_once ROOT.'/app/Controllers/SeoController.php';

/* ── API Dispatch ────────────────────────────────────── */

if($action !== ''){

    /* public_invoice is auth-free */
    if($action === 'public_invoice'){
        (new InvoiceController())->publicView();
        exit;
    }

    /* Load route table */
    $routes = require ROOT.'/routes/web.php';

    if(isset($routes[$action])){
        [$class, $method] = $routes[$action];
        (new $class())->$method();
    } else {
        json_error('Unknown action: '.$action, 404);
    }

    exit;

}

/* ── Page Render ─────────────────────────────────────── */

(new DashboardController())->show();
