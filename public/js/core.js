/*
=====================================================
ECO A+ PRO — core.js
Globals, CSRF interceptor, utilities, tab/panel switching,
simple work-status actions (pause/resume/hold/urgent).

ROLE, USER_ID, APP_URL, CSRF_TOKEN are injected by
views/layouts/app.php before any JS file loads.
=====================================================
*/

/* ── Global state ─────────────────────────────── */
var OPEN            = {};
var ALL_TASKS       = [];
var IS_EDITING      = {};
var CURRENT_PAGE    = 1;
var PAGE_SIZE       = 30;
var ALL_WORKERS     = [];
var ALL_PAYROLL_WORKERS = [];
var EXCHANGE_RATE   = 278.0;
var SELECTED_BULK_PRODUCTS = [];

(function(){
    fetch('https://open.er-api.com/v6/latest/USD')
        .then(r => r.json())
        .then(data => {
            if(data && data.rates && data.rates.PKR){
                window.EXCHANGE_RATE = parseFloat(data.rates.PKR);
                console.log('USD to PKR Exchange Rate loaded:', window.EXCHANGE_RATE);
            }
        })
        .catch(err => {
            console.warn('Failed to fetch exchange rate, using fallback:', window.EXCHANGE_RATE);
        });
})();

/* ── CSRF auto-injection ─────────────────────── */
/* Appends _csrf token to every POST request */
(function(){
    var _orig = window.fetch;
    window.fetch = function(url, opts){
        if(opts && opts.method && opts.method.toUpperCase() === 'POST'){
            if(typeof CSRF_TOKEN !== 'undefined' && CSRF_TOKEN){
                if(opts.body instanceof FormData || opts.body instanceof URLSearchParams){
                    opts.body.append('_csrf', CSRF_TOKEN);
                }
                opts.headers = opts.headers || {};
                if(opts.headers instanceof Headers){
                    if(!opts.headers.has('X-CSRF-TOKEN')) opts.headers.set('X-CSRF-TOKEN', CSRF_TOKEN);
                } else if(typeof opts.headers === 'object'){
                    opts.headers['X-CSRF-TOKEN'] = opts.headers['X-CSRF-TOKEN'] || CSRF_TOKEN;
                }
            }
        }
        return _orig.apply(this, arguments);
    };
})();

/* ── Header spacer ───────────────────────────── */
/* Backup analytics log for authenticated page loads */
(function(){
    try{
        var key = 'eco_login_logged_' + USER_ID + '_' + CSRF_TOKEN;
        if(!sessionStorage.getItem(key)){
            sessionStorage.setItem(key, '1');
            var fd = new FormData();
            fd.append('action', 'log_current_session');
            fetch('index.php', {method:'POST', body:fd}).catch(function(){});
        }
    }catch(e){}
})();

function updateHeaderSpacer(){
    var hdr = document.querySelector('.top');
    var spc = document.getElementById('header-spacer');
    if(hdr && spc){
        var h = hdr.offsetHeight;
        spc.style.height = h + 'px';
        document.querySelectorAll('.card').forEach(function(c){
            c.style.scrollMarginTop = (h + 6) + 'px';
        });
    }
}
function fixHeaderSpacer(){
    var hdr    = document.querySelector('.top');
    var spacer = document.getElementById('header-spacer');
    if(hdr && spacer) spacer.style.height = hdr.offsetHeight + 'px';
}
window.addEventListener('resize', updateHeaderSpacer);
document.addEventListener('DOMContentLoaded', updateHeaderSpacer);
setTimeout(updateHeaderSpacer, 100);

/* ── Time formatters ─────────────────────────── */
function formatWorkTime(seconds){
    seconds = parseInt(seconds) || 0;
    if(seconds <= 0) return '—';
    var h = Math.floor(seconds / 3600);
    var m = Math.floor((seconds % 3600) / 60);
    var s = seconds % 60;
    if(h > 0) return h + 'h ' + m + 'm ' + s + 's';
    if(m > 0) return m + 'm ' + s + 's';
    return s + 's';
}

/* Format a UTC datetime string → PAK + US time (12hr AM/PM) */
function formatDualTime(utcStr){
    if(!utcStr) return null;
    var d = new Date(utcStr.replace(' ', 'T') + 'Z');
    if(isNaN(d)) return null;
    var opts = {month:'short',day:'numeric',year:'numeric',
                hour:'numeric',minute:'2-digit',second:'2-digit',hour12:true};
    var pak = d.toLocaleString('en-US', Object.assign({}, opts, {timeZone:'Asia/Karachi'}));
    var us  = d.toLocaleString('en-US', Object.assign({}, opts, {timeZone:'America/New_York'}));
    return {pak: pak + ' PKT', us: us + ' ET'};
}

/* ── Tab / panel switching ───────────────────── */
function switchTab(name){
    var panels = ['productsPanel','adminPanel','analyticsPanel','invoicesPanel','payrollPanel','recycleBinPanel'];
    panels.forEach(function(id){
        var el = document.getElementById(id);
        if(el) el.style.display = 'none';
    });
    document.querySelectorAll('.tab-btn').forEach(function(b){
        b.classList.remove('active');
    });
    var target = document.getElementById(name + 'Panel');
    if(target) target.style.display = 'block';
    var btn = document.getElementById('tab-' + name);
    if(btn) btn.classList.add('active');
    if(name === 'analytics') {
        if(typeof loadDashboardStats === 'function') loadDashboardStats();
    }
    if(name === 'invoices')  initInvoicePanel();
    if(name === 'payroll')   initPayrollPanel();
    if(name === 'recycleBin') {
        if(typeof loadDeletedTasks === 'function') loadDeletedTasks();
    }
    if(name === 'admin'){
        if(typeof loadUsers === 'function') loadUsers();
    }
    fixHeaderSpacer();
}

function toggleAdminPanel()    { switchTab('admin');    }

function toggleInvoicePanel()  { switchTab('invoices'); }
function togglePayrollPanel()  { switchTab('payroll');  }

/* ── Filter / sort / search helpers ─────────── */
function getSortedFiltered(data){
    var filter      = document.getElementById('filter').value;
    var sort        = document.getElementById('sort').value;
    var search      = (document.getElementById('search').value || '').toLowerCase();
    var dateFilter  = document.getElementById('dateFilter')  ? document.getElementById('dateFilter').value  : '';
    var customDate  = document.getElementById('customDate')  ? document.getElementById('customDate').value  : '';
    var workerFilter= document.getElementById('workerFilter')? document.getElementById('workerFilter').value: '';

    var result = data.filter(function(item){
        if (typeof ACTIVE_FAMILY_FILTER !== 'undefined' && ACTIVE_FAMILY_FILTER) {
            return item.family_code === ACTIVE_FAMILY_FILTER;
        }
        var currentStatus = item.status === 'Hold'
            ? 'Hold'
            : (item.work_status && item.work_status !== 'Pending'
                ? item.work_status
                : item.status);

        if(filter === 'Info + A Plus'){ if(item.product_type !== 'Info + A Plus') return false; }
        else if(filter === 'Infographics'){ if(item.product_type !== 'Infographics') return false; }
        else if(filter === 'A+'){ if(item.product_type !== 'A+') return false; }
        else if(filter === 'Invoiced'){ if(item.invoice_status !== 'Invoiced' && item.info_invoice_status !== 'Invoiced' && item.aplus_invoice_status !== 'Invoiced') return false; }
        else if(filter === 'Paid')    { if(item.invoice_status !== 'Paid'     && item.info_invoice_status !== 'Paid'     && item.aplus_invoice_status !== 'Paid')     return false; }
        else if(filter === 'Published'){ if(!item.published_at) return false; }
        else if(filter === 'Work Done'){ if(item.work_status !== 'Work Done' || item.published_at) return false; }
        else if(filter === 'Work Done Only'){
            if(item.work_status !== 'Work Done') return false;
            if(item.invoice_status === 'Invoiced' || item.invoice_status === 'Paid') return false;
            if(item.info_invoice_status === 'Invoiced' || item.info_invoice_status === 'Paid') return false;
            if(item.aplus_invoice_status === 'Invoiced' || item.aplus_invoice_status === 'Paid') return false;
        }
        else if(filter === 'AI Work'){
            if(item.status !== 'AI Work') return false;
        }
        else if(filter === 'AI DONE'){
            if(item.status !== 'AI DONE') return false;
        }
        else if(filter === 'Info Done'){
            if(item.work_status !== 'Info Done' && item.status !== 'Info Done') return false;
        }
        else if(filter === 'Completed'){
            if((item.work_status !== 'Work Done' && item.work_status !== 'Info Done') || item.published_at) return false;
        }
        else if(filter && currentStatus !== filter) return false;

        if(search){
            var cleanSearch = search.replace(/^#/, '').trim();
            var titleLower  = (item.title || '').toLowerCase();
            var prodNoStr   = String(item.product_no || '');
            var prodNoLower = prodNoStr.toLowerCase();
            var cleanProdNo = prodNoLower.replace(/^#/, '').trim();

            var matchTitle = titleLower.indexOf(search) !== -1 || (cleanSearch && titleLower.indexOf(cleanSearch) !== -1);
            var matchProdNo = prodNoLower.indexOf(search) !== -1 ||
                              (cleanSearch && prodNoLower.indexOf(cleanSearch) !== -1) ||
                              (cleanSearch && cleanProdNo.indexOf(cleanSearch) !== -1) ||
                              ('#' + cleanProdNo).indexOf(search) !== -1;

            if(!matchTitle && !matchProdNo) return false;
        }

        if(workerFilter){
            var wId = String(workerFilter);
            var wfEl = document.getElementById('workerFilter');
            var selectedOpt = wfEl ? wfEl.querySelector('option[value="' + wId + '"]') : null;
            var selectedRole = selectedOpt ? (selectedOpt.getAttribute('data-role') || 'worker') : 'worker';
            var matchWorker = false;
            if(selectedRole === 'qa'){
                matchWorker = item.qa_submitted_by ? String(item.qa_submitted_by) === wId : false;
            } else if(selectedRole === 'seo_manager' || selectedRole === 'd4u_writer'){
                matchWorker = (item.written_by_user_id && String(item.written_by_user_id) === wId) || (item.seo_submitted_by && String(item.seo_submitted_by) === wId);
            } else if(selectedRole === 'ai_work'){
                matchWorker = item.ai_worked_by ? String(item.ai_worked_by) === wId : false;
            } else {
                var completedId = item.work_completed_by_worker_id ? String(item.work_completed_by_worker_id) : '';
                var assignedId  = item.assigned_worker_id          ? String(item.assigned_worker_id)          : '';
                matchWorker = (item.work_status === 'Work Done' && completedId)
                    ? completedId === wId
                    : assignedId  === wId;
            }
            if(!matchWorker) return false;
        }

        if(dateFilter){
            var rawDate = item.work_completed_at || item.work_started_at || item.created_at;
            if(rawDate){
                var itemDate  = new Date(rawDate);
                var now       = new Date();
                var today     = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                var yesterday = new Date(today);
                yesterday.setDate(today.getDate() - 1);

                if(dateFilter === 'today'     && itemDate < today) return false;
                if(dateFilter === 'yesterday' && (itemDate < yesterday || itemDate >= today)) return false;
                if(dateFilter === '7days'){
                    var last7 = new Date(today);
                    last7.setDate(today.getDate() - 7);
                    if(itemDate < last7) return false;
                }
                if(dateFilter === 'custom' && customDate){
                    var cd  = new Date(customDate);
                    var cd2 = new Date(customDate);
                    cd2.setDate(cd2.getDate() + 1);
                    if(itemDate < cd || itemDate >= cd2) return false;
                }
            }
        }
        return true;
    });

    result.sort(function(a, b){
        var aUrgent = (a.is_urgent == 1 && a.work_status !== 'Work Done') ? 1 : 0;
        var bUrgent = (b.is_urgent == 1 && b.work_status !== 'Work Done') ? 1 : 0;
        if(aUrgent !== bUrgent) return bUrgent - aUrgent;

        if(sort === 'az')      return a.title.localeCompare(b.title);
        if(sort === 'za')      return b.title.localeCompare(a.title);
        if(sort === 'no_asc')  return parseInt(a.product_no) - parseInt(b.product_no);
        if(sort === 'no_desc') return parseInt(b.product_no) - parseInt(a.product_no);
        if(sort === 'id_asc')  return a.id - b.id;
        if(sort === 'id_desc') return b.id - a.id;

        var aTime = a.last_activity_at || a.work_completed_at || a.work_started_at || a.created_at || '';
        var bTime = b.last_activity_at || b.work_completed_at || b.work_started_at || b.created_at || '';
        if (sort === 'activity_asc') {
            if (aTime < bTime) return -1;
            if (aTime > bTime) return 1;
            return a.id - b.id;
        } else {
            if (aTime > bTime) return -1;
            if (aTime < bTime) return 1;
            return b.id - a.id;
        }
    });
    return result;
}

function resetPageAndRender(){
    CURRENT_PAGE = 1;
    SELECTED_BULK_PRODUCTS = [];
    if(typeof updateBulkActionBar === 'function') updateBulkActionBar();
    renderSmart(ALL_TASKS);
}

/* ── Event listeners — filter bar ───────────── */
document.addEventListener('DOMContentLoaded', function(){
    document.getElementById('search').addEventListener('keyup', resetPageAndRender);
    document.getElementById('filter').addEventListener('change', resetPageAndRender);
    document.getElementById('sort').addEventListener('change', resetPageAndRender);

    document.getElementById('dateFilter').addEventListener('change', function(){
        var custom = document.getElementById('customDate');
        custom.style.display = this.value === 'custom' ? 'block' : 'none';
        resetPageAndRender();
    });
    document.getElementById('customDate').addEventListener('change', resetPageAndRender);

    var wfEl = document.getElementById('workerFilter');
    if(wfEl) wfEl.addEventListener('change', resetPageAndRender);
});

/* ── Work status actions ─────────────────────── */
function updateWorkStatus(taskId, status){
    var fd = new FormData();
    fd.append('action', 'update_work_status');
    fd.append('task_id', taskId);
    fd.append('status', status);
    fetch('index.php', {method:'POST', body:fd})
        .then(r => { if(!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(r => {
            if(r.success) loadTasks();
            else alert('Error: ' + (r.message || 'Task assigned nahi hai ya status already set hai'));
        });
}

function pauseWork(taskId){
    _confirm('Kaam pause karna chahte hain? Aap ka progress save ho jayega.', function(){
        var fd = new FormData();
        fd.append('action', 'pause_work');
        fd.append('task_id', taskId);
        fetch('index.php', {method:'POST', body:fd})
            .then(r => { if(!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(r => { if(r.success) loadTasks(); else alert('Pause failed'); });
    });
}

function resumeWork(taskId){
    var fd = new FormData();
    fd.append('action', 'resume_work');
    fd.append('task_id', taskId);
    fetch('index.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(r => { if(r.success) loadTasks(); else alert('Resume failed'); });
}

function holdProduct(taskId, hold){
    var msg = hold ? 'Is product ko Hold par set karein?' : 'Is product ko Hold se wapas karein?';
    _confirm(msg, function(){
        var fd = new FormData();
        fd.append('action', 'hold_product');
        fd.append('task_id', taskId);
        fd.append('hold', hold);
        fetch('index.php', {method:'POST', body:fd})
            .then(r => r.json())
            .then(r => { if(r.success) loadTasks(); else alert('Failed: ' + (r.message || '')); });
    });
}

function setUrgent(taskId, urgent){
    var msg = urgent
        ? 'Is product ko URGENT mark karein? Ye hamesha top per rahegi.'
        : 'Is product se URGENT tag hatayein?';
    _confirm(msg, function(){
        var fd = new FormData();
        fd.append('action', 'toggle_urgent');
        fd.append('task_id', taskId);
        fd.append('urgent', urgent);
        fetch('index.php', {method:'POST', body:fd})
            .then(r => r.json())
            .then(r => { if(r.success) loadTasks(); else alert('Failed: ' + (r.message || '')); });
    });
}

function setProductType(taskId, type){
    var fd = new FormData();
    fd.append('action', 'set_product_type');
    fd.append('task_id', taskId);
    fd.append('product_type', type);
    fetch('index.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(r => { if(r.success) loadTasks(); else alert('Failed'); });
}

function clearInvoiceStatus(taskId){
    _confirm('Is product ka Invoiced badge remove karein?', function(){
        var fd = new FormData();
        fd.append('action', 'clear_invoice_status');
        fd.append('task_id', taskId);
        fetch('index.php', {method:'POST', body:fd})
            .then(r => r.json())
            .then(r => { if(r.success) loadTasks(); else alert('Failed: ' + (r.message || '')); });
    });
}

function sendForContent(taskId){
    _confirm('Is product ko content ke liye send karein? Status "Content Pending" ho jayega.', function(){
        var fd = new FormData();
        fd.append('action', 'send_for_content');
        fd.append('task_id', taskId);
        fetch('index.php', {method:'POST', body:fd})
            .then(r => r.json())
            .then(r => { if(r.success) loadTasks(); else alert('Failed: ' + (r.message || 'Unknown error')); });
    });
}

/* ── Publish / Unpublish ─────────────────────── */
function publishProduct(taskId){
    _confirm('Is product ko Published mark karein?', function(){
        var fd = new FormData();
        fd.append('action', 'publish_product');
        fd.append('task_id', taskId);
        var pl = document.getElementById('pub-link-' + taskId);
        fd.append('published_link', pl ? pl.value.trim() : '');
        fetch('index.php', {method:'POST', body:fd})
            .then(r => r.json())
            .then(r => { if(r.success) loadTasks(); else alert('Publish failed: ' + (r.message || 'Unknown error')); })
            .catch(function(err){ alert('Request failed: ' + err.message); });
    });
}

function unpublishProduct(taskId){
    _confirm('Published status reset karein? Product wapas Work Done mode par aa jayega.', function(){
        var fd = new FormData();
        fd.append('action', 'unpublish_product');
        fd.append('task_id', taskId);
        fetch('index.php', {method:'POST', body:fd})
            .then(r => r.json())
            .then(r => { if(r.success) loadTasks(); else alert('Reset failed: ' + (r.message || 'Unknown error')); })
            .catch(function(err){ alert('Request failed: ' + err.message); });
    });
}

/* ── Force stage (admin) ─────────────────────── */
function forceStage(taskId, stage){
    _confirm('Stage change karein: ' + stage + ' ?', function(){
        var fd = new FormData();
        fd.append('action', 'force_stage');
        fd.append('task_id', taskId);
        fd.append('stage', stage);
        fetch('index.php', {method:'POST', body:fd})
            .then(r => { if(!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(r => { if(r.success) loadTasks(); else alert('Stage update failed: ' + (r.message || 'Unknown error')); })
            .catch(function(err){ alert('Request failed: ' + err.message); });
    });
}

/* ── Add product ─────────────────────────────── */
function toggleAddProductCard(){
    var card = document.getElementById('addProductCard');
    var btn  = document.getElementById('addProductBtn');
    if(card.style.display === 'none' || card.style.display === ''){
        card.style.display = 'block';
        btn.textContent    = '✕ Cancel';
        btn.style.background = '#475569';
        document.getElementById('ap-product-no').value      = '';
        document.getElementById('ap-title').value           = '';
        document.getElementById('ap-product-link').value    = '';
        if(document.getElementById('ap-aiwork')) document.getElementById('ap-aiwork').checked = false;
        document.getElementById('ap-infographics').checked  = false;
        document.getElementById('ap-aplus').checked         = false;
        document.getElementById('ap-msg').textContent       = '';
        document.getElementById('ap-product-no').focus();
        card.scrollIntoView({behavior:'smooth', block:'start'});
    } else {
        closeAddProductCard();
    }
}

function closeAddProductCard(){
    var card = document.getElementById('addProductCard');
    var btn  = document.getElementById('addProductBtn');
    if(card){ card.style.display = 'none'; }
    if(btn){ btn.textContent = '+ Add Product'; btn.style.background = '#c2410c'; }
}

function saveAddProduct(){
    var pno   = document.getElementById('ap-product-no').value.trim();
    var title = document.getElementById('ap-title').value.trim();
    var link  = document.getElementById('ap-product-link').value.trim();
    var msg   = document.getElementById('ap-msg');
    if(!pno || !title){ msg.style.color = '#f87171'; msg.textContent = 'Product No aur Title required hain'; return; }
    var subtasks = [];
    if(document.getElementById('ap-aiwork') && document.getElementById('ap-aiwork').checked) subtasks.push('AI Work');
    if(document.getElementById('ap-infographics') && document.getElementById('ap-infographics').checked) subtasks.push('Infographics');
    if(document.getElementById('ap-aplus') && document.getElementById('ap-aplus').checked) subtasks.push('A+ Banners');
    var fd = new FormData();
    fd.append('action', 'add_product');
    fd.append('product_no', pno);
    fd.append('title', title);
    fd.append('product_link', link);
    fd.append('info_subtasks', subtasks.join(','));
    fetch('index.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(r => {
            if(r.success){
                msg.style.color  = '#4ade80';
                msg.textContent  = '✅ Product #' + pno + ' add ho gaya!';
                loadTasks();
                setTimeout(function(){ closeAddProductCard(); }, 1500);
            } else {
                msg.style.color = '#f87171'; msg.textContent = r.message || 'Error';
            }
        });
}

/* ── Password change (admin) ─────────────────── */
function changeMyPassword(){
    var cur = document.getElementById('cp-current').value;
    var n1  = document.getElementById('cp-new1').value;
    var n2  = document.getElementById('cp-new2').value;
    var msg = document.getElementById('cp-msg');
    if(!cur || !n1 || !n2){ msg.style.color = '#f87171'; msg.textContent = 'Tamam fields bharein'; return; }
    var fd = new FormData();
    fd.append('action', 'change_password');
    fd.append('current_password', cur);
    fd.append('new_password', n1);
    fd.append('confirm_password', n2);
    fetch('index.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(r => {
            msg.style.color = r.success ? '#4ade80' : '#f87171';
            msg.textContent = r.message || '';
            if(r.success){
                document.getElementById('cp-current').value = '';
                document.getElementById('cp-new1').value    = '';
                document.getElementById('cp-new2').value    = '';
                setTimeout(function(){ msg.textContent = ''; }, 4000);
            }
        });
}

/* ── Force logout all (admin) ────────────────── */
function forceLogoutAll(){
    _confirm('Tamam users ko logout kar dein?\n\nSab ko dobara login karna parega. Aap khud logout nahi honge.', function(){
        var fd = new FormData();
        fd.append('action', 'force_logout_all');
        fetch('index.php', {method:'POST', body:fd})
            .then(r => { if(!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(r => {
                if(r.success) alert('✅ Done! Tamam users ki sessions expire ho gayi hain.');
                else alert('Failed: ' + (r.message || 'Unknown error'));
            })
            .catch(function(err){ alert('Force logout failed: ' + err.message); });
    });
}

/* ── Page init ───────────────────────────────── */
window.addEventListener('resize', fixHeaderSpacer);
setTimeout(fixHeaderSpacer, 300);

/* ── Custom confirm modal (Firefox-safe) ────────────── */
function _confirm(msg, onYes){
    var existing = document.getElementById('_eco_confirm_modal');
    if(existing) existing.parentNode.removeChild(existing);

    var modal = document.createElement('div');
    modal.id = '_eco_confirm_modal';
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:99999;display:flex;align-items:center;justify-content:center;';
    
    // Format message to handle newlines
    var safeMsg = String(msg).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');

    modal.innerHTML =
        '<div style="background:#0f2035;border:1px solid #1e3a5f;border-radius:12px;padding:28px 32px;max-width:420px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.6);">'
        + '<div style="display:flex;align-items:center;gap:10px;margin-bottom:15px;">'
        + '<span style="font-size:20px;">❓</span><span style="font-weight:700;color:#93c5fd;font-size:14px;">ecousa.deziner4you.com</span>'
        + '</div>'
        + '<div style="color:#e2e8f0;font-size:15px;font-weight:600;margin-bottom:22px;line-height:1.5;">' + safeMsg + '</div>'
        + '<div style="display:flex;gap:10px;justify-content:flex-end;">'
        + '<button id="_eco_confirm_no"  style="padding:8px 22px;border-radius:6px;border:1px solid #334155;background:#1e293b;color:#94a3b8;font-size:13px;font-weight:600;cursor:pointer;">Cancel</button>'
        + '<button id="_eco_confirm_yes" style="padding:8px 22px;border-radius:6px;border:none;background:#dc2626;color:#fff;font-size:13px;font-weight:700;cursor:pointer;">Confirm</button>'
        + '</div></div>';

    document.body.appendChild(modal);

    function _close(){ document.body.removeChild(modal); }

    document.getElementById('_eco_confirm_yes').onclick = function(){ _close(); onYes(); };
    document.getElementById('_eco_confirm_no').onclick  = function(){ _close(); };
    modal.onclick = function(e){ if(e.target === modal) _close(); };
}

/* ── Custom prompt modal override ────────────── */
function _prompt(msg, defaultVal, onResult) {
    var existing = document.getElementById('_eco_prompt_modal');
    if(existing) existing.parentNode.removeChild(existing);

    var modal = document.createElement('div');
    modal.id = '_eco_prompt_modal';
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:99999;display:flex;align-items:center;justify-content:center;';
    
    var safeMsg = String(msg).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');

    modal.innerHTML =
        '<div style="background:#0f2035;border:1px solid #1e3a5f;border-radius:12px;padding:28px 32px;max-width:420px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.6);">'
        + '<div style="display:flex;align-items:center;gap:10px;margin-bottom:15px;">'
        + '<span style="font-size:20px;">📝</span><span style="font-weight:700;color:#93c5fd;font-size:14px;">ecousa.deziner4you.com</span>'
        + '</div>'
        + '<div style="color:#e2e8f0;font-size:15px;font-weight:600;margin-bottom:15px;line-height:1.5;">' + safeMsg + '</div>'
        + '<div style="margin-bottom:22px;"><input type="text" id="_eco_prompt_input" value="' + (defaultVal || '') + '" style="width:100%;padding:10px;border-radius:6px;border:1px solid #334155;background:#0a1628;color:#fff;font-size:14px;outline:none;" /></div>'
        + '<div style="display:flex;gap:10px;justify-content:flex-end;">'
        + '<button id="_eco_prompt_cancel" style="padding:8px 22px;border-radius:6px;border:1px solid #334155;background:#1e293b;color:#94a3b8;font-size:13px;font-weight:600;cursor:pointer;">Cancel</button>'
        + '<button id="_eco_prompt_ok" style="padding:8px 26px;border-radius:6px;border:none;background:#0ea5e9;color:#fff;font-size:13px;font-weight:700;cursor:pointer;">OK</button>'
        + '</div></div>';

    document.body.appendChild(modal);
    
    var inp = document.getElementById('_eco_prompt_input');
    inp.focus();
    inp.select();

    function _close(){ document.body.removeChild(modal); }

    document.getElementById('_eco_prompt_ok').onclick = function(){ 
        var val = inp.value;
        _close(); 
        onResult(val);
    };
    document.getElementById('_eco_prompt_cancel').onclick = function(){ 
        _close(); 
        onResult(null);
    };
    modal.onclick = function(e){ if(e.target === modal) { _close(); onResult(null); } };
    inp.onkeydown = function(e){ if(e.key === 'Enter') document.getElementById('_eco_prompt_ok').click(); if(e.key === 'Escape') document.getElementById('_eco_prompt_cancel').click(); };
}

/* ── Custom alert modal override ────────────── */
window.alert = function(msg) {
    var existing = document.getElementById('_eco_alert_modal');
    if(existing) existing.parentNode.removeChild(existing);

    var modal = document.createElement('div');
    modal.id = '_eco_alert_modal';
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:99999;display:flex;align-items:center;justify-content:center;';
    
    // Format message to handle newlines
    var safeMsg = String(msg).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');

    modal.innerHTML =
        '<div style="background:#0f2035;border:1px solid #1e3a5f;border-radius:12px;padding:28px 32px;max-width:420px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.6);">'
        + '<div style="display:flex;align-items:center;gap:10px;margin-bottom:15px;">'
        + '<span style="font-size:20px;">🌐</span><span style="font-weight:700;color:#93c5fd;font-size:14px;">ecousa.deziner4you.com</span>'
        + '</div>'
        + '<div style="color:#e2e8f0;font-size:15px;font-weight:600;margin-bottom:22px;line-height:1.5;">' + safeMsg + '</div>'
        + '<div style="display:flex;justify-content:flex-end;">'
        + '<button id="_eco_alert_ok" style="padding:8px 26px;border-radius:6px;border:none;background:#0ea5e9;color:#fff;font-size:13px;font-weight:700;cursor:pointer;">OK</button>'
        + '</div></div>';

    document.body.appendChild(modal);

    function _close(){ document.body.removeChild(modal); }
    document.getElementById('_eco_alert_ok').onclick = function(){ _close(); };
    modal.onclick = function(e){ if(e.target === modal) _close(); };
};

/* ── Topbar Notification System ──────────────── */
function escapeNotifHtml(str){
    if(!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function toggleNotifDropdown(e){
    if(e){
        e.preventDefault();
        e.stopPropagation();
    }
    var drop = document.getElementById('notif-dropdown');
    if(!drop) return;
    if(drop.style.display === 'none' || !drop.style.display){
        drop.style.display = 'flex';
        // Recalculate and update based on current ALL_TASKS
        if(typeof ALL_TASKS !== 'undefined' && Array.isArray(ALL_TASKS)){
            updateNotifications(ALL_TASKS);
        }
    } else {
        drop.style.display = 'none';
    }
}

function closeNotifDropdown(e){
    if(e){
        e.preventDefault();
        e.stopPropagation();
    }
    var drop = document.getElementById('notif-dropdown');
    if(drop) drop.style.display = 'none';
}

document.addEventListener('click', function(e){
    var container = document.getElementById('nav-notif-container');
    if(container && !container.contains(e.target)){
        var drop = document.getElementById('notif-dropdown');
        if(drop) drop.style.display = 'none';
    }
});

document.addEventListener('keydown', function(e){
    if(e.key === 'Escape'){
        var drop = document.getElementById('notif-dropdown');
        if(drop) drop.style.display = 'none';
    }
});

function updateNotifications(tasks){
    if(!Array.isArray(tasks)) return;
    var count = 0;
    var notifItems = [];
    var subtitle = 'Notifications';
    var uRole = (typeof ROLE !== 'undefined' ? ROLE : '');
    var uId = (typeof USER_ID !== 'undefined' ? parseInt(USER_ID) : 0);
    var uName = (typeof USERNAME !== 'undefined' ? USERNAME.toLowerCase() : '');

    if(uRole === 'worker' || uRole === 'ai_work'){
        subtitle = 'Products assigned to you';
        notifItems = tasks.filter(function(t){
            if(t.status === 'Hold' || t.deleted_at) return false;
            if(t.work_status === 'Work Done' || t.work_status === 'Info Done') return false;
            return (parseInt(t.assigned_worker_id) === uId || 
                    parseInt(t.info_worker_id) === uId || 
                    parseInt(t.aplus_worker_id) === uId || 
                    parseInt(t.ai_worked_by) === uId);
        });
    } else if(uRole === 'eco_listing' || uName === 'ecolisting'){
        subtitle = 'Completed products to list (Info Done / Work Done)';
        notifItems = tasks.filter(function(t){
            if(t.status === 'Hold' || t.deleted_at) return false;
            return (t.work_status === 'Work Done' || t.work_status === 'Info Done') && !t.published_at;
        });
    } else if(uRole === 'qa'){
        subtitle = 'Products currently in QA review';
        notifItems = tasks.filter(function(t){
            if(t.status === 'Hold' || t.deleted_at) return false;
            return t.work_status === 'In QA';
        });
    } else if(uRole === 'seo_manager' || uRole === 'd4u_writer'){
        subtitle = 'Products pending content writing & SEO review';
        notifItems = tasks.filter(function(t){
            if(t.status === 'Hold' || t.deleted_at) return false;
            return t.work_status === 'SEO Review' || (t.status === 'Pending' && t.product_type !== 'Infographics');
        });
    } else if(uRole === 'eco_client'){
        subtitle = 'Products in Generated awaiting review';
        notifItems = tasks.filter(function(t){
            if(t.status === 'Hold' || t.deleted_at) return false;
            return t.status === 'Generated' && t.work_status !== 'Work Done' && t.work_status !== 'Info Done';
        });
    } else if(uRole === 'administrator'){
        subtitle = 'Products requiring QA or ready to publish';
        notifItems = tasks.filter(function(t){
            if(t.status === 'Hold' || t.deleted_at) return false;
            return t.work_status === 'In QA' || ((t.work_status === 'Work Done' || t.work_status === 'Info Done') && !t.published_at);
        });
    }

    count = notifItems.length;

    // Update bell badge
    var badge = document.getElementById('notif-badge');
    if(badge){
        if(count > 0){
            badge.innerText = count;
            badge.style.display = 'inline-flex';
        } else {
            badge.innerText = '0';
            badge.style.display = 'none';
        }
    }

    // Update pill and subtitle inside dropdown
    var pill = document.getElementById('notif-count-pill');
    if(pill) pill.innerText = count;
    var subEl = document.getElementById('notif-subtitle');
    if(subEl) subEl.innerText = subtitle;

    // Render items list inside dropdown
    var listEl = document.getElementById('notif-list');
    if(listEl){
        if(notifItems.length === 0){
            listEl.innerHTML = '<div class="notif-empty">✨ No pending notifications</div>';
        } else {
            var html = '';
            notifItems.slice(0, 50).forEach(function(item){
                var sClass = 'pending';
                if(item.work_status === 'Work Done') sClass = 'work-done';
                else if(item.work_status === 'Info Done') sClass = 'info-done';
                else if(item.work_status === 'In QA') sClass = 'in-qa';
                else if(item.work_status === 'Working' || item.work_status === 'Info Working') sClass = 'working';

                var pNo = escapeNotifHtml(item.product_no || '');
                var title = escapeNotifHtml(item.title || 'Untitled Product');
                var wStatus = escapeNotifHtml(item.work_status || item.status || 'Pending');
                var pType = item.product_type ? ('<div class="notif-item-meta">📦 ' + escapeNotifHtml(item.product_type) + '</div>') : '';

                html += '<div class="notif-item" onclick="openNotifTask(' + item.id + ', \'' + pNo.replace(/'/g, "\\'") + '\')">'
                      +   '<div class="notif-item-header">'
                      +     '<span class="notif-item-prodno">' + pNo + '</span>'
                      +     '<span class="notif-item-tag ' + sClass + '">' + wStatus + '</span>'
                      +   '</div>'
                      +   '<div class="notif-item-title">' + title + '</div>'
                      +   pType
                      + '</div>';
            });
            listEl.innerHTML = html;
        }
    }
}

function openNotifTask(taskId, productNo){
    closeNotifDropdown();
    if(typeof switchTab === 'function') switchTab('products');

    var sInput = document.getElementById('search');
    var fSel   = document.getElementById('filter');
    if(fSel) fSel.value = '';
    if(sInput && productNo){
        sInput.value = productNo;
        if(typeof resetPageAndRender === 'function') resetPageAndRender();
    }

    setTimeout(function(){
        var card = document.getElementById('card-' + taskId);
        if(card){
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if(!card.classList.contains('open') && typeof toggleCard === 'function'){
                toggleCard(taskId);
            }
            card.classList.remove('card-notif-highlight');
            void card.offsetWidth; // trigger reflow
            card.classList.add('card-notif-highlight');
        }
    }, 250);
}

function filterCompletedTasks(){
    closeNotifDropdown();
    if(typeof switchTab === 'function') switchTab('products');
    var sInput = document.getElementById('search');
    if(sInput) sInput.value = '';
    var fSel = document.getElementById('filter');
    if(fSel){
        fSel.value = 'Completed';
        if(typeof resetPageAndRender === 'function') resetPageAndRender();
    }
}

function filterMyTasks(){
    closeNotifDropdown();
    if(typeof switchTab === 'function') switchTab('products');
    var sInput = document.getElementById('search');
    if(sInput) sInput.value = '';
    var fSel = document.getElementById('filter');
    if(fSel){
        fSel.value = '';
        if(typeof resetPageAndRender === 'function') resetPageAndRender();
    }
}

