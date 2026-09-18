/*
=====================================================
ECO A+ PRO — tasks.js
loadTasks, renderSmart, buildCard, pagination,
diff viewer, blunder system, worker assignment,
QA submit, save/clear/delete task.
Depends on: core.js
=====================================================
*/

var ACTIVE_FAMILY_FILTER = null;

/* ── Load tasks ──────────────────────────────── */
function loadTasks(){
    fetch('index.php?action=get_tasks')
        .then(r => r.json())
        .then(r => {
            if(r.force_logout){ window.location.href = 'index.php'; return; }
            ALL_TASKS = r.data;
            renderSmart(r.data);
            if(typeof updateNotifications === 'function'){
                updateNotifications(r.data);
            }
        });
}

/* ── Workers (admin only) ────────────────────── */
function loadWorkersAndProducts(){
    fetch('index.php?action=get_workers')
        .then(r => r.json())
        .then(r => {
            ALL_WORKERS = r.data;
            populateCardWorkerDropdowns();
            var sel = document.getElementById('assign-worker');
            if(sel){
                sel.innerHTML = '<option value="">-- Select Worker --</option>';
                r.data.forEach(function(w){
                    sel.innerHTML += '<option value="' + w.id + '">' + w.username + '</option>';
                });
            }
        });
}

function loadPayrollWorkers(){
    fetch('index.php?action=get_payroll_workers')
        .then(r => r.json())
        .then(r => {
            ALL_PAYROLL_WORKERS = r.data;
            var wf = document.getElementById('workerFilter');
            if(wf){
                wf.innerHTML = '<option value="">All Workers</option>';
                r.data.forEach(function(w){
                    wf.innerHTML += '<option value="' + w.id + '" data-role="' + w.role + '">' + w.username + '</option>';
                });
            }
            var ps = document.getElementById('ps-worker');
            if(ps){
                ps.innerHTML = '<option value="">-- Select Worker --</option>';
                r.data.forEach(function(w){
                    var roleLabel = w.role === 'seo_manager' ? ' (SEO)' : w.role === 'd4u_writer' ? ' (Writer)' : w.role === 'ai_work' ? ' (AI)' : w.role === 'qa' ? ' (QA)' : '';
                    ps.innerHTML += '<option value="' + w.id + '" data-role="' + w.role + '">' + w.username + roleLabel + '</option>';
                });
            }
            var pwf = document.getElementById('penalty-worker-filter');
            if(pwf){
                pwf.innerHTML = '<option value="">-- All Workers --</option>';
                r.data.forEach(function(w){
                    pwf.innerHTML += '<option value="' + w.id + '">' + w.username + '</option>';
                });
            }
        });
}

function populateCardWorkerDropdowns(){
    if(!ALL_WORKERS.length) return;
    document.querySelectorAll('.inline-assign-sel').forEach(function(sel){
        if(!sel.id || sel.id.indexOf('aw-') !== 0) return;
        var taskId = sel.id.replace('aw-', '');
        var task   = ALL_TASKS.find(function(t){ return String(t.id) === taskId; });
        var aId    = task ? String(task.assigned_worker_id || '') : '';
        sel.innerHTML = '<option value="">👷 Worker...</option>';
        var assignLocked = task && (task.work_status === 'In QA' || task.work_status === 'Work Done' || task.work_status === 'Info Done');
        var softLocked   = task && (task.work_status === 'Working' || task.work_status === 'Paused');
        sel.disabled            = !!(assignLocked || softLocked);
        sel.style.opacity       = assignLocked ? '0.45' : '';
        sel.style.cursor        = assignLocked ? 'not-allowed' : '';
        sel.style.pointerEvents = assignLocked ? 'none' : '';
        ALL_WORKERS.forEach(function(w){
            sel.innerHTML += '<option value="' + w.id + '"' + (String(w.id) === aId ? ' selected' : '') + '>' + w.username + '</option>';
        });
        var btn = document.getElementById('abtn-' + taskId);
        if(btn){
            if(assignLocked){
                btn.disabled = true; btn.style.opacity = '0.45';
                btn.style.cursor = 'not-allowed'; btn.style.pointerEvents = 'none';
            } else {
                btn.disabled = false; btn.style.opacity = '';
                btn.style.cursor = ''; btn.style.pointerEvents = '';
            }
            if(aId){ btn.textContent = '✅ Assigned'; btn.classList.add('assigned'); }
            else    { btn.textContent = 'Assign';     btn.classList.remove('assigned'); }
        }
    });
}

function assignFromCard(taskId){
    var sel = document.getElementById('aw-' + taskId);
    if(!sel || !sel.value){ alert('Please select a worker'); return; }
    doAssign(sel.value, taskId, taskId);
}

function doAssign(workerId, taskId, cardId){
    var fd = new FormData();
    fd.append('action', 'assign_product');
    fd.append('worker_id', workerId);
    fd.append('task_id', taskId);
    fetch('index.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(r => {
            if(cardId){
                var btn = document.getElementById('abtn-' + cardId);
                if(btn){
                    if(r.success){ btn.textContent = '✅ Assigned'; btn.classList.add('assigned'); }
                    else { btn.textContent = '❌ Failed'; setTimeout(function(){ btn.textContent = 'Assign'; btn.classList.remove('assigned'); }, 2500); }
                }
            }
            if(r.success){
                var t = ALL_TASKS.find(function(x){ return String(x.id) === String(taskId); });
                if(t) t.assigned_worker_id = workerId;
                if(typeof loadTasks === 'function') loadTasks();
            }
        });
}

/* ── Render / pagination ─────────────────────── */
function render(data){ renderSmart(data); }

function renderSmart(data){
    var container = document.getElementById('tasks');
    if(!container) return;

    var oldBanner = document.getElementById('family-filter-banner');
    if(oldBanner) oldBanner.remove();

    if (ACTIVE_FAMILY_FILTER) {
        var banner = document.createElement('div');
        banner.id = 'family-filter-banner';
        banner.style.cssText = 'padding:12px 16px; background:#8b5cf6; color:#fff; border-radius:8px; margin: 10px 12px; display:flex; justify-content:space-between; align-items:center; font-size:13px; font-weight:600; box-shadow:0 2px 8px rgba(0,0,0,0.2);';
        banner.innerHTML = '<span>Showing Group Family Products</span>'
            + '<button onclick="clearFamilyFilter()" style="background:#ef4444; color:#fff; padding:6px 12px; border-radius:6px; font-size:12px; font-weight:700; border:none; cursor:pointer; transition:background 0.2s;">Show All Products</button>';
        container.before(banner);
    }

    var filtered  = getSortedFiltered(data);
    var totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    if(CURRENT_PAGE > totalPages) CURRENT_PAGE = totalPages;
    var start     = (CURRENT_PAGE - 1) * PAGE_SIZE;
    var pageItems = filtered.slice(start, start + PAGE_SIZE);

    container.querySelectorAll('.card').forEach(function(card){
        var cardId = parseInt(card.getAttribute('data-id'));
        var still  = pageItems.find(function(t){ return t.id == cardId; });
        if(!still) card.remove();
    });

    pageItems.forEach(function(item){
        var existing = container.querySelector('.card[data-id="' + item.id + '"]');

        if(existing){
            var _newWS  = String(item.work_status || '');
            var _newCA  = item.content_approved_at || '';
            var _newCU  = item.content_updated_at  || '';
            var _newPub = item.published_at ? '1' : '0';
            var _newFam = item.family_code || '';
            var _needRebuild = existing.getAttribute('data-work-status') !== _newWS
                            || existing.getAttribute('data-approved-at')  !== _newCA
                            || existing.getAttribute('data-updated-at')   !== _newCU
                            || existing.getAttribute('data-published')    !== _newPub
                            || existing.getAttribute('data-family-code')  !== _newFam;
            if(_needRebuild){
                existing.setAttribute('data-work-status', _newWS);
                existing.setAttribute('data-approved-at', _newCA);
                existing.setAttribute('data-updated-at',  _newCU);
                existing.setAttribute('data-published',   _newPub);
                existing.setAttribute('data-family-code', _newFam);
                existing.innerHTML = buildCard(item);
                if(ROLE === 'administrator' && ALL_WORKERS.length) populateCardWorkerDropdowns();
                updateButtons(existing, item);
                return;
            }

            var badge = existing.querySelector('.status');
            if(badge){
                var ds = getDisplayStatus(item);
                badge.className  = 'status ' + ds.cls;
                badge.textContent = ds.label;
            }

            var lock   = item.status === 'Approved' || item.status === 'Updated';
            var editor = existing.querySelector('.editor');
            if(editor){
                var editorContent = (item.content || '');
                if(!IS_EDITING[item.id]) editor.innerHTML = editorContent;
                var canEdit = ((ROLE === 'd4u_writer' || ROLE === 'seo_manager' || ROLE === 'ai_work') && item.status === 'Pending')
                           || (ROLE === 'eco_client'  && item.status === 'Generated')
                           || (ROLE === 'administrator');
                editor.contentEditable = canEdit ? 'true' : 'false';
                editor.className = 'editor' + (lock ? ' locked' : '');
            }

            if(ROLE === 'administrator'){
                var sel = existing.querySelector('#aw-' + item.id);
                var btn = existing.querySelector('#abtn-' + item.id);
                if(sel && ALL_WORKERS.length){
                    var aId = String(item.assigned_worker_id || '');
                    sel.innerHTML = '<option value="">👷 Worker...</option>';
                    ALL_WORKERS.forEach(function(w){
                        sel.innerHTML += '<option value="' + w.id + '"' + (String(w.id) === aId ? ' selected' : '') + '>' + w.username + '</option>';
                    });
                }
                if(btn){
                    if(item.assigned_worker_id){ btn.textContent = '✅ Assigned'; btn.classList.add('assigned'); }
                    else { btn.textContent = 'Assign'; btn.classList.remove('assigned'); }
                }
            }

            updateButtons(existing, item);
            if(item.is_urgent == 1 && item.work_status !== 'Work Done') existing.classList.add('urgent-card');
            else existing.classList.remove('urgent-card');
            return;
        }

        var div = document.createElement('div');
        div.className = 'card' 
            + (item.is_urgent == 1 && item.work_status !== 'Work Done' ? ' urgent-card' : '')
            + (OPEN[item.id] ? ' open' : '');
        div.setAttribute('data-id',          item.id);
        div.setAttribute('data-work-status', item.work_status || '');
        div.setAttribute('data-approved-at', item.content_approved_at || '');
        div.setAttribute('data-updated-at',  item.content_updated_at  || '');
        div.setAttribute('data-published',   item.published_at ? '1' : '0');
        div.setAttribute('data-family-code', item.family_code || '');
        div.innerHTML = buildCard(item);
        container.appendChild(div);

        if(ROLE === 'administrator' && ALL_WORKERS.length) populateCardWorkerDropdowns();
        if((ROLE === 'administrator' || ROLE === 'worker') && (item.status === 'Approved' || item.status === 'Updated') &&
           item.original_content && item.original_content !== item.content){
            renderDiff(item.id, item.original_content, item.content);
        }
    });

    renderPagination(filtered.length, totalPages);
    updateHeaderSpacer();
    if(typeof updateBulkActionBar === 'function') updateBulkActionBar();
}

/* ── Status label/class helper ───────────────── */
function getDisplayStatus(item){
    if(item.status === 'Hold')                                 return {cls:'Hold',          label:'Hold'};
    if(item.status === 'AI Work')                              return {cls:'AIWork',        label:'AI Work'};
    if(item.status === 'AI DONE' && (!item.assigned_worker_id || item.assigned_worker_id == 0) && (!item.work_status || item.work_status === 'Pending')) return {cls:'AIDone', label:'AI DONE'};
    if(item.work_status === 'Changes')                         return {cls:'Changes',       label:'Changes in Design'};
    if(item.work_status === 'Changes in Content')              return {cls:'ChangesInContent',label:'Changes in Content'};
    if(item.work_status === 'Changing')                        return {cls:'Changing',      label:'Changing'};
    if(item.work_status === 'Republish')                       return {cls:'Republish',     label:'Republish'};
    if(item.work_status === 'Info Work')                       return {cls:'InfoWork',      label:'Info Work'};
    if(item.work_status === 'Content Pending')                 return {cls:'ContentPending',label:'Content Pending'};
    if(item.work_status === 'Working')                         return {cls:'Working',       label:'Working'};
    if(item.work_status === 'Paused')                          return {cls:'Paused',        label:'Paused'};
    if(item.work_status === 'In QA')                           return {cls:'InQA',          label:'In QA'};
    if(item.work_status === 'SEO Review')                      return {cls:'SEOReview',     label:'SEO Review'};
    if(item.work_status === 'Work Done' && item.published_at)  return {cls:'Published',     label:'Published'};
    if(item.work_status === 'Work Done')                       return {cls:'WorkDone',      label:'Work Done'};
    if(item.work_status === 'Info Done')                       return {cls:'InfoDone',      label:'Info Done'};
    if((item.product_type === 'Infographics' || item.product_type === 'Info + A Plus') && (item.status === 'AI DONE' || item.status === 'Pending') && item.assigned_worker_id > 0 && (item.work_status === 'Pending' || !item.work_status)){
        return {cls: 'Pending', label: 'Infographics'};
    }
    if(item.product_type === 'Info + A Plus' && item.status === 'Pending' && (item.work_status === 'Pending' || !item.work_status)){
        return {cls: 'Pending', label: 'Infographics'};
    }
    return {cls: item.status, label: item.status};
}

function renderPagination(totalItems, totalPages){
    var pager = document.getElementById('pager');
    if(!pager) return;
    if(totalItems <= PAGE_SIZE){ pager.innerHTML = ''; return; }
    var start = ((CURRENT_PAGE - 1) * PAGE_SIZE) + 1;
    var end   = Math.min(CURRENT_PAGE * PAGE_SIZE, totalItems);
    pager.innerHTML = `
<button ${CURRENT_PAGE<=1?'disabled':''} onclick="changePage(${CURRENT_PAGE-1})">Previous</button>
<span>${start}-${end} of ${totalItems} | Page ${CURRENT_PAGE} of ${totalPages}</span>
<button ${CURRENT_PAGE>=totalPages?'disabled':''} onclick="changePage(${CURRENT_PAGE+1})">Next</button>`;
}

function changePage(page){
    CURRENT_PAGE = page;
    renderSmart(ALL_TASKS);
    window.scrollTo({top:0, behavior:'smooth'});
}

/* ── Update buttons ──────────────────────────── */
function updateButtons(cardEl, item){
    var wBtn = cardEl.querySelector('.btn-writer-save');
    if(wBtn) wBtn.disabled = item.status !== 'Pending';

    if(ROLE === 'eco_client' || ROLE === 'administrator'){
        if(item.work_status === 'Work Done') return;
        var uBtn = cardEl.querySelector('[id="u-' + item.id + '"]');
        var aBtn = cardEl.querySelector('[id="a-' + item.id + '"]');
        if(!uBtn || !aBtn) return;
        var lock = item.status === 'Approved' || item.status === 'Updated';
        if(lock){
            uBtn.disabled = true; aBtn.disabled = true;
        } else if(item.status === 'Generated'){
            var editor = cardEl.querySelector('.editor');
            if(editor && IS_EDITING[item.id]){
                /* keep current state */
            } else {
                uBtn.disabled = true; aBtn.disabled = false;
            }
        } else {
            uBtn.disabled = true; aBtn.disabled = true;
        }
    }
}

/* ── Build card HTML ─────────────────────────── */
function buildCard(item){
    var lock         = item.status === 'Approved' || item.status === 'Updated';
    var isInfoStage  = (item.product_type === 'Infographics')
                    || (item.product_type === 'Info + A Plus' && (item.status === 'Pending' || item.status === 'AI DONE' || item.status === 'Infographics') && !item.content_approved_at && !item.content_updated_at);
    var isWorker     = ROLE === 'worker';
    var isQa         = ROLE === 'qa';
    var isAdmin      = ROLE === 'administrator';
    var isListing    = ROLE === 'eco_listing';
    var isClient     = ROLE === 'eco_client';
    var showWorkerBadges = !isListing && !isClient && (typeof USERNAME === 'undefined' || (USERNAME.toLowerCase() !== 'ecolisting' && USERNAME.toLowerCase() !== 'ilyaeco'));
    var canEdit      = ((ROLE === 'd4u_writer' || ROLE === 'seo_manager' || ROLE === 'ai_work') && item.status === 'Pending')
                    || (ROLE === 'eco_client'  && item.status === 'Generated')
                    || isAdmin;
    var approvedDisabled  = lock || item.status !== 'Generated';
    var originalForDiff   = item.original_content || item.content || '';
    var workerCanSee = (isWorker || isQa) && (item.status === 'Approved' || item.status === 'Updated')
                    || (isWorker && (item.work_status === 'Changes' || item.work_status === 'Changing'));
                    /* eco_listing does NOT see product content — only SEO form + Publish */
    var isUrgent  = item.is_urgent == 1 && !item.published_at;

    /* Invoice badge */
    var invBadge = '';
    if(!isListing){
        var paidByPayroll = item.payslip_status === 'Paid';
        var invoicePaid   = item.invoice_status === 'Paid' || item.info_invoice_status === 'Paid' || item.aplus_invoice_status === 'Paid';
        var isWorkerView  = ['worker','qa','d4u_writer','seo_manager','ai_work'].includes(ROLE);

        if(isWorkerView){
            if(paidByPayroll){
                invBadge = '<span class="inv-badge-paid" style="margin-right:12px;">✅ Paid</span>';
            } else if(item.payslip_status === 'Generated'){
                invBadge = '<span class="inv-badge-invoiced" style="margin-right:12px;">🧾 Payslip Generated</span>';
            } else if(invoicePaid){
                invBadge = '<span class="inv-badge-invoiced" style="margin-right:12px;">🧾 Invoiced</span>';
            } else {
                invBadge = '';
            }
        } else {
            if(item.product_type === 'Info + A Plus'){
                var iBadge = (item.info_invoice_status === 'Paid')     ? '<span class="inv-badge-paid" style="margin-right:4px;">✅ Info Paid</span>'
                           : (item.info_invoice_status === 'Invoiced') ? '<span class="inv-badge-invoiced" style="margin-right:4px;">🧾 Info Invoiced</span>' : '';
                var aBadge = (item.aplus_invoice_status === 'Paid')    ? '<span class="inv-badge-paid" style="margin-right:12px;">✅ A+ Paid</span>'
                           : (item.aplus_invoice_status === 'Invoiced')? '<span class="inv-badge-invoiced" style="margin-right:12px;">🧾 A+ Invoiced</span>' : '';
                invBadge = iBadge + aBadge;
            } else if(item.product_type === 'Infographics'){
                invBadge = (item.info_invoice_status === 'Invoiced') ? '<span class="inv-badge-invoiced" style="margin-right:12px;">🧾 Invoiced</span>'
                         : (item.info_invoice_status === 'Paid')     ? '<span class="inv-badge-paid" style="margin-right:12px;">✅ Paid</span>' : '';
            } else if(item.product_type === 'A+'){
                invBadge = (item.aplus_invoice_status === 'Invoiced') ? '<span class="inv-badge-invoiced" style="margin-right:12px;">🧾 Invoiced</span>'
                         : (item.aplus_invoice_status === 'Paid')     ? '<span class="inv-badge-paid" style="margin-right:12px;">✅ Paid</span>' : '';
            } else {
                invBadge = (item.invoice_status === 'Invoiced') ? '<span class="inv-badge-invoiced" style="margin-right:12px;">🧾 Invoiced</span>'
                         : (item.invoice_status === 'Paid')     ? '<span class="inv-badge-paid" style="margin-right:12px;">✅ Paid</span>' : '';
            }
        }
    }
    var typeBadge = '';
    var tbStyle = "display:inline-block; font-size:9.5px; padding:2px 6px; white-space:nowrap; border-radius:12px; box-shadow:0 2px 4px rgba(0,0,0,0.3); letter-spacing:0.5px; line-height:1;";
    if(item.product_type === 'Info + A Plus'){
        typeBadge = '<span style="' + tbStyle + ' background:#8b5cf6; color:#fff;">Info + A Plus</span>';
    } else if(item.product_type === 'Infographics'){
        typeBadge = '<span style="' + tbStyle + ' background:#f59e0b; color:#fff;">Infographics</span>';
    } else {
        typeBadge = '<span style="' + tbStyle + ' background:#0ea5e9; color:#fff;">A Plus</span>';
    }

    var linkIcon = '';
    if(item.product_link && item.product_link.trim() !== ''){
        var cleanLink = item.product_link.trim();
        if(!/^https?:\/\//i.test(cleanLink)){
            cleanLink = 'https://' + cleanLink;
        }
        linkIcon = ` <a href="${cleanLink}" target="_blank" rel="noopener" onclick="event.stopPropagation();" class="product-research-link" title="Research Link">🔗 Ref. link for Research</a>`;
    }
    var revCount = parseInt(item.revision_count) || 0;
    if(!revCount){ try{ if(item.revision_log) revCount = JSON.parse(item.revision_log).length; }catch(e){} }
    if(!revCount && item.revision_comment) revCount = 1;
    var revBadge = revCount > 0 ? `<span style="display:inline-block;padding:1px 8px;border-radius:10px;background:#7c2d12;color:#fed7aa;font-size:10px;font-weight:bold;margin-left:4px;vertical-align:middle;border:1px solid #c2410c;">✏ Changes ${revCount}</span>` : '';
    var urgentBadge    = isUrgent ? `<span class="urgent-badge">🔴 URGENT</span>` : '';
    var publishedBadge = (item.published_at && (isAdmin || ROLE === 'eco_client' || isListing))
        ? `<span style="display:inline-block;padding:1px 8px;border-radius:10px;background:#15803d;color:#d1fae5;font-size:10px;font-weight:bold;margin-left:5px;vertical-align:middle;">📦 Published</span>` : '';

    /* Worker status bar */
    var workerStatusBarClass = 'worker-pending';
    var workerStatusMsg      = '⏳ Waiting for content approval...';
    if(item.work_status === 'Changes' || item.work_status === 'Changes in Content'){
        workerStatusBarClass = 'worker-paused';
        workerStatusMsg = '🎨 Revision Requested — Correction notes check karein';
    } else if(item.work_status === 'Changing'){
        workerStatusBarClass = 'worker-ready';
        workerStatusMsg = '🛠 Revision in Progress — Changes apply kiye ja rahe hain';
    } else if(isInfoStage){
        if(item.work_status === 'Working'){ workerStatusBarClass = 'worker-ready'; workerStatusMsg = '🟠 Infographics Kaam Jari Hai'; }
        else if(item.work_status === 'Paused'){ workerStatusBarClass = 'worker-paused'; workerStatusMsg = '⏸ Paused — Wapas aa kar Resume karein'; }
        else if(item.work_status === 'In QA'){ workerStatusBarClass = 'worker-ready'; workerStatusMsg = '🟣 QA mein hai — Infographics mukammal ho gaya'; }
        else if(item.work_status === 'Work Done' || item.work_status === 'Info Done'){ workerStatusBarClass = 'worker-ready'; workerStatusMsg = '✅ Infographics Mukammal (Info Done) — QA ne approve kar diya'; }
        else { workerStatusBarClass = 'worker-ready'; workerStatusMsg = '🎨 Infographics Ready — Aap kaam shuru kar sakte hain'; }
    } else if(item.work_status === 'Info Work'){
        workerStatusBarClass = 'worker-ready';
        workerStatusMsg = '🔶 Info Work — Infographics/A+ Banner par kaam kar rahe hain';
    } else if(item.work_status === 'Content Pending'){
        workerStatusBarClass = 'worker-paused';
        workerStatusMsg = '⏳ Content Pending — Writer content generate karay ga';
    } else if(item.status === 'Approved' || item.status === 'Updated'){
        if(item.work_status === 'Working'){ workerStatusBarClass = 'worker-ready'; workerStatusMsg = '🟠 Kaam Jari Hai'; }
        else if(item.work_status === 'Paused'){ workerStatusBarClass = 'worker-paused'; workerStatusMsg = '⏸ Paused — Wapas aa kar Resume karein'; }
        else if(item.work_status === 'In QA'){ workerStatusBarClass = 'worker-ready'; workerStatusMsg = '🟣 QA mein hai — Kaam mukammal ho gaya'; }
        else if(item.work_status === 'Work Done'){ workerStatusBarClass = 'worker-ready'; workerStatusMsg = '✅ Kaam Mukammal — QA ne approve kar diya'; }
        else { workerStatusBarClass = 'worker-ready'; workerStatusMsg = '✅ Content Ready — Aap kaam shuru kar sakte hain'; }
    }
    var workerStatusBar = isWorker ? `<div class="worker-status-bar ${workerStatusBarClass}">${workerStatusMsg}</div>` : '';

    var totalSecs   = parseInt(item.work_total_seconds) || 0;
    var workTimeBadge = '';
    if(isWorker && (item.work_status === 'Working' || item.work_status === 'Paused' || item.work_status === 'In QA' || item.work_status === 'Work Done' || item.work_status === 'Info Done') && totalSecs > 0){
        workTimeBadge = `<div class="work-time-badge">⏱ Total Kaam Ka Waqt: <span>${formatWorkTime(totalSecs)}</span></div>`;
    }

    /* Admin time block & actions */
    var adminTimeBlock = '';
    if(isAdmin){
        var approvedTimes  = item.content_approved_at ? formatDualTime(item.content_approved_at) : null;
        var updatedTimes   = item.content_updated_at  ? formatDualTime(item.content_updated_at)  : null;
        var startTimes     = item.work_started_at     ? formatDualTime(item.work_started_at)     : null;
        var completedTimes = item.work_completed_at   ? formatDualTime(item.work_completed_at)   : null;
        var pausedTimes    = item.work_paused_at      ? formatDualTime(item.work_paused_at)      : null;
        var approvedRow = approvedTimes ? `<div style="padding:6px 10px;background:#0d2210;border:1px solid #166534;border-radius:5px;"><div style="color:#4ade80;font-weight:bold;font-size:10px;margin-bottom:3px;">✅ CLIENT APPROVED CONTENT</div><div style="color:#86efac;font-size:10px;">🇵🇰 ${approvedTimes.pak}</div><div style="color:#86efac;font-size:10px;">🇺🇸 ${approvedTimes.us}</div></div>` : '';
        var updatedRow  = updatedTimes  ? `<div style="padding:6px 10px;background:#0f1f0a;border:1px solid #3f6212;border-radius:5px;"><div style="color:#a3e635;font-weight:bold;font-size:10px;margin-bottom:3px;">📝 CLIENT UPDATED CONTENT</div><div style="color:#bef264;font-size:10px;">🇺🇸 ${updatedTimes.us}</div><div style="color:#bef264;font-size:10px;">🇵🇰 ${updatedTimes.pak}</div></div>` : '';

        var adminActionsHtml = `
<div style="flex:1.5;min-width:300px;display:flex;flex-direction:column;gap:8px;background:#0a1628;padding:10px 14px;border-radius:6px;border:1px solid #1e3a5f;">
  <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #1e3a5f;padding-bottom:5px;margin-bottom:5px;">
    <span style="color:#22d3ee;font-weight:bold;font-size:11px;letter-spacing:0.5px;">⚙️ ADMIN ACTIONS</span>
    <button type="button" onclick="openEditServices(${item.id})" style="background:#0284c7;color:#fff;border:none;padding:2px 8px;border-radius:4px;font-size:10px;cursor:pointer;font-weight:700;display:inline-flex;align-items:center;gap:3px;" title="Edit Services & Worker Assignments">✏️ Edit Services</button>
  </div>
  
  <div style="display:flex;gap:6px;align-items:center;">
    <select id="performSel_${item.id}" style="flex:1;padding:6px 8px;background:#0d1e36;color:#e2e8f0;border:1px solid #1e3a5f;border-radius:4px;font-size:12px;outline:none;height:32px;">
      <option value="">⚡ Task Perform...</option>
      <option value="edit_services">✏️ Edit Services & Workers</option>
      ${item.status==='Pending'?'<option value="save">💾 Save Draft</option>':''}
      <option value="hold|${item.status==='Hold'?0:1}">${item.status==='Hold'?'▶ Unhold':'⏸ Hold'}</option>
      <option value="urgent|${isUrgent?0:1}">${isUrgent?'✅ Unmark Urgent':'🔴 Mark Urgent'}</option>
      <option value="settype|${item.product_type==='Info + A Plus'?'':'Info + A Plus'}">${item.product_type==='Info + A Plus'?'🏷 Remove Info+A Plus':'🏷 Set Info + A Plus'}</option>
      <option value="clear">🧹 Clear Task</option>
      <option value="delete">🗑 Delete Task</option>
      ${item.work_status==='Work Done'&&!item.published_at?'<option value="publish">📦 Publish</option>':''}
      ${item.product_type==='Info + A Plus'&&item.work_status==='Pending'?'<option value="infowork">▶ Info Work</option>':''}
      ${item.product_type==='Info + A Plus'&&item.work_status==='Info Work'?'<option value="send_content">📤 Send for Content</option>':''}
      ${item.work_status!=='Working'&&item.work_status!=='Changing'&&item.work_status!=='Paused'&&item.work_status!=='In QA'&&item.work_status!=='Work Done'&&item.work_status!=='Info Done'&&item.work_status!=='Info Work'&&item.work_status!=='Content Pending'?'<option value="working">' + (isInfoStage ? '▶ Info Working' : '▶ Working') + '</option>':''}
      ${item.work_status==='Working'?'<option value="pause">⏸ Pause</option>':''}
      ${item.work_status==='Paused'?'<option value="resume">▶ Resume</option>':''}
      ${(item.work_status==='Working'||item.work_status==='Paused')?'<option value="qaready">📤 Ready for QA</option>':''}
      ${(item.invoice_status||item.info_invoice_status||item.aplus_invoice_status)?'<option value="remove_invoiced">🧾 Remove Invoiced</option>':''}
      ${(item.product_type==='Infographics'||item.product_type==='Info + A Plus')&&(item.work_status==='Work Done'||item.work_status==='Info Done')?'<option value="start_aplus">🎨 Start A+ Banners</option>':''}
    </select>
    <button style="padding:0 12px;height:32px;background:#2563eb;color:#fff;border:none;border-radius:4px;font-size:12px;font-weight:bold;cursor:pointer;" onclick="applyPerform(${item.id})">Apply</button>
  </div>

  <div style="display:flex;gap:6px;align-items:center;">
    <select id="resetSel_${item.id}" style="flex:1;padding:6px 8px;background:#0d1e36;color:#e2e8f0;border:1px solid #1e3a5f;border-radius:4px;font-size:12px;outline:none;height:32px;">
      <option value="">↩ Reset To...</option>
      <option value="Pending">Pending</option>
      <option value="Generated">Generated</option>
      <option value="Updated">Updated</option>
      <option value="Approved">Approved</option>
      <option value="Working">Working</option>
      <option value="Paused">Paused</option>
      <option value="In QA">In QA</option>
      <option value="SEO Review">SEO Review</option>
      <option value="Work Done">Work Done</option>
      <option value="Info Done">Info Done</option>
      <option value="Republish">Republish</option>
      <option value="Changes">Changes in Design</option>
      <option value="Changes in Content">Changes in Content</option>
      <option value="AI Work">AI Work</option>
      <option value="AI DONE">AI DONE</option>
    </select>
    <button style="padding:0 12px;height:32px;background:#475569;color:#fff;border:none;border-radius:4px;font-size:12px;font-weight:bold;cursor:pointer;" onclick="applyReset(${item.id})">Apply</button>
  </div>
</div>`;

        var pubTimes = item.published_at ? formatDualTime(item.published_at) : null;
        var completionHtml = '';
        if(item.published_at && item.content_approved_at){
            var _approvedMs = new Date(item.content_approved_at.replace(' ','T')).getTime();
            var _publishedMs = new Date(item.published_at.replace(' ','T')).getTime();
            var _diffMs = _publishedMs - _approvedMs;
            if(_diffMs > 0){
                var _days  = Math.floor(_diffMs / 86400000);
                var _hrs   = Math.floor((_diffMs % 86400000) / 3600000);
                var _mins  = Math.floor((_diffMs % 3600000)  / 60000);
                var _parts = [];
                if(_days) _parts.push(_days + ' day' + (_days > 1 ? 's' : ''));
                if(_hrs)  _parts.push(_hrs  + ' hr'  + (_hrs  > 1 ? 's' : ''));
                if(_mins) _parts.push(_mins + ' min' + (_mins > 1 ? 's' : ''));
                if(!_parts.length) _parts.push('< 1 min');
                completionHtml = `<div style="margin-top:10px;padding:8px 10px;background:#1d4ed8;border-radius:6px;border:1px solid #3b82f6;">
  <div style="font-size:10px;font-weight:700;color:#bfdbfe;letter-spacing:.5px;margin-bottom:2px;">⏳ TOTAL COMPLETION TIME</div>
  <div style="font-size:10px;color:#93c5fd;margin-bottom:4px;">FROM APPROVAL TO PUBLISH</div>
  <div style="font-size:14px;font-weight:bold;color:#fff;">${_parts.join(', ')}</div>
</div>`;
            }
        }
        var publishedRow = pubTimes ? `<div style="flex:1;min-width:200px;display:flex;flex-direction:column;gap:4px;padding:6px 10px;background:#052e16;border:1px solid #16a34a;border-radius:5px;font-size:11px;color:#86efac;">
  <div style="font-weight:bold;font-size:10px;color:#4ade80;letter-spacing:.5px;margin-bottom:2px;">📦 PUBLISHED TIME</div>
  ${item.published_by_name ? `<div style="color:#4ade80;font-size:10px;">by <strong>${item.published_by_name}</strong></div>` : ''}
  <div>🇵🇰 ${pubTimes.pak}</div>
  <div>🇺🇸 ${pubTimes.us}</div>
  ${completionHtml}
</div>` : '';

        adminTimeBlock = `<div style="margin-bottom:10px;display:flex;gap:12px;align-items:stretch;flex-wrap:wrap;">
${(approvedRow || updatedRow) ? `<div style="flex:1;min-width:200px;display:flex;flex-direction:column;gap:8px;">${approvedRow}${updatedRow}</div>` : ''}
<div style="flex:1;min-width:200px;color:#fff;font-size:12px;line-height:1.6;background:#0a1628;padding:10px 14px;border-radius:6px;border:1px solid #1e3a5f;display:flex;flex-direction:column;justify-content:space-between;">
  <div style="font-size:10px;font-weight:700;color:#64748b;letter-spacing:.5px;border-bottom:1px solid #1e3a5f;padding-bottom:4px;margin-bottom:6px;">⏱ WORKER TIME</div>
  <div>
    <div style="margin-bottom:5px;">🕒 <strong style="color:#93c5fd;">Start Time</strong><br>${startTimes ? '<span style="color:#bfdbfe;">🇵🇰 '+startTimes.pak+'</span>' : '<span style="color:#475569;">-</span>'}</div>
    <div style="margin-bottom:5px;">✅ <strong style="color:#86efac;">Completed Time</strong><br>${completedTimes ? '<span style="color:#bbf7d0;">🇵🇰 '+completedTimes.pak+'</span>' : '<span style="color:#475569;">-</span>'}</div>
    <div style="margin-bottom:5px;">⏸ <strong style="color:#fcd34d;">Last Paused</strong><br>${pausedTimes ? '<span style="color:#fef3c7;">🇵🇰 '+pausedTimes.pak+'</span>' : '<span style="color:#475569;">-</span>'}</div>
  </div>
  <div style="margin-top:4px;border-top:1px solid #1e3a5f;padding-top:4px;">⏱ Total Work Time: <strong style="color:#4ade80;">${formatWorkTime(item.work_total_seconds || 0)}</strong></div>
</div>
${publishedRow}
${adminActionsHtml}
</div>`;
    }

    /* Admin inline worker assign */
    var adminAssign = '';
    if(isAdmin){
        var _ws = ALL_WORKERS || [];
        if(item.work_status === 'Work Done'){
            var _doneWorker = item.work_completed_worker_name;
            if(!_doneWorker && item.assigned_worker_id){ var _fw = _ws.find(function(w){ return String(w.id) === String(item.assigned_worker_id); }); if(_fw) _doneWorker = _fw.username; }
            adminAssign = `<select class="inline-assign-sel" disabled style="opacity:0.85;cursor:default;background:#0a2010;border-color:#16a34a;color:#4ade80;font-weight:bold;font-size:11px;"><option>👷 ${_doneWorker||'No Worker'}</option></select><button class="inline-assign-btn assigned" disabled style="opacity:0.7;cursor:default;">✅ Done</button>`;
        } else if(item.status === 'Hold'){
            if(item.assigned_worker_id){ var _hw = _ws.find(function(w){ return String(w.id) === String(item.assigned_worker_id); }); var _hwName = _hw ? _hw.username : (item.work_completed_worker_name || 'Assigned'); adminAssign = `<select class="inline-assign-sel" disabled style="opacity:0.85;cursor:not-allowed;background:#1a0505;border-color:#dc2626;color:#f87171;font-weight:bold;font-size:11px;"><option>👷 ${_hwName}</option></select><button class="inline-assign-btn" disabled style="opacity:0.85;cursor:not-allowed;background:#7f1d1d;color:#fca5a5;border:none;">✅ Assigned</button>`; }
            else { adminAssign = `<select class="inline-assign-sel" disabled style="opacity:0.7;cursor:not-allowed;background:#1a0505;border-color:#dc2626;color:#f87171;font-weight:bold;font-size:11px;"><option>🚫 On Hold</option></select><button class="inline-assign-btn" disabled style="opacity:0.7;cursor:not-allowed;background:#7f1d1d;color:#fca5a5;border:none;">Assign</button>`; }
        } else if(item.status === 'AI DONE'){
            var _aw = item.assigned_worker_id ? _ws.find(function(w){ return String(w.id) === String(item.assigned_worker_id); }) : null;
            if(!_aw || _aw.role === 'ai_work'){
                // Available to assign to next worker (e.g. Infographics)
                adminAssign = `<select class="inline-assign-sel" id="aw-${item.id}"><option value="">👷 Assign Worker...</option></select><button class="inline-assign-btn" id="abtn-${item.id}" onclick="assignFromCard(${item.id})">Assign</button>`;
            } else {
                var _awName = _aw.username;
                adminAssign = `<select class="inline-assign-sel" disabled style="opacity:0.9;cursor:default;background:#071428;border-color:#2563eb;color:#93c5fd;font-weight:bold;font-size:11px;"><option>👷 ${_awName}</option></select><button class="inline-assign-btn assigned" disabled style="opacity:0.75;cursor:default;">✅ Assigned</button>`;
            }
        } else if(item.assigned_worker_id){
            var _aw = _ws.find(function(w){ return String(w.id) === String(item.assigned_worker_id); });
            var _awName = _aw ? _aw.username : (item.work_completed_worker_name || 'Assigned');
            // Dropdown is permanently locked once assigned. 
            // Re-assignment or un-assignment can only be done via enforced tools by admin.
            adminAssign = `<select class="inline-assign-sel" disabled style="opacity:0.9;cursor:default;background:#071428;border-color:#2563eb;color:#93c5fd;font-weight:bold;font-size:11px;"><option>👷 ${_awName}</option></select><button class="inline-assign-btn assigned" disabled style="opacity:0.75;cursor:default;">✅ Assigned</button>`;
        } else {
            adminAssign = `<select class="inline-assign-sel" id="aw-${item.id}"><option value="">👷 Worker...</option></select><button class="inline-assign-btn" id="abtn-${item.id}" onclick="assignFromCard(${item.id})">Assign</button>`;
        }
    }

    /* QA send-for-SEO button */
    var qaSendSEO = (ROLE === 'qa' && item.work_status === 'In QA' && item.active_revision_type !== 'design' && !isInfoStage && item.product_type !== 'Infographics')
        ? `<button class="status" style="background:#0891b2;cursor:pointer;border:none;width:auto;padding:6px 12px;" onclick="event.stopPropagation();sendForSEO(${item.id})">Send for SEO</button>` : '';

    var ds = getDisplayStatus(item);
    
    var isAssignedToMe = false;
    if (ROLE === 'qa' && (parseInt(item.qa_submitted_by) === USER_ID || parseInt(item.qa_user_id) === USER_ID)) isAssignedToMe = true;
    else if ((ROLE === 'd4u_writer' || ROLE === 'seo_manager') && (parseInt(item.written_by_user_id) === USER_ID || parseInt(item.seo_submitted_by) === USER_ID)) isAssignedToMe = true;
    else if (ROLE === 'ai_work' && parseInt(item.ai_worked_by) === USER_ID) isAssignedToMe = true;
    else if (parseInt(item.assigned_worker_id) === USER_ID || parseInt(item.work_completed_by_worker_id) === USER_ID) isAssignedToMe = true;
    // We don't have eco_tool_assignments array in JS, but checking assigned_worker_id / completed_by is usually enough for the card display
    
    var canSeeStatusTime = isAdmin || isAssignedToMe;

    var statusTimeBlock = '';
    if(canSeeStatusTime && item.last_activity_at && typeof formatDualTime === 'function'){
        var dTime = formatDualTime(item.last_activity_at);
        if(dTime){
            var diffMs = new Date().getTime() - new Date(item.last_activity_at.replace(/-/g, '/').replace('T', ' ') + 'Z').getTime();
            var pendingText = '';
            if(diffMs > 0){
                var _days  = Math.floor(diffMs / 86400000);
                if(_days > 0) pendingText = _days + ' day' + (_days > 1 ? 's' : '') + ' before';
                else {
                    var _hrs = Math.floor(diffMs / 3600000);
                    if(_hrs > 0) pendingText = _hrs + ' hour' + (_hrs > 1 ? 's' : '') + ' before';
                    else pendingText = 'Just now';
                }
            }
            statusTimeBlock = `<div style="display:flex; flex-direction:column; text-align:right; font-size:10px; color:#64748b; line-height:1.2; margin-right:8px; justify-content:center;">
                <div style="color:#f87171; font-weight:700; margin-bottom:2px;">${pendingText}</div>
                <div>🇵🇰 ${dTime.pak.replace(' PKT', '')} PKT</div>
                <div>🇺🇸 ${dTime.us.replace(' ET', '')} ET</div>
            </div>`;
        }
    }

    /* Published info — admin sees it inside adminTimeBlock above; client/listing see it here */
    var publishedInfo = '';
    if(item.published_at && !isAdmin && (ROLE === 'eco_client' || isListing)){
        var pubT = formatDualTime(item.published_at);
        var pubStr = pubT ? '🇵🇰 ' + pubT.pak + '<br>🇺🇸 ' + pubT.us : item.published_at;
        publishedInfo = `<div style="margin:6px 0;padding:7px 12px;background:#052e16;border:1px solid #16a34a;border-radius:5px;font-size:11px;color:#86efac;">📦 <strong>Published</strong>${item.published_by_name?' by <strong>'+item.published_by_name+'</strong>':''}<br>${pubStr}</div>`;
    }

    /* Links box */
    var linksBox = '';
    if((item.work_status === 'Work Done' || item.work_status === 'Info Done') && (item.media_link || item.seo_doc_link || item.published_link)){
        var isInfoProd = item.work_status === 'Info Done' || item.product_type === 'Infographics';
        var mediaLabel = isInfoProd ? '📁 Infographics Drive Folder' : '📁 Media Link';
        linksBox = `<div class="qa-links-box" style="margin-top:10px;">
${item.media_link ? `<a href="${item.media_link}" target="_blank" rel="noopener" style="${isInfoProd ? 'background:#0369a1;border-color:#0284c7;color:#fff;font-weight:600;padding:5px 12px;border-radius:4px;display:inline-flex;align-items:center;gap:6px;' : ''}">${mediaLabel}</a>` : ''}
${!isListing && item.seo_doc_link ? `<a href="${item.seo_doc_link}" target="_blank" rel="noopener">SEO Doc Link</a>` : ''}
${item.published_link ? `<a href="${item.published_link}" target="_blank" rel="noopener" style="background:#052e16;border-color:#16a34a;color:#86efac;">📦 Published Link</a>` : ''}
</div>`;
    }

    /* Action buttons */
    var actionBtns = buildActionButtons(item, isAdmin, isWorker, isQa, isListing, isUrgent, lock, approvedDisabled);

    return `
<div class="head" onclick="headClick(event,${item.id})" ontouchend="headTouch(event,${item.id})" style="cursor:pointer; display:flex; align-items:center;">
<div class="pid${isUrgent?' urgent-pid':''}" style="position:relative; display:flex; flex-direction:column; align-items:center; justify-content:flex-start; padding-top:12px;">
    <div style="line-height:1;">#${item.product_no}</div>
    <div style="position:absolute; bottom:3px; z-index:10; display:flex; justify-content:center; width:100%;">
        ${typeBadge}
    </div>
</div>
<div class="title" style="flex:1;">${item.title}${linkIcon}</div>
<div class="card-meta${isAdmin?' admin-meta':''}" style="display:flex; align-items:center;">
${(showWorkerBadges && item.ai_worked_by_name) ? `<span style="background:#3b0764;border:1px solid #9333ea;color:#e9d5ff;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;margin-right:6px;" title="AI Work completed by ${item.ai_worked_by_name}">🤖 AI: ${item.ai_worked_by_name}</span>` : ''}
${(showWorkerBadges && item.info_worker_name) ? `<span style="background:#082f49;border:1px solid #0284c7;color:#bae6fd;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;margin-right:6px;" title="Infographics Worker: ${item.info_worker_name}">🎨 Info: ${item.info_worker_name}</span>` : ''}
${(showWorkerBadges && item.aplus_worker_name) ? `<span style="background:#2e1065;border:1px solid #7c3aed;color:#ddd6fe;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;margin-right:6px;" title="A+ Banners Worker: ${item.aplus_worker_name}">🏷 A+: ${item.aplus_worker_name}</span>` : ''}
${revBadge}
${invBadge}
${(typeof HAS_BULK_ACTION !== 'undefined' && HAS_BULK_ACTION && document.getElementById('filter') && document.getElementById('filter').value === 'Pending') ? `<input type="checkbox" class="bulk-chk" data-id="${item.id}" ${(typeof SELECTED_BULK_PRODUCTS !== 'undefined' && SELECTED_BULK_PRODUCTS.indexOf(item.id) !== -1) ? 'checked' : ''} onclick="event.stopPropagation(); toggleBulkSelection();" style="width:16px; height:16px; margin-right:8px; cursor:pointer; accent-color:#c2410c; vertical-align:middle;">` : ''}
${statusTimeBlock}
<div class="status ${ds.cls}">${ds.label}</div>
${adminAssign}
${qaSendSEO}
</div>
<button class="toggle" type="button"><span class="toggle-icon" style="display:inline-block; transition:transform 0.3s; transform:${OPEN[item.id] ? 'rotate(180deg)' : 'rotate(0deg)'};">▼</span></button>
</div>
<div class="body${OPEN[item.id]?' open':''}" id="body-${item.id}">
<div class="card-close-bar" onclick="toggle(${item.id})" ontouchend="headTouch(event,${item.id})">▲ &nbsp;Close</div>
${isUrgent ? `<div style="margin-bottom:10px;">${urgentBadge}</div>` : ''}
${(() => {
    var logs = [];
    try { logs = item.revision_log ? JSON.parse(item.revision_log) : []; } catch(e){}
    if(!logs.length && item.revision_comment){
        logs = [{ type: item.active_revision_type || 'design', comment: item.revision_comment, at: '' }];
    }
    if(!logs.length) return '';
    var isActive = item.work_status === 'Changes' || item.work_status === 'Changes in Content';
    var html = `<div style="margin-bottom:10px;border:2px solid #b91c1c;border-radius:7px;overflow:hidden;">`;
    html += `<div style="background:#7f1d1d;padding:7px 14px;font-size:11px;font-weight:700;color:#fca5a5;letter-spacing:.5px;">`;
    html += isActive ? '🎨 REVISION LOG — CLIENT FEEDBACK' : '📋 REVISION HISTORY';
    html += `</div>`;
    for(var li = logs.length - 1; li >= 0; li--){
        var entry = logs[li];
        var entryLabel = entry.type === 'content' ? '✍ Changes in Content' : '🎨 Changes in Design';
        var entryColor = entry.type === 'content' ? '#a78bfa' : '#fb923c';
        var entryNum = li + 1;
        var isLatest = li === logs.length - 1;
        html += `<div style="padding:10px 14px;background:${isLatest ? '#3b0712' : '#1a0505'};border-top:1px solid #7f1d1d;">`;
        html += `<div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">`;
        html += `<span style="background:#7f1d1d;color:#fca5a5;font-size:10px;font-weight:bold;padding:1px 7px;border-radius:10px;">#${entryNum}</span>`;
        html += `<span style="color:${entryColor};font-size:11px;font-weight:bold;">${entryLabel}</span>`;
        if(entry.at) html += `<span style="color:#64748b;font-size:10px;margin-left:auto;">${entry.at}</span>`;
        if(isLatest) html += `<span style="background:#b91c1c;color:#fff;font-size:10px;font-weight:bold;padding:1px 6px;border-radius:8px;">LATEST</span>`;
        html += `</div>`;
        html += `<div style="color:#fef2f2;font-size:12px;line-height:1.6;white-space:pre-wrap;">${entry.comment || '<em style="color:#64748b;">No comment</em>'}</div>`;
        html += `</div>`;
    }
    html += `</div>`;
    return html;
})()}
${workerStatusBar}
${workTimeBadge}
${adminTimeBlock}
${buildFamilyGroupingSection(item, isAdmin)}
${((!isWorker && !isQa) || workerCanSee) && !isInfoStage ? `<div class="editor ${lock&&!isWorker?'locked':''} ${isWorker||isQa?'locked':''}" id="editor-${item.id}" contenteditable="${canEdit?'true':'false'}" data-original="${encodeURIComponent(originalForDiff)}" oninput="clientEditorChanged(${item.id})" onblur="unmarkEditing(${item.id})">${item.content||''}</div>` : ''}
${(isAdmin || isWorker) && lock && item.original_content && item.original_content !== item.content ? `<div class="diff-bar" id="diff-label-${item.id}"><span class="diff-legend">🔍 Client Changes — <span class="diff-legend-add">■ Added</span> &nbsp; <span class="diff-legend-del">■ Deleted</span></span></div><div class="diff-preview" id="diff-${item.id}"></div>` : ''}
${(ROLE === 'eco_client' || isAdmin) && !lock ? `<div class="diff-bar" id="diff-label-${item.id}" style="display:none;"><span class="diff-legend">📝 Changes — <span class="diff-legend-add">■ Added</span> &nbsp; <span class="diff-legend-del">■ Deleted</span></span><button class="btn-revert" id="revert-${item.id}" onclick="revertToOriginal(${item.id})">↩ Go Back to Original</button></div><div class="diff-preview" id="diff-${item.id}" style="display:none;"></div>` : ''}
${buildQABoxes(item, isAdmin, isQa)}
${publishedInfo}
${buildSeoSection(item, isAdmin, isListing)}
${linksBox}
<div class="actions">${actionBtns}</div>
</div>`;
}

/* ── QA submit boxes ─────────────────────────── */
function buildQABoxes(item, isAdmin, isQa){
    var html = '';
    var isInfoStage = (item.product_type === 'Infographics' && item.work_status !== 'Content Pending' && item.status !== 'Generated' && item.status !== 'Approved' && item.status !== 'Updated' && !item.content_approved_at && !item.content_updated_at)
                   || (item.product_type === 'Info + A Plus' && (item.status === 'Pending' || item.status === 'AI DONE' || item.status === 'Infographics') && item.work_status !== 'Content Pending' && item.status !== 'Generated' && !item.content_approved_at && !item.content_updated_at);
    var isInfoOnly = isInfoStage || item.product_type === 'Infographics';
    
    var qaLabelHtml = '';
    if((item.work_status === 'Work Done' || item.work_status === 'Info Done') && item.qa_user_name) {
        qaLabelHtml = `<div style="font-size:10px; color:#10b981; margin-top:6px; font-weight:bold; line-height:1.3;">QA Done by:<br>${item.qa_user_name}</div>`;
    }

    var seoLabel  = isInfoOnly ? '' : `<label for="seo-${item.id}">SEO Doc.</label>`;
    var seoInput  = isInfoOnly ? '' : `<input type="url" id="seo-${item.id}" value="${item.seo_doc_link||''}" placeholder="SEO Doc.">`;
    var seoLabelA = isInfoOnly ? '' : `<label for="admin-seo-${item.id}">SEO Doc.</label>`;
    var seoInputA = isInfoOnly ? '' : `<input type="url" id="admin-seo-${item.id}" value="${item.seo_doc_link||''}" placeholder="SEO Doc.">`;
    var seoLabelD = isInfoOnly ? '' : `<label for="seo-doc-${item.id}">SEO Doc.</label>`;
    var seoInputD = isInfoOnly ? '' : `<input type="url" id="seo-doc-${item.id}" value="${item.seo_doc_link||''}" placeholder="SEO Doc.">`;

    var linkVal = item.media_link || '';
    var mediaPlaceholder = isInfoOnly ? 'Google Drive Folder Link (Infographics)' : 'Media Link';
    var mediaLabelText   = isInfoOnly ? '📁 Drive Link' : 'Media';
    
    var mediaInputHtml = `<input type="url" id="media-${item.id}" value="${linkVal}" placeholder="${mediaPlaceholder}">`;
    var mediaInputHtmlA = `<input type="url" id="admin-media-${item.id}" value="${linkVal}" placeholder="${mediaPlaceholder}">`;
    var mediaInputHtmlD = `<input type="url" id="seo-media-${item.id}" value="${linkVal}" placeholder="${mediaPlaceholder}">`;

    if(isAdmin && item.work_status === 'In QA'){
        if(isInfoOnly){
            html += `<div class="qa-submit-box" style="grid-template-columns:110px 1fr;"><div class="qa-labels"><label>${mediaLabelText}</label>${qaLabelHtml}</div><div class="qa-fields">${mediaInputHtml}</div><div style="grid-column:1/-1;display:flex;gap:8px;"><button class="qa-submit-btn" style="flex:1;background:#0284c7;" onclick="submitQA(${item.id})">✅ Info Done</button></div></div>`;
        } else {
            html += `<div class="qa-submit-box" style="grid-template-columns:110px 1fr;"><div class="qa-labels"><label>${mediaLabelText}</label>${qaLabelHtml}${seoLabel}</div><div class="qa-fields">${mediaInputHtml}${seoInput}</div><div style="grid-column:1/-1;display:flex;gap:8px;"><button class="qa-submit-btn" style="flex:1;" onclick="submitQA(${item.id})">Submit</button><button class="qa-submit-btn" style="flex:1;background:#7c3aed;" onclick="sendForSEO(${item.id})">Send for SEO</button></div></div>`;
        }
    }
    if(isQa && item.work_status === 'In QA'){
        if(isInfoOnly){
            html += `<div class="qa-submit-box"><div class="qa-labels"><label>${mediaLabelText}</label>${qaLabelHtml}</div><div class="qa-fields">${mediaInputHtml}</div><button class="qa-submit-btn" style="align-self:start; padding:12px 18px; min-height:45px; background:#0284c7;" onclick="submitQA(${item.id})">✅ Info Done</button></div>`;
        } else {
            html += `<div class="qa-submit-box"><div class="qa-labels"><label>${mediaLabelText}</label>${qaLabelHtml}${seoLabel}</div><div class="qa-fields">${mediaInputHtml}${seoInput}</div><button class="qa-submit-btn" style="align-self:start; padding:12px 0; min-height:45px;" onclick="submitQA(${item.id})">Submit</button></div>`;
        }
    }
    if(ROLE === 'seo_manager' && item.work_status === 'SEO Review'){
        html += `<div class="qa-submit-box"><div class="qa-labels"><label>Media</label>${qaLabelHtml}${seoLabel}</div><div class="qa-fields">${mediaInputHtml}${seoInput}</div><button class="qa-submit-btn" style="align-self:start; padding:12px 0; min-height:45px;" onclick="submitQA(${item.id})">Submit</button></div>`;
    }
    if(isAdmin && item.work_status === 'SEO Review'){
        html += `<div class="qa-submit-box"><div class="qa-labels"><label>Media</label>${qaLabelHtml}${seoLabelD}</div><div class="qa-fields">${mediaInputHtmlD}${seoInputD}</div><div style="display:flex;gap:8px;flex-wrap:wrap;align-self:start;"><button class="qa-submit-btn" style="background:#7c3aed; padding:12px 15px; min-height:45px;" onclick="adminSubmitSEO(${item.id})">Submit SEO</button></div></div>`;
    }
    if(isAdmin && (item.work_status === 'Work Done' || item.work_status === 'Info Done')){
        html += `<div class="qa-submit-box"><div class="qa-labels"><label>${mediaLabelText}</label>${qaLabelHtml}${seoLabelA}</div><div class="qa-fields">${mediaInputHtmlA}${seoInputA}</div><button class="qa-submit-btn" style="align-self:start; padding:12px 15px; min-height:45px; ${item.work_status==='Info Done'?'background:#0284c7;':''}" onclick="saveFinalLinks(${item.id})">Save Links</button></div>`;
    }
    return html;
}

/* ── SEO Content section inside card ────────── */
var _SEO_LOADED = {};
function toggleSeoSection(id) {
    var section = document.getElementById('seo-section-' + id);
    var icon    = document.getElementById('seo-toggle-icon-' + id);
    if (!section) return;
    var isHidden = section.style.display === 'none' || section.style.display === '';
    if (isHidden) {
        section.style.display = 'block';
        if (icon) icon.textContent = '▼ Hide';
        if (!_SEO_LOADED[id]) {
            _SEO_LOADED[id] = true;
            var task     = ALL_TASKS.find(function(t){ return t.id == id; });
            var readonly = (ROLE !== 'administrator' && ROLE !== 'seo_manager');
            loadSeoPanel(id, readonly);
        }
    } else {
        section.style.display = 'none';
        if (icon) icon.textContent = '▶ Show';
    }
}

function buildSeoSection(item, isAdmin, isListing){
    var isSeoManager = ROLE === 'seo_manager';

    /* Infographics-only products: no SEO content needed */
    if(item.product_type === 'Infographics') return '';

    var seoStatuses = ['SEO Review','Work Done','Republish'];
    var showForm = (isSeoManager && item.work_status === 'SEO Review')
                || (isAdmin && (seoStatuses.indexOf(item.work_status) !== -1 || item.published_at))
                || (isListing && item.work_status === 'Work Done');

    if (!showForm) return '';

    var isListing_ = isListing && !isAdmin && !isSeoManager;
    var borderColor = isListing_ ? '#16a34a' : '#7c3aed';
    var labelColor  = isListing_ ? '#4ade80' : '#a78bfa';
    var label       = isListing_ ? '📋 SEO CONTENT' : '🎯 SEO CONTENT FORM';

    return `<div style="margin-top:14px;border-top:2px solid ${borderColor};padding-top:12px;">
<div style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;padding:4px 0 6px;" onclick="toggleSeoSection(${item.id})">
  <div style="font-size:12px;font-weight:700;color:${labelColor};letter-spacing:.5px;">${label}</div>
  <span id="seo-toggle-icon-${item.id}" style="color:${labelColor};font-size:11px;font-weight:700;padding:3px 10px;background:rgba(124,58,237,.15);border-radius:4px;">▶ Show</span>
</div>
<div id="seo-section-${item.id}" style="display:none;">
  <div id="seo-panel-${item.id}"></div>
</div>
</div>`;
}

/* ── Action buttons per role ─────────────────── */
function buildActionButtons(item, isAdmin, isWorker, isQa, isListing, isUrgent, lock, approvedDisabled){
    if(isAdmin) return buildAdminButtons(item, isUrgent, lock);
    var btns = '';
    if(isWorker){
        var isDesignRev = item.active_revision_type === 'design';
        var isInfoStage = (item.product_type === 'Infographics')
                       || (item.product_type === 'Info + A Plus' && (item.status === 'Pending' || item.status === 'AI DONE' || item.status === 'Infographics') && !item.content_approved_at && !item.content_updated_at);
        if(isInfoStage){
            var isContentApproved = item.status === 'Approved' || item.status === 'Updated' || item.content_approved_at || item.content_updated_at;
            if(!isContentApproved) {
                if(item.work_status === 'Pending' || item.status === 'AI DONE' || item.status === 'Infographics') {
                    btns += `<button class="actionbtn" style="background:#6d28d9;color:#fff;" onclick="sendForContent(${item.id})">📤 Generate Info Content</button>`;
                } else if(item.work_status === 'Content Pending') {
                    btns += `<div style="color:#94a3b8;font-size:12px;padding:8px 0;font-style:italic;">⏳ Content generate hone ka intezaar karein...</div>`;
                }
            } else {
                if(item.work_status !== 'Working' && item.work_status !== 'Changing' && item.work_status !== 'Paused' && item.work_status !== 'In QA' && item.work_status !== 'Work Done' && item.work_status !== 'Info Done'){
                    btns += `<button class="btn1 actionbtn" onclick="updateWorkStatus(${item.id},'Working')">▶ Info Working</button>`;
                }
                if(item.work_status === 'Working' || item.work_status === 'Changing')  btns += `<button class="actionbtn" style="background:#b45309;color:#fff;" onclick="pauseWork(${item.id})">⏸ Pause</button>`;
                if(item.work_status === 'Paused')   btns += `<button class="btn1 actionbtn" onclick="resumeWork(${item.id})">▶ Resume</button>`;
                if(item.work_status === 'Working' || item.work_status === 'Changing' || item.work_status === 'Paused') {
                    btns += `<button class="btn2 actionbtn" onclick="updateWorkStatus(${item.id},'In QA')">📤 Ready for QA</button>`;
                }
            }
        } else if(item.product_type === 'Info + A Plus'){
            if(item.work_status === 'Pending')         btns += `<button class="actionbtn" style="background:#c2410c;color:#fff;" onclick="updateWorkStatus(${item.id},'Info Work')">▶ Info Work</button>`;
            if(item.work_status === 'Info Work')       btns += `<button class="actionbtn" style="background:#6d28d9;color:#fff;" onclick="sendForContent(${item.id})">📤 Send for Content</button>`;
            if(item.work_status === 'Content Pending') btns += `<div style="color:#94a3b8;font-size:12px;padding:8px 0;font-style:italic;">⏳ Content generate hone ka intezaar karein...</div>`;
            if(item.work_status !== 'Info Work' && item.work_status !== 'Content Pending' && item.work_status !== 'Pending'){
                if(item.work_status !== 'Working' && item.work_status !== 'Changing' && item.work_status !== 'Paused' && item.work_status !== 'In QA' && item.work_status !== 'Work Done'){
                    btns += `<button class="btn1 actionbtn" onclick="updateWorkStatus(${item.id},'Working')">▶ Working</button>`;
                }
                if(item.work_status === 'Working')  btns += `<button class="actionbtn" style="background:#b45309;color:#fff;" onclick="pauseWork(${item.id})">⏸ Pause</button>`;
                if(item.work_status === 'Paused')   btns += `<button class="btn1 actionbtn" onclick="resumeWork(${item.id})">▶ Resume</button>`;
                if(item.work_status === 'Working' || item.work_status === 'Paused') btns += `<button class="btn2 actionbtn" onclick="updateWorkStatus(${item.id},'In QA')">📤 Ready for QA</button>`;
            }
        } else {
            if(item.work_status !== 'Working' && item.work_status !== 'Changing' && item.work_status !== 'Paused' && item.work_status !== 'In QA' && item.work_status !== 'Work Done'){
                if(isDesignRev){
                    btns += `<button class="btn1 actionbtn" style="background:#d97706;color:#fff;" onclick="updateWorkStatus(${item.id},'Changing')">▶ Changing</button>`;
                } else {
                    btns += `<button class="btn1 actionbtn" onclick="updateWorkStatus(${item.id},'Working')">▶ Working</button>`;
                }
            }
            if(item.work_status === 'Working' || item.work_status === 'Changing')  btns += `<button class="actionbtn" style="background:#b45309;color:#fff;" onclick="pauseWork(${item.id})">⏸ Pause</button>`;
            if(item.work_status === 'Paused')   btns += `<button class="btn1 actionbtn" onclick="resumeWork(${item.id})">▶ Resume</button>`;
            if(item.work_status === 'Working' || item.work_status === 'Changing' || item.work_status === 'Paused') {
                btns += `<button class="btn2 actionbtn" onclick="updateWorkStatus(${item.id},'In QA')">📤 Ready for QA</button>`;
            }
        }
    }
    if(ROLE === 'eco_client'){
        if(item.status === 'Generated' || item.status === 'Hold'){
            btns += `<button class="actionbtn" style="background:#0f766e;color:#fff;" onclick="holdProduct(${item.id},${item.status==='Hold'?0:1})">${item.status==='Hold'?'▶ Unhold':'⏸ Hold'}</button>`;
        }
        if(item.status === 'Generated' && item.work_status !== 'Work Done'){
            btns += `<button class="actionbtn" style="background:${isUrgent?'#475569':'#ef4444'};color:#fff;" onclick="setUrgent(${item.id},${isUrgent?0:1})">${isUrgent?'✅ Unmark Urgent':'🔴 Mark Urgent'}</button>`;
        }
    }
    if((ROLE === 'd4u_writer' || ROLE === 'seo_manager') && item.status === 'Pending') btns += `<button class="btn-writer-save actionbtn" onclick="writerSave(${item.id})">💾 Save Draft</button>`;
    if(ROLE === 'ai_work' && item.status === 'AI Work') btns += `<button class="btn2 actionbtn" style="background:#16a34a;color:#fff;" onclick="updateWorkStatus(${item.id},'AI DONE')">🤖 AI Done</button>`;
    if(isListing && item.work_status === 'Work Done' && !item.published_at) {
        btns += `<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;width:100%;margin-top:4px;"><input type="url" id="pub-link-${item.id}" placeholder="Amazon published link (optional)" style="flex:1;min-width:160px;padding:9px 12px;border:1px solid #334155;border-radius:5px;background:#0a1628;color:#e2e8f0;font-size:13px;outline:none;"><button class="btn2 actionbtn" style="flex-shrink:0;" onclick="publishProduct(${item.id})">📦 Publish</button></div>`;
        btns += `<div style="display:flex;gap:8px;margin-top:6px;width:100%;"><button class="actionbtn" style="background:#d97706;color:#fff;flex:1;" onclick="openRevisionBox(${item.id},'design')">🎨 Revise Design</button><button class="actionbtn" style="background:#db2777;color:#fff;flex:1;" onclick="openRevisionBox(${item.id},'content')">✍ Revise Content</button></div>`;
    }
    if(isListing && item.work_status === 'Republish') btns += `<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;width:100%;margin-top:4px;"><input type="url" id="pub-link-${item.id}" placeholder="Amazon Re-Publish link (required)" style="flex:1;min-width:160px;padding:9px 12px;border:2px solid #dc2626;border-radius:5px;background:#1a0505;color:#fca5a5;font-size:13px;outline:none;"><button class="btn2 actionbtn" style="flex-shrink:0;background:#dc2626;" onclick="publishProduct(${item.id})">📦 Re-Publish</button></div>`;
    if(((ROLE === 'eco_client' && item.status === 'Generated') || isAdmin) && item.work_status !== 'Work Done'){
        btns = `<button class="btn1 actionbtn" id="u-${item.id}" disabled onclick="save(${item.id},'Updated')">UPDATED</button><button class="btn2 actionbtn" id="a-${item.id}" ${approvedDisabled?'disabled':''} onclick="save(${item.id},'Approved')">APPROVED</button>` + btns;
    }
    return btns;
}

function buildAdminButtons(item, isUrgent, lock){
    var approvedDisabled = lock || item.status !== 'Generated';
    var isInfoStage = (item.product_type === 'Infographics' && !item.content_approved_at && !item.content_updated_at && item.status !== 'Approved' && item.status !== 'Updated')
                   || (item.product_type === 'Info + A Plus' && (item.status === 'Pending' || item.status === 'AI DONE') && !item.content_approved_at && !item.content_updated_at);
    var btns = '';
    if(item.status === 'AI Work'){
        btns += `<button class="btn2 actionbtn" style="background:#16a34a;color:#fff;" onclick="updateWorkStatus(${item.id},'AI DONE')">🤖 AI Done</button>`;
    }
    if(item.work_status !== 'Work Done' && item.work_status !== 'Info Done' && !isInfoStage){
        btns += `<button class="btn1 actionbtn" id="u-${item.id}" disabled onclick="save(${item.id},'Updated')">UPDATED</button>`;
        btns += `<button class="btn2 actionbtn" id="a-${item.id}" ${approvedDisabled?'disabled':''} onclick="save(${item.id},'Approved')">APPROVED</button>`;
    }
    if((item.work_status === 'Work Done' || item.work_status === 'Info Done') && (item.product_type === 'Infographics' || item.product_type === 'Info + A Plus')){
        btns += `<button class="actionbtn" style="background:#7c3aed;color:#fff;" onclick="startAplusWorkflow(${item.id})">🎨 Start A+ Banners</button>`;
    }
    return btns;
}

/* ── Perform / reset (admin) ─────────────────── */
function applyReset(taskId){
    var sel = document.getElementById('resetSel_' + taskId);
    var stage = sel ? sel.value : '';
    if(!stage){ alert('Pehle reset stage select karein'); return; }
    if(stage === '__unpublish__'){ unpublishProduct(taskId); return; }
    forceStage(taskId, stage);
}

function applyPerform(taskId){
    var sel = document.getElementById('performSel_' + taskId);
    var act = sel ? sel.value : '';
    if(!act){ alert('Pehle koi action select karein'); return; }
    if(act === 'edit_services')                openEditServices(taskId);
    else if(act === 'save')                    writerSave(taskId);
    else if(act.startsWith('hold|'))           holdProduct(taskId, parseInt(act.split('|')[1]));
    else if(act.startsWith('urgent|'))         setUrgent(taskId, parseInt(act.split('|')[1]));
    else if(act.startsWith('settype|'))        setProductType(taskId, act.split('|')[1] || '');
    else if(act === 'clear')                   clearTask(taskId);
    else if(act === 'delete')                  deleteTask(taskId);
    else if(act === 'publish')                 publishProduct(taskId);
    else if(act === 'infowork')                updateWorkStatus(taskId, 'Info Work');
    else if(act === 'send_content')            sendForContent(taskId);
    else if(act === 'working')                 updateWorkStatus(taskId, 'Working');
    else if(act === 'pause')                   pauseWork(taskId);
    else if(act === 'resume')                  resumeWork(taskId);
    else if(act === 'qaready')                 updateWorkStatus(taskId, 'In QA');
    else if(act === 'remove_invoiced')         clearInvoiceStatus(taskId);
    else if(act === 'enable_aplus' || act === 'start_aplus') startAplusWorkflow(taskId);
}

/* ── Edit Services & Workers (Admin) ──────────── */
function openEditServices(taskId){
    var task = (typeof ALL_TASKS !== 'undefined' ? ALL_TASKS : []).find(function(t){ return parseInt(t.id) === parseInt(taskId); });
    if(!task){ alert('Product not found'); return; }

    var modal = document.getElementById('editServicesModal');
    if(!modal) return;

    var titleEl = document.getElementById('es-modal-title');
    if(titleEl) titleEl.innerHTML = '✏️ Edit Services & Assignments — #' + (task.product_no || '') + ' ' + (task.title || '');
    document.getElementById('es-task-id').value = task.id;

    var subtasks = (task.info_subtasks || '').split(',').map(function(s){ return s.trim(); });
    var hasAi = subtasks.includes('AI Work') || task.status === 'AI Work' || task.status === 'AI DONE' || !!task.ai_worked_by;
    var hasInfo = subtasks.includes('Infographics') || task.product_type === 'Infographics' || task.product_type === 'Info + A Plus' || (!task.info_subtasks && !task.product_type);
    var hasAplus = subtasks.includes('A+ Banners') || task.product_type === 'Info + A Plus' || task.product_type === 'A+' || task.product_type === 'A Plus';

    document.getElementById('es-chk-ai').checked = hasAi;
    document.getElementById('es-chk-info').checked = hasInfo;
    document.getElementById('es-chk-aplus').checked = hasAplus;

    var workerList = (typeof ALL_PAYROLL_WORKERS !== 'undefined' && ALL_PAYROLL_WORKERS.length) ? ALL_PAYROLL_WORKERS : (typeof ALL_WORKERS !== 'undefined' ? ALL_WORKERS : []);

    var selAi = document.getElementById('es-sel-ai');
    var selInfo = document.getElementById('es-sel-info');
    var selAplus = document.getElementById('es-sel-aplus');

    selAi.innerHTML = '<option value="">(None / Unassigned)</option>';
    selInfo.innerHTML = '<option value="">(None / Unassigned)</option>';
    selAplus.innerHTML = '<option value="">(None / Unassigned)</option>';

    workerList.forEach(function(w){
        var roleLabel = w.role === 'ai_work' ? ' [AI]' : (w.role === 'worker' ? ' [Worker]' : '');
        var opt = '<option value="' + w.id + '">' + w.username + roleLabel + '</option>';
        selAi.innerHTML += opt;
        selInfo.innerHTML += opt;
        selAplus.innerHTML += opt;
    });

    if(task.ai_worked_by) selAi.value = String(task.ai_worked_by);
    if(task.info_worker_id) selInfo.value = String(task.info_worker_id);
    else if(task.assigned_worker_id && hasInfo && !hasAplus) selInfo.value = String(task.assigned_worker_id);
    
    if(task.aplus_worker_id) selAplus.value = String(task.aplus_worker_id);

    document.getElementById('es-sel-active').value = 'unchanged';
    modal.style.display = 'flex';
}

function closeEditServices(){
    var modal = document.getElementById('editServicesModal');
    if(modal) modal.style.display = 'none';
}

function saveEditServices(){
    var taskId = document.getElementById('es-task-id').value;
    if(!taskId) return;

    var hasAi = document.getElementById('es-chk-ai').checked;
    var hasInfo = document.getElementById('es-chk-info').checked;
    var hasAplus = document.getElementById('es-chk-aplus').checked;

    if(!hasAi && !hasInfo && !hasAplus){
        alert('Kam az kam aik service zaroor select karein!');
        return;
    }

    var services = [];
    if(hasAi) services.push('AI Work');
    if(hasInfo) services.push('Infographics');
    if(hasAplus) services.push('A+ Banners');

    var aiWorkerId = document.getElementById('es-sel-ai').value || '';
    var infoWorkerId = document.getElementById('es-sel-info').value || '';
    var aplusWorkerId = document.getElementById('es-sel-aplus').value || '';
    var activeAssign = document.getElementById('es-sel-active').value || 'unchanged';

    var btn = document.getElementById('es-btn-save');
    if(btn){ btn.disabled = true; btn.innerText = 'Saving...'; }

    var formData = new FormData();
    formData.append('task_id', taskId);
    formData.append('services', services.join(','));
    formData.append('ai_worker_id', aiWorkerId);
    formData.append('info_worker_id', infoWorkerId);
    formData.append('aplus_worker_id', aplusWorkerId);
    formData.append('active_assign', activeAssign);
    if(typeof CSRF_TOKEN !== 'undefined') formData.append('_csrf', CSRF_TOKEN);

    fetch('index.php?action=update_product_services', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if(btn){ btn.disabled = false; btn.innerText = 'Save Changes'; }
        if(res.ok){
            closeEditServices();
            loadTasks();
        } else {
            alert('Error: ' + (res.message || 'Could not update services'));
        }
    })
    .catch(err => {
        if(btn){ btn.disabled = false; btn.innerText = 'Save Changes'; }
        alert('Request failed');
    });
}

/* ── Editing helpers ─────────────────────────── */
function markEditing(id)  { IS_EDITING[id] = true; }
function unmarkEditing(id){ setTimeout(function(){ IS_EDITING[id] = false; }, 5000); }

function revertToOriginal(id){
    var editor = document.getElementById('editor-' + id);
    if(!editor) return;
    editor.innerHTML = decodeURIComponent(editor.getAttribute('data-original'));
    IS_EDITING[id] = false;
    clientEditorChanged(id);
}

/* ── Diff viewer ─────────────────────────────── */
function diffWords(oldStr, newStr){
    var oldWords = oldStr.split(/(\s+)/);
    var newWords = newStr.split(/(\s+)/);
    var m = oldWords.length, n = newWords.length;
    var dp = [];
    for(var i = 0; i <= m; i++){ dp[i] = []; for(var j = 0; j <= n; j++) dp[i][j] = 0; }
    for(var i = 1; i <= m; i++) for(var j = 1; j <= n; j++){
        if(oldWords[i-1] === newWords[j-1]) dp[i][j] = dp[i-1][j-1] + 1;
        else dp[i][j] = Math.max(dp[i-1][j], dp[i][j-1]);
    }
    var result = []; var i = m, j = n;
    while(i > 0 || j > 0){
        if(i > 0 && j > 0 && oldWords[i-1] === newWords[j-1]){ result.unshift({type:'same', val:oldWords[i-1]}); i--; j--; }
        else if(j > 0 && (i === 0 || dp[i][j-1] >= dp[i-1][j])){ result.unshift({type:'add',  val:newWords[j-1]}); j--; }
        else { result.unshift({type:'del', val:oldWords[i-1]}); i--; }
    }
    return result;
}

function getPlainText(html){
    var tmp = document.createElement('div'); tmp.innerHTML = html;
    return tmp.innerText || tmp.textContent || '';
}

function renderDiff(id, overrideOriginal, overrideCurrent){
    var preview = document.getElementById('diff-' + id);
    if(!preview) return;
    var originalText, currentText;
    if(overrideOriginal !== undefined){
        originalText = getPlainText(overrideOriginal);
        currentText  = getPlainText(overrideCurrent);
    } else {
        var editor = document.getElementById('editor-' + id);
        if(!editor) return;
        originalText = getPlainText(decodeURIComponent(editor.getAttribute('data-original')));
        currentText  = getPlainText(editor.innerHTML);
    }
    if(currentText.trim() === originalText.trim()){
        preview.style.display = 'none'; preview.innerHTML = '';
        var lbl = document.getElementById('diff-label-' + id); if(lbl) lbl.style.display = 'none';
        var rev = document.getElementById('revert-' + id);     if(rev) rev.style.display = 'none';
        return;
    }
    var diff = diffWords(originalText, currentText);
    var html = '';
    diff.forEach(function(part){
        var escaped = part.val.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        if(part.type === 'same') html += escaped;
        else if(part.type === 'add') html += '<span class="diff-add">' + escaped + '</span>';
        else html += '<span class="diff-del">' + escaped + '</span>';
    });
    preview.innerHTML = html; preview.style.display = 'block';
    var lbl = document.getElementById('diff-label-' + id); if(lbl) lbl.style.display = 'flex';
    var rev = document.getElementById('revert-' + id);     if(rev) rev.style.display = 'inline-block';
}

function clientEditorChanged(id){
    IS_EDITING[id] = true;
    if(ROLE !== 'eco_client' && ROLE !== 'administrator') return;
    var editor = document.getElementById('editor-' + id);
    var uBtn   = document.getElementById('u-' + id);
    var aBtn   = document.getElementById('a-' + id);
    if(!editor || !uBtn || !aBtn) return;
    var originalText = getPlainText(decodeURIComponent(editor.getAttribute('data-original')));
    var currentText  = getPlainText(editor.innerHTML);
    var changed = currentText.trim() !== originalText.trim();
    if(changed){ uBtn.disabled = false; aBtn.disabled = true; }
    else        { uBtn.disabled = true;  aBtn.disabled = false; }
    renderDiff(id);
}

/* ── Card toggle ─────────────────────────────── */
var _TOGGLE_LOCK = {};

function toggle(id){
    if(_TOGGLE_LOCK[id]) return;
    _TOGGLE_LOCK[id] = true;
    setTimeout(function(){ delete _TOGGLE_LOCK[id]; }, 300);
    OPEN[id] = !OPEN[id];
    var body = document.getElementById('body-' + id);
    if(body){
        var card = body.closest('.card');
        var toggleBtn = card ? card.querySelector('.toggle-icon') : null;
        if(OPEN[id]){
            body.classList.add('open');
            if(card) card.classList.add('open');
            if(toggleBtn) toggleBtn.style.transform = 'rotate(180deg)';
            /* Scroll card header into view on mobile */
            if(card){
                setTimeout(function(){
                    card.scrollIntoView({behavior:'smooth', block:'start'});
                }, 50);
            }
        } else {
            body.classList.remove('open');
            if(card) card.classList.remove('open');
            if(toggleBtn) toggleBtn.style.transform = 'rotate(0deg)';
        }
    }
}

function headClick(e, id){
    if(e.target.closest('.inline-assign-sel') ||
       e.target.closest('.inline-assign-btn') ||
       e.target.tagName === 'BUTTON' && !e.target.classList.contains('toggle') ||
       e.target.tagName === 'SELECT' ||
       e.target.tagName === 'INPUT'  ||
       e.target.tagName === 'A') return;
    toggle(id);
}

function headTouch(e, id){
    if(e.target.closest('.inline-assign-sel') ||
       e.target.closest('.inline-assign-btn') ||
       e.target.tagName === 'BUTTON' && !e.target.classList.contains('toggle') ||
       e.target.tagName === 'SELECT' ||
       e.target.tagName === 'INPUT'  ||
       e.target.tagName === 'A') return;
    e.preventDefault();
    toggle(id);
}

/* ── Save task (client approve/update) ───────── */
function save(id, status){
    var editor  = document.getElementById('editor-' + id);
    var content = editor.innerHTML;
    if(content.trim() === '') return;
    var fd = new FormData();
    fd.append('action', 'save_task'); fd.append('id', id);
    fd.append('content', content);   fd.append('status', status);
    fetch('index.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(r => { if(r.success) loadTasks(); });
}

/* ── Writer save ─────────────────────────────── */
function writerSave(id){
    var editor  = document.getElementById('editor-' + id);
    var content = editor.innerHTML;
    if(content.replace(/<[^>]+>/g, '').trim() === ''){ alert('Content khali hai!'); return; }
    var fd = new FormData();
    fd.append('action', 'save_task'); fd.append('id', id);
    fd.append('content', content);   fd.append('status', 'Generated');
    fetch('index.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(r => { if(r.success){ IS_EDITING[id] = false; loadTasks(); } });
}

/* ── Clear / Delete task ─────────────────────── */
function clearTask(id){
    _confirm('Clear Data?', function(){
        var fd = new FormData();
        fd.append('action', 'clear_task'); fd.append('id', id);
        fetch('index.php', {method:'POST', body:fd}).then(function(r){ return r.json(); }).then(function(){ loadTasks(); });
    });
}

function deleteTask(id){
    _confirm('Is product ko Recycle Bin mein move karein?', function(){
        var fd = new FormData();
        fd.append('action', 'delete_task'); fd.append('id', id);
        fetch('index.php', {method:'POST', body:fd})
            .then(function(r){ return r.json(); })
            .then(function(r){
                if(r.success){
                    var card = document.querySelector('.card[data-id="' + id + '"]');
                    if(card) card.remove();
                    ALL_TASKS = ALL_TASKS.filter(function(t){ return t.id != id; });
                    renderSmart(ALL_TASKS);
                    if(typeof updateNotifications === 'function') updateNotifications(ALL_TASKS);
                } else { alert('Delete fail ho gaya'); }
            });
    });
}

/* ── QA actions ──────────────────────────────── */
function _hasSeoContent(id) {
    var task = ALL_TASKS.find(function(t){ return t.id == id; });
    if (task && task.product_type === 'Infographics') return true;
    var seoIds = ['seo-'+id, 'seo-doc-'+id, 'admin-seo-'+id];
    for (var i = 0; i < seoIds.length; i++) {
        var el = document.getElementById(seoIds[i]);
        if (el && el.value.trim()) return true;
    }
    var panel = document.getElementById('seo-panel-' + id);
    if (panel) {
        var inputs = panel.querySelectorAll('input[type="text"],input[type="url"],textarea');
        for (var j = 0; j < inputs.length; j++) {
            if (inputs[j].value.trim()) return true;
        }
    }
    return false;
}

function submitQA(id){
    var media = document.getElementById('media-' + id);
    var seo   = document.getElementById('seo-' + id);
    if(!media) return;
    
    if(!media.value.trim()){ alert('Media / Google Drive link zaroori hai'); return; }
    
    var taskItem = ALL_TASKS.find(function(t){ return t.id == id; });
    var isInfoStage = taskItem && ((taskItem.product_type === 'Infographics')
                   || (taskItem.product_type === 'Info + A Plus' && (taskItem.status === 'Pending' || taskItem.status === 'AI DONE' || taskItem.status === 'Infographics') && !taskItem.content_approved_at && !taskItem.content_updated_at));
    var isInfoOnly = isInfoStage || (taskItem && taskItem.product_type === 'Infographics');
    var seoVal = seo ? seo.value.trim() : '';
    if(!isInfoOnly && !seoVal && !_hasSeoContent(id)){ alert('SEO Doc. link ya SEO Content Form mein data zaroori hai'); return; }
    
    var fd = new FormData();
    fd.append('action', 'submit_qa'); fd.append('task_id', id);
    fd.append('media_link', media.value.trim()); fd.append('seo_doc_link', seoVal);
    if(typeof CSRF_TOKEN !== 'undefined') fd.append('_csrf', CSRF_TOKEN);
    
    document.body.style.cursor = 'wait';
    fetch('index.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(r => { 
            document.body.style.cursor = 'default';
            if(r.success) loadTasks(); else alert(r.message || 'Submit failed'); 
        })
        .catch(e => { document.body.style.cursor = 'default'; alert('Upload Error'); });
}


function sendForSEO(id){
    _confirm('Is product ko SEO Manager k paas bhejein?', function(){
        var fd = new FormData();
        fd.append('action', 'send_for_seo'); fd.append('task_id', id);
        if(typeof CSRF_TOKEN !== 'undefined') fd.append('_csrf', CSRF_TOKEN);
        fetch('index.php', {method:'POST', body:fd})
            .then(function(r){ return r.json(); })
            .then(function(r){ if(r.success) loadTasks(); else alert(r.message || 'Failed'); });
    });
}

function adminSubmitSEO(id){
    var media = document.getElementById('seo-media-' + id);
    var seo   = document.getElementById('seo-doc-' + id);
    if(!media) return;
    
    if(!media.value.trim()){ alert('Media / Google Drive link zaroori hai'); return; }
    
    var taskItem = ALL_TASKS.find(function(t){ return t.id == id; });
    var isInfoStage = taskItem && ((taskItem.product_type === 'Infographics')
                   || (taskItem.product_type === 'Info + A Plus' && (taskItem.status === 'Pending' || taskItem.status === 'AI DONE' || taskItem.status === 'Infographics') && !taskItem.content_approved_at && !taskItem.content_updated_at));
    var isInfoOnly = isInfoStage || (taskItem && taskItem.product_type === 'Infographics');
    var seoVal = seo ? seo.value.trim() : '';
    if(!isInfoOnly && !seoVal && !_hasSeoContent(id)){ alert('SEO Doc. link ya SEO Content Form mein data zaroori hai'); return; }
    
    var fd = new FormData();
    fd.append('action', 'submit_qa'); fd.append('task_id', id);
    fd.append('media_link', media.value.trim()); fd.append('seo_doc_link', seoVal);
    if(typeof CSRF_TOKEN !== 'undefined') fd.append('_csrf', CSRF_TOKEN);
    
    document.body.style.cursor = 'wait';
    fetch('index.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(r => { 
            document.body.style.cursor = 'default';
            if(r.success) loadTasks(); else alert(r.message || 'Submit failed'); 
        })
        .catch(e => { document.body.style.cursor = 'default'; alert('Upload Error'); });
}

function saveFinalLinks(id){
    var media = document.getElementById('admin-media-' + id);
    var seo   = document.getElementById('admin-seo-' + id);
    if(!media) return;
    
    if(!media.value.trim()){ alert('Media / Google Drive link zaroori hai'); return; }
    
    var taskItem = ALL_TASKS.find(function(t){ return t.id == id; });
    var isInfoStage = taskItem && ((taskItem.product_type === 'Infographics')
                   || (taskItem.product_type === 'Info + A Plus' && (taskItem.status === 'Pending' || taskItem.status === 'AI DONE' || taskItem.status === 'Infographics') && !taskItem.content_approved_at && !taskItem.content_updated_at));
    var isInfoOnly = isInfoStage || (taskItem && taskItem.product_type === 'Infographics');
    var seoVal = seo ? seo.value.trim() : '';
    if(!isInfoOnly && !seoVal && !_hasSeoContent(id)){ alert('SEO Doc. link ya SEO Content Form mein data zaroori hai'); return; }
    
    var fd = new FormData();
    fd.append('action', 'save_final_links'); fd.append('task_id', id);
    fd.append('media_link', media.value.trim()); fd.append('seo_doc_link', seoVal);
    if(typeof CSRF_TOKEN !== 'undefined') fd.append('_csrf', CSRF_TOKEN);
    
    document.body.style.cursor = 'wait';
    fetch('index.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(r => { 
            document.body.style.cursor = 'default';
            if(r.success) loadTasks(); else alert(r.message || 'Save failed'); 
        })
        .catch(e => { document.body.style.cursor = 'default'; alert('Upload Error'); });
}

function startAplusWorkflow(id){
    _confirm('Is product per A+ Banner ka work start karein?\nProduct A+ Content Generation stage mein chala jayega.', function(){
        var fd = new FormData();
        fd.append('action', 'start_aplus_workflow');
        fd.append('task_id', id);
        if(typeof CSRF_TOKEN !== 'undefined') fd.append('_csrf', CSRF_TOKEN);
        fetch('index.php?action=start_aplus_workflow', {method:'POST', body:fd})
            .then(r => r.json())
            .then(r => { 
                if(r.success || r.ok) {
                    alert(r.message || 'A+ Banner workflow shuru ho gaya');
                    loadTasks();
                } else {
                    alert(r.message || 'Failed');
                }
            })
            .catch(function(){ alert('Request failed'); });
    });
}

function enableAplusService(id){
    startAplusWorkflow(id);
}

/* ── Revision system ──────────────────────────── */
function openRevisionBox(taskId, revisionType){
    var boxId = 'revision-box-' + taskId;
    var box = document.getElementById(boxId);
    if(box){ box.remove(); return; }

    var isDesign = revisionType === 'design';
    var titleColor = isDesign ? '#fb923c' : '#f472b6';
    var borderColor = isDesign ? '#b45309' : '#be185d';
    var btnBg = isDesign ? '#d97706' : '#db2777';
    var typeLabel = isDesign ? 'Design' : 'Content';

    var div = document.createElement('div');
    div.id = boxId;
    div.style.cssText = 'margin:10px 0;padding:12px;background:#0f0f15;border:2px solid '+borderColor+';border-radius:7px;';
    div.innerHTML = '<div style="color:'+titleColor+';font-weight:bold;font-size:12px;margin-bottom:8px;">🎨 Request ' + typeLabel + ' Revision (worker/writer ko dikhega)</div>'
      +'<textarea id="revision-txt-'+taskId+'" style="width:100%;box-sizing:border-box;padding:8px;background:#0c0a0f;border:1px solid '+borderColor+';border-radius:5px;color:#fff;font-size:13px;min-height:80px;resize:vertical;" placeholder="Revision details enter karein..."></textarea>'
      +'<div style="display:flex;gap:8px;margin-top:8px;">'
      +'<button onclick="submitRevision('+taskId+',\''+revisionType+'\')" style="flex:1;padding:8px;background:'+btnBg+';color:#fff;border:none;border-radius:5px;font-weight:bold;cursor:pointer;">Send for ' + typeLabel + ' Revision</button>'
      +'<button onclick="document.getElementById(\''+boxId+'\').remove()" style="padding:8px 14px;background:#374151;color:#d1d5db;border:none;border-radius:5px;cursor:pointer;">Cancel</button>'
      +'</div>';

    var body = document.getElementById('body-'+taskId);
    if(body){
      var actDiv = body.querySelector('.actions');
      if(actDiv) actDiv.before(div);
      else body.prepend(div);
    }
}

function submitRevision(taskId, revisionType){
    var txt = document.getElementById('revision-txt-'+taskId);
    if(!txt) return;
    var comment = txt.value.trim();
    if(!comment){ alert('Revision details likhna zaroori hai.'); return; }

    var fd = new FormData();
    fd.append('action','request_revision');
    fd.append('task_id',taskId);
    fd.append('revision_type',revisionType);
    fd.append('comment',comment);

    fetch('index.php',{method:'POST',body:fd})
    .then(function(r){ return r.json(); })
    .then(function(r){
      if(r.success){ loadTasks(); }
      else { alert('Revision request failed: '+(r.message||'Error')); }
    })
    .catch(function(err){ alert('Request failed: '+err.message); });
}

function markRepublish(taskId){
    _confirm('Product ko Republish (Eco Listing) par bhejein?', function(){
        var fd = new FormData();
        fd.append('action', 'force_stage'); fd.append('task_id', taskId); fd.append('stage', 'Republish');
        fetch('index.php', {method:'POST', body:fd})
            .then(function(r){ return r.json(); })
            .then(function(r){ if(r.success) loadTasks(); else alert('Republish failed: ' + (r.message || 'Error')); })
            .catch(function(err){ alert('Request failed: ' + err.message); });
    });
}

/* ── Bulk Actions for Pending Products ───────── */
function toggleBulkSelection() {
    SELECTED_BULK_PRODUCTS = [];
    var chks = document.querySelectorAll('.bulk-chk');
    chks.forEach(function(chk) {
        if (chk.checked) {
            SELECTED_BULK_PRODUCTS.push(parseInt(chk.getAttribute('data-id')));
        }
    });
    updateBulkActionBar();
}

function toggleSelectAllBulk(checked) {
    var chks = document.querySelectorAll('.bulk-chk');
    chks.forEach(function(chk) {
        chk.checked = checked;
        var taskId = parseInt(chk.getAttribute('data-id'));
        if (checked) {
            if (SELECTED_BULK_PRODUCTS.indexOf(taskId) === -1) {
                SELECTED_BULK_PRODUCTS.push(taskId);
            }
        } else {
            var idx = SELECTED_BULK_PRODUCTS.indexOf(taskId);
            if (idx !== -1) {
                SELECTED_BULK_PRODUCTS.splice(idx, 1);
            }
        }
    });
    updateBulkActionBar();
}

function clearBulkSelection() {
    SELECTED_BULK_PRODUCTS = [];
    var chks = document.querySelectorAll('.bulk-chk');
    chks.forEach(function(chk) {
        chk.checked = false;
    });
    updateBulkActionBar();
}

function updateBulkActionBar() {
    var bar = document.getElementById('bulk-action-bar');
    if (!bar) return;
    
    var filterEl = document.getElementById('filter');
    var isPending = filterEl && filterEl.value === 'Pending';
    var count = SELECTED_BULK_PRODUCTS.length;
    
    if (typeof HAS_BULK_ACTION !== 'undefined' && HAS_BULK_ACTION && isPending && count > 0) {
        bar.style.display = 'flex';
        document.getElementById('bulk-selected-count').textContent = count;
        
        var selectAll = document.getElementById('bulk-select-all');
        if (selectAll) {
            var chks = document.querySelectorAll('.bulk-chk');
            if (chks.length > 0) {
                var allChecked = true;
                chks.forEach(function(chk) {
                    if (!chk.checked) allChecked = false;
                });
                selectAll.checked = allChecked;
            } else {
                selectAll.checked = false;
            }
        }
    } else {
        bar.style.display = 'none';
        var selectAll = document.getElementById('bulk-select-all');
        if (selectAll) selectAll.checked = false;
    }
}

function applyBulkAction() {
    var actionSelect = document.getElementById('bulk-action-select');
    var action = actionSelect ? actionSelect.value : '';
    if (!action) {
        alert('Pehle koi action select karein');
        return;
    }
    
    if (SELECTED_BULK_PRODUCTS.length === 0) {
        alert('Koi product select nahi hai');
        return;
    }
    
    var msg = '';
    if (action === 'urgent') {
        msg = 'Kya aap select shuda products ko URGENT mark karna chahte hain?';
    } else if (action === 'hold') {
        msg = 'Kya aap select shuda products ko HOLD par daalna chahte hain?';
    }
    
    _confirm(msg, function(){
        var fd = new FormData();
        fd.append('action', 'bulk_update_tasks');
        fd.append('task_ids', SELECTED_BULK_PRODUCTS.join(','));
        fd.append('bulk_action', action);
        fetch('index.php', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(r){
                if (r.success) {
                    SELECTED_BULK_PRODUCTS = [];
                    updateBulkActionBar();
                    loadTasks();
                } else {
                    alert('Failed: ' + (r.message || ''));
                }
            });
    });
}

/* ── Page init ───────────────────────────────── */
setInterval(loadTasks, 5000);
loadTasks();
switchTab('products');

if (ROLE === 'administrator') {
    loadWorkersAndProducts();
    loadPayrollWorkers();
} else if (ROLE === 'qa') {
    loadPayrollWorkers();
}

function buildFamilyGroupingSection(item, isAdmin) {
    if (isAdmin) {
        var familyTagsHtml = '';
        var members = item.family_code ? ALL_TASKS.filter(function(t) { return t.family_code === item.family_code; }) : [];
        members.forEach(function(m) {
            var isCurrent = m.id === item.id;
            var currentStyle = isCurrent ? 'background:#1d4ed8; border: 1px solid #60a5fa;' : 'background:#2563eb;';
            familyTagsHtml += `<span class="product-tag" style="display:inline-flex; align-items:center; ${currentStyle} color:#fff; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:bold; gap:6px; margin: 2px 0;">${m.product_no} <span onclick="removeProductFromFamily(event, ${m.id}, ${item.id})" style="cursor:pointer; font-weight:bold; font-size:11px; opacity:0.8; padding: 0 2px;">✕</span></span>`;
        });

        return `
<div class="group-product-container" style="margin-bottom:12px; background:#0f2035; border:1px solid #1e3a5f; border-radius:8px; padding:10px 14px;">
    <div style="font-size:11px; font-weight:700; color:#60a5fa; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.5px;">GROUP PRODUCT</div>
    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
        <div class="tags-input-container" style="flex:1; min-width:200px; display:flex; flex-wrap:wrap; align-items:center; gap:6px; background:#0a1628; border:1px solid #1e3a5f; border-radius:6px; padding:4px 10px; min-height:36px;">
            <span id="family-tags-${item.id}" style="display:inline-flex; flex-wrap:wrap; gap:6px;">${familyTagsHtml}</span>
            <input type="text" id="group-input-${item.id}" placeholder="Product No. likhen" onkeydown="handleGroupInputKeydown(event, ${item.id})" style="flex:1; border:none; background:transparent; outline:none; color:#e2e8f0; font-size:13px; min-width:100px;">
        </div>
        <button onclick="addProductToFamily(${item.id}, document.getElementById('group-input-${item.id}').value, document.getElementById('group-input-${item.id}'))" style="height:36px;padding:0 14px;background:#2563eb;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:bold;cursor:pointer;flex-shrink:0;">+ Add</button>
        ${item.family_code ? `<button onclick="viewFamily('${item.family_code}')" class="adminbtn" style="background:#8b5cf6; color:#fff; font-weight:bold; font-size:12px; padding:0 16px; border-radius:6px; height:36px; cursor:pointer; border:none; flex-shrink:0;">VIEW GROUP</button>` : ''}
    </div>
    <div id="group-msg-${item.id}" style="font-size:11px;margin-top:4px;"></div>
</div>`;
    } else {
        if (item.family_code) {
            return `
<div class="group-product-container" style="margin-bottom:12px; background:#0f2035; border:1px solid #1e3a5f; border-radius:8px; padding:10px 14px; display:flex; justify-content:space-between; align-items:center;">
    <span style="font-size:12px; color:#94a3b8; font-weight:500;">This product is part of a family group.</span>
    <button onclick="viewFamily('${item.family_code}')" style="background:#8b5cf6; color:#fff; font-weight:bold; font-size:12px; padding:8px 16px; border-radius:6px; border:none; cursor:pointer;">💜 View Family</button>
</div>`;
        }
    }
    return '';
}

/* ── Family Grouping Handlers ────────────────── */
function handleGroupInputKeydown(event, taskId) {
    if (event.key === 'Enter') {
        event.preventDefault();
        var val = event.target.value.trim();
        if (!val) return;
        addProductToFamily(taskId, val, event.target);
    }
}

function addProductToFamily(taskId, productNo, inputEl) {
    var cleanNo = (productNo || '').replace(/^#/, '').replace(/\+$/, '').trim();
    var msgEl = document.getElementById('group-msg-' + taskId);
    if(!cleanNo){
        if(msgEl) msgEl.innerHTML = '<span style="color:#f87171;">Product number khali hai</span>';
        return;
    }
    if(msgEl) msgEl.innerHTML = '<span style="color:#94a3b8;">Adding...</span>';
    var fd = new FormData();
    fd.append('action', 'add_to_family');
    fd.append('task_id', taskId);
    fd.append('product_no', cleanNo);
    fetch('index.php', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(r){
            if (r.success) {
                if (inputEl) inputEl.value = '';
                if(msgEl) msgEl.innerHTML = '';
                loadTasks();
            } else {
                if(msgEl) msgEl.innerHTML = '<span style="color:#f87171;">❌ ' + (r.message || 'Product nahi mila') + '</span>';
            }
        })
        .catch(function(err){
            if(msgEl) msgEl.innerHTML = '<span style="color:#f87171;">❌ Error: ' + err.message + '</span>';
        });
}

function removeProductFromFamily(event, memberTaskId, cardTaskId) {
    if (event) event.stopPropagation();
    var msgEl = document.getElementById('group-msg-' + cardTaskId);
    if(msgEl) msgEl.innerHTML = '<span style="color:#94a3b8;">Removing...</span>';
    var fd = new FormData();
    fd.append('action', 'remove_from_family');
    fd.append('task_id', memberTaskId);
    fetch('index.php', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(r){
            if (r.success) {
                if(msgEl) msgEl.innerHTML = '';
                loadTasks();
            } else {
                if(msgEl) msgEl.innerHTML = '<span style="color:#f87171;">❌ ' + (r.message || 'Remove nahi hua') + '</span>';
            }
        })
        .catch(function(err){
            if(msgEl) msgEl.innerHTML = '<span style="color:#f87171;">❌ Error: ' + err.message + '</span>';
        });
}

function viewFamily(familyCode) {
    if (!familyCode) return;
    ACTIVE_FAMILY_FILTER = familyCode;
    resetPageAndRender();
}

function clearFamilyFilter() {
    ACTIVE_FAMILY_FILTER = null;
    resetPageAndRender();
}

/* ── Recycle Bin Handlers ────────────────────── */
function loadDeletedTasks() {
    fetch('index.php?action=get_deleted_tasks')
        .then(r => r.json())
        .then(r => {
            if (r.success) {
                renderDeletedTasks(r.data);
            }
        });
}

function renderDeletedTasks(tasks) {
    var container = document.getElementById('deleted-tasks-list');
    if (!container) return;
    
    if (!tasks || tasks.length === 0) {
        container.innerHTML = `<div style="text-align:center; padding:40px; color:#64748b; font-size:14px; background:#0f2035; border:1px solid #1e3a5f; border-radius:10px;">Recycle Bin is empty</div>`;
        var emptyBtn = document.getElementById('empty-bin-btn');
        if (emptyBtn) emptyBtn.style.display = 'none';
        return;
    }

    var emptyBtn = document.getElementById('empty-bin-btn');
    if (emptyBtn) emptyBtn.style.display = 'block';

    container.innerHTML = '';
    tasks.forEach(function(t) {
        var dateStr = t.deleted_at || '';
        var row = document.createElement('div');
        row.style.cssText = 'background:#0f2035; border:1px solid #1e3a5f; border-radius:10px; padding:14px 18px; display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:10px;';
        
        row.innerHTML = `
            <div style="display:flex; align-items:center; gap:12px; flex:1; min-width:250px;">
                <span style="background:#dc2626; color:#fff; font-size:11px; font-weight:bold; padding:2px 8px; border-radius:4px;">#${t.product_no}</span>
                <div>
                    <div style="font-size:14px; font-weight:600; color:#f1f5f9;">${t.title || 'Untitled'}</div>
                    <div style="font-size:11px; color:#64748b; margin-top:2px;">Deleted at: ${dateStr}</div>
                </div>
            </div>
            <div style="display:flex; gap:8px;">
                <button onclick="restoreTask(${t.id})" class="adminbtn" style="background:#16a34a; color:#fff; border:none; padding:6px 14px; border-radius:6px; cursor:pointer; font-weight:bold; font-size:12px;">Restore</button>
                <button onclick="permanentDeleteTask(${t.id})" class="adminbtn" style="background:#dc2626; color:#fff; border:none; padding:6px 14px; border-radius:6px; cursor:pointer; font-weight:bold; font-size:12px;">Delete Forever</button>
            </div>
        `;
        container.appendChild(row);
    });
}

function restoreTask(id) {
    _confirm('Is product ko restore karein?', function(){
        var fd = new FormData();
        fd.append('action', 'restore_task');
        fd.append('task_id', id);
        fetch('index.php', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(r){
                if (r.success) { loadDeletedTasks(); loadTasks(); }
                else { alert('Restore failed'); }
            });
    });
}

function permanentDeleteTask(id) {
    _confirm('⚠️ Permanent delete — wapas nahi ho ga. Confirm karein?', function(){
        var fd = new FormData();
        fd.append('action', 'permanent_delete_task');
        fd.append('task_id', id);
        fetch('index.php', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(r){
                if (r.success) { loadDeletedTasks(); }
                else { alert('Delete failed'); }
            });
    });
}

function emptyRecycleBin() {
    _confirm('⚠️ Recycle Bin ke SARE products permanently delete ho jaenge. Pakka?', function(){
        var fd = new FormData();
        fd.append('action', 'empty_recycle_bin');
        fd.append('csrf_token', CSRF_TOKEN);
        fetch('index.php', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(r){
                if (r.success) { loadDeletedTasks(); }
                else { alert('Empty bin failed'); }
            });
    });
}
