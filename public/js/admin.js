/*
=====================================================
ECO A+ PRO — admin.js
User management, permissions, login analytics.
Admin-only functions — guards check ROLE internally.
Depends on: core.js
=====================================================
*/

/* ── User management ─────────────────────────── */
var _USER_PERMS_OPEN = {};

function createUser(){
    var fd = new FormData();
    fd.append('action',   'create_user');
    fd.append('username', document.getElementById('nu').value);
    fd.append('password', document.getElementById('np').value);
    fd.append('role',     document.getElementById('nr').value);
    fetch('index.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(r => { alert('User Created'); loadUsers(); });
}

function updateUser(id){
    var username = document.getElementById('un-' + id).value.trim();
    var role     = document.getElementById('ur-' + id).value;
    var password = document.getElementById('up-' + id).value;
    if(!username){ alert('Username required'); return; }
    var fd = new FormData();
    fd.append('action',   'update_user');
    fd.append('id',       id);
    fd.append('username', username);
    fd.append('role',     role);
    fd.append('password', password);
    fetch('index.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(r => {
            if(r.success){
                document.getElementById('up-' + id).value = '';
                loadUsers();
            } else {
                alert(r.message || 'Error');
            }
        });
}

function deleteUser(id){
    _confirm('Delete User?', function(){
        var fd = new FormData();
        fd.append('action', 'delete_user'); fd.append('id', id);
        fetch('index.php', {method:'POST', body:fd})
            .then(r => r.json())
            .then(() => loadUsers());
    });
}

function importCSV(){
    var file = document.getElementById('csv').files[0];
    if(!file){ alert('Select CSV'); return; }
    var fd = new FormData();
    fd.append('action', 'import_csv'); fd.append('csv', file);
    fetch('index.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(() => { alert('CSV Imported'); loadTasks(); });
}

/* ── Permissions dashboard ───────────────────── */
var ALL_FILTER_OPTS = [
    {val:'',          label:'All'},
    {val:'Pending',   label:'Pending'},
    {val:'AI Work',   label:'AI Work'},
    {val:'AI DONE',   label:'AI DONE'},
    {val:'Generated', label:'Generated'},
    {val:'Approved',  label:'Approved'},
    {val:'Updated',   label:'Updated'},
    {val:'Working',   label:'Working'},
    {val:'Paused',    label:'Paused'},
    {val:'Info Work', label:'Info Work'},
    {val:'Content Pending', label:'Content Pending'},
    {val:'In QA',     label:'In QA'},
    {val:'SEO Review', label:'SEO Review'},
    {val:'Work Done', label:'Work Done'},
    {val:'Info Done', label:'Info Done'},
    {val:'Changes',   label:'Changes in Design'},
    {val:'Changes in Content', label:'Changes in Content'},
    {val:'Changing',  label:'Changing'},
    {val:'Hold',      label:'Hold'},
    {val:'Published', label:'Published'},
    {val:'Invoiced',  label:'Invoiced'},
    {val:'Paid',      label:'Paid'},
    {val:'Info + A Plus', label:'Info + A Plus'}
];
var ROLE_LABELS_MAP = {
    'd4u_writer':'D4U Writer','eco_client':'ECO Client','ai_work':'AI Worker',
    'worker':'Worker','qa':'QA','eco_listing':'ECO Listing','seo_manager':'SEO Manager'
};
var PERM_ROLES   = ['eco_client','worker','qa','eco_listing','seo_manager','ai_work'];
var CURRENT_PERMS = {};

function roleToLabel(role){
    return ROLE_LABELS_MAP[role] || role.split('_').map(function(w){ return w.charAt(0).toUpperCase() + w.slice(1); }).join(' ');
}

function loadPermissions(){
    fetch('index.php?action=get_permissions')
        .then(r => r.json())
        .then(r => {
            if(r.success){
                CURRENT_PERMS = r.data;
                if(r.roles && r.roles.length) PERM_ROLES = r.roles;
                renderPermsUI();
            }
        });
}

function renderPermsUI(){
    var box = document.getElementById('perms-container');
    if(!box) return;
    var html = '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:12px;"><thead><tr>';
    html += '<th style="padding:8px 10px;color:#94a3b8;text-align:left;border-bottom:1px solid #1e3a5f;white-space:nowrap;">Role</th>';
    ALL_FILTER_OPTS.forEach(function(f){
        html += '<th style="padding:6px 4px;color:#94a3b8;text-align:center;border-bottom:1px solid #1e3a5f;font-size:10px;white-space:nowrap;">' + f.label + '</th>';
    });
    html += '</tr></thead><tbody>';
    PERM_ROLES.forEach(function(role){
        var filters = (CURRENT_PERMS[role] || {}).filters || [];
        html += '<tr><td style="padding:8px 10px;color:#e2e8f0;font-weight:bold;border-bottom:1px solid #1e3a5f;white-space:nowrap;">' + roleToLabel(role) + '</td>';
        ALL_FILTER_OPTS.forEach(function(f){
            var chk = filters.indexOf(f.val) !== -1 ? 'checked' : '';
            html += '<td style="padding:6px 4px;text-align:center;border-bottom:1px solid #1e3a5f;"><input type="checkbox" class="perm-chk" data-role="' + role + '" data-filter="' + f.val + '" ' + chk + '></td>';
        });
        html += '</tr>';
    });
    html += '</tbody></table></div>';
    html += '<div style="margin-top:14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">';
    html += '<button class="adminbtn" onclick="saveAllPerms()">💾 Save Permissions</button>';
    html += '<span id="perms-msg" style="font-size:13px;"></span></div>';
    box.innerHTML = html;
}

function saveAllPerms(){
    var promises = PERM_ROLES.map(function(role){
        var filters = [];
        document.querySelectorAll('.perm-chk[data-role="' + role + '"]').forEach(function(chk){
            if(chk.checked) filters.push(chk.getAttribute('data-filter'));
        });
        var fd = new FormData();
        fd.append('action', 'save_permissions');
        fd.append('role', role);
        fd.append('settings', JSON.stringify({filters: filters}));
        return fetch('index.php', {method:'POST', body:fd}).then(r => r.json());
    });
    Promise.all(promises).then(function(results){
        var ok  = results.every(function(r){ return r.success; });
        var msg = document.getElementById('perms-msg');
        if(msg){
            msg.textContent = ok ? '✅ Permissions saved!' : '❌ Some errors occurred';
            msg.style.color = ok ? '#4ade80' : '#f87171';
            setTimeout(function(){ msg.textContent = ''; }, 2500);
        }
    });
}

/* ── Login analytics ─────────────────────────── */
var ALL_LOGIN_LOGS = [];
var ANALYTICS_SELF_LOGGED = false;

var ROLE_COLORS = {
    administrator:'#7c3aed', eco_client:'#0e7490', eco_listing:'#15803d',
    worker:'#ea580c', qa:'#b45309', d4u_writer:'#2563eb', ai_work:'#8b5cf6'
};

var COUNTRY_FLAGS = {
    'United States':'🇺🇸','Pakistan':'🇵🇰','United Kingdom':'🇬🇧','Canada':'🇨🇦',
    'Australia':'🇦🇺','Germany':'🇩🇪','France':'🇫🇷','India':'🇮🇳','UAE':'🇦🇪',
    'Saudi Arabia':'🇸🇦','Netherlands':'🇳🇱','Singapore':'🇸🇬','Bangladesh':'🇧🇩'
};

function getFlag(country){ return COUNTRY_FLAGS[country] || '🌐'; }

function parseUA(ua){
    if(!ua) return 'Unknown';
    if(/iPhone|iPad/.test(ua)) return '📱 iOS';
    if(/Android/.test(ua))     return '📱 Android';
    if(/Windows/.test(ua))     return '🖥 Windows';
    if(/Mac/.test(ua))         return '🖥 Mac';
    if(/Linux/.test(ua))       return '🖥 Linux';
    return '💻 Other';
}

function loadLoginLogs(){
    if(ROLE !== 'administrator') return;
    if(!ANALYTICS_SELF_LOGGED){
        ANALYTICS_SELF_LOGGED = true;
        var fd = new FormData();
        fd.append('action', 'log_current_session');
        fetch('index.php', {method:'POST', body:fd})
            .then(function(){ loadLoginLogs(); })
            .catch(function(){ loadLoginLogs(); });
        return;
    }
    fetch('index.php?action=get_login_logs&limit=500')
        .then(r => r.json())
        .then(r => {
            if(r.success){
                ALL_LOGIN_LOGS = r.data;
                renderLoginLogs();
            }else{
                var box = document.getElementById('analytics-container');
                if(box) box.innerHTML = '<div style="color:#f87171;font-size:13px;">Analytics load nahi ho saki.</div>';
            }
        })
        .catch(function(){
            var box = document.getElementById('analytics-container');
            if(box) box.innerHTML = '<div style="color:#f87171;font-size:13px;">Analytics load nahi ho saki. Please refresh karein.</div>';
        });
}

function toggleAnalyticsCustomDate(){
    var select = document.getElementById('analytics-date-filter');
    var custom = document.getElementById('analytics-custom-date');
    if(select && custom){
        custom.style.display = select.value === 'custom' ? 'block' : 'none';
        if(select.value === 'custom') custom.value = '';
    }
}

function renderLoginLogs(){
    var roleF         = (document.getElementById('analytics-role-filter') || {value:''}).value;
    var dateF         = (document.getElementById('analytics-date-filter') || {value:''}).value;
    var customDateVal = (document.getElementById('analytics-custom-date') || {value:''}).value;
    var search        = ((document.getElementById('analytics-search') || {}).value || '').toLowerCase();

    var filtered = ALL_LOGIN_LOGS.filter(function(l){
        if(roleF && l.role !== roleF) return false;
        if(search){
            var hay = (l.username + ' ' + (l.country||'') + ' ' + (l.city||'') + ' ' + (l.region||'')).toLowerCase();
            if(hay.indexOf(search) === -1) return false;
        }

        if(dateF){
            var logDateStr = l.created_at ? l.created_at.substring(0, 10) : '';
            var now = new Date();
            if(dateF === 'today'){
                var todayStr = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
                if(logDateStr !== todayStr) return false;
            } else if(dateF === 'yesterday'){
                var yes = new Date();
                yes.setDate(yes.getDate() - 1);
                var yesterdayStr = yes.getFullYear() + '-' + String(yes.getMonth() + 1).padStart(2, '0') + '-' + String(yes.getDate()).padStart(2, '0');
                if(logDateStr !== yesterdayStr) return false;
            } else if(dateF === '7days'){
                var cutoff = new Date();
                cutoff.setDate(cutoff.getDate() - 7);
                var cutoffStr = cutoff.getFullYear() + '-' + String(cutoff.getMonth() + 1).padStart(2, '0') + '-' + String(cutoff.getDate()).padStart(2, '0');
                if(logDateStr < cutoffStr) return false;
            } else if(dateF === 'custom' && customDateVal){
                if(logDateStr !== customDateVal) return false;
            }
        }

        return true;
    });

    var countEl = document.getElementById('analytics-count');
    if(countEl) countEl.textContent = filtered.length + ' records';

    var box = document.getElementById('analytics-container');
    if(!box) return;
    if(!ALL_LOGIN_LOGS.length){
        box.innerHTML = '<div style="color:#94a3b8;font-size:13px;">Abhi koi login record nahi mila. Is update ke baad users login karenge to yahan location aur device logs aayenge.</div>';
        return;
    }
    if(!filtered.length){
        box.innerHTML = '<div style="color:#94a3b8;font-size:13px;">Is filter/search mein koi record nahi mila. All Roles select karke dekhein.</div>';
        return;
    }

    var countryCounts = {};
    filtered.forEach(function(l){ var c = l.country || 'Unknown'; countryCounts[c] = (countryCounts[c]||0)+1; });
    var sortedCountries = Object.keys(countryCounts).sort(function(a,b){ return countryCounts[b]-countryCounts[a]; });
    var summaryHtml = '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">';
    sortedCountries.slice(0, 8).forEach(function(c){
        summaryHtml += '<div style="padding:5px 12px;background:#0a1628;border:1px solid #1e3a5f;border-radius:20px;font-size:12px;color:#e2e8f0;">'
            + getFlag(c) + ' ' + c + ' <strong style="color:#4ade80;">' + countryCounts[c] + '</strong></div>';
    });
    summaryHtml += '</div>';

    var html = summaryHtml + '<div style="overflow-x:auto;">';
    html += '<table class="inv-table"><thead><tr><th>#</th><th>User</th><th>Role</th><th>Location</th><th>Device</th><th>IP</th><th>Date & Time</th></tr></thead><tbody>';
    filtered.forEach(function(l, i){
        var roleColor = ROLE_COLORS[l.role] || '#475569';
        var flag      = getFlag(l.country || '');
        var location  = flag + ' ' + (l.city ? l.city + (l.region ? ', ' + l.region : '') : '') + (l.country ? ' (' + l.country + ')' : '');
        var device    = parseUA(l.user_agent || '');
        var dt        = l.created_at ? l.created_at.replace('T',' ').substring(0,19) : '-';
        html += '<tr>'
            + '<td style="color:#475569;font-size:11px;">' + (i+1) + '</td>'
            + '<td style="font-weight:bold;color:#e2e8f0;">' + l.username + '</td>'
            + '<td><span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:bold;background:' + roleColor + ';color:#fff;">' + l.role + '</span></td>'
            + '<td style="font-size:12px;white-space:nowrap;">' + location + '</td>'
            + '<td style="font-size:12px;white-space:nowrap;">' + device + '</td>'
            + '<td style="font-size:11px;color:#64748b;white-space:nowrap;">' + (l.ip_address||'-') + '</td>'
            + '<td style="font-size:11px;color:#94a3b8;white-space:nowrap;">' + dt + '</td>'
            + '</tr>';
    });
    html += '</tbody></table></div>';
    box.innerHTML = html;
}

/* ══════════════════════════════════════════════
   MODULE PERMISSIONS (RBAC)
   View / Add / Edit / Delete per role per module
   ══════════════════════════════════════════════ */

var _MP_DATA    = {};   /* [role][module] = {view,add,edit,delete} */
var _MP_MODULES = {};   /* module => [actions] */
var _MP_LABELS  = {};   /* module => display label */

var MP_ROLES = [
    {key:'worker',      label:'Worker'},
    {key:'eco_client',  label:'ECO Client'},
    {key:'qa',          label:'QA'},
    {key:'ai_work',     label:'AI Worker'},
    {key:'eco_listing', label:'ECO Listing'},
    {key:'seo_manager', label:'SEO Manager'},
];

function loadModulePermissions(){
    if(ROLE !== 'administrator') return;
    var box = document.getElementById('module-perms-container');
    fetch('index.php?action=get_module_permissions')
        .then(r => r.json())
        .then(r => {
            if(!r.success){
                if(box) box.innerHTML = '<div style="color:#f87171;font-size:13px;">' + (r.message || 'Module permissions load nahi ho saken.') + '</div>';
                return;
            }
            _MP_DATA    = r.data    || {};
            _MP_MODULES = r.modules || {};
            _MP_LABELS  = r.labels  || {};
            renderModulePermissions();
        })
        .catch(function(){
            if(box) box.innerHTML = '<div style="color:#f87171;font-size:13px;">Module permissions load nahi ho saken. Please page refresh karein.</div>';
        });
}

function renderModulePermissions(){
    var box = document.getElementById('module-perms-container');
    if(!box) return;

    var moduleKeys = Object.keys(_MP_MODULES);
    var actionLabels = {view:'View', add:'Add', edit:'Edit', delete:'Delete'};

    /* ─── Table header ─── */
    var html = '<div style="overflow-x:auto;">';
    html += '<table class="mp-table">';
    html += '<thead><tr><th class="mp-th-role">Role</th>';

    moduleKeys.forEach(function(mod){
        var actions = _MP_MODULES[mod];
        html += '<th class="mp-th-module" colspan="' + actions.length + '">'
             + (_MP_LABELS[mod] || mod) + '</th>';
    });
    html += '</tr>';

    /* Sub-header: action names */
    html += '<tr><th></th>';
    moduleKeys.forEach(function(mod){
        _MP_MODULES[mod].forEach(function(act){
            html += '<th class="mp-th-action">' + actionLabels[act] + '</th>';
        });
    });
    html += '</tr></thead><tbody>';

    /* ─── Role rows ─── */
    MP_ROLES.forEach(function(roleObj){
        var role = roleObj.key;
        html += '<tr><td class="mp-td-role">' + roleObj.label + '</td>';

        moduleKeys.forEach(function(mod){
            var savedPerms = (_MP_DATA[role] && _MP_DATA[role][mod]) || {};
            _MP_MODULES[mod].forEach(function(act){
                var checked = savedPerms[act] ? 'checked' : '';
                html += '<td class="mp-td-cb">'
                     + '<input type="checkbox" class="mp-chk"'
                     + ' data-role="' + role + '"'
                     + ' data-module="' + mod + '"'
                     + ' data-action="' + act + '"'
                     + ' ' + checked
                     + ' onchange="mpToggle(this)">'
                     + '</td>';
            });
        });
        html += '</tr>';
    });

    html += '</tbody></table></div>';

    /* Save button + message */
    html += '<div style="margin-top:14px;display:flex;align-items:center;gap:12px;">'
         + '<button class="adminbtn" onclick="saveAllModulePerms()">💾 Save Module Permissions</button>'
         + '<span id="mp-save-msg" style="font-size:12px;"></span>'
         + '</div>';

    box.innerHTML = html;
}

/* Toggle one checkbox and auto-save that role+module */
function mpToggle(chk){
    var role   = chk.getAttribute('data-role');
    var module = chk.getAttribute('data-module');

    /* Collect all actions for this role+module */
    var perms = {};
    document.querySelectorAll('.mp-chk[data-role="'+role+'"][data-module="'+module+'"]')
        .forEach(function(c){
            perms[c.getAttribute('data-action')] = c.checked ? 1 : 0;
        });

    /* Update local cache */
    if(!_MP_DATA[role]) _MP_DATA[role] = {};
    _MP_DATA[role][module] = perms;

    /* Auto-save this cell immediately */
    var fd = new FormData();
    fd.append('action', 'save_module_permissions');
    fd.append('role',   role);
    fd.append('module', module);
    fd.append('perms',  JSON.stringify(perms));
    fd.append('_csrf',  CSRF_TOKEN);

    fetch('index.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(r => {
            var msg = document.getElementById('mp-save-msg');
            if(msg){
                msg.textContent = r.success ? '✅ Saved' : '❌ Error';
                msg.style.color = r.success ? '#4ade80' : '#f87171';
                setTimeout(function(){ msg.textContent = ''; }, 2000);
            }
        });
}

/* Bulk save all (for the button) */
function saveAllModulePerms(){
    var msg = document.getElementById('mp-save-msg');
    if(msg){ msg.textContent = '⏳ Saving...'; msg.style.color = '#94a3b8'; }

    /* Collect all checkboxes */
    var byRoleMod = {};
    document.querySelectorAll('.mp-chk').forEach(function(c){
        var role   = c.getAttribute('data-role');
        var module = c.getAttribute('data-module');
        var action = c.getAttribute('data-action');
        var key    = role + '|' + module;
        if(!byRoleMod[key]) byRoleMod[key] = {role:role, module:module, perms:{}};
        byRoleMod[key].perms[action] = c.checked ? 1 : 0;
    });

    var promises = Object.values(byRoleMod).map(function(item){
        var fd = new FormData();
        fd.append('action', 'save_module_permissions');
        fd.append('role',   item.role);
        fd.append('module', item.module);
        fd.append('perms',  JSON.stringify(item.perms));
        fd.append('_csrf',  CSRF_TOKEN);
        return fetch('index.php', {method:'POST', body:fd}).then(r => r.json());
    });

    Promise.all(promises).then(function(results){
        var ok = results.every(function(r){ return r.success; });
        if(msg){
            msg.textContent = ok ? '✅ All permissions saved!' : '❌ Some errors';
            msg.style.color = ok ? '#4ade80' : '#f87171';
            setTimeout(function(){ msg.textContent = ''; }, 3000);
        }
    });
}

/* ── Init ────────────────────────────────────── */
/* Unified role permissions: module CRUD + status filters in one panel */
var UNIFIED_PERMS = {};
var UNIFIED_MODULE_PERMS = {};
var UNIFIED_MODULES = {};
var UNIFIED_LABELS = {};
var UNIFIED_ROLES = [];



function hideLegacyPermissionCards(){
    ['perms-container','module-perms-container'].forEach(function(id){
        var el = document.getElementById(id);
        if(el && el.parentElement) el.parentElement.style.display = 'none';
    });
}

function loadUnifiedPermissions(){
    if(ROLE !== 'administrator') return;
    hideLegacyPermissionCards();
    var box = document.getElementById('role-perms-container');
    if(!box) return;
    box.innerHTML = '<div style="color:#94a3b8;font-size:13px;">Loading...</div>';

    Promise.all([
        fetch('index.php?action=get_permissions').then(r => r.json()),
        fetch('index.php?action=get_module_permissions').then(r => r.json())
    ]).then(function(res){
        var filterRes = res[0] || {};
        var moduleRes = res[1] || {};
        if(!filterRes.success || !moduleRes.success){
            box.innerHTML = '<div style="color:#f87171;font-size:13px;">Permissions load nahi ho saken.</div>';
            return;
        }

        UNIFIED_PERMS = filterRes.data || {};
        UNIFIED_MODULE_PERMS = moduleRes.data || {};
        UNIFIED_MODULES = moduleRes.modules || {};
        UNIFIED_LABELS = moduleRes.labels || {};

        var roleSet = {};
        (filterRes.roles || []).forEach(function(role){ if(role !== 'administrator') roleSet[role] = true; });
        MP_ROLES.forEach(function(r){ roleSet[r.key] = true; });
        Object.keys(UNIFIED_MODULE_PERMS).forEach(function(role){ if(role !== 'administrator') roleSet[role] = true; });
        UNIFIED_ROLES = Object.keys(roleSet).sort();

        renderUnifiedPermissions();
    }).catch(function(){
        box.innerHTML = '<div style="color:#f87171;font-size:13px;">Permissions load nahi ho saken. Please refresh karein.</div>';
    });
}

function renderUnifiedPermissions(){
    var box = document.getElementById('role-perms-container');
    if(!box) return;
    var moduleKeys = Object.keys(UNIFIED_MODULES);
    var actionLabels = {view:'View', add:'Add', edit:'Edit', delete:'Delete'};
    var html = '<div style="display:flex;justify-content:flex-end;margin-bottom:12px;">'
        + '<button class="adminbtn" onclick="saveAllUnifiedPermissions()">Save All Permissions</button>'
        + '</div>';

    UNIFIED_ROLES.forEach(function(role){
        html += '<div style="border:1px solid #1e3a5f;background:#071428;border-radius:8px;margin-bottom:14px;overflow:hidden;">';
        html += '<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:11px 12px;background:#0a1628;border-bottom:1px solid #1e3a5f;">'
            + '<div style="color:#e2e8f0;font-size:13px;font-weight:800;">' + roleToLabel(role) + '</div>'
            + '<div style="display:flex;align-items:center;gap:10px;"><span id="unified-msg-' + role + '" style="font-size:12px;"></span>'
            + '<button class="adminbtn" onclick="saveUnifiedRole(\'' + role + '\')" style="padding:7px 12px;">Save Role</button></div>'
            + '</div>';

        html += '<div style="overflow-x:auto;"><table class="mp-table" style="min-width:760px;"><thead><tr><th class="mp-th-role">Module</th>';
        ['view','add','edit','delete'].forEach(function(act){
            html += '<th class="mp-th-action">' + actionLabels[act] + '</th>';
        });
        html += '</tr></thead><tbody>';
        moduleKeys.forEach(function(mod){
            var allowedActs = UNIFIED_MODULES[mod] || [];
            var savedPerms = (UNIFIED_MODULE_PERMS[role] && UNIFIED_MODULE_PERMS[role][mod]) || {};
            html += '<tr><td class="mp-td-role">' + (UNIFIED_LABELS[mod] || mod) + '</td>';
            ['view','add','edit','delete'].forEach(function(act){
                if(allowedActs.indexOf(act) === -1){
                    html += '<td class="mp-td-cb" style="color:#334155;">-</td>';
                } else {
                    var checked = savedPerms[act] ? 'checked' : '';
                    html += '<td class="mp-td-cb"><input type="checkbox" class="unified-module-chk" data-role="' + role + '" data-module="' + mod + '" data-action="' + act + '" ' + checked + '></td>';
                }
            });
            html += '</tr>';
        });
        html += '</tbody></table></div>';

        html += '<div style="padding:12px;border-top:1px solid #1e3a5f;">'
            + '<div style="color:#93c5fd;font-size:11px;font-weight:800;letter-spacing:.5px;margin-bottom:8px;">STATUS FILTERS</div>'
            + '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:8px;">';
        var filters = (UNIFIED_PERMS[role] || {}).filters || [];
        ALL_FILTER_OPTS.forEach(function(f){
            var checked = filters.indexOf(f.val) !== -1 ? 'checked' : '';
            html += '<label style="display:flex;align-items:center;gap:7px;color:#cbd5e1;font-size:12px;background:#0a1628;border:1px solid #1e3a5f;border-radius:6px;padding:7px 9px;">'
                + '<input type="checkbox" class="unified-filter-chk" data-role="' + role + '" data-filter="' + f.val + '" ' + checked + '>'
                + '<span>' + f.label + '</span></label>';
        });
        html += '</div></div></div>';
    });

    box.innerHTML = html;
}

function collectUnifiedRole(role){
    var filters = [];
    document.querySelectorAll('.unified-filter-chk[data-role="' + role + '"]').forEach(function(chk){
        if(chk.checked) filters.push(chk.getAttribute('data-filter'));
    });
    var modules = {};
    document.querySelectorAll('.unified-module-chk[data-role="' + role + '"]').forEach(function(chk){
        var mod = chk.getAttribute('data-module');
        var act = chk.getAttribute('data-action');
        if(!modules[mod]) modules[mod] = {};
        modules[mod][act] = chk.checked ? 1 : 0;
    });
    return {filters:filters, modules:modules};
}

function saveUnifiedRole(role){
    var msg = document.getElementById('unified-msg-' + role);
    if(msg){ msg.textContent = 'Saving...'; msg.style.color = '#94a3b8'; }
    var data = collectUnifiedRole(role);
    var jobs = [];
    var fd = new FormData();
    fd.append('action', 'save_permissions');
    fd.append('role', role);
    fd.append('settings', JSON.stringify({filters:data.filters}));
    jobs.push(fetch('index.php', {method:'POST', body:fd}).then(r => r.json()));
    Object.keys(data.modules).forEach(function(mod){
        var mfd = new FormData();
        mfd.append('action', 'save_module_permissions');
        mfd.append('role', role);
        mfd.append('module', mod);
        mfd.append('perms', JSON.stringify(data.modules[mod]));
        jobs.push(fetch('index.php', {method:'POST', body:mfd}).then(r => r.json()));
    });
    return Promise.all(jobs).then(function(results){
        var ok = results.every(function(r){ return r.success; });
        if(msg){
            msg.textContent = ok ? 'Saved' : 'Error';
            msg.style.color = ok ? '#4ade80' : '#f87171';
            setTimeout(function(){ msg.textContent = ''; }, 2500);
        }
        return ok;
    });
}

function saveAllUnifiedPermissions(){
    Promise.all(UNIFIED_ROLES.map(function(role){ return saveUnifiedRole(role); }));
}

var USER_PERMISSION_PAYLOAD = null;

function loadUsers(){
    if(ROLE !== 'administrator') return;
    hideLegacyPermissionCards();

    fetch('index.php?action=get_user_permissions').then(r => r.json()).then(function(res){
        if(!res.success){
            document.getElementById('users').innerHTML = '<div style="color:#f87171;font-size:13px;">Users load nahi ho saken.</div>';
            return;
        }
        USER_PERMISSION_PAYLOAD = res;
        renderUsersWithPermissions();
    }).catch(function(){
        document.getElementById('users').innerHTML = '<div style="color:#f87171;font-size:13px;">Users load nahi ho saken. Please refresh karein.</div>';
    });
}

function filterUsersByRole(){
    renderUsersWithPermissions();
}

function renderUsersWithPermissions(){
    var res = USER_PERMISSION_PAYLOAD || {};
    var users = res.users || [];

    var filterEl = document.getElementById('user-role-filter');
    var selectedRole = filterEl ? filterEl.value : '';
    if(selectedRole){
        users = users.filter(function(u){ return u.role === selectedRole; });
    }

    var roles = ['worker','eco_client','qa','seo_manager','eco_listing','administrator','ai_work'];
    var html  = '<div style="display:flex;flex-direction:column;gap:8px;">';

    if(users.length === 0){
        html += '<div style="color:#64748b;font-size:13px;padding:10px 0;text-align:center;">Is role ke liye koi user nahi mila.</div>';
    }

    users.forEach(function(u){
        var opts = roles.map(function(ro){
            return '<option value="' + ro + '"' + (u.role === ro ? ' selected' : '') + '>' + roleToLabel(ro) + '</option>';
        }).join('');
        var canCustomize = u.role !== 'administrator';
        var open = (canCustomize && _USER_PERMS_OPEN[u.id]) ? 'block' : 'none';
        var arrow = _USER_PERMS_OPEN[u.id] ? 'Hide Permissions' : 'Permissions';

        html += '<div style="background:#0f2035;border:1px solid #1e3a5f;border-radius:8px;overflow:hidden;">'
            + '<div style="display:flex;flex-wrap:nowrap;gap:6px;align-items:center;padding:8px 10px;overflow-x:auto;">'
            + '<input id="un-' + u.id + '" type="text" value="' + String(u.username).replace(/"/g,'&quot;') + '" style="flex:2;min-width:120px;padding:6px 9px;border:1px solid #334155;border-radius:6px;background:#0a1628;color:#e2e8f0;font-size:13px;outline:none;">'
            + '<select id="ur-' + u.id + '" style="flex:1;min-width:130px;max-width:180px;padding:6px 8px;border:1px solid #334155;border-radius:6px;background:#0a1628;color:#e2e8f0;font-size:12px;outline:none;">' + opts + '</select>'
            + '<input id="up-' + u.id + '" type="password" placeholder="New Password" style="flex:1;min-width:140px;max-width:190px;padding:6px 8px;border:1px solid #334155;border-radius:6px;background:#0a1628;color:#e2e8f0;font-size:13px;outline:none;">'
            + '<button onclick="updateUser(' + u.id + ')" style="flex-shrink:0;background:#2563eb;color:#fff;border:none;padding:6px 13px;border-radius:6px;cursor:pointer;font-size:12px;font-weight:700;white-space:nowrap;">Save</button>'
            + '<button onclick="deleteUser(' + u.id + ')" style="flex-shrink:0;background:#dc2626;color:#fff;border:none;padding:6px 10px;border-radius:6px;cursor:pointer;font-size:12px;font-weight:700;">Delete</button>';
        if(canCustomize){
            html += '<button id="uperm-toggle-' + u.id + '" onclick="toggleUserPermissionPanel(' + u.id + ')" style="flex-shrink:0;background:#1e3a5f;color:#93c5fd;border:1px solid #2563eb;padding:6px 11px;border-radius:6px;cursor:pointer;font-size:12px;font-weight:700;">' + arrow + '</button>';
        } else {
            html += '<span style="flex-shrink:0;color:#4ade80;font-size:12px;font-weight:700;padding:6px 10px;">Full Access</span>';
        }
        html += ''
            + '</div>'
            + '<div id="user-perms-' + u.id + '" style="display:' + open + ';padding:12px;border-top:1px solid #1e3a5f;background:#071428;">'
            + buildUserPermissionsHtml(u)
            + '</div></div>';
    });

    html += '</div>';
    document.getElementById('users').innerHTML = html;
}

function buildUserPermissionsHtml(user){
    var res = USER_PERMISSION_PAYLOAD || {};
    var uid = String(user.id);
    var effective = (res.effective && res.effective[uid]) || {filters:[], modules:{}};
    var userFilters = (res.user_filters && res.user_filters[uid]) || null;
    var userModules = (res.user_modules && res.user_modules[uid]) || null;
    var moduleKeys = Object.keys(res.modules || {});
    var labels = res.labels || {};
    var actionLabels = {view:'View', add:'Add', edit:'Edit', delete:'Delete'};
    var filters = effective.filters || [];
    var customized = userFilters || userModules;

    var resetBtn = customized ? '<button class="adminbtn" onclick="resetUserPermissionSet(' + uid + ')" style="padding:7px 12px;background:#ef4444;margin-right:6px;">Reset to Role Defaults</button>' : '';

    var html = '<div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;flex-wrap:wrap;">'
        + '<div><div style="color:#e2e8f0;font-size:13px;font-weight:800;">' + String(user.username) + ' Permissions</div>'
        + '<div style="color:#64748b;font-size:11px;">Role default: ' + roleToLabel(user.role) + (customized ? ' | custom override active' : ' | inherited from role') + '</div></div>'
        + '<div style="display:flex;align-items:center;gap:10px;"><span id="user-perm-msg-' + uid + '" style="font-size:12px;"></span>'
        + resetBtn
        + '<button class="adminbtn" onclick="saveUserPermissionSet(' + uid + ')" style="padding:7px 12px;">Save User Permissions</button></div></div>';

    html += '<div style="overflow-x:auto;"><table class="mp-table" style="min-width:760px;"><thead><tr><th class="mp-th-role">Module</th>';
    ['view','add','edit','delete'].forEach(function(act){ html += '<th class="mp-th-action">' + actionLabels[act] + '</th>'; });
    html += '</tr></thead><tbody>';

    moduleKeys.forEach(function(mod){
        var allowedActs = (res.modules && res.modules[mod]) || [];
        var savedPerms = (effective.modules && effective.modules[mod]) || {};
        html += '<tr><td class="mp-td-role">' + (labels[mod] || mod) + '</td>';
        ['view','add','edit','delete'].forEach(function(act){
            if(allowedActs.indexOf(act) === -1){
                html += '<td class="mp-td-cb" style="color:#334155;">-</td>';
            } else {
                var checked = savedPerms[act] ? 'checked' : '';
                html += '<td class="mp-td-cb"><input type="checkbox" class="user-module-chk" data-user="' + uid + '" data-module="' + mod + '" data-action="' + act + '" ' + checked + '></td>';
            }
        });
        html += '</tr>';
    });
    html += '</tbody></table></div>';

    html += '<div style="padding-top:12px;border-top:1px solid #1e3a5f;margin-top:0;">'
        + '<div style="color:#93c5fd;font-size:11px;font-weight:800;letter-spacing:.5px;margin-bottom:8px;">STATUS FILTERS</div>'
        + '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:8px;">';
    ALL_FILTER_OPTS.forEach(function(f){
        var checked = filters.indexOf(f.val) !== -1 ? 'checked' : '';
        html += '<label style="display:flex;align-items:center;gap:7px;color:#cbd5e1;font-size:12px;background:#0a1628;border:1px solid #1e3a5f;border-radius:6px;padding:7px 9px;">'
            + '<input type="checkbox" class="user-filter-chk" data-user="' + uid + '" data-filter="' + f.val + '" ' + checked + '>'
            + '<span>' + f.label + '</span></label>';
    });
    html += '</div></div>';
    return html;
}

function toggleUserPermissionPanel(userId){
    _USER_PERMS_OPEN[userId] = !_USER_PERMS_OPEN[userId];
    renderUsersWithPermissions();
}

function saveUserPermissionSet(userId){
    var filters = [];
    document.querySelectorAll('.user-filter-chk[data-user="' + userId + '"]').forEach(function(chk){
        if(chk.checked) filters.push(chk.getAttribute('data-filter'));
    });

    var modules = {};
    document.querySelectorAll('.user-module-chk[data-user="' + userId + '"]').forEach(function(chk){
        var mod = chk.getAttribute('data-module');
        var act = chk.getAttribute('data-action');
        if(!modules[mod]) modules[mod] = {};
        modules[mod][act] = chk.checked ? 1 : 0;
    });

    var msg = document.getElementById('user-perm-msg-' + userId);
    if(msg){ msg.textContent = 'Saving...'; msg.style.color = '#94a3b8'; }

    var fd = new FormData();
    fd.append('action', 'save_user_permissions');
    fd.append('user_id', userId);
    fd.append('filters', JSON.stringify(filters));
    fd.append('modules', JSON.stringify(modules));

    fetch('index.php', {method:'POST', body:fd}).then(r => r.json()).then(function(r){
        if(msg){
            msg.textContent = r.success ? 'Saved' : (r.message || 'Error');
            msg.style.color = r.success ? '#4ade80' : '#f87171';
            setTimeout(function(){ msg.textContent = ''; }, 2500);
        }
        if(r.success) loadUsers();
    });
}

function resetUserPermissionSet(userId){
    _confirm('Are you sure you want to reset this user\'s permissions to role defaults?', function(){
        var msg = document.getElementById('user-perm-msg-' + userId);
        if(msg){ msg.textContent = 'Resetting...'; msg.style.color = '#94a3b8'; }

        var fd = new FormData();
        fd.append('action', 'reset_user_permissions');
        fd.append('user_id', userId);

        fetch('index.php', {method:'POST', body:fd}).then(r => r.json()).then(function(r){
            if(msg){
                msg.textContent = r.success ? 'Reset' : (r.message || 'Error');
                msg.style.color = r.success ? '#4ade80' : '#f87171';
                setTimeout(function(){ msg.textContent = ''; }, 2500);
            }
            if(r.success) loadUsers();
        });
    });
}

loadUsers();
if(ROLE === 'administrator'){
    hideLegacyPermissionCards();
}

/* ── Dashboard Stats ─────────────────────────── */

function toggleLogsAccordion() {
    var content = document.getElementById('logs-accordion-content');
    var arrow = document.getElementById('logs-accordion-arrow');
    if (!content) return;
    if (content.style.display === 'none') {
        content.style.display = 'block';
        if (arrow) arrow.textContent = '▲ Hide Audit Logs';
        loadLoginLogs();
    } else {
        content.style.display = 'none';
        if (arrow) arrow.textContent = '▼ Show Audit Logs';
    }
}

function formatCurrency(val) {
    return '$' + parseFloat(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatPKR(val) {
    return 'Rs. ' + parseFloat(val || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function loadDashboardStats() {
    var isIlyaeco = (typeof USERNAME !== 'undefined' && USERNAME === 'ilyaeco');
    if (ROLE !== 'administrator' && !isIlyaeco) return;
    
    // Set loading indicator in worker table
    var tbody = document.getElementById('dashboard-workers-tbody');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: #64748b; padding: 20px; font-size: 13px;">🔄 Fetching latest statistics...</td></tr>`;
    }

    // Set loading indicator in expenses table
    var expTbody = document.getElementById('dashboard-expenses-tbody');
    if (expTbody) {
        expTbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: #64748b; padding: 20px; font-size: 13px;">🔄 Fetching latest expenses...</td></tr>`;
    }

    // Get filter inputs
    var period = document.getElementById('db-period')?.value || 'all';
    var startDate = document.getElementById('db-start-date')?.value || '';
    var endDate = document.getElementById('db-end-date')?.value || '';

    // Set default value for expense date to today if it is empty
    var expDateInput = document.getElementById('exp-date');
    if (expDateInput && !expDateInput.value) {
        expDateInput.value = new Date().toISOString().substring(0, 10);
    }

    var url = `index.php?action=get_dashboard_stats&period=${encodeURIComponent(period)}&start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}`;

    fetch(url)
        .then(r => r.json())
        .then(r => {
            if (!r.success) {
                console.error('Error fetching dashboard stats:', r.message);
                return;
            }

            var finances = r.finances || {};
            var pipeline = r.pipeline || {};
            var workers = r.workers || [];
            var expenses = r.expenses || [];

            // Populate Finances
            var totalInvoiced = parseFloat(finances.invoices_paid || 0) + parseFloat(finances.invoices_pending || 0);
            
            // Client Invoices (stored and billed in USD)
            document.getElementById('stat-total-invoiced').textContent = formatCurrency(totalInvoiced);
            document.getElementById('stat-invoice-paid').textContent = formatCurrency(finances.invoices_paid);
            document.getElementById('stat-invoice-pending').textContent = formatCurrency(finances.invoices_pending);

            // Conversion rate from core.js global
            var rate = window.EXCHANGE_RATE || 278.0;

            // Worker Payroll Card (Calculated in PKR, converted to USD)
            var totalPayrollPkr = parseFloat(finances.payslips_paid || 0) + parseFloat(finances.payslips_generated || 0);
            var totalPayrollUsd = totalPayrollPkr / rate;

            document.getElementById('stat-total-payroll').textContent = formatCurrency(totalPayrollUsd);
            document.getElementById('stat-total-payroll-pkr').textContent = formatPKR(totalPayrollPkr);

            document.getElementById('stat-payslip-paid').textContent = formatCurrency(parseFloat(finances.payslips_paid || 0) / rate);
            document.getElementById('stat-payslip-paid-pkr').textContent = formatPKR(finances.payslips_paid);

            document.getElementById('stat-payslip-generated').textContent = formatCurrency(parseFloat(finances.payslips_generated || 0) / rate);
            document.getElementById('stat-payslip-generated-pkr').textContent = formatPKR(finances.payslips_generated);

            // Worker Ledger Liability (total balances)
            var balEl = document.getElementById('stat-worker-balances');
            var balPkrEl = document.getElementById('stat-worker-balances-pkr');
            if (balEl) {
                var balPkr = parseFloat(finances.worker_balances || 0);
                var balUsd = balPkr / rate;
                balEl.textContent = formatCurrency(balUsd);
                if (balPkrEl) balPkrEl.textContent = formatPKR(balPkr);

                if (balPkr > 0) {
                    balEl.style.color = '#f87171'; // red for liability
                } else {
                    balEl.style.color = '#4ade80'; // green for zero/good
                }
            }

            // Fines & Penalties Card (Calculated in PKR, converted to USD)
            var finesDeductedPkr = parseFloat(finances.fines_deducted || 0);
            var finesPendingPkr = parseFloat(finances.fines_pending || 0);
            var totalFinesPkr = finesDeductedPkr + finesPendingPkr;
            var totalFinesUsd = totalFinesPkr / rate;

            document.getElementById('stat-total-fines').textContent = formatCurrency(totalFinesUsd);
            document.getElementById('stat-total-fines-pkr').textContent = formatPKR(totalFinesPkr);
            document.getElementById('stat-fines-deducted').textContent = formatCurrency(finesDeductedPkr / rate);
            document.getElementById('stat-fines-deducted-pkr').textContent = formatPKR(finesDeductedPkr);
            document.getElementById('stat-fines-pending').textContent = formatCurrency(finesPendingPkr / rate);
            document.getElementById('stat-fines-pending-pkr').textContent = formatPKR(finesPendingPkr);

            // Fixed Expenses Card (Office, Utility, Other)
            var expOfficePkr = parseFloat(finances.expenses_office || 0);
            var expUtilityPkr = parseFloat(finances.expenses_utility || 0);
            var expOtherPkr = parseFloat(finances.expenses_other || 0);
            var expTotalPkr = parseFloat(finances.expenses_total || 0);
            var expTotalUsd = expTotalPkr / rate;

            document.getElementById('stat-total-expenses').textContent = formatCurrency(expTotalUsd);
            document.getElementById('stat-total-expenses-pkr').textContent = formatPKR(expTotalPkr);
            document.getElementById('stat-expenses-office-pkr').textContent = formatPKR(expOfficePkr);
            document.getElementById('stat-expenses-utility-pkr').textContent = formatPKR(expUtilityPkr);
            document.getElementById('stat-expenses-other-pkr').textContent = formatPKR(expOtherPkr);

            // Profit & Loss Card
            // Revenue (USD) = Invoices Paid (Cash based)
            // Deducted fines reduce the gross payroll expense, meaning: Net Payroll Expense = Gross Payroll - Deducted Fines.
            // Total Expenses = Net Payroll + Fixed Expenses.
            // Net Profit = Gross Revenue - Total Expenses.
            var grossRevenueUsd = parseFloat(finances.invoices_paid || 0);
            var grossPayrollPkr = parseFloat(finances.payslips_paid || 0) + parseFloat(finances.payslips_generated || 0);
            var netPayrollPkr = Math.max(0, grossPayrollPkr - finesDeductedPkr);
            
            var totalExpensesPkr = netPayrollPkr + expTotalPkr;
            var totalExpensesUsd = totalExpensesPkr / rate;
            var netProfitUsd = grossRevenueUsd - totalExpensesUsd;
            var netProfitPkr = netProfitUsd * rate;

            document.getElementById('stat-pl-revenue').textContent = formatCurrency(grossRevenueUsd);
            document.getElementById('stat-pl-expenses').textContent = formatCurrency(totalExpensesUsd);
            
            var plEl = document.getElementById('stat-net-profit');
            var plPkrEl = document.getElementById('stat-net-profit-pkr');
            if (plEl) {
                plEl.textContent = formatCurrency(netProfitUsd);
                if (plPkrEl) plPkrEl.textContent = formatPKR(netProfitPkr);

                if (netProfitUsd >= 0) {
                    plEl.style.color = '#4ade80'; // Green for profit
                    if (plPkrEl) plPkrEl.style.color = '#4ade80';
                } else {
                    plEl.style.color = '#f87171'; // Red for loss
                    if (plPkrEl) plPkrEl.style.color = '#f87171';
                }
            }

            // Populate Pipeline
            document.getElementById('stat-pipe-working').textContent = pipeline.working || 0;
            document.getElementById('stat-pipe-aiwork').textContent = pipeline.ai_work || 0;
            document.getElementById('stat-pipe-aidone').textContent = pipeline.ai_done || 0;
            document.getElementById('stat-pipe-paused').textContent = pipeline.paused || 0;
            document.getElementById('stat-pipe-inqa').textContent = pipeline.in_qa || 0;
            document.getElementById('stat-pipe-seoreview').textContent = pipeline.seo_review || 0;
            document.getElementById('stat-pipe-changes').textContent = pipeline.changes || 0;
            document.getElementById('stat-pipe-changes-in-content').textContent = pipeline.changes_in_content || 0;
            document.getElementById('stat-pipe-changing').textContent = pipeline.changing || 0;
            document.getElementById('stat-pipe-hold').textContent = pipeline.hold || 0;

            // Populate Task Summary
            document.getElementById('stat-task-total').textContent = pipeline.total || 0;
            document.getElementById('stat-task-pending').textContent = pipeline.pending || 0;
            document.getElementById('stat-task-approved').textContent = pipeline.approved || 0;
            document.getElementById('stat-task-workdone').textContent = pipeline.work_done || 0;
            document.getElementById('stat-task-published').textContent = pipeline.published || 0;

            // Populate Workers Table
            if (tbody) {
                if (!workers.length) {
                    tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: #64748b; padding: 20px; font-size: 13px;">No workers found.</td></tr>`;
                } else {
                    var html = '';
                    workers.forEach(w => {
                        var roleText = roleToLabel(w.role);
                        var roleColor = ROLE_COLORS[w.role] || '#475569';
                        
                        var balancePkr = parseFloat(w.balance || 0);
                        var balanceUsd = balancePkr / rate;
                        var usdBalanceText = formatCurrency(balanceUsd);
                        var pkrBalanceText = formatPKR(balancePkr);
                        var balanceColor = balancePkr > 0 ? '#fbbf24' : '#4ade80'; // Yellow if positive balance, green if zero

                        // Hours formatting
                        var hoursText = '—';
                        var secs = parseInt(w.work_seconds || 0);
                        if (secs > 0) {
                            var hrs = Math.floor(secs / 3600);
                            var mins = Math.floor((secs % 3600) / 60);
                            hoursText = `${hrs}h ${mins}m`;
                        }

                        // Worker action trigger - switches to Payroll tab and sets selected worker
                        var actionBtn = `<button onclick="viewWorkerPayroll(${w.id})" class="adminbtn" style="padding: 4px 8px; font-size: 11px; background: #1e3a5f;">💳 Payroll</button>`;
                        var isIlyaeco = (typeof USERNAME !== 'undefined' && USERNAME === 'ilyaeco');

                        html += `
                            <tr style="border-bottom: 1px solid #1e3a5f;">
                                <td style="padding: 10px; font-weight: 600; color: #e2e8f0; font-size: 13px;">👤 ${w.username}</td>
                                <td style="padding: 10px; text-align: center;">
                                    <span style="padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600; background: ${roleColor}; color: #fff;">${roleText}</span>
                                </td>
                                <td style="padding: 10px; text-align: center; color: #cbd5e1; font-size: 13px;">${w.active_tasks}</td>
                                <td style="padding: 10px; text-align: center; color: #cbd5e1; font-size: 13px;">${w.completed_tasks}</td>
                                <td style="padding: 10px; text-align: center; color: #60a5fa; font-weight: 500; font-size: 13px;">${hoursText}</td>
                                ${!isIlyaeco ? `
                                <td style="padding: 10px; text-align: right; font-size: 13px; white-space: nowrap;">
                                    <div style="font-weight: 600; color: ${balanceColor};">${usdBalanceText}</div>
                                    <div style="font-size: 10px; color: #94a3b8;">${pkrBalanceText}</div>
                                </td>
                                <td style="padding: 10px; text-align: center;">${actionBtn}</td>
                                ` : ''}
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;
                }
            }

            // Populate Expenses Table
            if (expTbody) {
                if (!expenses.length) {
                    expTbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: #64748b; padding: 20px; font-size: 13px;">No expenses logged for this period.</td></tr>`;
                } else {
                    var expHtml = '';
                    expenses.forEach(exp => {
                        var amtPkr = parseFloat(exp.amount || 0);
                        var amtUsd = amtPkr / rate;
                        var catLabel = exp.category === 'office' ? 'Office' : (exp.category === 'utility' ? 'Utility' : 'Other');
                        var catColor = exp.category === 'office' ? '#3b82f6' : (exp.category === 'utility' ? '#f59e0b' : '#6b7280');
                        
                        expHtml += `
                            <tr style="border-bottom: 1px solid #1e3a5f;">
                                <td style="padding: 10px; color: #cbd5e1; font-size: 13px;">${exp.expense_date}</td>
                                <td style="padding: 10px; font-weight: 600; color: #e2e8f0; font-size: 13px;">${exp.title}</td>
                                <td style="padding: 10px; text-align: center;">
                                    <span style="padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600; background: ${catColor}; color: #fff;">${catLabel}</span>
                                </td>
                                <td style="padding: 10px; text-align: right; color: #cbd5e1; font-size: 13px; font-weight: 500;">${formatPKR(amtPkr)}</td>
                                <td style="padding: 10px; text-align: right; color: #94a3b8; font-size: 13px;">${formatCurrency(amtUsd)}</td>
                                <td style="padding: 10px; text-align: center;">
                                    <button onclick="deleteExpenseEntry(${exp.id})" class="adminbtn" style="padding: 4px 8px; font-size: 11px; background: #dc2626; border: none; cursor: pointer;">🗑 Delete</button>
                                </td>
                            </tr>
                        `;
                    });
                    expTbody.innerHTML = expHtml;
                }
            }
        })
        .catch(err => {
            console.error('Failed to load dashboard statistics:', err);
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: #f87171; padding: 20px; font-size: 13px;">❌ Error loading dashboard stats.</td></tr>`;
            }
            if (expTbody) {
                expTbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: #f87171; padding: 20px; font-size: 13px;">❌ Error loading expenses list.</td></tr>`;
            }
        });
}

function onDashboardPeriodChange() {
    var period = document.getElementById('db-period')?.value || 'all';
    var customDiv = document.getElementById('db-custom-dates');
    if (customDiv) {
        if (period === 'custom') {
            customDiv.style.display = 'inline-flex';
        } else {
            customDiv.style.display = 'none';
            loadDashboardStats();
        }
    }
}

function addExpenseEntry(e) {
    if (e) e.preventDefault();
    var title = document.getElementById('exp-title')?.value.trim();
    var amount = parseFloat(document.getElementById('exp-amount')?.value || 0);
    var category = document.getElementById('exp-category')?.value;
    var date = document.getElementById('exp-date')?.value;

    if (!title || isNaN(amount) || amount <= 0 || !category || !date) {
        alert('Please fill out all fields correctly.');
        return;
    }

    var formData = new FormData();
    formData.append('title', title);
    formData.append('amount', amount);
    formData.append('category', category);
    formData.append('expense_date', date);
    
    if (typeof CSRF_TOKEN !== 'undefined') {
        formData.append('_csrf', CSRF_TOKEN);
    }

    fetch('index.php?action=add_expense', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(r => {
        if (r.success) {
            var titleInput = document.getElementById('exp-title');
            var amtInput = document.getElementById('exp-amount');
            if (titleInput) titleInput.value = '';
            if (amtInput) amtInput.value = '';
            loadDashboardStats();
        } else {
            alert('Error adding expense: ' + (r.message || 'Unknown error'));
        }
    })
    .catch(err => {
        console.error('Failed to add expense:', err);
        alert('Failed to add expense.');
    });
}

function deleteExpenseEntry(id) {
    _confirm('Are you sure you want to delete this expense?', function(){
        var formData = new FormData();
        formData.append('id', id);
        if (typeof CSRF_TOKEN !== 'undefined') {
            formData.append('_csrf', CSRF_TOKEN);
        }

        fetch('index.php?action=delete_expense', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(r => {
            if (r.success) {
                loadDashboardStats();
            } else {
                alert('Error deleting expense: ' + (r.message || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error('Failed to delete expense:', err);
            alert('Failed to delete expense.');
        });
    });
}

function viewWorkerPayroll(workerId) {
    switchTab('payroll');
    // We want to simulate set worker and load
    setTimeout(function() {
        // Find the worker rate row or elements and toggle it to expand
        var acc = document.getElementById('rate-acc-' + workerId);
        if (acc) {
            // Expand the rate row
            acc.style.display = 'block';
            var btn = document.getElementById('rate-toggle-' + workerId);
            if (btn) btn.textContent = '▲';
            if (acc.dataset.loaded !== '1') {
                loadWorkerAccountInline(workerId);
            }
            // Scroll to it
            acc.parentElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, 200);
}
