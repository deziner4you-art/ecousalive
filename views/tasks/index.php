<?php
/*
=====================================================
ECO A+ PRO — Task list view
Filter bar + Add Product card + task container.
$filterPerms — array of allowed filter values for
               the current role (from RoleMiddleware).
=====================================================
*/
$isAdmin   = $user['role'] === 'administrator';
$isWorker  = $user['role'] === 'worker';
$isClient  = $user['role'] === 'eco_client';
$isQA      = $user['role'] === 'qa';
$isWriter  = in_array($user['role'], ['d4u_writer', 'seo_manager']);
$isSEO     = $user['role'] === 'seo_manager';
$isListing = $user['role'] === 'eco_listing';

$userPerms = ModulePermission::getForUser($user);
$canAddProduct = $isAdmin || !empty($userPerms['products']['add']);

/* Build the full filter option list */
$allFilters = [
    ''              => 'All',
    'Pending'       => 'Pending',
    'AI Work'       => 'AI Work',
    'AI DONE'       => 'AI DONE',
    'Generated'     => 'Generated',
    'Approved'      => 'Approved',
    'Updated'       => 'Updated',
    'Working'       => 'Working',
    'Paused'        => 'Paused',
    'Info Work'     => 'Info Work',
    'Content Pending'=> 'Content Pending',
    'In QA'         => 'In QA',
    'SEO Review'    => 'SEO Review',
    'Work Done'     => 'Work Done',
    'Info Done'     => 'Info Done',
    'Work Done Only'=> '✅ Work Done Only (No Invoice)',
    'Changes'       => 'Changes in Design',
    'Changes in Content'=> 'Changes in Content',
    'Changing'      => 'Changing',
    'Hold'          => 'Hold',
    'Published'     => 'Published',
    'Invoiced'      => 'Invoiced',
    'Paid'          => 'Paid',
    'Info + A Plus' => 'Info + A Plus',
    'Infographics'  => 'Infographics',
    'A+'            => 'A+',
];

/* Allowed keys for this role */
$allowed = $filterPerms ?: array_keys($allFilters);
if(!in_array('Info Done', $allowed)){
    $allowed[] = 'Info Done';
}
?>

<!-- ── Filter bar ── -->
<div class="filter-bar" id="filter-bar">

    <!-- Search -->
    <input id="search" type="text" placeholder="🔍 Search product..." class="filter-input" style="flex:2;min-width:140px;">

    <!-- Status filter -->
    <select id="filter" class="filter-select hidden sm:block">
        <?php foreach($allFilters as $val => $label):
            if($val === '' || $val === 'Info Done' || in_array($val, $allowed)): ?>
        <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
        <?php endif; endforeach; ?>
    </select>

    <!-- Mobile Status Filters (Horizontal Pills) -->
    <div class="flex sm:hidden overflow-x-auto no-scrollbar gap-2 py-1 w-full" id="mobile-filter-pills">
        <?php foreach($allFilters as $val => $label):
            if($val === '' || $val === 'Info Done' || in_array($val, $allowed)): 
                $activeClass = ($val === '') ? 'bg-[#adc6ff] text-[#002e6a] border-[#adc6ff] shadow-[0_0_10px_rgba(173,198,255,0.2)]' : 'border-[#424754] text-[#c2c6d6]';
                $labelShort = ($val === '') ? 'All' : $label;
                if (strpos($labelShort, 'Only') !== false) continue;
            ?>
            <button type="button" class="mob-pill-btn px-4 py-1.5 rounded-full border text-xs font-semibold whitespace-nowrap active:scale-95 transition-transform flex items-center <?= $activeClass ?>" onclick="selectMobileFilter(this, '<?= htmlspecialchars($val) ?>')">
                <?= htmlspecialchars($labelShort) ?>
            </button>
        <?php endif; endforeach; ?>
    </div>

    <script>
    function selectMobileFilter(btn, value) {
        document.querySelectorAll('.mob-pill-btn').forEach(function(b) {
            b.className = 'mob-pill-btn px-4 py-1.5 rounded-full border text-xs font-semibold whitespace-nowrap active:scale-95 transition-transform flex items-center border-[#424754] text-[#c2c6d6]';
        });
        btn.className = 'mob-pill-btn px-4 py-1.5 rounded-full border text-xs font-semibold whitespace-nowrap active:scale-95 transition-transform flex items-center bg-[#adc6ff] text-[#002e6a] border-[#adc6ff] shadow-[0_0_10px_rgba(173,198,255,0.2)]';
        
        var filterSel = document.getElementById('filter');
        if (filterSel) {
            filterSel.value = value;
            filterSel.dispatchEvent(new Event('change'));
        }
    }
    </script>

    <!-- Sort -->
    <select id="sort" class="filter-select">
        <option value="activity_asc">Oldest Activity</option>
        <option value="activity_desc">Latest Activity</option>
        <option value="no_asc">Product # ↑</option>
        <option value="no_desc">Product # ↓</option>
        <option value="id_asc">ID ↑</option>
        <option value="id_desc">ID ↓</option>
        <option value="az">A → Z</option>
        <option value="za">Z → A</option>
    </select>

    <!-- Date filter -->
    <select id="dateFilter" class="filter-select">
        <option value="">All Dates</option>
        <option value="today">Today</option>
        <option value="yesterday">Yesterday</option>
        <option value="7days">Last 7 Days</option>
        <option value="custom">Custom Date</option>
    </select>
    <input id="customDate" type="date" style="display:none;padding:7px 10px;border:1px solid #334155;border-radius:6px;background:#0a1628;color:#e2e8f0;font-size:13px;outline:none;">

    <?php if($isAdmin || $isQA): ?>
    <!-- Worker filter (admin only) -->
    <select id="workerFilter" class="filter-select" style="min-width:130px;">
        <option value="">All Workers</option>
    </select>
    <?php else: ?>
    <!-- Hidden placeholder so JS doesn't break on getElementById -->
    <select id="workerFilter" style="display:none;"></select>
    <?php endif; ?>

    <?php if($canAddProduct): ?>
    <!-- Add product button -->
    <button id="addProductBtn" onclick="toggleAddProductCard()" class="adminbtn" style="background:#c2410c;flex-shrink:0;">+ Add Product</button>
    <?php endif; ?>

</div>

<?php if($canAddProduct): ?>
<!-- ── Add Product card ── -->
<div id="addProductCard" style="display:none;margin:10px 16px;padding:18px;background:#0f2035;border:1px solid #1e3a5f;border-radius:10px;">
    <div style="font-size:13px;font-weight:700;color:#93c5fd;margin-bottom:14px;letter-spacing:.5px;">➕ ADD NEW PRODUCT</div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
        <input id="ap-product-no" type="text"    placeholder="Product No *" style="flex:1;min-width:120px;padding:9px 12px;border:1px solid #334155;border-radius:6px;background:#0a1628;color:#e2e8f0;font-size:13px;outline:none;">
        <input id="ap-title"      type="text"    placeholder="Product Title *" style="flex:3;min-width:200px;padding:9px 12px;border:1px solid #334155;border-radius:6px;background:#0a1628;color:#e2e8f0;font-size:13px;outline:none;">
        <input id="ap-product-link" type="text"   placeholder="Product Link" style="flex:2;min-width:180px;padding:9px 12px;border:1px solid #334155;border-radius:6px;background:#0a1628;color:#e2e8f0;font-size:13px;outline:none;">
    </div>
    <div style="display:flex;gap:20px;margin-bottom:14px;flex-wrap:wrap;">
        <label style="display:flex;align-items:center;gap:7px;font-size:13px;color:#94a3b8;cursor:pointer;">
            <input type="checkbox" id="ap-aiwork" style="width:15px;height:15px;cursor:pointer;">
            AI Work
        </label>
        <label style="display:flex;align-items:center;gap:7px;font-size:13px;color:#94a3b8;cursor:pointer;">
            <input type="checkbox" id="ap-infographics" style="width:15px;height:15px;cursor:pointer;">
            Infographics subtask
        </label>
        <label style="display:flex;align-items:center;gap:7px;font-size:13px;color:#94a3b8;cursor:pointer;">
            <input type="checkbox" id="ap-aplus" style="width:15px;height:15px;cursor:pointer;">
            A+ Banners subtask
        </label>
    </div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <button onclick="saveAddProduct()" class="adminbtn" style="background:#16a34a;">✅ Add Product</button>
        <button onclick="closeAddProductCard()" class="adminbtn" style="background:#475569;">Cancel</button>
        <span id="ap-msg" style="font-size:13px;"></span>
    </div>
</div>
<?php endif; ?>

<!-- ── Bulk Action Bar ── -->
<div id="bulk-action-bar" style="display:none; background:#1e293b; border:1px solid #334155; border-radius:12px; padding:12px 18px; margin: 10px 12px 14px; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);">
    <div style="display:flex; align-items:center; gap:15px; flex-wrap:wrap;">
        <span style="color:#f1f5f9; font-size:14px; font-weight:bold; display:inline-flex; align-items:center; gap:8px;">
            <span style="background:#c2410c; color:#fff; border-radius:50%; width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center; font-size:12px;" id="bulk-selected-count">0</span>
            Products Selected
        </span>
        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; user-select:none; color:#94a3b8; font-size:13px;">
            <input type="checkbox" id="bulk-select-all" onclick="toggleSelectAllBulk(this.checked)" style="width:16px; height:16px; cursor:pointer; accent-color:#c2410c;">
            Select All on Page
        </label>
        <button onclick="clearBulkSelection()" style="background:transparent; border:1px solid #475569; color:#94a3b8; padding:6px 12px; border-radius:6px; font-size:12px; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.borderColor='#94a3b8'; this.style.color='#f1f5f9';" onmouseout="this.style.borderColor='#475569'; this.style.color='#94a3b8';">Clear Selection</button>
    </div>
    <div style="display:flex; align-items:center; gap:10px;">
        <select id="bulk-action-select" style="background:#0f172a; border:1px solid #334155; border-radius:6px; color:#e2e8f0; padding:8px 12px; font-size:13px; outline:none; cursor:pointer; min-width:165px;">
            <option value="">⚡ Bulk Action...</option>
            <option value="urgent">Mark as Urgent</option>
            <option value="hold">Mark as Hold</option>
        </select>
        <button onclick="applyBulkAction()" style="background:#c2410c; color:#fff; border:none; padding:8px 20px; border-radius:6px; font-size:13px; font-weight:bold; cursor:pointer; transition:background-color 0.2s;" onmouseover="this.style.backgroundColor='#ea580c';" onmouseout="this.style.backgroundColor='#c2410c';">Apply</button>
    </div>
</div>

<!-- ── Task list ── -->
<div id="tasks" style="padding:10px 12px;"></div>

<!-- ── Pagination ── -->
<div id="pager" style="display:flex;align-items:center;justify-content:center;gap:12px;padding:16px;font-size:13px;color:#94a3b8;flex-wrap:wrap;"></div>

<?php if($isAdmin): ?>
<!-- ── Edit Services & Workers Modal ── -->
<div id="editServicesModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.75);z-index:99999;align-items:center;justify-content:center;backdrop-filter:blur(3px);">
    <div style="background:#0f172a;border:1px solid #1e3a5f;border-radius:12px;width:95%;max-width:560px;padding:24px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.5);color:#f1f5f9;position:relative;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid #1e3a5f;padding-bottom:12px;">
            <div style="font-size:15px;font-weight:700;color:#38bdf8;" id="es-modal-title">✏️ Edit Services & Worker Assignments</div>
            <button onclick="closeEditServices()" style="background:transparent;border:none;color:#94a3b8;font-size:24px;cursor:pointer;line-height:1;">&times;</button>
        </div>
        <input type="hidden" id="es-task-id">
        
        <!-- Services Checkboxes -->
        <div style="margin-bottom:16px;background:#09111e;padding:12px 14px;border-radius:8px;border:1px solid #1e293b;">
            <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;">Active Services On Product</div>
            <div style="display:flex;gap:18px;flex-wrap:wrap;">
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;color:#e2e8f0;">
                    <input type="checkbox" id="es-chk-ai" style="width:16px;height:16px;accent-color:#9333ea;">
                    🤖 AI Work
                </label>
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;color:#e2e8f0;">
                    <input type="checkbox" id="es-chk-info" style="width:16px;height:16px;accent-color:#0284c7;">
                    🎨 Infographics
                </label>
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;color:#e2e8f0;">
                    <input type="checkbox" id="es-chk-aplus" style="width:16px;height:16px;accent-color:#7c3aed;">
                    🏷 A+ Banners
                </label>
            </div>
        </div>

        <!-- Worker Assignments -->
        <div style="margin-bottom:16px;background:#09111e;padding:12px 14px;border-radius:8px;border:1px solid #1e293b;">
            <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;">Worker Record / Service Credit</div>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                    <span style="font-size:12px;color:#e9d5ff;width:140px;">🤖 AI Worker:</span>
                    <select id="es-sel-ai" style="flex:1;background:#0f172a;border:1px solid #334155;color:#e2e8f0;padding:6px 10px;border-radius:6px;font-size:12px;outline:none;">
                        <option value="">(None / Unassigned)</option>
                    </select>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                    <span style="font-size:12px;color:#bae6fd;width:140px;">🎨 Info Worker:</span>
                    <select id="es-sel-info" style="flex:1;background:#0f172a;border:1px solid #334155;color:#e2e8f0;padding:6px 10px;border-radius:6px;font-size:12px;outline:none;">
                        <option value="">(None / Unassigned)</option>
                    </select>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                    <span style="font-size:12px;color:#ddd6fe;width:140px;">🏷 A+ Worker:</span>
                    <select id="es-sel-aplus" style="flex:1;background:#0f172a;border:1px solid #334155;color:#e2e8f0;padding:6px 10px;border-radius:6px;font-size:12px;outline:none;">
                        <option value="">(None / Unassigned)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Active Assignment Selection -->
        <div style="margin-bottom:20px;background:#09111e;padding:12px 14px;border-radius:8px;border:1px solid #1e293b;">
            <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">Active Stage / Re-assign To:</div>
            <select id="es-sel-active" style="width:100%;background:#0f172a;border:1px solid #2563eb;color:#bfdbfe;padding:8px 10px;border-radius:6px;font-size:12px;outline:none;font-weight:bold;">
                <option value="unchanged">Keep Current Assignment</option>
                <option value="ai">Assign to AI Worker Now</option>
                <option value="info">Assign to Infographics Worker Now</option>
                <option value="aplus">Assign to A+ Banners Worker Now</option>
                <option value="start_aplus_content">Start A+ Content Flow (Pending for D4U Writer)</option>
                <option value="none">Unassign (Clear Active Assignment)</option>
            </select>
            <div style="font-size:11px;color:#64748b;margin-top:6px;line-height:1.4;">Har worker ki service ka record permanently maintain rahay ga taa k un ka payroll sahi se ban sakay.</div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:10px;">
            <button type="button" onclick="closeEditServices()" style="background:#334155;color:#cbd5e1;border:none;padding:8px 16px;border-radius:6px;font-size:13px;cursor:pointer;font-weight:600;">Cancel</button>
            <button type="button" onclick="saveEditServices()" id="es-btn-save" style="background:#0284c7;color:#fff;border:none;padding:8px 20px;border-radius:6px;font-size:13px;cursor:pointer;font-weight:bold;">Save Changes</button>
        </div>
    </div>
</div>
<?php endif; ?>
