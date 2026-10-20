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
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN no_charge_prev_status TEXT DEFAULT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN no_charge_reason TEXT DEFAULT NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN no_charge_at DATETIME NULL",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN no_charge_by INT NULL",
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
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN revision_count INT NOT NULL DEFAULT 0",
        "ALTER TABLE wp_eco_aplus_tasks ADD COLUMN revision_log LONGTEXT DEFAULT NULL",
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
        // Clear any AI users erroneously set as info_worker_id or aplus_worker_id
        db()->exec("UPDATE wp_eco_aplus_tasks t JOIN eco_tool_users u ON u.id = t.info_worker_id SET t.info_worker_id = NULL WHERE u.role = 'ai_work'");
        db()->exec("UPDATE wp_eco_aplus_tasks t JOIN eco_tool_users u ON u.id = t.aplus_worker_id SET t.aplus_worker_id = NULL WHERE u.role = 'ai_work'");

        // Populate info_worker_id and aplus_worker_id from completed workers or active assignments (human workers only)
        db()->exec("UPDATE wp_eco_aplus_tasks t JOIN eco_tool_users u ON u.id = t.work_completed_by_worker_id SET t.info_worker_id = t.work_completed_by_worker_id WHERE (t.info_worker_id IS NULL OR t.info_worker_id = 0) AND (t.product_type = 'Infographics' OR t.product_type = 'Info + A Plus' OR t.info_subtasks LIKE '%Infographics%') AND u.role = 'worker'");
        db()->exec("UPDATE wp_eco_aplus_tasks t JOIN eco_tool_assignments a ON a.task_id = t.id JOIN eco_tool_users u ON u.id = a.worker_id SET t.info_worker_id = a.worker_id WHERE (t.info_worker_id IS NULL OR t.info_worker_id = 0) AND (t.product_type = 'Infographics' OR t.product_type = 'Info + A Plus' OR t.info_subtasks LIKE '%Infographics%') AND u.role = 'worker'");
        db()->exec("UPDATE wp_eco_aplus_tasks t JOIN eco_tool_users u ON u.id = t.work_completed_by_worker_id SET t.aplus_worker_id = t.work_completed_by_worker_id WHERE (t.aplus_worker_id IS NULL OR t.aplus_worker_id = 0) AND t.product_type = 'A+' AND u.role = 'worker'");
        
        // Auto-heal tasks that have both Infographics and A+ Banners assigned to 'Info + A Plus' tag
        db()->exec("UPDATE wp_eco_aplus_tasks
            SET product_type = 'Info + A Plus',
                info_subtasks = CASE 
                    WHEN info_subtasks IS NULL OR info_subtasks = '' THEN 'Infographics, A+ Banners'
                    WHEN info_subtasks NOT LIKE '%A+ Banners%' THEN CONCAT(info_subtasks, ', A+ Banners')
                    ELSE info_subtasks
                END,
                info_invoice_status = COALESCE(info_invoice_status, invoice_status)
            WHERE deleted_at IS NULL
              AND aplus_worker_id IS NOT NULL AND aplus_worker_id > 0
              AND (info_worker_id IS NOT NULL AND info_worker_id > 0 OR product_type = 'Infographics' OR info_subtasks LIKE '%Infographics%')
              AND product_type != 'Info + A Plus'");

        db()->exec("UPDATE wp_eco_aplus_tasks
            SET info_invoice_status = invoice_status
            WHERE deleted_at IS NULL
              AND product_type = 'Info + A Plus'
              AND info_invoice_status IS NULL
              AND invoice_status IS NOT NULL");
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

    /* ── Permanent service-charge ledger ─────────────────
       A service may be invoiced only once per product. This ledger is
       intentionally retained even if an invoice is later removed, so a
       mistaken old invoice can never make the same service chargeable again.
    */
    try{ db()->exec("CREATE TABLE IF NOT EXISTS eco_task_service_charges (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        task_id       INT NOT NULL,
        service_type  VARCHAR(20) NOT NULL,
        invoice_id    INT NULL,
        invoice_no    VARCHAR(40) NOT NULL,
        amount        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        invoice_status VARCHAR(20) NOT NULL DEFAULT 'Invoiced',
        charged_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        paid_at       DATETIME NULL,
        UNIQUE KEY uq_task_service (task_id, service_type),
        KEY idx_service_invoice (invoice_id),
        KEY idx_service_task (task_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); }catch(Exception $e){}

    /* ── Service-level workflow state ─────────────────────────────
       One product may contain multiple services.  These tables keep each
       service's content, work, QA and completion state independent.  The
       legacy task columns remain in place for old reports and rollback safety.
    */
    try{ db()->exec("CREATE TABLE IF NOT EXISTS eco_task_services (
        id                 INT AUTO_INCREMENT PRIMARY KEY,
        task_id            INT NOT NULL,
        service_code       VARCHAR(40) NOT NULL,
        service_order      INT NOT NULL DEFAULT 1,
        phase_status       VARCHAR(40) NOT NULL DEFAULT 'Pending',
        content_status     VARCHAR(40) NOT NULL DEFAULT 'Pending',
        worker_id          INT NULL,
        media_link         TEXT NULL,
        document_link      TEXT NULL,
        qa_submitted_by    INT NULL,
        qa_submitted_at    DATETIME NULL,
        work_started_at    DATETIME NULL,
        work_paused_at     DATETIME NULL,
        work_completed_at  DATETIME NULL,
        work_total_seconds INT NOT NULL DEFAULT 0,
        completed_by       INT NULL,
        completed_at       DATETIME NULL,
        created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_task_service (task_id, service_code),
        KEY idx_service_phase (service_code, phase_status),
        KEY idx_service_worker (worker_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); }catch(Exception $e){}

    try{ db()->exec("CREATE TABLE IF NOT EXISTS eco_task_service_events (
        id            BIGINT AUTO_INCREMENT PRIMARY KEY,
        task_id       INT NOT NULL,
        service_code  VARCHAR(40) NOT NULL,
        event_type    VARCHAR(60) NOT NULL,
        from_status   VARCHAR(40) NULL,
        to_status     VARCHAR(40) NULL,
        actor_id      INT NULL,
        notes         TEXT NULL,
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_task_service_event (task_id, service_code, id),
        KEY idx_service_event_type (service_code, event_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); }catch(Exception $e){}

    /* AI Work is also a first-class service so QA revisions can return the
       task to the same AI worker without confusing it with Info/A+ work. */
    try{ db()->exec("INSERT IGNORE INTO eco_task_services
        (task_id, service_code, service_order, phase_status, content_status, worker_id,
         qa_submitted_by, qa_submitted_at, work_started_at, work_paused_at,
         work_completed_at, work_total_seconds, completed_by, completed_at)
        SELECT t.id, 'ai', 0,
               CASE
                   WHEN t.work_status='AI In QA' THEN 'In QA'
                   WHEN t.status='AI DONE' THEN 'Done'
                   WHEN t.status='AI Work' AND t.work_status IN ('Working','Changing') THEN 'Working'
                   WHEN t.status='AI Work' AND t.work_status='Paused' THEN 'Paused'
                   ELSE 'Pending'
               END,
               CASE WHEN t.status='AI DONE' THEN 'Done' ELSE 'Pending' END,
               t.ai_worked_by, t.qa_submitted_by, t.qa_submitted_at,
               t.work_started_at, t.work_paused_at, t.work_completed_at, t.work_total_seconds,
               t.ai_worked_by,
               CASE WHEN t.status='AI DONE' THEN COALESCE(t.work_completed_at,NOW()) ELSE NULL END
        FROM wp_eco_aplus_tasks t
        WHERE t.deleted_at IS NULL AND (t.ai_worked_by IS NOT NULL OR t.status IN ('AI Work','AI DONE'))"); }catch(Exception $e){}

    /* Backfill the separate Info service from existing task data. */
    try{ db()->exec("INSERT IGNORE INTO eco_task_services
        (task_id, service_code, service_order, phase_status, content_status, worker_id,
         media_link, qa_submitted_by, qa_submitted_at, work_started_at, work_paused_at,
         work_completed_at, work_total_seconds, completed_by, completed_at)
        SELECT t.id, 'info', 1,
               CASE
                   WHEN t.work_status='Info Done' OR (t.work_status='Work Done' AND t.product_type IN ('Infographics','Info + A Plus')) THEN 'Done'
                   WHEN t.work_status='In QA' THEN 'In QA'
                   WHEN t.work_status='Paused' THEN 'Paused'
                   WHEN t.work_status IN ('Working','Info Work','Changing') THEN 'Working'
                   ELSE 'Pending'
               END,
               CASE WHEN t.status IN ('Generated','All Generated','Approved','Updated') THEN t.status ELSE 'Pending' END,
               t.info_worker_id, t.media_link, t.qa_submitted_by, t.qa_submitted_at,
               t.work_started_at, t.work_paused_at, t.work_completed_at, t.work_total_seconds,
               t.work_completed_by_worker_id,
               CASE WHEN t.work_status IN ('Info Done','Work Done') THEN COALESCE(t.work_completed_at,NOW()) ELSE NULL END
        FROM wp_eco_aplus_tasks t
        WHERE t.deleted_at IS NULL AND t.product_type IN ('Infographics','Info + A Plus')"); }catch(Exception $e){}

    /* Backfill A+ only where the legacy row proves that A+ was started. */
    try{ db()->exec("INSERT IGNORE INTO eco_task_services
        (task_id, service_code, service_order, phase_status, content_status, worker_id,
         media_link, document_link, qa_submitted_by, qa_submitted_at, work_started_at,
         work_paused_at, work_completed_at, work_total_seconds, completed_by, completed_at)
        SELECT t.id, 'aplus', 2,
               CASE
                   WHEN t.work_status='Work Done' AND t.seo_doc_link IS NOT NULL AND t.seo_doc_link<>'' THEN 'Done'
                   WHEN t.work_status='SEO Review' THEN 'SEO Review'
                   WHEN t.work_status='In QA' THEN 'In QA'
                   WHEN t.work_status='Paused' THEN 'Paused'
                   WHEN t.work_status IN ('Working','Changing') THEN 'Working'
                   ELSE 'Pending'
               END,
               CASE WHEN t.status IN ('Generated','All Generated','Approved','Updated') THEN t.status ELSE 'Pending' END,
               t.aplus_worker_id, t.media_link, t.seo_doc_link, t.qa_submitted_by, t.qa_submitted_at,
               t.work_started_at, t.work_paused_at, t.work_completed_at, t.work_total_seconds,
               t.work_completed_by_worker_id,
               CASE WHEN t.work_status='Work Done' AND t.seo_doc_link IS NOT NULL AND t.seo_doc_link<>'' THEN COALESCE(t.work_completed_at,NOW()) ELSE NULL END
        FROM wp_eco_aplus_tasks t
        WHERE t.deleted_at IS NULL
          AND (t.product_type IN ('A+','A Plus') OR t.status='A Plus' OR t.aplus_worker_id IS NOT NULL
               OR t.work_status='SEO Review' OR (t.work_status='Work Done' AND t.seo_doc_link IS NOT NULL AND t.seo_doc_link<>''))"); }catch(Exception $e){}

    /* Backfill the ledger from invoices that already exist. Existing invoice
       history is preserved; INSERT IGNORE keeps this safe on every page load. */
    try{ db()->exec("INSERT IGNORE INTO eco_task_service_charges
        (task_id, service_type, invoice_id, invoice_no, amount, invoice_status, charged_at, paid_at)
        SELECT ii.task_id, 'info', ii.invoice_id, i.invoice_no, ii.info_price,
               i.status, COALESCE(i.created_at, NOW()), i.paid_at
        FROM eco_invoice_items ii
        JOIN eco_invoices i ON i.id = ii.invoice_id
        WHERE ii.info_price > 0
        UNION ALL
        SELECT ii.task_id, 'aplus', ii.invoice_id, i.invoice_no, ii.aplus_price,
               i.status, COALESCE(i.created_at, NOW()), i.paid_at
        FROM eco_invoice_items ii
        JOIN eco_invoices i ON i.id = ii.invoice_id
        WHERE ii.aplus_price > 0
        UNION ALL
        SELECT ii.task_id,
               CASE
                   WHEN t.product_type = 'Infographics' THEN 'info'
                   WHEN t.product_type IN ('A+', 'A Plus') THEN 'aplus'
                   ELSE 'info'
               END,
               ii.invoice_id, i.invoice_no, ii.item_price,
               i.status, COALESCE(i.created_at, NOW()), i.paid_at
        FROM eco_invoice_items ii
        JOIN eco_invoices i ON i.id = ii.invoice_id
        LEFT JOIN wp_eco_aplus_tasks t ON t.id = ii.task_id
        WHERE (ii.info_price IS NULL OR ii.info_price = 0)
          AND (ii.aplus_price IS NULL OR ii.aplus_price = 0)
          AND ii.item_price > 0"); }catch(Exception $e){}

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
    try{ db()->exec("ALTER TABLE eco_worker_rates ADD COLUMN rate_ai_work      DECIMAL(10,2) DEFAULT 0.00"); }catch(Exception $e){}

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
        can_rename TINYINT(1) NOT NULL DEFAULT 0,
        can_group  TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (role, module)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    "); }catch(Exception $e){}
    try{ db()->exec("ALTER TABLE eco_module_permissions ADD COLUMN can_rename TINYINT(1) NOT NULL DEFAULT 0"); }catch(Exception $e){}
    try{ db()->exec("ALTER TABLE eco_module_permissions ADD COLUMN can_group TINYINT(1) NOT NULL DEFAULT 0"); }catch(Exception $e){}

    try{ db()->exec("
    CREATE TABLE IF NOT EXISTS eco_user_permissions (
        user_id  INT NOT NULL PRIMARY KEY,
        settings LONGTEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    "); }catch(Exception $e){}

    try{ db()->exec("
    CREATE TABLE IF NOT EXISTS eco_user_module_permissions (
        user_id    INT NOT NULL,
        module     VARCHAR(40) NOT NULL,
        can_view   TINYINT(1) NOT NULL DEFAULT 0,
        can_add    TINYINT(1) NOT NULL DEFAULT 0,
        can_edit   TINYINT(1) NOT NULL DEFAULT 0,
        can_delete TINYINT(1) NOT NULL DEFAULT 0,
        can_rename TINYINT(1) NOT NULL DEFAULT 0,
        can_group  TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (user_id, module)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    "); }catch(Exception $e){}
    try{ db()->exec("ALTER TABLE eco_user_module_permissions ADD COLUMN can_rename TINYINT(1) NOT NULL DEFAULT 0"); }catch(Exception $e){}
    try{ db()->exec("ALTER TABLE eco_user_module_permissions ADD COLUMN can_group TINYINT(1) NOT NULL DEFAULT 0"); }catch(Exception $e){}

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

    /* ── Vendor System ── */
    try {
        require_once ROOT . '/app/Models/Vendor.php';
        Vendor::ensureTable();
    } catch(Exception $e){}

    /* ── Payslips Columns ── */
    $_psCols = [
        "ALTER TABLE eco_payslips ADD COLUMN prev_d4u DECIMAL(10,2) DEFAULT 0.00",
        "ALTER TABLE eco_payslips ADD COLUMN adv_d4u DECIMAL(10,2) DEFAULT 0.00",
        "ALTER TABLE eco_payslips ADD COLUMN loan_d4u DECIMAL(10,2) DEFAULT 0.00",
        "ALTER TABLE eco_payslips ADD COLUMN penalty_deducted DECIMAL(10,2) DEFAULT 0.00",
        "ALTER TABLE eco_payslips ADD COLUMN total_paid DECIMAL(10,2) DEFAULT 0.00",
    ];
    foreach($_psCols as $_sql){
        try{ db()->exec($_sql); }catch(Exception $e){}
    }
}
