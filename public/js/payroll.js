/*
=====================================================
ECO A+ PRO — payroll.js
Payslip generation, worker rates, ledger,
worker progress dashboard.
Depends on: core.js
=====================================================
*/

var _psAllProducts = {};   /* keyed by workerId */
var _psCart        = {};   /* keyed by workerId → {taskId: item} */

function _getItemRate(productType, rateInfo, rateAplus){
    if(productType==='Info + A Plus') return rateInfo+rateAplus;
    if(productType==='Infographics')  return rateInfo;
    return rateAplus;
}
function _typeLabel(t){ return t==='Info + A Plus'?'Info+A+':t==='Infographics'?'Info':'A+'; }

/* ── Panel init ──────────────────────────────── */
function initPayrollPanel(){
    loadPenalties();
    if(ROLE === 'administrator'){
        loadWorkerRates();
        loadPayrollWorkers();
    } else {
        loadPayslips();
        loadWorkerAccount();
    }
}

function loadWorkerAccount(){
    var earnedEl  = document.getElementById('ps-worker-earned');
    var advanceEl = document.getElementById('ps-worker-advance');
    var paidoutEl = document.getElementById('ps-worker-paidout');
    var balEl     = document.getElementById('ps-worker-balance');
    var historyBox = document.getElementById('ps-account-history');
    if(earnedEl)  earnedEl.textContent  = 'Loading...';
    if(advanceEl) advanceEl.textContent = 'Loading...';
    if(paidoutEl) paidoutEl.textContent = 'Loading...';
    if(balEl)     balEl.textContent     = 'Loading...';
    if(historyBox) historyBox.innerHTML = '<div style="color:#94a3b8;font-size:13px;padding:10px;">Loading...</div>';

    fetch('index.php?action=get_worker_account&worker_id=' + encodeURIComponent(USER_ID))
        .then(r => r.json())
        .then(r => {
            if(!r.success){
                if(earnedEl) earnedEl.textContent = 'Error';
                if(historyBox) historyBox.innerHTML = '<div style="color:#f87171;font-size:13px;padding:10px;">Unable to load account.</div>';
                return;
            }
            var data = r;
            if(earnedEl)  earnedEl.textContent  = 'PKR ' + parseFloat(data.total_earned || 0).toFixed(2);
            if(advanceEl) advanceEl.textContent = 'PKR ' + parseFloat(data.total_advance || 0).toFixed(2);
            if(paidoutEl) paidoutEl.textContent = 'PKR ' + parseFloat(data.total_paid_out || 0).toFixed(2);
            if(balEl)     balEl.textContent     = 'PKR ' + parseFloat(data.balance || 0).toFixed(2);

            var earnedRange = document.getElementById('ps-earned-range');
            var paidDate    = document.getElementById('ps-paid-date');
            var advanceDate = document.getElementById('ps-advance-date');
            var pendingPayslipEl = document.getElementById('ps-stat-pending-payslip');
            var invoicedEl  = document.getElementById('ps-stat-invoiced');
            var paidCountEl = document.getElementById('ps-stat-paid');
            var pendingEl   = document.getElementById('ps-stat-pending');
            var workingEl   = document.getElementById('ps-stat-working');
            var pausedEl    = document.getElementById('ps-stat-paused');
            var inqaEl      = document.getElementById('ps-stat-inqa');
            var workdoneEl  = document.getElementById('ps-stat-workdone');

            if(earnedRange) {
                var from = data.earned_from || 'N/A';
                var to   = data.earned_to   || 'N/A';
                earnedRange.textContent = (from !== 'N/A' && to !== 'N/A') ? from + ' → ' + to : 'Date range not available';
            }
            if(paidDate)    paidDate.textContent    = data.last_paid_date   ? 'Last paid: ' + data.last_paid_date   : 'No payment recorded';
            if(advanceDate) advanceDate.textContent = data.last_advance_date? 'Last advance: ' + data.last_advance_date : 'No advance recorded';
            if(pendingPayslipEl) pendingPayslipEl.textContent = parseInt(data.pending_payslip || 0);
            if(invoicedEl)  invoicedEl.textContent  = parseInt(data.stats?.invoiced_count || 0);
            if(paidCountEl) paidCountEl.textContent = parseInt(data.stats?.paid_count || 0);
            if(pendingEl)   pendingEl.textContent   = parseInt(data.stats?.pending || 0);
            if(workingEl)   workingEl.textContent   = parseInt(data.stats?.working || 0);
            if(pausedEl)    pausedEl.textContent    = parseInt(data.stats?.paused || 0);
            if( inqaEl)     inqaEl.textContent      = parseInt(data.stats?.in_qa || 0);
            if(workdoneEl)  workdoneEl.textContent  = parseInt(data.stats?.work_done || 0);

            if(!historyBox) return;
            var entries = [];
            var monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

            function normalizeDate(value){
                if(!value) return null;
                var d = new Date(value);
                return isNaN(d.getTime()) ? null : d;
            }

            function get10thPeriod(date){
                var year = date.getFullYear();
                var month = date.getMonth();
                var day = date.getDate();
                var start, end;
                if(day >= 10){
                    start = new Date(year, month, 10);
                    var nextMonth = month + 1;
                    var nextYear = year;
                    if(nextMonth > 11){ nextMonth = 0; nextYear++; }
                    end = new Date(nextYear, nextMonth, 9);
                } else {
                    var prevMonth = month - 1;
                    var prevYear = year;
                    if(prevMonth < 0){ prevMonth = 11; prevYear--; }
                    start = new Date(prevYear, prevMonth, 10);
                    end = new Date(year, month, 9);
                }
                var label = monthNames[start.getMonth()] + ' ' + start.getDate() + ' to ' + monthNames[end.getMonth()] + ' ' + end.getDate() + ' ' + end.getFullYear();
                return { key: start.toISOString().slice(0,10) + '_' + end.toISOString().slice(0,10), label: label, start: start, end: end };
            }

            function formatDate(d){ return d ? d.toISOString().slice(0,10) : '-'; }

            (data.payslips || []).forEach(function(ps){
                var date = normalizeDate(ps.created_at);
                entries.push({
                    date: date,
                    type: 'payslip',
                    title: 'Payslip: ' + (ps.month || 'N/A'),
                    amount: parseFloat(ps.total_amount || 0),
                    raw: ps
                });
            });
            (data.entries || []).forEach(function(entry){
                var date = normalizeDate(entry.transaction_date || entry.created_at);
                entries.push({
                    date: date,
                    type: entry.type || 'ledger',
                    title: entry.notes || (entry.type === 'advance' ? 'Advance Payment' : 'Ledger entry'),
                    amount: parseFloat(entry.amount || 0),
                    raw: entry
                });
            });

            entries.sort(function(a,b){
                if(!a.date) return 1;
                if(!b.date) return -1;
                return a.date - b.date;
            });

            var grouped = {};
            entries.forEach(function(entry){
                var period = entry.date ? get10thPeriod(entry.date) : { key:'unknown', label:'Unknown' };
                if(!grouped[period.key]){
                    grouped[period.key] = { label: period.label, start: period.start, end: period.end, items: [] };
                }
                grouped[period.key].items.push(entry);
            });

            var today = new Date();
            var currentPeriod = get10thPeriod(today);
            if(!grouped[currentPeriod.key]){
                grouped[currentPeriod.key] = { label: currentPeriod.label, start: currentPeriod.start, end: currentPeriod.end, items: [] };
            }

            var currentKeys = [];
            var pastKeys = [];
            Object.keys(grouped).forEach(function(key){
                if(key === currentPeriod.key) currentKeys.push(key);
                else pastKeys.push(key);
            });
            pastKeys.sort(function(a,b){ return grouped[b].start - grouped[a].start; });
            var orderedKeys = currentKeys.concat(pastKeys);
            var html = '';
            orderedKeys.forEach(function(key){
                var group = grouped[key];
                var isCurrent = key === currentPeriod.key;
                var summary = group.items.length + ' record' + (group.items.length === 1 ? '' : 's');
                var sectionId = 'ps-history-' + key.replace(/[^a-z0-9]/gi,'');
                var openStyle = isCurrent ? 'block' : 'none';

                var headerLabel = '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">';
                headerLabel += '<div style="background:#2563eb;padding:6px 12px;border-radius:6px;color:#fff;font-weight:700;font-size:12px;">' + group.label + '</div>';
                if(isCurrent) headerLabel += '<div style="background:#0b1220;padding:6px 12px;border-radius:6px;color:#fff;font-weight:700;font-size:12px;margin-left:6px;">CURRENT MONTH</div>';
                headerLabel += '</div>';

                html += '<div style="border:1px solid #1e3a5f;border-radius:10px;margin-bottom:10px;overflow:hidden;">';
                html += '<button onclick="togglePayrollHistorySection(\'' + sectionId + '\')" style="width:100%;background:#0f2035;border:none;color:#e2e8f0;display:flex;justify-content:space-between;align-items:center;padding:10px 14px;cursor:pointer;font-size:13px;font-weight:700;">';
                html += '<span>' + headerLabel + '</span>';
                html += '<span style="font-size:11px;color:#94a3b8;">' + summary + '</span>';
                html += '</button>';
                html += '<div id="' + sectionId + '" style="display:' + openStyle + ';background:#071428;padding:14px;">';

                if(isCurrent){
                    var currentWrapper = document.getElementById('ps-current-panel-wrapper');
                    if(currentWrapper){
                        html += currentWrapper.innerHTML;
                    }
                }

                if(group.items.length){
                    html += '<table class="inv-table"><thead><tr><th>Date</th><th>Type</th><th>Description</th><th style="text-align:right;">Earned</th><th style="text-align:right;">Paid</th></tr></thead><tbody>';
                    group.items.forEach(function(entry){
                        var earnedAmt = '';
                        var paidAmt = '';
                        if(entry.type === 'payslip'){
                            earnedAmt = 'PKR ' + entry.amount.toFixed(2);
                        } else if(entry.type === 'advance' || entry.type === 'paid' || entry.type === 'ledger'){
                            paidAmt = 'PKR ' + entry.amount.toFixed(2);
                        }
                        html += '<tr>';
                        html += '<td>' + formatDate(entry.date) + '</td>';
                        html += '<td>' + entry.type.charAt(0).toUpperCase() + entry.type.slice(1) + '</td>';
                        html += '<td>' + (entry.title || '-') + '</td>';
                        html += '<td style="text-align:right;">' + earnedAmt + '</td>';
                        html += '<td style="text-align:right;">' + paidAmt + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table>';
                } else {
                    html += '<div style="color:#94a3b8;font-size:13px;padding:10px 0;">Koi ledger entry nahi mili.</div>';
                }
                html += '</div></div>';
            });
            // remove the original wrapper from DOM to avoid duplicate rendering
            var orig = document.getElementById('ps-current-panel-wrapper'); if(orig) orig.parentNode.removeChild(orig);
            historyBox.innerHTML = html;
        })
        .catch(function(err){
            if(historyBox) historyBox.innerHTML = '<div style="color:#f87171;font-size:13px;padding:10px;">Load failed: ' + err.message + '</div>';
        });
}

function togglePayrollHistorySection(id){
    var section = document.getElementById(id);
    if(!section) return;
    section.style.display = section.style.display === 'block' ? 'none' : 'block';
}

/* ── Worker rates ────────────────────────────── */
function loadWorkerRates(filterMonth = null){
    var box = document.getElementById('rates-container');
    if(!box) return Promise.resolve();
    var roleLabel = {worker:'Worker', seo_manager:'SEO Manager', d4u_writer:'Writer', ai_work:'AI Worker', qa:'QA'};
    
    if (filterMonth === null) {
        var monthInput = document.getElementById('worker-balances-month');
        filterMonth = monthInput ? monthInput.value : '';
    }
    
    if (filterMonth === 'custom') {
        var customPicker = document.getElementById('custom-month-picker');
        filterMonth = customPicker ? customPicker.value : '';
        if (!filterMonth) return Promise.resolve(); // wait for user to pick
    }

    var balancesUrl = 'index.php?action=get_all_worker_balances';
    if(filterMonth) {
        balancesUrl += '&month=' + encodeURIComponent(filterMonth);
    }

    return Promise.all([
        fetch('index.php?action=get_worker_rates').then(r => r.json()),
        fetch(balancesUrl).then(r => r.json())
    ]).then(function(results){
        var rates  = results[0].data || [];
        var bals   = results[1].data || [];
        var balMap = {};
        bals.forEach(function(b){ balMap[b.id] = b; });

        var usd  = window.EXCHANGE_RATE || 278.0;
        
        var masterTotal = 0;
        rates.forEach(function(w){
            var b = balMap[w.id] || {};
            masterTotal += parseFloat(b.last_total_paid || 0);
        });
        
        var lbl = 'TOTAL PAYABLE';
        if (filterMonth && filterMonth !== 'custom') {
            var d = new Date();
            var m = (d.getMonth() + 1).toString().padStart(2, '0');
            var curr = d.getFullYear() + '-' + m;
            if (filterMonth < curr) {
                lbl = 'TOTAL PAID';
            }
        }
        
        var lblEl = document.getElementById('master-total-label');
        var amtEl = document.getElementById('master-total-amount');
        if (lblEl) lblEl.textContent = lbl;
        if (amtEl) amtEl.textContent = 'PKR ' + masterTotal.toFixed(0);

        var html = '<div style="display:flex;flex-direction:column;gap:6px;">';
        rates.forEach(function(w){
            var b          = balMap[w.id] || {prev_d4u:0,current_adv:0,current_loan:0,last_earned:0,last_total_paid:0};
            var prevD4u    = parseFloat(b.prev_d4u        || 0);
            var curAdv     = parseFloat(b.current_adv     || 0);
            var curLoan    = parseFloat(b.current_loan    || 0);
            var lastEarned = parseFloat(b.last_earned     || 0);
            var lastPaid   = parseFloat(b.last_total_paid || 0);
            var wName      = w.username.replace(/"/g, '&quot;');

            html += `<div style="background:#0f2035;border:1px solid #1e3a5f;border-radius:8px;overflow:hidden;">
<div style="display:flex;align-items:center;gap:8px;padding:10px 14px;cursor:pointer;flex-wrap:wrap;" onclick="toggleRateRow(${w.id})">
  <div style="display:flex;align-items:center;gap:8px;width:220px;flex-shrink:0;">
    <div style="font-weight:bold;color:#e2e8f0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:145px;">${w.username}</div>
    <div style="font-size:10px;color:#94a3b8;background:#1e293b;padding:2px 6px;border-radius:4px;white-space:nowrap;font-weight:600;text-transform:uppercase;">${roleLabel[w.role]||w.role}</div>
  </div>
  <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;" onclick="event.stopPropagation()">
    ${(w.role === 'ai_work') ? `
    <span style="color:#a78bfa;font-size:10px;font-weight:700;white-space:nowrap;">AI Work:</span>
    <input type="number" class="rate-input" id="rate-ai-${w.id}" value="${parseFloat(w.rate_ai_work || w.rate_per_product || w.rate_infographics || 0).toFixed(2)}" min="0" step="0.01" style="width:65px;padding:3px;background:#071428;border:1px solid #7c3aed66;color:#e2e8f0;border-radius:4px;outline:none;" title="AI Work rate">
    ` : `
    <span style="color:#64748b;font-size:10px;white-space:nowrap;">Info:</span>
    <input type="number" class="rate-input" id="rate-info-${w.id}" value="${parseFloat(w.rate_infographics||0).toFixed(2)}" min="0" step="0.01" style="width:60px;padding:3px;background:#071428;border:1px solid #1e3a5f;color:#e2e8f0;border-radius:4px;outline:none;" title="Infographics rate">
    <span style="color:#64748b;font-size:10px;white-space:nowrap;">A+:</span>
    <input type="number" class="rate-input" id="rate-aplus-${w.id}" value="${parseFloat(w.rate_aplus||0).toFixed(2)}" min="0" step="0.01" style="width:60px;padding:3px;background:#071428;border:1px solid #1e3a5f;color:#e2e8f0;border-radius:4px;outline:none;" title="A+ Banners rate">
    `}
    <span style="color:#64748b;font-size:10px;white-space:nowrap;margin-left:4px;">Fine:</span>
    <input type="number" class="rate-input" id="fine-${w.id}" value="${parseFloat(w.fine_per_revision||0).toFixed(2)}" min="0" step="0.01" style="width:55px;padding:3px;background:#071428;border:1px solid #1e3a5f;color:#e2e8f0;border-radius:4px;outline:none;">
    <button class="inv-btn inv-btn-blue" style="padding:4px 8px;font-size:11px;" onclick="saveWorkerRate(${w.id})">Save</button>
  </div>
  <div style="display:flex;gap:5px;align-items:center;margin-left:auto;flex-shrink:0;flex-wrap:wrap;">
    <div style="background:#071428;border:1px solid #f8717155;border-radius:6px;padding:5px 10px;text-align:center;min-width:80px;">
      <div style="color:#475569;font-size:9px;letter-spacing:.4px;font-weight:700;">PREV. D4U</div>
      <div id="hdr-prevd4u-${w.id}" style="color:#f87171;font-size:12px;font-weight:bold;margin-top:2px;">PKR ${prevD4u.toFixed(0)}</div>
      <div style="color:#64748b;font-size:10px;margin-top:1px;">$${(prevD4u/usd).toFixed(2)}</div>
    </div>
    <div style="background:#071428;border:1px solid #f8717155;border-radius:6px;padding:5px 10px;text-align:center;min-width:78px;">
      <div style="color:#475569;font-size:9px;letter-spacing:.4px;font-weight:700;">NO WORK LOAN</div>
      <div id="hdr-loan-${w.id}" data-base="${curLoan.toFixed(2)}" style="color:#f87171;font-size:12px;font-weight:bold;margin-top:2px;">&minus;PKR ${curLoan.toFixed(0)}</div>
      <div style="color:#64748b;font-size:10px;margin-top:1px;">$${(curLoan/usd).toFixed(2)}</div>
    </div>
    <div style="background:#071428;border:1px solid #93c5fd44;border-radius:6px;padding:5px 10px;text-align:center;min-width:78px;">
      <div style="color:#475569;font-size:9px;letter-spacing:.4px;font-weight:700;">LAST EARNED</div>
      <div style="color:#93c5fd;font-size:12px;font-weight:bold;margin-top:2px;">PKR ${lastEarned.toFixed(0)}</div>
      <div style="color:#64748b;font-size:10px;margin-top:1px;">$${(lastEarned/usd).toFixed(2)}</div>
    </div>
    <div style="background:#071428;border:1px solid #f8717155;border-radius:6px;padding:5px 10px;text-align:center;min-width:78px;">
      <div style="color:#475569;font-size:9px;letter-spacing:.4px;font-weight:700;">ADV. BY D4U</div>
      <div id="hdr-advance-${w.id}" data-base="${curAdv.toFixed(2)}" style="color:#f87171;font-size:12px;font-weight:bold;margin-top:2px;">&minus;PKR ${curAdv.toFixed(0)}</div>
      <div style="color:#64748b;font-size:10px;margin-top:1px;">$${(curAdv/usd).toFixed(2)}</div>
    </div>
    <div style="background:#071428;border:1px solid #16a34a55;border-radius:6px;padding:5px 10px;text-align:center;min-width:78px;">
      <div style="color:#475569;font-size:9px;letter-spacing:.4px;font-weight:700;">LAST TOTAL PAID</div>
      <div style="color:#4ade80;font-size:12px;font-weight:bold;margin-top:2px;">PKR ${lastPaid.toFixed(0)}</div>
      <div style="color:#64748b;font-size:10px;margin-top:1px;">$${(lastPaid/usd).toFixed(2)}</div>
    </div>
    <button id="rate-toggle-${w.id}" style="background:none;border:1px solid #334155;border-radius:4px;color:#94a3b8;font-size:12px;cursor:pointer;padding:3px 8px;flex-shrink:0;" onclick="event.stopPropagation();toggleRateRow(${w.id})">▼</button>
  </div>
</div>
<div id="rate-acc-${w.id}" style="display:none;border-top:1px solid #1e3a5f;background:#071428;" data-worker-name="${wName}">
  <div style="padding:16px;color:#475569;font-size:13px;text-align:center;">⏳ Loading account history...</div>
</div>
</div>`;
        });
        html += '</div>';
        box.innerHTML = html;
    });
}

function toggleRateRow(id){
    var acc = document.getElementById('rate-acc-'    + id);
    var btn = document.getElementById('rate-toggle-' + id);
    if(!acc) return;
    var open = acc.style.display !== 'none';
    if(open){ acc.style.display='none'; if(btn) btn.textContent='▼'; }
    else    { acc.style.display='block'; if(btn) btn.textContent='▲'; if(acc.dataset.loaded !== '1') loadWorkerAccountInline(id); }
}

function saveWorkerRate(workerId){
    var inpInfo  = document.getElementById('rate-info-'+workerId);
    var inpAplus = document.getElementById('rate-aplus-'+workerId);
    var inpAi    = document.getElementById('rate-ai-'+workerId);
    var finp     = document.getElementById('fine-'+workerId);
    var fine     = finp ? (parseFloat(finp.value)||0) : 0;
    var fd = new FormData();
    fd.append('action','save_worker_rate');
    fd.append('worker_id',workerId);
    fd.append('fine',fine);

    if(inpAi){
        var rateAi = parseFloat(inpAi.value) || 0;
        fd.append('rate_ai_work', rateAi);
        fd.append('rate_per_product', rateAi);
        fd.append('rate_infographics', rateAi);
        fd.append('rate_aplus', rateAi);
    } else {
        if(!inpInfo || !inpAplus) return;
        var rateInfo  = parseFloat(inpInfo.value)  || 0;
        var rateAplus = parseFloat(inpAplus.value) || 0;
        fd.append('rate_infographics',rateInfo);
        fd.append('rate_aplus',rateAplus);
    }

    fetch('index.php',{method:'POST',body:fd}).then(r=>r.json()).then(r=>{
        if(r.success){
            if(inpAi)    inpAi.style.borderColor='#22c55e';
            if(inpInfo)  inpInfo.style.borderColor='#22c55e';
            if(inpAplus) inpAplus.style.borderColor='#22c55e';
            if(finp)     finp.style.borderColor='#22c55e';
        }
        setTimeout(function(){
            if(inpAi)    inpAi.style.borderColor='';
            if(inpInfo)  inpInfo.style.borderColor='';
            if(inpAplus) inpAplus.style.borderColor='';
            if(finp)     finp.style.borderColor='';
        },1500);
    });
}

/* ── Worker account (inline ledger + payslip generation) ──────────── */
function loadWorkerAccountInline(workerId){
    var acc        = document.getElementById('rate-acc-' + workerId);
    if(!acc) return;
    var workerName = acc.dataset.workerName || 'Worker';
    acc.innerHTML  = '<div style="padding:16px;color:#94a3b8;font-size:13px;">Loading...</div>';

    var monthInput = document.getElementById('worker-balances-month');
    var filterMonth = monthInput ? monthInput.value : '';
    if (filterMonth === 'custom') {
        var customPicker = document.getElementById('custom-month-picker');
        filterMonth = customPicker ? customPicker.value : '';
    }
    
    var url = 'index.php?action=get_worker_account&worker_id=' + workerId;
    if (filterMonth) url += '&month=' + encodeURIComponent(filterMonth);

    fetch(url).then(r => r.json()).then(r => {
        if(!r.success){ acc.innerHTML='<div style="padding:16px;color:#f87171;">Error loading account.</div>'; return; }
        acc.dataset.loaded      = '1';
        acc.dataset.prevD4u     = r.prev_d4u       || 0;
        acc.dataset.currentAdv  = r.current_adv    || 0;
        acc.dataset.currentLoan = r.current_loan   || 0;
        acc.dataset.penalty     = r.current_penalty || 0;

        var earned       = parseFloat(r.earned          || 0);
        var prevD4u      = parseFloat(r.prev_d4u        || 0);
        var actualEarned = parseFloat(r.actual_earned   || 0);
        var curAdv       = parseFloat(r.current_adv     || 0);
        var curLoan      = parseFloat(r.current_loan    || 0);
        var projected    = parseFloat(r.projected_total || 0);
        var rate         = parseFloat(r.rate            || 0);
        var pp           = parseInt(r.pending_payslip   || 0);
        var projColor    = projected >= 0 ? '#4ade80' : '#f87171';
        var s            = r.stats || {};
        var total        = parseInt(s.total)     || 0;
        var workDone     = parseInt(s.work_done) || 0;
        var workPct      = total > 0 ? Math.round(workDone / total * 100) : 0;

        var html = `<div style="padding:14px 16px;">
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
<div style="flex:1;min-width:65px;background:#0a1628;border:1px solid #0ea5e944;border-radius:7px;padding:10px;text-align:center;"><div style="color:#475569;font-size:9px;font-weight:700;letter-spacing:.4px;">PENDING</div><div style="color:#0ea5e9;font-size:20px;font-weight:bold;margin-top:3px;">${parseInt(s.pending)||0}</div></div>
<div style="flex:1;min-width:65px;background:#0a1628;border:1px solid #ea580c44;border-radius:7px;padding:10px;text-align:center;"><div style="color:#475569;font-size:9px;font-weight:700;letter-spacing:.4px;">WORKING</div><div style="color:#ea580c;font-size:20px;font-weight:bold;margin-top:3px;">${parseInt(s.working)||0}</div></div>
<div style="flex:1;min-width:65px;background:#0a1628;border:1px solid #b4530944;border-radius:7px;padding:10px;text-align:center;"><div style="color:#475569;font-size:9px;font-weight:700;letter-spacing:.4px;">PAUSED</div><div style="color:#b45309;font-size:20px;font-weight:bold;margin-top:3px;">${parseInt(s.paused)||0}</div></div>
<div style="flex:1;min-width:65px;background:#0a1628;border:1px solid #7c3aed44;border-radius:7px;padding:10px;text-align:center;"><div style="color:#475569;font-size:9px;font-weight:700;letter-spacing:.4px;">IN QA</div><div style="color:#a78bfa;font-size:20px;font-weight:bold;margin-top:3px;">${parseInt(s.in_qa)||0}</div></div>
<div style="flex:1;min-width:65px;background:#0a1628;border:1px solid #16a34a44;border-radius:7px;padding:10px;text-align:center;"><div style="color:#475569;font-size:9px;font-weight:700;letter-spacing:.4px;">WORK DONE</div><div style="color:#4ade80;font-size:20px;font-weight:bold;margin-top:3px;">${workDone}</div></div>
<div style="flex:1;min-width:65px;background:#0a1628;border:1px solid #2563eb44;border-radius:7px;padding:10px;text-align:center;"><div style="color:#475569;font-size:9px;font-weight:700;letter-spacing:.4px;">INVOICED</div><div style="color:#93c5fd;font-size:20px;font-weight:bold;margin-top:3px;">${parseInt(s.invoiced_count)||0}</div></div>
<div style="flex:1;min-width:65px;background:#0a1628;border:1px solid #16a34a44;border-radius:7px;padding:10px;text-align:center;"><div style="color:#475569;font-size:9px;font-weight:700;letter-spacing:.4px;">PAID</div><div style="color:#4ade80;font-size:20px;font-weight:bold;margin-top:3px;">${parseInt(s.paid_count)||0}</div></div>
<div style="flex:1;min-width:65px;background:#0a1628;border:1px solid #fbbf2444;border-radius:7px;padding:10px;text-align:center;"><div style="color:#475569;font-size:9px;font-weight:700;letter-spacing:.4px;">PAYSLIP PENDING</div><div style="color:#fbbf24;font-size:20px;font-weight:bold;margin-top:3px;">${pp}</div></div>
</div>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
<div style="flex:1;min-width:90px;background:#0a1628;border:1px solid #f8717155;border-radius:7px;padding:10px;text-align:center;"><div style="color:#64748b;font-size:9px;font-weight:700;letter-spacing:.5px;margin-bottom:4px;">PREV. D4U (DEDUCT)</div><div style="color:#f87171;font-size:14px;font-weight:bold;">PKR ${prevD4u.toFixed(2)}</div><div style="color:#64748b;font-size:9px;margin-top:3px;">Carried from last cycle</div></div>
<div style="flex:1;min-width:90px;background:#0a1628;border:1px solid #93c5fd44;border-radius:7px;padding:10px;text-align:center;"><div style="color:#64748b;font-size:9px;font-weight:700;letter-spacing:.5px;margin-bottom:4px;">EARNED (THIS CYCLE)</div><div style="color:#93c5fd;font-size:14px;font-weight:bold;">PKR ${earned.toFixed(2)}</div><div style="color:${actualEarned>=0?'#4ade80':'#f87171'};font-size:10px;margin-top:3px;">Actual: PKR ${actualEarned.toFixed(2)}</div></div>
<div style="flex:1;min-width:90px;background:#0a1628;border:1px solid #f8717144;border-radius:7px;padding:10px;text-align:center;"><div style="color:#64748b;font-size:9px;font-weight:700;letter-spacing:.5px;margin-bottom:4px;">ADV. BY D4U</div><div style="color:#f87171;font-size:14px;font-weight:bold;">&minus;PKR ${curAdv.toFixed(2)}</div><div style="color:#64748b;font-size:9px;margin-top:3px;">Already given (deduct)</div></div>
<div style="flex:1;min-width:90px;background:#0a1628;border:1px solid #f8717144;border-radius:7px;padding:10px;text-align:center;"><div style="color:#64748b;font-size:9px;font-weight:700;letter-spacing:.5px;margin-bottom:4px;">NO WORK LOAN D4U</div><div style="color:#f87171;font-size:14px;font-weight:bold;">&minus;PKR ${curLoan.toFixed(2)}</div><div style="color:#64748b;font-size:9px;margin-top:3px;">Already given (deduct)</div></div>
<div style="flex:1;min-width:90px;background:#0a1628;border:1px solid ${projColor}44;border-radius:7px;padding:10px;text-align:center;"><div style="color:#64748b;font-size:9px;font-weight:700;letter-spacing:.5px;margin-bottom:4px;">PROJECTED TOTAL PAID</div><div style="color:${projColor};font-size:14px;font-weight:bold;">PKR ${projected.toFixed(2)}</div><div style="color:#64748b;font-size:9px;margin-top:3px;">At payroll generation</div></div>
<div style="flex:1;min-width:90px;background:#0a1628;border:1px solid #1e3a5f;border-radius:7px;padding:10px;text-align:center;"><div style="color:#64748b;font-size:9px;font-weight:700;letter-spacing:.5px;margin-bottom:4px;">RATE / PRODUCT</div><div style="color:#fbbf24;font-size:14px;font-weight:bold;">PKR ${rate.toFixed(2)}</div><div style="color:#64748b;font-size:9px;margin-top:3px;">${pp} pending payslip</div></div>
</div>
${total > 0 ? `<div style="background:#0a1628;border-radius:4px;height:5px;overflow:hidden;margin-bottom:10px;"><div style="height:100%;width:${workPct}%;background:linear-gradient(90deg,#16a34a,#4ade80);"></div></div><div style="color:#64748b;font-size:10px;margin-bottom:12px;">${total} total — ${workPct}% done</div>` : ''}
<div style="background:#1a0f00;border:1px solid #f59e0b66;border-radius:8px;padding:12px;margin-bottom:14px;">
<div style="color:#fbbf24;font-size:10px;font-weight:700;letter-spacing:.5px;margin-bottom:10px;">💸 ADD DEBIT ENTRY</div>
<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
<div><label style="display:block;color:#64748b;font-size:10px;margin-bottom:4px;">TYPE</label>
<select class="inv-input" id="acc-type-${workerId}" onchange="previewLedgerAmount(${workerId})" style="width:240px;background:#071428;border:1px solid #1e3a5f;color:#e2e8f0;padding:6px;border-radius:4px;">
  <option value="advance">💸 ADV. BY D4U (Work in Approval)</option>
  <option value="loan">🏦 NO WORK LOAN D4U (Pre-Work)</option>
</select></div>
<div><label style="display:block;color:#64748b;font-size:10px;margin-bottom:4px;">DATE</label>
<input type="date" class="inv-input" id="acc-date-${workerId}" value="${new Date().toISOString().substring(0,10)}" style="width:145px;"></div>
<div><label style="display:block;color:#64748b;font-size:10px;margin-bottom:4px;">AMOUNT (PKR)</label>
<input type="number" class="inv-input" id="acc-amount-${workerId}" placeholder="0.00" min="0.01" step="0.01" style="width:130px;" oninput="previewLedgerAmount(${workerId})"></div>
<div style="flex:1;min-width:160px;"><label style="display:block;color:#64748b;font-size:10px;margin-bottom:4px;">NOTES</label>
<input type="text" class="inv-input" id="acc-notes-${workerId}" placeholder="e.g. May advance, Eid payment..." style="width:100%;"></div>
<button class="inv-btn inv-btn-blue" onclick="addLedgerEntryInline(${workerId})" style="align-self:flex-end;">+ Add</button>
<span id="acc-msg-${workerId}" style="font-size:12px;align-self:center;"></span>
</div></div>`;

        var transactions = [];
        (r.payslips || []).forEach(function(ps){
            transactions.push({date:ps.created_at?ps.created_at.substring(0,10):'', type:'payslip', desc:'Pay Slip: '+ps.month, amount:parseFloat(ps.total_amount), id:ps.id, sortDate:ps.created_at||''});
        });
        (r.entries || []).forEach(function(e){
            var displayDate = e.transaction_date ? e.transaction_date : (e.created_at ? e.created_at.substring(0,10) : '');
            var defaultDesc = e.type==='advance'?'ADV. BY D4U':e.type==='loan'?'NO WORK LOAN D4U':e.type==='paid'?'Paid':'Previous Payable';
            transactions.push({date:displayDate, type:e.type, desc:e.notes||defaultDesc, amount:parseFloat(e.amount), id:e.id, sortDate:e.transaction_date||e.created_at});
        });
        transactions.sort(function(a,b){ var sd=a.sortDate||a.date; var se=b.sortDate||b.date; return sd>se?1:sd<se?-1:0; });

        if(transactions.length){
            var running = 0;
            html += '<table class="inv-table"><thead><tr><th>Date</th><th>Type</th><th>Description</th><th style="text-align:right;">Earned</th><th style="text-align:right;">Paid</th><th style="text-align:right;">Running Balance</th><th style="width:30px;"></th></tr></thead><tbody>';
            transactions.forEach(function(t){
                var earnedAmt='', paidAmt='', typeLabel='', typeBg='';
                if(t.type==='payslip'){      running+=t.amount; earnedAmt='PKR '+t.amount.toFixed(2); typeLabel='📋 Payslip';                typeBg='#1e3a5f'; }
                else if(t.type==='loan')  { running-=t.amount; paidAmt='PKR '+t.amount.toFixed(2);   typeLabel='🏦 NO WORK LOAN D4U';      typeBg='#4c1d95'; }
                else if(t.type==='advance'){ running-=t.amount; paidAmt='PKR '+t.amount.toFixed(2);   typeLabel='💸 ADV. BY D4U';           typeBg='#92400e'; }
                else if(t.type==='payment'){ running-=t.amount; paidAmt='PKR '+t.amount.toFixed(2);   typeLabel='📋 Prev. Payable';      typeBg='#14532d'; }
                else if(t.type==='penalty'){ running-=t.amount; paidAmt='PKR '+t.amount.toFixed(2);   typeLabel='⚠ Penalty';             typeBg='#7f1d1d'; }
                else                       { running-=t.amount; paidAmt='PKR '+t.amount.toFixed(2);   typeLabel='✅ Paid';                typeBg='#15803d'; }
                var runColor = running>0?'#4ade80':running<0?'#f87171':'#94a3b8';
                var delBtn   = t.type!=='payslip' ? `<button style="background:#7f1d1d;border:none;color:#fca5a5;cursor:pointer;font-size:12px;font-weight:700;padding:3px 8px;border-radius:4px;" title="Delete" onclick="deleteLedgerEntryInline(${t.id},${workerId},'${t.type}')">✕ Del</button>` : '';
                html += `<tr>
<td style="font-size:11px;color:#64748b;white-space:nowrap;">${t.date?t.date.substring(0,10):'-'}</td>
<td><span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:bold;background:${typeBg};color:#fff;white-space:nowrap;">${typeLabel}</span></td>
<td style="font-size:12px;">${t.desc}</td>
<td style="text-align:right;color:#93c5fd;font-size:12px;">${earnedAmt}</td>
<td style="text-align:right;color:#fbbf24;font-size:12px;">${paidAmt}</td>
<td style="text-align:right;font-weight:bold;color:${runColor};font-size:13px;">PKR ${running.toFixed(2)}</td>
<td>${delBtn}</td>
</tr>`;
            });
            html += '</tbody></table>';
        } else {
            html += '<div style="color:#64748b;font-size:13px;padding:10px 0;">Abhi tak koi transaction nahi hua.</div>';
        }

        /* ── Generate Pay Slip section ─────────────────── */
        /* ── Pay Slips list ──────────────────────── */
        html += `
<div style="margin-top:18px;background:#071e12;border:1px solid #16a34a55;border-radius:8px;padding:14px;">
<div style="font-size:11px;font-weight:700;color:#4ade80;letter-spacing:.5px;margin-bottom:12px;">📋 PAY SLIPS</div>`;
        if(r.payslips && r.payslips.length){
            html += '<div style="overflow-x:auto;"><table class="inv-table"><thead><tr><th>Month</th><th style="text-align:center;">Items</th><th>Earned</th><th>Total Paid</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
            r.payslips.slice().reverse().forEach(function(ps){
                var st = ps.status === 'Paid'
                    ? '<span class="inv-status-badge inv-status-paid">Paid</span>'
                    : '<span class="inv-status-badge inv-status-generated">Generated</span>';
                var act = `<button class="inv-btn inv-btn-gray" style="padding:3px 8px;font-size:11px;" onclick="showPayslipDetailInline(${ps.id}, ${workerId})">View</button> `;
                if(ps.status !== 'Paid') act += `<button class="inv-btn inv-btn-green" style="padding:3px 8px;font-size:11px;" onclick="markPayslipPaidInline(${ps.id},${workerId})">Mark Paid</button> `;
                act += `<button class="inv-btn" style="padding:3px 8px;font-size:11px;background:#dc2626;color:#fff;" onclick="deletePayslipInline(${ps.id},${workerId})">Delete</button>`;
                var tPaid = parseFloat(ps.total_paid||0) > 0 ? parseFloat(ps.total_paid) : parseFloat(ps.total_amount);
                html += `<tr><td>${ps.month}</td><td style="text-align:center;">${ps.item_count||0}</td><td style="color:#93c5fd;">PKR ${parseFloat(ps.total_amount).toFixed(2)}</td><td style="color:#4ade80;font-weight:bold;">PKR ${tPaid.toFixed(2)}</td><td>${st}</td><td style="white-space:nowrap;">${act}</td></tr>`;
            });
            html += '</tbody></table></div>';
        } else {
            html += '<div style="color:#64748b;font-size:13px;">Koi pay slip nahi mila.</div>';
        }
        html += `<div id="ps-detail-box-${workerId}" class="inv-detail-box" style="margin-top:16px;"></div>`;
        html += '</div>';

        /* ── Generate Pay Slip section ─────────────────── */
        html += `
<div style="margin-top:18px;background:#071428;border:1px solid #2563eb55;border-radius:8px;padding:14px;">
<div style="font-size:11px;font-weight:700;color:#60a5fa;letter-spacing:.5px;margin-bottom:12px;">📄 GENERATE PAY SLIP</div>
<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:12px;">
<div><label style="display:block;color:#64748b;font-size:10px;margin-bottom:4px;">DATE FROM</label>
<input type="date" class="inv-input" id="ps-date-from-${workerId}" style="width:145px;"></div>
<div><label style="display:block;color:#64748b;font-size:10px;margin-bottom:4px;">DATE TO</label>
<input type="date" class="inv-input" id="ps-date-to-${workerId}" style="width:145px;"></div>
<div><label style="display:block;color:#64748b;font-size:10px;margin-bottom:4px;">LABEL</label>
<input type="text" class="inv-input" id="ps-label-${workerId}" placeholder="e.g. May 2026" style="width:145px;"></div>
<button class="inv-btn inv-btn-blue" onclick="loadWorkerProductsForPayslipInline(${workerId})">Load Products</button>
</div>
<div id="ps-product-filter-bar-${workerId}" style="display:none;margin-bottom:10px;"></div>
<div id="ps-cart-section-${workerId}" style="display:none;margin-bottom:12px;"></div>
<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
<button class="inv-btn inv-btn-green" onclick="generatePayslipInline(${workerId})">📄 Generate Pay Slip</button>
<span id="ps-total-preview-${workerId}" style="font-size:13px;color:#4ade80;font-weight:bold;"></span>
</div>
</div>`;

        html += '</div>';
        acc.innerHTML = html;
    });
}

/* ── Payslip total live-update helper ───────────────── */
function _psUpdateTotal(earned, totalPrevD4u, penalty){
    var freshAdv  = parseFloat((document.getElementById('_ps_fresh_adv')  || {}).value) || 0;
    var freshLoan = parseFloat((document.getElementById('_ps_fresh_loan') || {}).value) || 0;
    var total     = earned - totalPrevD4u + freshAdv + freshLoan - penalty;
    var el        = document.getElementById('_ps_total_display');
    if(el){ el.textContent = 'PKR ' + total.toFixed(2); el.style.color = total >= 0 ? '#4ade80' : '#f87171'; }
}


/* ── Shared: reload worker accordion + update header ── */
function _reloadWorkerAccordion(workerId){
    var acc = document.getElementById('rate-acc-'    + workerId);
    var btn = document.getElementById('rate-toggle-' + workerId);
    if(acc){
        acc.dataset.loaded = '';
        acc.style.display  = 'block';
        if(btn) btn.textContent = '▲';
        loadWorkerAccountInline(workerId);
    }
    /* fire-and-forget to update header badges */
    loadWorkerRates().catch(function(){});
}

/* ── Ledger preview / add / delete ──────────── */
function previewLedgerAmount(workerId){
    var amtEl  = document.getElementById('acc-amount-'  + workerId);
    var typeEl = document.getElementById('acc-type-'    + workerId);
    var loanEl = document.getElementById('hdr-loan-'    + workerId);
    var advEl  = document.getElementById('hdr-advance-' + workerId);
    if(!amtEl) return;

    var amount   = parseFloat(amtEl.value) || 0;
    var type     = typeEl ? typeEl.value : 'advance';
    var baseLoan = loanEl ? parseFloat(loanEl.dataset.base || 0) : 0;
    var baseAdv  = advEl  ? parseFloat(advEl.dataset.base  || 0) : 0;

    /* Reset to base when amount is cleared */
    if(amount <= 0){
        if(loanEl){ loanEl.textContent='PKR '+baseLoan.toFixed(0); loanEl.style.color='#a78bfa'; }
        if(advEl) { advEl.textContent ='PKR '+baseAdv.toFixed(0);  advEl.style.color ='#fbbf24'; }
        return;
    }

    if(type === 'loan'){
        if(loanEl){ loanEl.textContent='PKR '+(baseLoan+amount).toFixed(0); loanEl.style.color='#c4b5fd'; }
        if(advEl) { advEl.textContent ='PKR '+baseAdv.toFixed(0); }
    } else {
        if(advEl) { advEl.textContent ='PKR '+(baseAdv+amount).toFixed(0);  advEl.style.color='#facc15'; }
        if(loanEl){ loanEl.textContent='PKR '+baseLoan.toFixed(0); }
    }
}

function addLedgerEntryInline(workerId){
    var type    = (document.getElementById('acc-type-' + workerId) || {value:'advance'}).value || 'advance';
    var amount  = parseFloat(document.getElementById('acc-amount-' + workerId).value) || 0;
    var notes   = document.getElementById('acc-notes-'  + workerId).value.trim();
    var dateVal = (document.getElementById('acc-date-'  + workerId) || {}).value || new Date().toISOString().substring(0,10);
    var msg     = document.getElementById('acc-msg-'    + workerId);
    if(amount <= 0){ msg.style.color='#f87171'; msg.textContent='Amount darj karein'; return; }
    var fd = new FormData();
    fd.append('action', 'add_ledger_entry'); fd.append('worker_id', workerId);
    fd.append('type', type); fd.append('amount', amount);
    fd.append('notes', notes); fd.append('transaction_date', dateVal);
    fetch('index.php', {method:'POST', body:fd}).then(r => r.json()).then(r => {
        if(r.success){
            msg.style.color='#4ade80'; msg.textContent='✅ Added!';
            setTimeout(function(){ _reloadWorkerAccordion(workerId); }, 600);
        } else { msg.style.color='#f87171'; msg.textContent=r.message||'Error'; }
    });
}

function deleteLedgerEntryInline(entryId, workerId, entryType){
    var typeLabel = entryType === 'loan' ? 'loan (pre-work) entry' : 'advance entry';
    _confirm('Ye ' + typeLabel + ' delete karein?', function(){
        var fd = new FormData();
        fd.append('action', 'delete_ledger_entry');
        fd.append('id', entryId);
        fetch('index.php', {method:'POST', body:fd})
            .then(function(resp){ return resp.json(); })
            .then(function(r){
                if(r.success){
                    _reloadWorkerAccordion(workerId);
                } else {
                    alert('Delete failed: ' + (r.message || 'Unknown error'));
                }
            })
            .catch(function(err){ alert('Delete error: ' + err.message); });
    });
}

/* ══════════════════════════════════════════════════
   INLINE PAYSLIP GENERATION (per-worker accordion)
   ══════════════════════════════════════════════════ */

function loadWorkerProductsForPayslipInline(wid){
    var dateFrom = (document.getElementById('ps-date-from-' + wid) || {}).value || '';
    var dateTo   = (document.getElementById('ps-date-to-'   + wid) || {}).value || '';
    var fb       = document.getElementById('ps-product-filter-bar-' + wid);

    _psAllProducts[wid] = [];
    _psCart[wid]        = _psCart[wid] || {};

    if(fb){ fb.style.display='none'; fb.innerHTML=''; }

    fetch('index.php?action=get_worker_products_for_payslip&worker_id=' + wid
        + (dateFrom ? '&date_from=' + dateFrom : '')
        + (dateTo   ? '&date_to='   + dateTo   : ''))
        .then(r => r.json())
        .then(function(r){
            if(!r.success || !r.data || !r.data.length){
                if(fb){ fb.innerHTML='<div style="color:#f87171;font-size:13px;padding:8px 0;">Koi product nahi mila — ya sab ka payslip already ban chuka hai.</div>'; }
                return;
            }
            _psAllProducts[wid] = r.data;
            renderPayslipTableInline(wid);
        })
        .catch(function(err){
            if(fb){ fb.innerHTML='<div style="color:#f87171;font-size:13px;padding:8px 0;">Load failed: ' + err.message + '</div>'; }
        });
}

function renderPayslipTableInline(wid){
    var fb   = document.getElementById('ps-product-filter-bar-' + wid);
    if(!fb) return;

    /* Build filter bar if not built yet */
    if(!document.getElementById('ps-table-wrap-' + wid)){
        fb.style.display = 'block';
        fb.innerHTML = '<div class="inv-filter-bar" style="margin-bottom:8px;">'
            + '<label style="display:flex;align-items:center;gap:5px;font-size:12px;color:#94a3b8;cursor:pointer;white-space:nowrap;"><input type="checkbox" id="ps-f-workdone-' + wid + '" onchange="renderPayslipTableInline(' + wid + ')" checked> Work Done</label>'
            + '<label style="display:flex;align-items:center;gap:5px;font-size:12px;color:#94a3b8;cursor:pointer;white-space:nowrap;"><input type="checkbox" id="ps-f-invoiced-' + wid + '" onchange="renderPayslipTableInline(' + wid + ')"> Invoiced</label>'
            + '<label style="display:flex;align-items:center;gap:5px;font-size:12px;color:#94a3b8;cursor:pointer;white-space:nowrap;"><input type="checkbox" id="ps-f-paid-'     + wid + '" onchange="renderPayslipTableInline(' + wid + ')"> Paid</label>'
            + '<label style="display:flex;align-items:center;gap:5px;font-size:12px;color:#94a3b8;cursor:pointer;white-space:nowrap;"><input type="checkbox" id="ps-f-all-'     + wid + '" onchange="renderPayslipTableInline(' + wid + ')"> All</label>'
            + '<input type="text" class="inv-input" id="ps-search-' + wid + '" placeholder="Search..." oninput="renderPayslipTableInline(' + wid + ')" style="flex:1;max-width:200px;">'
            + '<label style="display:flex;align-items:center;gap:6px;font-size:13px;color:#94a3b8;cursor:pointer;white-space:nowrap;"><input type="checkbox" id="ps-select-all-' + wid + '" onchange="togglePayslipSelectAllInline(' + wid + ')"> Select All</label>'
            + '<span id="ps-sel-count-' + wid + '" style="font-size:13px;color:#64748b;"></span>'
            + '<button class="inv-btn inv-btn-green" style="padding:5px 14px;font-size:12px;white-space:nowrap;" onclick="addSelectedToPayslipCartInline(' + wid + ')">🛒 Add to Payslip</button>'
            + '</div><div id="ps-table-wrap-' + wid + '"></div>';
    }

    /* Read filter state AFTER filter bar is guaranteed to exist */
    var fWD  = !!(document.getElementById('ps-f-workdone-' + wid) || {checked:true}).checked;
    var fAll = !!(document.getElementById('ps-f-all-'      + wid) || {}).checked;
    var fInv = !!(document.getElementById('ps-f-invoiced-' + wid) || {}).checked;
    var fPd  = !!(document.getElementById('ps-f-paid-'     + wid) || {}).checked;
    var q    = ((document.getElementById('ps-search-'      + wid) || {}).value || '').toLowerCase();
    var cart = _psCart[wid] || {};
    var all  = _psAllProducts[wid] || [];

    var filtered = all.filter(function(t){
        if(!fAll){
            var match = false;
            if(fWD  && t.work_status==='Work Done') match = true;
            if(fInv && (t.invoice_status==='Invoiced'||t.info_invoice_status==='Invoiced'||t.aplus_invoice_status==='Invoiced')) match = true;
            if(fPd  && (t.invoice_status==='Paid'   ||t.info_invoice_status==='Paid'   ||t.aplus_invoice_status==='Paid'))    match = true;
            if(!match) return false;
        }
        return !q || t.title.toLowerCase().indexOf(q) !== -1 || String(t.product_no).indexOf(q) !== -1;
    });

    var wrap = document.getElementById('ps-table-wrap-' + wid);
    if(!wrap) return;
    if(!filtered.length){ wrap.innerHTML='<div style="color:#94a3b8;font-size:13px;padding:8px 0;">Koi matching product nahi.</div>'; calcPayslipTotalInline(wid); return; }

    var html = '<table class="inv-table"><thead><tr><th style="width:30px;"></th><th>#</th><th>Type</th><th>Work Status</th><th>Invoice</th><th>Title</th></tr></thead><tbody>';
    filtered.forEach(function(t){
        var inCart   = !!cart[t.id];
        var invBadge = '';
        var typeCell = '<td style="white-space:nowrap;font-size:10px;color:#a78bfa;">'+_typeLabel(t.product_type)+'</td>';
        if(t.invoice_status==='Paid'||t.info_invoice_status==='Paid'||t.aplus_invoice_status==='Paid') invBadge='<span class="inv-badge-paid">✅ Paid</span>';
        else if(t.invoice_status==='Invoiced'||t.info_invoice_status==='Invoiced'||t.aplus_invoice_status==='Invoiced') invBadge='<span class="inv-badge-invoiced">🧾 Inv</span>';
        if(inCart) html+=`<tr style="opacity:0.45;"><td style="text-align:center;color:#4ade80;font-size:11px;">✓</td><td style="white-space:nowrap;">${t.product_no}</td>${typeCell}<td style="white-space:nowrap;font-size:11px;color:#94a3b8;">${t.work_status}</td><td>${invBadge}</td><td style="white-space:normal;word-break:break-word;">${t.title} <span style="font-size:10px;color:#4ade80;">In Cart</span></td></tr>`;
        else       html+=`<tr><td><input type="checkbox" class="ps-chk-${wid}" data-id="${t.id}" onchange="syncPayslipSelectAllInline(${wid});calcPayslipTotalInline(${wid})"></td><td style="white-space:nowrap;">${t.product_no}</td>${typeCell}<td style="white-space:nowrap;font-size:11px;color:#94a3b8;">${t.work_status}</td><td>${invBadge}</td><td style="white-space:normal;word-break:break-word;">${t.title}</td></tr>`;
    });
    html += '</tbody></table>';
    wrap.innerHTML = html;
    syncPayslipSelectAllInline(wid);
    calcPayslipTotalInline(wid);
}

function togglePayslipSelectAllInline(wid){
    var sa = document.getElementById('ps-select-all-' + wid);
    if(!sa) return;
    document.querySelectorAll('.ps-chk-' + wid).forEach(function(c){ c.checked = sa.checked; });
    calcPayslipTotalInline(wid);
    updatePayslipSelCountInline(wid);
}

function syncPayslipSelectAllInline(wid){
    var all  = document.querySelectorAll('.ps-chk-' + wid);
    var chkd = document.querySelectorAll('.ps-chk-' + wid + ':checked');
    var sa   = document.getElementById('ps-select-all-' + wid);
    if(sa){ sa.checked = all.length > 0 && chkd.length === all.length; sa.indeterminate = chkd.length > 0 && chkd.length < all.length; }
    updatePayslipSelCountInline(wid);
}

function updatePayslipSelCountInline(wid){
    var el = document.getElementById('ps-sel-count-' + wid);
    var n  = document.querySelectorAll('.ps-chk-' + wid + ':checked').length;
    if(el) el.textContent = n > 0 ? n + ' selected' : '';
}

function addSelectedToPayslipCartInline(wid){
    var added = 0;
    _psCart[wid] = _psCart[wid] || {};
    document.querySelectorAll('.ps-chk-' + wid + ':checked').forEach(function(chk){
        var id   = chk.getAttribute('data-id');
        var task = (_psAllProducts[wid] || []).find(function(t){ return String(t.id) === String(id); });
        if(!task) return;
        _psCart[wid][id] = {id: id, product_no: task.product_no, title: task.title, work_status: task.work_status, product_type: task.product_type};
        added++;
    });
    if(!added){ alert('Pehle products select karein'); return; }
    renderPayslipTableInline(wid);
    renderPayslipCartInline(wid);
    calcPayslipTotalInline(wid);
}

function removeFromPayslipCartInline(tid, wid){
    if(_psCart[wid]) delete _psCart[wid][tid];
    renderPayslipTableInline(wid);
    renderPayslipCartInline(wid);
    calcPayslipTotalInline(wid);
}

function renderPayslipCartInline(wid){
    var cs = document.getElementById('ps-cart-section-' + wid);
    if(!cs) return;
    var cart = _psCart[wid] || {};
    var keys = Object.keys(cart);
    if(!keys.length){ cs.style.display='none'; cs.innerHTML=''; return; }
    var rateInfo  = parseFloat((document.getElementById('rate-info-'+wid)||{}).value)||0;
    var rateAplus = parseFloat((document.getElementById('rate-aplus-'+wid)||{}).value)||0;
    var total=0, infoN=0, aplusN=0, bothN=0;
    keys.forEach(function(id){
        var it = cart[id];
        var pt = it.product_type || '';
        total += _getItemRate(pt, rateInfo, rateAplus);
        if(pt==='Info + A Plus') bothN++;
        else if(pt==='Infographics') infoN++;
        else aplusN++;
    });
    var breakdown = '';
    if(infoN  > 0) breakdown += infoN  + '×Info PKR '+(infoN*rateInfo).toFixed(0);
    if(aplusN > 0) breakdown += (breakdown?'  |  ':'')+aplusN+'×A+ PKR '+(aplusN*rateAplus).toFixed(0);
    if(bothN  > 0) breakdown += (breakdown?'  |  ':'')+bothN +'×Info+A+ PKR '+(bothN*(rateInfo+rateAplus)).toFixed(0);
    var html = '<div style="background:#082f49;border:2px solid #38bdf8;border-radius:8px;padding:12px;">'
        + '<div style="font-size:13px;font-weight:700;color:#7dd3fc;margin-bottom:4px;">🛒 Payslip Cart (' + keys.length + ' product' + (keys.length > 1 ? 's' : '') + ') — PKR ' + total.toFixed(2) + '</div>'
        + (breakdown ? '<div style="font-size:11px;color:#94a3b8;margin-bottom:8px;">' + breakdown + '</div>' : '')
        + '<table class="inv-table"><thead><tr><th>#</th><th>Type</th><th>Status</th><th>Title</th><th style="width:28px;"></th></tr></thead><tbody>';
    keys.forEach(function(id){ var it = cart[id]; html += `<tr><td style="white-space:nowrap;">${it.product_no}</td><td style="white-space:nowrap;font-size:10px;color:#a78bfa;">${_typeLabel(it.product_type)}</td><td style="white-space:nowrap;font-size:11px;color:#94a3b8;">${it.work_status||'-'}</td><td style="white-space:normal;word-break:break-word;">${it.title}</td><td><button onclick="removeFromPayslipCartInline('${id}',${wid})" style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:14px;padding:0 4px;" title="Remove">✕</button></td></tr>`; });
    html += '</tbody></table></div>';
    cs.innerHTML = html;
    cs.style.display = 'block';
}

function _getItemRate(item, rInfo, rAplus, wid){
    var pt = item.product_type || '';
    if(pt === 'Info + A Plus'){
        var isInfo = (item.info_worker_id && parseInt(item.info_worker_id) === parseInt(wid));
        var isAplus = (item.aplus_worker_id && parseInt(item.aplus_worker_id) === parseInt(wid));
        if(isInfo && isAplus) return rInfo + rAplus;
        if(isInfo && !item.aplus_worker_id) return rInfo + rAplus;
        if(isAplus && !item.info_worker_id) return rInfo + rAplus;
        if(isInfo) return rInfo;
        if(isAplus) return rAplus;
        return rInfo + rAplus;
    }
    if(pt === 'Infographics') return rInfo;
    return rAplus;
}

function calcPayslipTotalInline(wid){
    var rateInfo  = parseFloat((document.getElementById('rate-info-'+wid)||{}).value)||0;
    var rateAplus = parseFloat((document.getElementById('rate-aplus-'+wid)||{}).value)||0;
    var cart      = _psCart[wid] || {};
    var total = 0, n = 0;
    Object.values(cart).forEach(function(item){
        total += _getItemRate(item, rateInfo, rateAplus, wid); n++;
    });
    document.querySelectorAll('.ps-chk-'+wid+':checked').forEach(function(chk){
        var id = parseInt(chk.getAttribute('data-id'));
        if(!(cart[id])){
            var p = (_psAllProducts[wid]||[]).find(function(x){ return x.id==id; });
            if(p){ total += _getItemRate(p, rateInfo, rateAplus, wid); n++; }
        }
    });
    var el = document.getElementById('ps-total-preview-'+wid);
    if(el) el.textContent = n>0 ? n+' products — PKR '+total.toFixed(2) : '';
}

function generatePayslipInline(wid){
    var cart    = _psCart[wid] || {};
    var taskIds = Object.keys(cart).map(Number);
    document.querySelectorAll('.ps-chk-' + wid + ':checked').forEach(function(chk){
        var id = parseInt(chk.getAttribute('data-id'));
        if(!cart[id]) taskIds.push(id);
    });
    if(!taskIds.length){ alert('Pehle products select karein ya cart mein add karein'); return; }

    var labelEl  = document.getElementById('ps-label-'     + wid);
    var dateFrom = (document.getElementById('ps-date-from-' + wid) || {}).value || '';
    var dateTo   = (document.getElementById('ps-date-to-'   + wid) || {}).value || '';
    var label    = (labelEl && labelEl.value.trim()) || (dateFrom && dateTo ? dateFrom + ' to ' + dateTo : new Date().toISOString().substring(0, 7));
    var rateInfo  = parseFloat((document.getElementById('rate-info-'+wid)||{}).value)||0;
    var rateAplus = parseFloat((document.getElementById('rate-aplus-'+wid)||{}).value)||0;

    /* Read cycle data stored on accordion element */
    var acc         = document.getElementById('rate-acc-' + wid);
    var prevD4u     = parseFloat((acc && acc.dataset.prevD4u)     || 0);
    var currentAdv  = parseFloat((acc && acc.dataset.currentAdv)  || 0);
    var currentLoan = parseFloat((acc && acc.dataset.currentLoan) || 0);
    var penalty     = parseFloat((acc && acc.dataset.penalty)     || 0);

    /* Count per type from cart + checked items */
    var infoCount=0, aplusCount=0, bothCount=0;
    taskIds.forEach(function(id){
        var item = cart[id] || ((_psAllProducts[wid]||[]).find(function(x){ return x.id==id; })||{});
        var pt   = item.product_type || '';
        if(pt==='Info + A Plus'){
            var isInfo = (item.info_worker_id && parseInt(item.info_worker_id) === parseInt(wid));
            var isAplus = (item.aplus_worker_id && parseInt(item.aplus_worker_id) === parseInt(wid));
            if(isInfo && isAplus) bothCount++;
            else if(isInfo && !item.aplus_worker_id) bothCount++;
            else if(isAplus && !item.info_worker_id) bothCount++;
            else if(isInfo) infoCount++;
            else if(isAplus) aplusCount++;
            else bothCount++;
        }
        else if(pt==='Infographics') infoCount++;
        else aplusCount++;
    });
    var earned = infoCount*rateInfo + aplusCount*rateAplus + bothCount*(rateInfo+rateAplus);

    /* All pre-given amounts merge into totalPrevD4u → all DEDUCTED */
    var totalPrevD4u = prevD4u + currentAdv + currentLoan;

    /* Build type breakdown rows (hide 0-count rows) */
    var breakdownRows = '';
    if(infoCount  > 0) breakdownRows += `<tr><td style="color:#a78bfa;padding:4px 0;">Infographics (${infoCount} × PKR ${rateInfo.toFixed(0)})</td><td style="text-align:right;color:#a78bfa;">PKR ${(infoCount*rateInfo).toFixed(2)}</td></tr>`;
    if(aplusCount > 0) breakdownRows += `<tr><td style="color:#60a5fa;padding:4px 0;">A+ Banners (${aplusCount} × PKR ${rateAplus.toFixed(0)})</td><td style="text-align:right;color:#60a5fa;">PKR ${(aplusCount*rateAplus).toFixed(2)}</td></tr>`;
    if(bothCount  > 0) breakdownRows += `<tr><td style="color:#34d399;padding:4px 0;">Info+A+ (${bothCount} × PKR ${(rateInfo+rateAplus).toFixed(0)})</td><td style="text-align:right;color:#34d399;">PKR ${(bothCount*(rateInfo+rateAplus)).toFixed(2)}</td></tr>`;

    /* Build confirm dialog with editable fresh ADV / LOAN fields */
    var modalDiv = document.createElement('div');
    modalDiv.id  = '_eco_confirm_modal';
    modalDiv.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:99999;display:flex;align-items:center;justify-content:center;';
    modalDiv.innerHTML = `
<div style="background:#0f2035;border:1px solid #1e3a5f;border-radius:12px;padding:24px 28px;max-width:480px;width:94%;box-shadow:0 20px 60px rgba(0,0,0,.6);">
  <div style="color:#60a5fa;font-size:13px;font-weight:700;letter-spacing:.5px;margin-bottom:14px;">📄 GENERATE PAY SLIP — ${taskIds.length} products</div>
  <table style="width:100%;font-size:13px;border-collapse:collapse;margin-bottom:14px;">
    ${breakdownRows}
    <tr style="border-top:1px solid #334155;"><td style="color:#93c5fd;padding:6px 0 4px;font-weight:700;">TOTAL EARNED</td><td style="text-align:right;color:#93c5fd;font-weight:bold;padding:6px 0 4px;">PKR ${earned.toFixed(2)}</td></tr>
    ${prevD4u > 0 ? `<tr><td style="color:#f87171;padding:4px 0;">↳ PREV. D4U (last cycle)</td><td style="text-align:right;color:#f87171;">&minus;PKR ${prevD4u.toFixed(2)}</td></tr>` : ''}
    ${currentAdv > 0 ? `<tr><td style="color:#f87171;padding:4px 0;">↳ ADV. BY D4U (this cycle)</td><td style="text-align:right;color:#f87171;">&minus;PKR ${currentAdv.toFixed(2)}</td></tr>` : ''}
    ${currentLoan > 0 ? `<tr><td style="color:#f87171;padding:4px 0;">↳ NO WORK LOAN (this cycle)</td><td style="text-align:right;color:#f87171;">&minus;PKR ${currentLoan.toFixed(2)}</td></tr>` : ''}
    ${penalty > 0 ? `<tr><td style="color:#f87171;padding:4px 0;">PENALTY</td><td style="text-align:right;color:#f87171;">&minus;PKR ${penalty.toFixed(2)}</td></tr>` : ''}
    ${totalPrevD4u > 0 ? `<tr style="border-top:1px solid #334155;"><td style="color:#94a3b8;padding:6px 0 2px;font-size:11px;">TOTAL PRE-DEDUCTIONS</td><td style="text-align:right;color:#f87171;padding:6px 0 2px;">&minus;PKR ${totalPrevD4u.toFixed(2)}</td></tr>` : ''}
  </table>
  <div style="background:#071428;border:1px solid #2563eb44;border-radius:8px;padding:12px;margin-bottom:14px;">
    <div style="color:#60a5fa;font-size:10px;font-weight:700;margin-bottom:10px;">➕ PAYROLL PE ADDITIONAL DENA HAI? (optional)</div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <div style="flex:1;min-width:140px;">
        <label style="display:block;color:#64748b;font-size:10px;margin-bottom:4px;">💸 ADV. BY D4U (fresh)</label>
        <input type="number" id="_ps_fresh_adv" value="0" min="0" step="0.01"
          style="width:100%;background:#0a1628;border:1px solid #1e3a5f;color:#fbbf24;padding:7px;border-radius:4px;font-size:14px;font-weight:bold;"
          oninput="_psUpdateTotal(${earned},${totalPrevD4u},${penalty})">
      </div>
      <div style="flex:1;min-width:140px;">
        <label style="display:block;color:#64748b;font-size:10px;margin-bottom:4px;">🏦 NO WORK LOAN D4U (fresh)</label>
        <input type="number" id="_ps_fresh_loan" value="0" min="0" step="0.01"
          style="width:100%;background:#0a1628;border:1px solid #1e3a5f;color:#fbbf24;padding:7px;border-radius:4px;font-size:14px;font-weight:bold;"
          oninput="_psUpdateTotal(${earned},${totalPrevD4u},${penalty})">
      </div>
    </div>
  </div>
  <div style="background:#071e12;border:1px solid #16a34a55;border-radius:8px;padding:12px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;">
    <span style="color:#94a3b8;font-size:12px;font-weight:700;">TOTAL PAID</span>
    <span id="_ps_total_display" style="color:#4ade80;font-size:20px;font-weight:900;">PKR ${(earned - totalPrevD4u - penalty).toFixed(2)}</span>
  </div>
  <div style="display:flex;gap:10px;justify-content:flex-end;">
    <button id="_eco_confirm_no" style="padding:8px 22px;border-radius:6px;border:1px solid #334155;background:#1e293b;color:#94a3b8;font-size:13px;font-weight:600;cursor:pointer;">Cancel</button>
    <button id="_eco_confirm_yes" style="padding:8px 22px;border-radius:6px;border:none;background:#16a34a;color:#fff;font-size:13px;font-weight:700;cursor:pointer;">✅ Generate</button>
  </div>
</div>`;
    document.body.appendChild(modalDiv);
    function _closePs(){ document.body.removeChild(modalDiv); }
    document.getElementById('_eco_confirm_no').onclick  = _closePs;
    modalDiv.onclick = function(e){ if(e.target===modalDiv) _closePs(); };

    document.getElementById('_eco_confirm_yes').onclick = function(){
        var freshAdv  = parseFloat(document.getElementById('_ps_fresh_adv').value)  || 0;
        var freshLoan = parseFloat(document.getElementById('_ps_fresh_loan').value) || 0;
        _closePs();

        var fd = new FormData();
        fd.append('action',           'generate_payslip');
        fd.append('worker_id',        wid);
        fd.append('task_ids',         JSON.stringify(taskIds));
        fd.append('label',            label);
        fd.append('prev_d4u',         totalPrevD4u);
        fd.append('adv_d4u',          freshAdv);
        fd.append('loan_d4u',         freshLoan);
        fd.append('penalty_deducted', penalty);

        fetch('index.php', {method: 'POST', body: fd})
            .then(function(r){ return r.json(); })
            .then(function(r){
                if(r.success){
                    alert('Pay Slip generate ho gaya!\n' + r.count + ' products\nEarned: PKR ' + parseFloat(r.earned||0).toFixed(2) + '\nTotal Paid: PKR ' + parseFloat(r.total_paid||0).toFixed(2));
                    _psCart[wid]        = {};
                    _psAllProducts[wid] = [];
                    _reloadWorkerAccordion(wid);
                } else {
                    alert(r.message || 'Failed');
                }
            })
            .catch(function(err){ alert('Request failed: ' + err.message); });
    };
}

/* ── Per-worker payslip actions (inside accordion) ── */
function markPayslipPaidInline(id, wid){
    _confirm('Pay Slip ko Paid mark karein?', function(){
        var fd = new FormData(); fd.append('action','mark_payslip_paid'); fd.append('id',id);
        fetch('index.php',{method:'POST',body:fd})
            .then(function(r){ return r.json(); })
            .then(function(r){
                if(r.success){
                    var box = document.getElementById('ps-detail-box');
                    if(box) box.className = 'inv-detail-box';
                    _reloadWorkerAccordion(wid);
                } else alert('Failed: ' + (r.message||'Unknown error'));
            })
            .catch(function(err){ alert('Error: '+err.message); });
    });
}

function deletePayslipInline(id, wid){
    _confirm('Ye pay slip permanently delete karein?', function(){
        var fd = new FormData(); fd.append('action','delete_payslip'); fd.append('id',id);
        fetch('index.php',{method:'POST',body:fd})
            .then(function(r){ return r.json(); })
            .then(function(r){
                if(r.success){
                    var box = document.getElementById('ps-detail-box');
                    if(box) box.className = 'inv-detail-box';
                    _reloadWorkerAccordion(wid);
                } else alert('Delete failed: '+(r.message||'Unknown error'));
            })
            .catch(function(err){ alert('Delete error: '+err.message); });
    });
}

/* ── Load / list payslips ────────────────────── */
function loadPayslips(){
    fetch('index.php?action=get_payslips').then(r=>r.json()).then(r=>{
        var box=document.getElementById('ps-list-container');
        if(!box) return;
        if(!r.data||!r.data.length){ box.innerHTML='<div style="color:#94a3b8;font-size:13px;">Koi pay slip nahi mila.</div>'; return; }
        var html='<table class="inv-table"><tr><th>Worker</th><th>Month</th><th>Products</th><th>Total</th><th>Status</th><th>Actions</th></tr>';
        r.data.forEach(function(ps){
            var status  =ps.status==='Paid'?'<span class="inv-status-badge inv-status-paid">Paid</span>':'<span class="inv-status-badge inv-status-generated">Generated</span>';
            var actions =`<button class="inv-btn inv-btn-gray" style="padding:4px 10px;font-size:11px;" onclick="showPayslipDetail(${ps.id})">View</button> `;
            if(ROLE==='administrator'){
                if(ps.status!=='Paid') actions+=`<button class="inv-btn inv-btn-green" style="padding:4px 10px;font-size:11px;" onclick="markPayslipPaid(${ps.id})">Mark Paid</button> `;
                actions+=`<button class="inv-btn" style="padding:4px 10px;font-size:11px;background:#dc2626;color:#fff;" onclick="deletePayslip(${ps.id})">Delete</button>`;
            }
            html+=`<tr><td>${ps.worker_name}</td><td>${ps.month}</td><td style="text-align:center;">${ps.item_count}</td><td>PKR ${parseFloat(ps.total_amount).toFixed(2)}</td><td>${status}</td><td style="white-space:nowrap;">${actions}</td></tr>`;
        });
        html+='</table>'; box.innerHTML=html;
    });
}

function showPayslipDetail(id){
    var box=document.getElementById('ps-detail-box');
    box.innerHTML='<div style="color:#94a3b8;">Loading...</div>';
    box.className='inv-detail-box open';
    fetch('index.php?action=get_payslip_detail&id='+id).then(r=>r.json()).then(r=>{
        if(!r.success){ box.innerHTML='<div style="color:#f87171;">Error loading payslip.</div>'; return; }
        var ps      = r.payslip;
        var earned  = parseFloat(ps.total_amount     || 0);
        var prevD4u = parseFloat(ps.prev_d4u         || 0);
        var advD4u  = parseFloat(ps.adv_d4u          || 0);
        var loanD4u = parseFloat(ps.loan_d4u         || 0);
        var penalty = parseFloat(ps.penalty_deducted || 0);
        var tPaid   = parseFloat(ps.total_paid       || 0) > 0 ? parseFloat(ps.total_paid) : earned;
        var actEarned = earned - prevD4u;

        var status=ps.status==='Paid'?'<span class="inv-status-badge inv-status-paid">Paid</span>':'<span class="inv-status-badge inv-status-generated">Generated</span>';
        var html=`<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;"><div><strong style="color:#fff;font-size:16px;">Pay Slip — ${ps.worker_name}</strong> &nbsp; ${status}</div><div style="color:#94a3b8;font-size:12px;">Month: <strong style="color:#e2e8f0;">${ps.month}</strong> &nbsp;|&nbsp; Total Paid: <strong style="color:#4ade80;">PKR ${tPaid.toFixed(2)}</strong></div></div>`;

        html+='<div style="overflow-x:auto;"><table class="inv-table"><thead><tr><th>#</th><th>Product No.</th><th>Invoice No.</th></tr></thead><tbody>';
        r.items.forEach(function(it,i){ html+=`<tr><td>${i+1}</td><td style="font-weight:bold;">${it.product_no}</td><td style="color:#94a3b8;">${it.invoice_no||'-'}</td></tr>`; });
        html+='</tbody></table></div>';

        html+=`<div style="margin-top:16px;background:#071428;border:1px solid #1e3a5f;border-radius:8px;padding:14px;max-width:380px;">
<div style="font-size:10px;font-weight:700;color:#64748b;letter-spacing:.5px;margin-bottom:10px;">PAYSLIP BREAKDOWN</div>
<table style="width:100%;font-size:13px;border-collapse:collapse;">
<tr><td style="color:#94a3b8;padding:4px 0;">EARNED</td><td style="text-align:right;color:#93c5fd;font-weight:bold;">PKR ${earned.toFixed(2)}</td></tr>`
+ (prevD4u > 0 ? `<tr><td style="color:#f87171;padding:4px 0;">TOTAL PRE-DEDUCTIONS</td><td style="text-align:right;color:#f87171;">&minus;PKR ${prevD4u.toFixed(2)}</td></tr><tr><td style="color:#94a3b8;padding:4px 0;">ACTUAL EARNED</td><td style="text-align:right;color:${actEarned>=0?'#4ade80':'#f87171'};">PKR ${actEarned.toFixed(2)}</td></tr>` : '')
+ (advD4u  > 0 ? `<tr><td style="color:#fbbf24;padding:4px 0;">ADV. BY D4U (fresh at payroll)</td><td style="text-align:right;color:#fbbf24;">+PKR ${advD4u.toFixed(2)}</td></tr>` : '')
+ (loanD4u > 0 ? `<tr><td style="color:#a78bfa;padding:4px 0;">NO WORK LOAN D4U (fresh)</td><td style="text-align:right;color:#a78bfa;">+PKR ${loanD4u.toFixed(2)}</td></tr>` : '')
+ (penalty > 0 ? `<tr><td style="color:#f87171;padding:4px 0;">PENALTY</td><td style="text-align:right;color:#f87171;">&minus;PKR ${penalty.toFixed(2)}</td></tr>` : '')
+ `<tr style="border-top:1px solid #334155;"><td style="color:#fff;font-weight:700;padding-top:8px;">TOTAL PAID</td><td style="text-align:right;color:#4ade80;font-size:16px;font-weight:700;padding-top:8px;">PKR ${tPaid.toFixed(2)}</td></tr>
</table></div>`;
        if(ROLE==='administrator'){
            var detailBtns='';
            if(ps.status!=='Paid') detailBtns+=`<button class="inv-btn inv-btn-green" onclick="markPayslipPaid(${ps.id})">✅ Mark as Paid</button> `;
            detailBtns+=`<button class="inv-btn" style="background:#dc2626;color:#fff;" onclick="deletePayslip(${ps.id})">🗑 Delete Pay Slip</button>`;
            if(detailBtns) html+=`<div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;">${detailBtns}</div>`;
        }
        html+=`<div style="margin-top:10px;"><button class="inv-btn inv-btn-gray" style="font-size:11px;" onclick="document.getElementById('ps-detail-box').className='inv-detail-box';">Close</button></div>`;
        box.innerHTML=html;
    });
    box.scrollIntoView({behavior:'smooth', block:'nearest'});
}

function showPayslipDetailInline(id, workerId){
    var boxId = 'ps-detail-box-' + workerId;
    var box = document.getElementById(boxId);
    if (!box) return;
    box.innerHTML = '<div style="color:#94a3b8;">Loading...</div>';
    box.className = 'inv-detail-box open';
    fetch('index.php?action=get_payslip_detail&id=' + id).then(r => r.json()).then(r => {
        if (!r.success) { box.innerHTML = '<div style="color:#f87171;">Error loading payslip.</div>'; return; }
        var ps      = r.payslip;
        var earned  = parseFloat(ps.total_amount     || 0);
        var prevD4u = parseFloat(ps.prev_d4u         || 0);
        var advD4u  = parseFloat(ps.adv_d4u          || 0);
        var loanD4u = parseFloat(ps.loan_d4u         || 0);
        var penalty = parseFloat(ps.penalty_deducted || 0);
        var tPaid   = parseFloat(ps.total_paid       || 0) > 0 ? parseFloat(ps.total_paid) : earned;
        var actEarned = earned - prevD4u;

        var status = ps.status === 'Paid' ? '<span class="inv-status-badge inv-status-paid">Paid</span>' : '<span class="inv-status-badge inv-status-generated">Generated</span>';
        var html = `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;"><div><strong style="color:#fff;font-size:16px;">Pay Slip — ${ps.worker_name}</strong> &nbsp; ${status}</div><div style="color:#94a3b8;font-size:12px;">Month: <strong style="color:#e2e8f0;">${ps.month}</strong> &nbsp;|&nbsp; Total Paid: <strong style="color:#4ade80;">PKR ${tPaid.toFixed(2)}</strong></div></div>`;

        html += '<div style="overflow-x:auto;"><table class="inv-table"><thead><tr><th>#</th><th>Product No.</th><th>Invoice No.</th></tr></thead><tbody>';
        r.items.forEach(function(it, i) { html += `<tr><td>${i+1}</td><td style="font-weight:bold;">${it.product_no}</td><td style="color:#94a3b8;">${it.invoice_no||'-'}</td></tr>`; });
        html += '</tbody></table></div>';

        html += `<div style="margin-top:16px;background:#071428;border:1px solid #1e3a5f;border-radius:8px;padding:14px;max-width:380px;">
<div style="font-size:10px;font-weight:700;color:#64748b;letter-spacing:.5px;margin-bottom:10px;">PAYSLIP BREAKDOWN</div>
<table style="width:100%;font-size:13px;border-collapse:collapse;">
<tr><td style="color:#94a3b8;padding:4px 0;">EARNED</td><td style="text-align:right;color:#93c5fd;font-weight:bold;">PKR ${earned.toFixed(2)}</td></tr>`
+ (prevD4u > 0 ? `<tr><td style="color:#f87171;padding:4px 0;">TOTAL PRE-DEDUCTIONS</td><td style="text-align:right;color:#f87171;">&minus;PKR ${prevD4u.toFixed(2)}</td></tr><tr><td style="color:#94a3b8;padding:4px 0;">ACTUAL EARNED</td><td style="text-align:right;color:${actEarned>=0?'#4ade80':'#f87171'};">PKR ${actEarned.toFixed(2)}</td></tr>` : '')
+ (advD4u  > 0 ? `<tr><td style="color:#fbbf24;padding:4px 0;">ADV. BY D4U (fresh at payroll)</td><td style="text-align:right;color:#fbbf24;">+PKR ${advD4u.toFixed(2)}</td></tr>` : '')
+ (loanD4u > 0 ? `<tr><td style="color:#a78bfa;padding:4px 0;">NO WORK LOAN D4U (fresh)</td><td style="text-align:right;color:#a78bfa;">+PKR ${loanD4u.toFixed(2)}</td></tr>` : '')
+ (penalty > 0 ? `<tr><td style="color:#f87171;padding:4px 0;">PENALTY</td><td style="text-align:right;color:#f87171;">&minus;PKR ${penalty.toFixed(2)}</td></tr>` : '')
+ `<tr style="border-top:1px solid #334155;"><td style="color:#fff;font-weight:700;padding-top:8px;">TOTAL PAID</td><td style="text-align:right;color:#4ade80;font-size:16px;font-weight:700;padding-top:8px;">PKR ${tPaid.toFixed(2)}</td></tr>
</table></div>`;
        if (ROLE === 'administrator') {
            var detailBtns = '';
            if (ps.status !== 'Paid') detailBtns += `<button class="inv-btn inv-btn-green" onclick="markPayslipPaidInline(${ps.id}, ${workerId})">✅ Mark as Paid</button> `;
            detailBtns += `<button class="inv-btn" style="background:#dc2626;color:#fff;" onclick="deletePayslipInline(${ps.id}, ${workerId})">🗑 Delete Pay Slip</button>`;
            if (detailBtns) html += `<div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;">${detailBtns}</div>`;
        }
        html += `<div style="margin-top:10px;"><button class="inv-btn inv-btn-gray" style="font-size:11px;" onclick="document.getElementById('${boxId}').className='inv-detail-box';">Close</button></div>`;
        box.innerHTML = html;
    });
    box.scrollIntoView({behavior:'smooth', block:'nearest'});
}

function markPayslipPaid(id){
    _confirm('Pay Slip ko Paid mark karein?', function(){
        var fd=new FormData(); fd.append('action','mark_payslip_paid'); fd.append('id',id);
        fetch('index.php',{method:'POST',body:fd}).then(r=>r.json()).then(r=>{
            if(r.success){ loadPayslips(); document.getElementById('ps-detail-box').className='inv-detail-box'; }
            else alert('Failed');
        });
    });
}

function deletePayslip(id){
    _confirm('Ye pay slip permanently delete ho jaaye gi. Confirm?', function(){
        var fd=new FormData(); fd.append('action','delete_payslip'); fd.append('id',id);
        fetch('index.php',{method:'POST',body:fd})
            .then(r=>{ var ct=r.headers.get('content-type')||''; if(!ct.includes('application/json')) return r.text().then(t=>{ throw new Error('Server returned HTML. PHP error: '+t.substring(0,200)); }); return r.json(); })
            .then(r=>{ if(r.success){ loadPayslips(); loadWorkerRates(); var el=document.getElementById('ps-detail-box'); if(el) el.className='inv-detail-box'; } else alert('Delete failed: '+(r.message||'Unknown error')); })
            .catch(function(err){ alert('Delete error: '+err.message); });
    });
}

function loadPenalties(){
    var box = document.getElementById('penalties-container');
    if(!box) return;

    var isAdmin = ROLE === 'administrator';

    var filterVal = '';
    var pFilter = document.getElementById('penalty-worker-filter');
    if(pFilter){
        filterVal = pFilter.value;
    }

    fetch('index.php?action=get_penalties&worker_id='+filterVal)
    .then(function(r){ return r.json(); })
    .then(function(r){
        if(!r.success) {
            box.innerHTML = '<div style="color:#f87171;font-size:13px;">Failed to load penalties.</div>';
            return;
        }
        
        var data = r.data || [];
        if(!data.length){
            box.innerHTML = '<div style="color:#94a3b8;font-size:13px;">Koi revision ya penalty record nahi mila.</div>';
            return;
        }
        
        var html = '<div style="overflow-x:auto;">';
        html += '<table class="inv-table"><thead><tr>'
             + '<th>#</th>'
             + (isAdmin ? '<th>Worker</th>' : '')
             + '<th>Product</th>'
             + '<th>Type</th>'
             + '<th>Fine Amount</th>'
             + '<th>Status</th>'
             + '<th>Notes / Remarks</th>'
             + '<th>Date</th>'
             + (isAdmin ? '<th>Action</th>' : '')
             + '</tr></thead><tbody>';
             
        data.forEach(function(item, idx){
            var statusColor = '#94a3b8';
            if(item.status === 'Deducted') statusColor = '#ef4444';
            else if(item.status === 'Waived') statusColor = '#10b981';
            else if(item.status === 'Pending') statusColor = '#eab308';
            
            var typeLabel = item.type === 'design' ? '🎨 Design Revision' : '✍ Content Revision';
            var fineDisplay = 'PKR ' + parseFloat(item.penalty_amount || 0).toFixed(0);
            
            html += '<tr>'
                 + '<td>' + (idx+1) + '</td>'
                 + (isAdmin ? '<td style="font-weight:bold;color:#e2e8f0;">' + item.worker_name + '</td>' : '')
                 + '<td><span style="font-weight:600;color:#93c5fd;">#' + item.product_no + '</span> - ' + item.title + '</td>'
                 + '<td style="font-size:12px;white-space:nowrap;">' + typeLabel + '</td>'
                 + '<td style="font-weight:bold;color:#fca5a5;">' + fineDisplay + '</td>'
                 + '<td><span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:bold;background:'+statusColor+';color:#fff;">' + item.status + '</span></td>'
                 + '<td style="font-size:12px;color:#cbd5e1;max-width:220px;word-break:break-word;">' + (item.notes || '-') + '</td>'
                 + '<td style="font-size:11px;color:#94a3b8;white-space:nowrap;">' + item.created_at.substring(0,10) + '</td>';
                 
            if(isAdmin){
                html += '<td style="white-space:nowrap;" onclick="event.stopPropagation()">';
                if(item.status === 'Pending'){
                    html += '<button class="inv-btn inv-btn-blue" style="padding:3px 8px;font-size:11px;margin-right:4px;" onclick="processPenalty(' + item.id + ',\'deduct\',' + item.penalty_amount + ')">Deduct</button>'
                         + '<button class="inv-btn inv-btn-gray" style="padding:3px 8px;font-size:11px;margin-right:4px;" onclick="processPenalty(' + item.id + ',\'waive\',0)">Waive</button>';
                } else {
                    html += '<span style="color:#64748b;font-size:11px;margin-right:8px;">Processed</span>';
                }
                html += '<button class="inv-btn" style="padding:3px 8px;font-size:11px;background:#dc2626;color:#fff;" onclick="deletePenalty(' + item.id + ')">Delete</button>';
                html += '</td>';
            }
            
            html += '</tr>';
        });
        
        html += '</tbody></table></div>';
        box.innerHTML = html;
    });
}

function processPenalty(penaltyId, decision, defaultAmount){
    if(decision === 'waive'){
        _confirm('Kya aap is penalty ko Waive (maaf) karna chahte hain?', function(){
            executePenaltyDecision(penaltyId, 'waive', 0);
        });
    } else if(decision === 'deduct') {
        _prompt('Deduction fine amount enter karein (PKR):', defaultAmount, function(amt){
            if(amt === null) return;
            amt = parseFloat(amt);
            if(isNaN(amt) || amt < 0){
                alert('Invalid amount enter kiya gaya.');
                return;
            }
            _confirm('Kya aap PKR ' + amt + ' worker ki pay roll se deduct karna chahte hain?', function(){
                executePenaltyDecision(penaltyId, 'deduct', amt);
            });
        });
    }
}

function executePenaltyDecision(penaltyId, decision, amount){
    var fd = new FormData();
    fd.append('action', 'process_penalty');
    fd.append('penalty_id', penaltyId);
    fd.append('decision', decision);
    fd.append('custom_fine', amount);

    fetch('index.php', {method: 'POST', body: fd})
    .then(function(r){ return r.json(); })
    .then(function(r){
        if(r.success){
            loadPenalties();
            if(typeof loadWorkerRates === 'function') loadWorkerRates();
            if(typeof loadDashboardStats === 'function') loadDashboardStats();
        } else {
            alert('Action failed: ' + (r.message || 'Error'));
        }
    });
}

function deletePenalty(penaltyId){
    _confirm('Kya aap is penalty ko permanently delete karna chahte hain?', function(){
        var fd = new FormData();
        fd.append('action', 'delete_penalty');
        fd.append('penalty_id', penaltyId);

        fetch('index.php', {method: 'POST', body: fd})
        .then(function(r){ return r.json(); })
        .then(function(r){
            if(r.success){
                loadPenalties();
                if(typeof loadWorkerRates === 'function') loadWorkerRates();
                if(typeof loadDashboardStats === 'function') loadDashboardStats();
            } else {
                alert('Delete failed: ' + (r.message || 'Error'));
            }
        });
    });
}
