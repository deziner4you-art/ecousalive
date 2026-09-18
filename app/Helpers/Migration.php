<?php

/*
=====================================================
MIGRATION HELPER
All idempotent ALTER TABLE / CREATE TABLE statements
extracted from the original monolith (lines 311–665)
Runs on every page load — all statements use IF NOT EXISTS
or try/catch so they are safe to run repeatedly
=====================================================
*/

function runMigrations(): void {

    /* ── Assignments Table ──────────────────────────── */
    db()->exec("
    CREATE TABLE IF NOT EXISTS eco_tool_assignments (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        worker_id  INT NOT NULL,
        task_id    INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_assign (worker_id, task_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    /* ── Task Columns ───────────────────────────────── */
    $_taskCols = [
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN original_content LONGTEXT DEFAULT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN work_status VARCHAR(50) DEFAULT 'Pending'",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN work_started_at DATETIME NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN work_completed_at DATETIME NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN media_link TEXT DEFAULT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN seo_doc_link TEXT DEFAULT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN qa_submitted_at DATETIME NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN qa_submitted_by INT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN hold_prev_status TEXT DEFAULT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN is_urgent TINYINT(1) DEFAULT 0",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN last_activity_at DATETIME NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN work_paused_at DATETIME NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN work_total_seconds INT DEFAULT 0",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN invoice_status VARCHAR(30) DEFAULT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN content_approved_at DATETIME NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN content_updated_at DATETIME NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN product_type VARCHAR(50) DEFAULT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN work_completed_by_worker_id INT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN written_by_user_id INT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN published_at DATETIME NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN published_by INT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN published_link TEXT DEFAULT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN info_subtasks VARCHAR(100) DEFAULT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN blunder_comment TEXT DEFAULT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN revision_comment TEXT DEFAULT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN active_revision_type VARCHAR(20) DEFAULT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN info_invoice_status VARCHAR(30) DEFAULT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN aplus_invoice_status VARCHAR(30) DEFAULT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN product_link TEXT DEFAULT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN deleted_at DATETIME DEFAULT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN family_code VARCHAR(50) DEFAULT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN seo_submitted_by INT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN ai_worked_by INT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN info_worker_id INT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN aplus_worker_id INT NULL",
    ];

    foreach($_taskCols as $_sql){
        try{ db()->exec($_sql); }catch(Exception $e){}
    }

    try {
        db()->exec("UPDATE wp_eco_aplus_tasks SET info_worker_id = work_completed_by_worker_id WHERE (info_worker_id IS NULL OR info_worker_id = 0) AND (product_type = 'Infographics' OR info_subtasks LIKE '%Infographics%') AND work_completed_by_worker_id IS NOT NULL");
        db()->exec("UPDATE wp_eco_aplus_tasks t JOIN eco_tool_assignments a ON a.task_id = t.id SET t.info_worker_id = a.worker_id WHERE (t.info_worker_id IS NULL OR t.info_worker_id = 0) AND (t.product_type = 'Infographics' OR t.info_subtasks LIKE '%Infographics%')");
        db()->exec("UPDATE wp_eco_aplus_tasks SET aplus_worker_id = work_completed_by_worker_id WHERE (aplus_worker_id IS NULL OR aplus_worker_id = 0) AND product_type = 'A+' AND work_completed_by_worker_id IS NOT NULL");
    } catch(Exception $e){}

    try{ db()->exec("CREATE INDEX idx_deleted_at ON wp_eco_aplus_tasks (deleted_at)"); }catch(Exception $e){}
    try{ db()->exec("CREATE INDEX idx_family_code ON wp_eco_aplus_tasks (family_code)"); }catch(Exception $e){}

    /* ── Backfill last_activity_at ──────────────────── */
    try{
        db()->exec("
        UPDATE wp_eco_aplus_tasks
        SET last_activity_at = COALESCE(work_completed_at, work_started_at, created_at)
        WHERE last_activity_at IS NULL
        ");
    }catch(Exception $e){}

    /* ── Backfill content timestamps ────────────────── */
    try{
        db()->exec("
        UPDATE wp_eco_aplus_tasks
        SET content_approved_at = last_activity_at
        WHERE status = 'Approved' AND content_approved_at IS NULL AND last_activity_at IS NOT NULL
        ");
        db()->exec("
        UPDATE wp_eco_aplus_tasks
        SET content_updated_at = last_activity_at
        WHERE status = 'Updated' AND content_updated_at IS NULL AND last_activity_at IS NOT NULL
        ");
    }catch(Exception $e){}

    /* ── App Config Table ───────────────────────────── */
    db()->exec("
    CREATE TABLE IF NOT EXISTS eco_app_config (
        config_key   VARCHAR(60) NOT NULL PRIMARY KEY,
        config_value TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    db()->exec("INSERT IGNORE INTO eco_app_config (config_key,config_value) VALUES ('logout_token','init')");

    /* ── Login Logs ─────────────────────────────────── */
    db()->exec("
    CREATE TABLE IF NOT EXISTS eco_login_logs (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL,
        username   VARCHAR(100) NOT NULL,
        role       VARCHAR(30) NOT NULL,
        ip_address VARCHAR(50) DEFAULT NULL,
        country    VARCHAR(100) DEFAULT NULL,
        region     VARCHAR(100) DEFAULT NULL,
        city       VARCHAR(100) DEFAULT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    /* ── Invoices ───────────────────────────────────── */
    try{ db()->exec("
    CREATE TABLE IF NOT EXISTS eco_invoices (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        invoice_no   VARCHAR(30) NOT NULL,
        client_id    INT NOT NULL,
        status       VARCHAR(20) DEFAULT 'Pending',
        total_amount DECIMAL(10,2) DEFAULT 0.00,
        notes        TEXT DEFAULT NULL,
        share_token  VARCHAR(64) DEFAULT NULL,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        paid_at      DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    "); }catch(Exception $e){}

    try{ db()->exec("ALTER TABLE eco_invoices ADD COLUMN share_token VARCHAR(64) DEFAULT NULL"); }catch(Exception $e){}

    try{ db()->exec("
    CREATE TABLE IF NOT EXISTS eco_invoice_items (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id   INT NOT NULL,
        task_id      INT NOT NULL,
        item_price   DECIMAL(10,2) DEFAULT 0.00,
        info_price   DECIMAL(10,2) DEFAULT 0.00,
        aplus_price  DECIMAL(10,2) DEFAULT 0.00,
        UNIQUE KEY uq_inv_item (invoice_id, task_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    "); }catch(Exception $e){}

    try{ db()->exec("ALTER TABLE eco_invoice_items ADD COLUMN info_price DECIMAL(10,2) DEFAULT 0.00"); }catch(Exception $e){}
    try{ db()->exec("ALTER TABLE eco_invoice_items ADD COLUMN aplus_price DECIMAL(10,2) DEFAULT 0.00"); }catch(Exception $e){}

    /* ── Worker Rates ───────────────────────────────── */
    try{ db()->exec("
    CREATE TABLE IF NOT EXISTS eco_worker_rates (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        worker_id        INT NOT NULL UNIQUE,
        rate_per_product DECIMAL(10,2) DEFAULT 0.00,
        updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    "); }catch(Exception $e){}

    try{ db()->exec("ALTER TABLE eco_worker_rates ADD COLUMN fine_per_revision  DECIMAL(10,2) DEFAULT 0.00"); }catch(Exception $e){}
    try{ db()->exec("ALTER TABLE eco_worker_rates ADD COLUMN rate_infographics DECIMAL(10,2) DEFAULT 0.00"); }catch(Exception $e){}
    try{ db()->exec("ALTER TABLE eco_worker_rates ADD COLUMN rate_aplus        DECIMAL(10,2) DEFAULT 0.00"); }catch(Exception $e){}

    /* ── Penalties & Revisions ──────────────────────── */
    try{ db()->exec("
    CREATE TABLE IF NOT EXISTS eco_penalties (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        task_id        INT NOT NULL,
        worker_id      INT NOT NULL,
        type           VARCHAR(20) NOT NULL,
        penalty_amount DECIMAL(10,2) DEFAULT 0.00,
        status         VARCHAR(20) DEFAULT 'Pending',
        notes          TEXT DEFAULT NULL,
        created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    "); }catch(Exception $e){}

    /* ── Payslips ───────────────────────────────────── */
    try{ db()->exec("
    CREATE TABLE IF NOT EXISTS eco_payslips (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        worker_id    INT NOT NULL,
        month        VARCHAR(30) NOT NULL,
        total_amount DECIMAL(10,2) DEFAULT 0.00,
        status       VARCHAR(20) DEFAULT 'Generated',
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        paid_at      DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    "); }catch(Exception $e){}

    try{ db()->exec("ALTER TABLE eco_payslips MODIFY COLUMN month VARCHAR(30) NOT NULL"); }catch(Exception $e){}

    try{ db()->exec("
    CREATE TABLE IF NOT EXISTS eco_payslip_items (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        payslip_id INT NOT NULL,
        task_id    INT NOT NULL,
        UNIQUE KEY uq_ps_item (payslip_id, task_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    "); }catch(Exception $e){}

    /* ── Worker Ledger ──────────────────────────────── */
    try{ db()->exec("
    CREATE TABLE IF NOT EXISTS eco_worker_ledger (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        worker_id        INT NOT NULL,
        type             VARCHAR(20) NOT NULL,
        amount           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        notes            VARCHAR(255) DEFAULT NULL,
        transaction_date DATE DEFAULT NULL,
        created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    "); }catch(Exception $e){}

    try{ db()->exec("ALTER TABLE eco_worker_ledger ADD COLUMN transaction_date DATE DEFAULT NULL"); }catch(Exception $e){}

    /* ── Permissions ────────────────────────────────── */
    try{ db()->exec("
    CREATE TABLE IF NOT EXISTS eco_permissions (
        role     VARCHAR(30) NOT NULL PRIMARY KEY,
        settings LONGTEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    "); }catch(Exception $e){}

    /* Default + forced permissions per role */
    $_rolePerms = [
        'ai_work'     => ['filters'=>['','Pending','AI Work','AI DONE','Generated','Approved','Updated','Working','Paused','In QA','Work Done','Info Done','Invoiced','Paid']],
        'd4u_writer'  => ['filters'=>['','Pending','Generated','Approved','Updated','Working','Paused','In QA','Work Done','Info Done','Invoiced','Paid']],
        'worker'      => ['filters'=>['','Working','Paused','In QA','Work Done','Info Done','Invoiced','Paid']],
        'qa'          => ['filters'=>['','In QA','SEO Review','Work Done','Info Done','Invoiced','Paid']],
        'eco_listing' => ['filters'=>['','Pending','Generated','Approved','Updated','Info Work','Content Pending','In QA','SEO Review','Work Done','Info Done','Working','Paused','Hold','Published','Invoiced','Paid','Republish','Changes','Changes in Content','Changing']],
        'seo_manager' => ['filters'=>['','SEO Review','Work Done','Info Done','Invoiced','Paid']],
        'eco_client'  => ['filters'=>['','Pending','Generated','Approved','Updated','Info Work','Content Pending','In QA','SEO Review','Work Done','Info Done','Working','Paused','Hold','Published','Invoiced','Paid','Republish','Changes','Changes in Content','Changing']],
    ];
    foreach($_rolePerms as $_r => $_s){
        try{
            $_enc = json_encode($_s);
            db()->prepare("INSERT INTO eco_permissions (role,settings) VALUES(?,?) ON DUPLICATE KEY UPDATE settings=?")
               ->execute([$_r, $_enc, $_enc]);
        }catch(Exception $e){}
    }

    try{ db()->exec("
    CREATE TABLE IF NOT EXISTS eco_module_permissions (
        role       VARCHAR(30) NOT NULL,
        module     VARCHAR(40) NOT NULL,
        can_view   TINYINT(1) NOT NULL DEFAULT 0,
        can_add    TINYINT(1) NOT NULL DEFAULT 0,
        can_edit   TINYINT(1) NOT NULL DEFAULT 0,
        can_delete TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (role, module)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    "); }catch(Exception $e){}

    try{ db()->exec("
    CREATE TABLE IF NOT EXISTS eco_user_permissions (
        user_id  INT NOT NULL PRIMARY KEY,
        settings LONGTEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    "); }catch(Exception $e){}

    db()->exec("
    CREATE TABLE IF NOT EXISTS eco_user_module_permissions (
        user_id    INT NOT NULL,
        module     VARCHAR(40) NOT NULL,
        can_view   TINYINT(1) NOT NULL DEFAULT 0,
        can_add    TINYINT(1) NOT NULL DEFAULT 0,
        can_edit   TINYINT(1) NOT NULL DEFAULT 0,
        can_delete TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (user_id, module)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    db()->exec("
    CREATE TABLE IF NOT EXISTS eco_seo_content (
        id                   INT AUTO_INCREMENT PRIMARY KEY,
        task_id              INT NOT NULL UNIQUE,
        banners              LONGTEXT DEFAULT NULL,
        infographics         LONGTEXT DEFAULT NULL,
        backend_search_terms TEXT DEFAULT NULL,
        seo_notes            TEXT DEFAULT NULL,
        updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES wp_eco_aplus_tasks(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    db()->exec("
    CREATE TABLE IF NOT EXISTS eco_expenses (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        title        VARCHAR(100) NOT NULL,
        amount       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        category     VARCHAR(50) NOT NULL,
        expense_date DATE NOT NULL,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    /* ── User Active Usage Seconds ── */
    try {
        db()->exec("
        CREATE TABLE IF NOT EXISTS eco_user_usage (
            user_id INT NOT NULL PRIMARY KEY,
            active_seconds INT NOT NULL DEFAULT 0,
            last_ping_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch(Exception $e){}

}

