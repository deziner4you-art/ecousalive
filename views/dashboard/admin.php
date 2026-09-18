<?php /* Admin dashboard — tab panels + main task list */ ?>

<!-- ── Products tab ── -->
<div id="productsPanel">
<?php require_once ROOT . '/views/tasks/index.php'; ?>
</div>

<!-- ── Admin (Users) tab ── -->
<div id="adminPanel" style="display:none;padding:16px;width:100%;">
    <div style="width:100%;max-width:none;margin:0;">

        <!-- Create user -->
        <div style="background:#0f2035;border:1px solid #1e3a5f;border-radius:10px;padding:18px;margin-bottom:20px;">
            <div style="font-size:13px;font-weight:700;color:#93c5fd;margin-bottom:12px;letter-spacing:.5px;">➕ CREATE USER</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <input id="nu" type="text"     placeholder="Username" style="flex:2;min-width:120px;padding:8px 10px;border:1px solid #334155;border-radius:6px;background:#0a1628;color:#e2e8f0;font-size:13px;outline:none;">
                <input id="np" type="password" placeholder="Password" style="flex:2;min-width:120px;padding:8px 10px;border:1px solid #334155;border-radius:6px;background:#0a1628;color:#e2e8f0;font-size:13px;outline:none;">
                <select id="nr" style="flex:2;min-width:140px;padding:8px 10px;border:1px solid #334155;border-radius:6px;background:#0a1628;color:#e2e8f0;font-size:13px;outline:none;">
                    <option value="">-- Role --</option>
                    <option value="worker">Worker</option>
                    <option value="eco_client">ECO Client</option>
                    <option value="qa">QA</option>
                    <option value="ai_work">AI Worker</option>
                    <option value="seo_manager">SEO Manager</option>
                    <option value="eco_listing">ECO Listing</option>
                    <option value="administrator">Administrator</option>
                </select>
                <button onclick="createUser()" class="adminbtn" style="flex-shrink:0;">Create</button>
            </div>
        </div>

        <!-- Import CSV -->
        <div style="background:#0f2035;border:1px solid #1e3a5f;border-radius:10px;padding:18px;margin-bottom:20px;">
            <div style="font-size:13px;font-weight:700;color:#93c5fd;margin-bottom:12px;letter-spacing:.5px;">📂 IMPORT CSV</div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <input id="csv" type="file" accept=".csv" style="color:#94a3b8;font-size:13px;">
                <button onclick="importCSV()" class="adminbtn" style="flex-shrink:0;">Import</button>
            </div>
        </div>

        <!-- Filter Permissions -->
        <div style="background:#0f2035;border:1px solid #1e3a5f;border-radius:10px;padding:18px;margin-bottom:20px;">
            <div style="font-size:13px;font-weight:700;color:#93c5fd;margin-bottom:4px;letter-spacing:.5px;">🔎 FILTER PERMISSIONS</div>
            <div style="font-size:11px;color:#94a3b8;margin-bottom:12px;">Which task status filters each role can see</div>
            <div id="perms-container"><div style="color:#94a3b8;font-size:13px;">Loading...</div></div>
        </div>

        <!-- Module Permissions (RBAC) -->
        <div style="background:#0f2035;border:1px solid #2563eb;border-radius:10px;padding:18px;margin-bottom:20px;">
            <div style="font-size:13px;font-weight:700;color:#60a5fa;margin-bottom:4px;letter-spacing:.5px;">🔐 MODULE PERMISSIONS</div>
            <div style="font-size:11px;color:#94a3b8;margin-bottom:14px;">View / Add / Edit / Delete — per role per module. Checkbox change karte hi auto-save hota hai.</div>
            <div id="module-perms-container"><div style="color:#94a3b8;font-size:13px;">Loading...</div></div>
        </div>



        <!-- Change password -->
        <div style="background:#0f2035;border:1px solid #1e3a5f;border-radius:10px;padding:18px;margin-bottom:20px;">
            <div style="font-size:13px;font-weight:700;color:#93c5fd;margin-bottom:12px;letter-spacing:.5px;">🔑 CHANGE MY PASSWORD</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <input id="cp-current" type="password" placeholder="Current Password" style="flex:1;min-width:140px;padding:8px 10px;border:1px solid #334155;border-radius:6px;background:#0a1628;color:#e2e8f0;font-size:13px;outline:none;">
                <input id="cp-new1"    type="password" placeholder="New Password"     style="flex:1;min-width:140px;padding:8px 10px;border:1px solid #334155;border-radius:6px;background:#0a1628;color:#e2e8f0;font-size:13px;outline:none;">
                <input id="cp-new2"    type="password" placeholder="Confirm New"      style="flex:1;min-width:140px;padding:8px 10px;border:1px solid #334155;border-radius:6px;background:#0a1628;color:#e2e8f0;font-size:13px;outline:none;">
                <button onclick="changeMyPassword()" class="adminbtn" style="flex-shrink:0;">Change</button>
            </div>
            <span id="cp-msg" style="font-size:12px;margin-top:6px;display:block;"></span>
        </div>

        <!-- Force logout all -->
        <div style="background:#0f2035;border:1px solid #1e3a5f;border-radius:10px;padding:18px;margin-bottom:20px;">
            <div style="font-size:13px;font-weight:700;color:#f87171;margin-bottom:8px;letter-spacing:.5px;">FORCE LOGOUT ALL</div>
            <div style="font-size:11px;color:#64748b;margin-bottom:12px;">Sab users ko dobara login karwana hai taake fresh analytics records ban sakein.</div>
            <button onclick="forceLogoutAll()" class="adminbtn" style="background:#dc2626;">Force Logout All Users</button>
        </div>

        <!-- User list -->
        <div style="background:#0f2035;border:1px solid #1e3a5f;border-radius:10px;padding:18px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:10px;">
                <div style="font-size:13px;font-weight:700;color:#93c5fd;letter-spacing:.5px;">👥 MANAGE USERS</div>
                <div>
                    <select id="user-role-filter" onchange="filterUsersByRole()" style="padding:6px 10px;border:1px solid #334155;border-radius:6px;background:#0a1628;color:#e2e8f0;font-size:12px;outline:none;">
                        <option value="">All Roles</option>
                        <option value="worker">Worker</option>
                        <option value="eco_client">ECO Client</option>
                        <option value="qa">QA</option>
                        <option value="ai_work">AI Worker</option>
                        <option value="seo_manager">SEO Manager</option>
                        <option value="eco_listing">ECO Listing</option>
                        <option value="administrator">Administrator</option>
                    </select>
                </div>
            </div>
            <div id="users"><div style="color:#94a3b8;font-size:13px;">Loading...</div></div>
        </div>

    </div>
</div>

<!-- ── Invoices tab ── -->
<!-- ── Dashboard/Analytics tab ── -->
<?php require_once ROOT . '/views/dashboard/analytics_shared.php'; ?>

<div id="invoicesPanel" style="display:none;padding:16px;">
<?php require_once ROOT . '/views/invoices/index.php'; ?>
</div>

<!-- ── Payroll tab ── -->
<div id="payrollPanel" style="display:none;padding:16px;">
<?php require_once ROOT . '/views/payroll/index.php'; ?>
</div>

<!-- ── Recycle Bin tab ── -->
<div id="recycleBinPanel" style="display:none;padding:16px;">
<?php require_once ROOT . '/views/tasks/recycle_bin.php'; ?>
</div>
