/*
=====================================================
ECO A+ PRO — invoices.js
Invoice panel: create, list, view, print, share,
cart management, uninvoiced product selector.
Depends on: core.js
=====================================================
*/

var _invTasks = [];
var _invCart  = {};   /* keyed by task_id */

/* ── Panel init ──────────────────────────────── */
function initInvoicePanel(){
    loadInvoices();
    if(ROLE === 'administrator'){
        fetch('index.php?action=get_eco_clients').then(r => r.json()).then(r => {
            var sel = document.getElementById('inv-client');
            if(!sel) return;
            sel.innerHTML = '<option value="">-- Select Client --</option>';
            r.data.forEach(function(c){ sel.innerHTML += '<option value="' + c.id + '">' + c.username + '</option>'; });
        });
    }
}

/* ── Uninvoiced product selector ─────────────── */
function loadUninvoicedTasks(){
    var clientId = document.getElementById('inv-client').value;
    var listDiv  = document.getElementById('inv-product-list');
    _invCart = {};
    var cs = document.getElementById('inv-cart-section'); if(cs){ cs.style.display='none'; cs.innerHTML=''; }
    document.getElementById('inv-total-preview').textContent = '';
    if(!clientId){ listDiv.style.display='none'; listDiv.innerHTML=''; _invTasks=[]; return; }

    fetch('index.php?action=get_uninvoiced_tasks').then(r => r.json()).then(r => {
        if(!r.data || !r.data.length){
            listDiv.style.display='block';
            listDiv.innerHTML='<div style="color:#94a3b8;font-size:13px;padding:10px 0;">No uninvoiced Work Done products.</div>';
            return;
        }
        _invTasks = r.data;
        var bar = '<div class="inv-filter-bar">'
            + '<label style="display:flex;align-items:center;gap:5px;font-size:12px;color:#94a3b8;cursor:pointer;white-space:nowrap;"><input type="checkbox" id="inv-f-infodone" onchange="renderInvTable()" checked> Info Done</label>'
            + '<label style="display:flex;align-items:center;gap:5px;font-size:12px;color:#94a3b8;cursor:pointer;white-space:nowrap;"><input type="checkbox" id="inv-f-workdone" onchange="renderInvTable()" checked> A + Done</label>'
            + '<label style="display:flex;align-items:center;gap:5px;font-size:12px;color:#94a3b8;cursor:pointer;white-space:nowrap;"><input type="checkbox" id="inv-f-infoaplus" onchange="renderInvTable()"> Info + A Plus Done</label>'
            + '<label style="display:flex;align-items:center;gap:5px;font-size:12px;color:#94a3b8;cursor:pointer;white-space:nowrap;"><input type="checkbox" id="inv-f-all" onchange="renderInvTable()"> All</label>'
            + '<input type="text" class="inv-input" id="inv-search" placeholder="Search..." oninput="renderInvTable()" style="flex:1;max-width:200px;">'
            + '<label style="display:flex;align-items:center;gap:6px;font-size:13px;color:#94a3b8;cursor:pointer;white-space:nowrap;"><input type="checkbox" id="inv-select-all" onchange="toggleInvSelectAll()"> Select All</label>'
            + '<span id="inv-sel-count" style="font-size:13px;color:#64748b;"></span>'
            + '<button class="inv-btn inv-btn-green" style="padding:5px 14px;font-size:12px;white-space:nowrap;" onclick="addSelectedToCart()">🛒 Add to Invoice</button>'
            + '</div><div id="inv-table-wrap"></div>';
        listDiv.innerHTML = bar;
        listDiv.style.display = 'block';
        renderInvTable();
    });
}

function renderInvTable(){
    var q    = ((document.getElementById('inv-search')    ||{}).value||'').toLowerCase();
    var fAll = (document.getElementById('inv-f-all')      ||{}).checked;
    var fWD  = (document.getElementById('inv-f-workdone') ||{}).checked;
    var fID  = (document.getElementById('inv-f-infodone') ||{}).checked;
    var fIA  = (document.getElementById('inv-f-infoaplus')||{}).checked;
    var anyChecked = fAll||fWD||fID||fIA;

    var filtered = _invTasks.filter(function(t){
        var isIA   = t.product_type === 'Info + A Plus';
        var isInfo = t.product_type === 'Infographics';
        var isAplus = !isIA && !isInfo;
        var infoInvd  = !!(t.info_invoice_status  && t.info_invoice_status  !== '');
        var aplusInvd = !!(t.aplus_invoice_status && t.aplus_invoice_status !== '') || (isAplus && !!(t.invoice_status && t.invoice_status !== ''));
        var isFullyInvoiced = isIA ? (infoInvd&&aplusInvd)
                            : (isInfo ? infoInvd
                            : (isAplus ? aplusInvd
                            : !!(t.invoice_status && t.invoice_status !== '')));
        if(isFullyInvoiced && !_invCart[t.id]) return false;

        if(anyChecked){
            var match=false;
            if(fAll) match=true;
            if(!match&&fWD&&t.work_status==='Work Done') match=true;
            if(!match&&fID&&t.work_status==='Info Done') match=true;
            if(!match&&fIA&&t.product_type==='Info + A Plus') match=true;
            if(!match) return false;
        }
        return !q||t.title.toLowerCase().indexOf(q)!==-1||t.product_no.toLowerCase().indexOf(q)!==-1;
    });

    var wrap = document.getElementById('inv-table-wrap');
    if(!wrap) return;
    if(!filtered.length){ wrap.innerHTML='<div style="color:#94a3b8;font-size:13px;padding:10px;">No matching products.</div>'; calcInvTotal(); return; }

    var html='<table class="inv-table"><thead><tr><th style="width:30px;"></th><th>#</th><th>Status</th><th>Title</th>'
        +'<th style="width:105px;text-align:right;">Info (USD)</th>'
        +'<th style="width:105px;text-align:right;">A+ (USD)</th>'
        +'<th style="width:105px;text-align:right;">Price (USD)</th>'
        +'</tr></thead><tbody>';

    filtered.forEach(function(t){
        var inCart = !!_invCart[t.id];
        var isIA   = t.product_type === 'Info + A Plus';
        var isInfo = t.product_type === 'Infographics';
        var isAplus = !isIA && !isInfo;
        var isSelectableStatus = t.work_status==='Work Done' || t.work_status==='Info Done' || !!t.published_at;
        var infoInvd  = !!(t.info_invoice_status  && t.info_invoice_status  !== '');
        var aplusInvd = !!(t.aplus_invoice_status && t.aplus_invoice_status !== '') || (isAplus && !!(t.invoice_status && t.invoice_status !== ''));
        var isFullyInvoiced = isIA ? (infoInvd&&aplusInvd)
                            : (isInfo ? infoInvd
                            : (isAplus ? aplusInvd
                            : !!(t.invoice_status && t.invoice_status !== '')));
        var canSelect = isSelectableStatus && !isFullyInvoiced;

        if(inCart){
            var ci = _invCart[t.id];
            html += `<tr style="opacity:0.45;"><td style="text-align:center;font-size:11px;color:#4ade80;">✓</td><td style="white-space:nowrap;">${t.product_no}</td><td style="white-space:nowrap;font-size:11px;color:#94a3b8;">${t.work_status||'-'}</td><td style="white-space:normal;word-break:break-word;">${t.title} <span style="font-size:10px;color:#4ade80;">In Cart</span></td><td style="text-align:right;color:#94a3b8;font-size:12px;">${ci.info_price>0?'$'+parseFloat(ci.info_price).toFixed(2):'-'}</td><td style="text-align:right;color:#94a3b8;font-size:12px;">${ci.aplus_price>0?'$'+parseFloat(ci.aplus_price).toFixed(2):'-'}</td><td style="text-align:right;color:#4ade80;font-size:12px;">$${parseFloat(ci.price).toFixed(2)}</td></tr>`;
        } else if(!canSelect){
            var lockLabel='';
            if(isFullyInvoiced){
                if(isIA){
                    lockLabel='<span style="font-size:10px;color:#4ade80;margin-left:5px;">'+(infoInvd?'✅ Info '+t.info_invoice_status:'')+'</span><span style="font-size:10px;color:#4ade80;margin-left:3px;">'+(aplusInvd?'✅ A+ '+t.aplus_invoice_status:'')+'</span>';
                } else if(isInfo){
                    lockLabel='<span style="font-size:10px;color:'+(t.info_invoice_status==='Paid'?'#4ade80':'#93c5fd')+';margin-left:5px;">'+(t.info_invoice_status==='Paid'?'✅ Paid':'🧾 Invoiced')+'</span>';
                } else if(isAplus){
                    lockLabel='<span style="font-size:10px;color:'+(t.aplus_invoice_status==='Paid'?'#4ade80':'#93c5fd')+';margin-left:5px;">'+(t.aplus_invoice_status==='Paid'?'✅ Paid':'🧾 Invoiced')+'</span>';
                } else {
                    lockLabel='<span style="font-size:10px;color:'+(t.invoice_status==='Paid'?'#4ade80':'#93c5fd')+';margin-left:5px;">'+(t.invoice_status==='Paid'?'✅ Paid':'🧾 Invoiced')+'</span>';
                }
            } else { lockLabel='<span style="font-size:10px;color:#475569;margin-left:5px;">🔒 '+t.work_status+'</span>'; }
            html += `<tr style="opacity:0.4;background:#060d1a;"><td style="text-align:center;"><input type="checkbox" disabled style="cursor:not-allowed;opacity:0.3;"></td><td style="white-space:nowrap;color:#475569;">${t.product_no}</td><td style="white-space:nowrap;font-size:11px;color:#475569;">${t.work_status||'-'}${lockLabel}</td><td style="white-space:normal;word-break:break-word;color:#475569;">${t.title}</td><td style="text-align:right;color:#1e3a5f;font-size:11px;">-</td><td style="text-align:right;color:#1e3a5f;font-size:11px;">-</td><td style="text-align:right;color:#1e3a5f;font-size:11px;">-</td></tr>`;
        } else if(isIA || isInfo || isAplus){
            var infoHtml = '-';
            var aplusHtml = '-';
            
            if (isIA || isInfo) {
                var infoDisabled = infoInvd ? 'disabled style="opacity:0.35;cursor:not-allowed;background:#0a0f1a;"' : '';
                var infoVal = infoInvd ? `placeholder="(${t.info_invoice_status})"` : 'placeholder="0.00"';
                infoHtml = `<input type="number" class="inv-ia-info" id="inv-info-${t.id}" data-id="${t.id}" min="0" step="0.01" ${infoVal} style="width:90px;" oninput="applyInfoAplusPrice('${t.id}','info',this.value)" ${infoDisabled}>`;
            }
            
            if (isIA || isAplus) {
                var aplusDisabled = aplusInvd ? 'disabled style="opacity:0.35;cursor:not-allowed;background:#0a0f1a;"' : '';
                var aplusVal = aplusInvd ? `placeholder="(${t.aplus_invoice_status})"` : 'placeholder="0.00"';
                aplusHtml = `<input type="number" class="inv-ia-aplus" id="inv-aplus-${t.id}" data-id="${t.id}" min="0" step="0.01" ${aplusVal} style="width:90px;" oninput="applyInfoAplusPrice('${t.id}','aplus',this.value)" ${aplusDisabled}>`;
            }
            
            html += `<tr><td><input type="checkbox" class="inv-chk" data-id="${t.id}" onchange="syncInvSelectAll();calcInvTotal()"></td><td style="white-space:nowrap;">${t.product_no}</td><td style="white-space:nowrap;font-size:11px;color:#94a3b8;">${t.work_status||'-'}</td><td style="white-space:normal;word-break:break-word;">${t.title}</td><td>${infoHtml}</td><td>${aplusHtml}</td><td style="text-align:right;"><span id="inv-ia-sum-${t.id}" style="color:#4ade80;font-size:13px;font-weight:bold;">0.00</span></td></tr>`;
        } else {
            html += `<tr><td><input type="checkbox" class="inv-chk" data-id="${t.id}" onchange="syncInvSelectAll();calcInvTotal()"></td><td style="white-space:nowrap;">${t.product_no}</td><td style="white-space:nowrap;font-size:11px;color:#94a3b8;">${t.work_status||'-'}</td><td style="white-space:normal;word-break:break-word;">${t.title}</td><td style="text-align:right;color:#334155;font-size:11px;">-</td><td style="text-align:right;color:#334155;font-size:11px;">-</td><td><input type="number" class="inv-price-input inv-price-field" data-id="${t.id}" min="0" step="0.01" placeholder="0.00" oninput="applyBulkPrice('${t.id}',this.value)"></td></tr>`;
        }
    });
    html += '</tbody></table>';
    wrap.innerHTML = html;
    syncInvSelectAll();
    calcInvTotal();
}

function toggleInvSelectAll(){
    var checked = document.getElementById('inv-select-all').checked;
    document.querySelectorAll('.inv-chk').forEach(function(c){ c.checked=checked; });
    calcInvTotal(); updateInvSelCount();
}
function syncInvSelectAll(){
    var all  = document.querySelectorAll('.inv-chk');
    var chkd = document.querySelectorAll('.inv-chk:checked');
    var sa   = document.getElementById('inv-select-all');
    if(sa){ sa.checked=all.length>0&&chkd.length===all.length; sa.indeterminate=chkd.length>0&&chkd.length<all.length; }
    updateInvSelCount();
}
function updateInvSelCount(){
    var el = document.getElementById('inv-sel-count');
    var n  = document.querySelectorAll('.inv-chk:checked').length;
    if(el) el.textContent = n>0 ? n+' selected' : '';
}

function applyInfoAplusPrice(id, field, val){
    var v = parseFloat(val) || 0;
    // update current row first (in case it is not checked)
    var currInfoInp  = document.getElementById('inv-info-'  + id);
    var currAplusInp = document.getElementById('inv-aplus-' + id);
    if(currInfoInp && field==='info' && !currInfoInp.disabled) currInfoInp.value = val;
    if(currAplusInp && field==='aplus' && !currAplusInp.disabled) currAplusInp.value = val;
    var currSum = (currInfoInp ? (parseFloat(currInfoInp.value)||0) : 0) + (currAplusInp ? (parseFloat(currAplusInp.value)||0) : 0);
    var currSumEl = document.getElementById('inv-ia-sum-' + id);
    if(currSumEl) currSumEl.textContent = currSum.toFixed(2);

    // propagate to other checked rows
    document.querySelectorAll('.inv-chk:checked').forEach(function(chk){
        var cid      = chk.getAttribute('data-id');
        if(cid === id) return;
        var infoInp  = document.getElementById('inv-info-'  + cid);
        var aplusInp = document.getElementById('inv-aplus-' + cid);
        if(!infoInp && !aplusInp) return;
        if(field==='info' && infoInp && !infoInp.disabled)  infoInp.value  = val;
        if(field==='aplus' && aplusInp && !aplusInp.disabled) aplusInp.value = val;
        var sum = (infoInp ? (parseFloat(infoInp.value)||0) : 0) + (aplusInp ? (parseFloat(aplusInp.value)||0) : 0);
        var sumEl = document.getElementById('inv-ia-sum-' + cid);
        if(sumEl) sumEl.textContent = sum.toFixed(2);
    });
    calcInvTotal();
}

function applyBulkPrice(id, val){
    var chk = document.querySelector('.inv-chk[data-id="' + id + '"]');
    if(chk && chk.checked){
        document.querySelectorAll('.inv-chk:checked').forEach(function(c){
            var tid = c.getAttribute('data-id');
            if(tid !== id){ var inp = document.querySelector('.inv-price-field[data-id="'+tid+'"]'); if(inp) inp.value=val; }
        });
    }
    calcInvTotal();
}

function calcInvTotal(){
    var total = 0;
    Object.keys(_invCart).forEach(function(id){ total += parseFloat(_invCart[id].price)||0; });
    document.querySelectorAll('.inv-chk:checked').forEach(function(chk){
        var id = chk.getAttribute('data-id');
        if(_invCart[id]) return;
        var infoInp  = document.getElementById('inv-info-'  + id);
        var aplusInp = document.getElementById('inv-aplus-' + id);
        if(infoInp || aplusInp){ total += (infoInp ? (parseFloat(infoInp.value)||0) : 0) + (aplusInp ? (parseFloat(aplusInp.value)||0) : 0); }
        else { var inp = document.querySelector('.inv-price-field[data-id="'+id+'"]'); if(inp) total += parseFloat(inp.value)||0; }
    });
    var el = document.getElementById('inv-total-preview');
    if(el) el.textContent = total>0 ? 'Total: USD '+total.toFixed(2) : '';
}

/* ── Cart ────────────────────────────────────── */
function addSelectedToCart(){
    var added = 0;
    document.querySelectorAll('.inv-chk:checked').forEach(function(chk){
        var id   = chk.getAttribute('data-id');
        var task = _invTasks.find(function(t){ return String(t.id)===String(id); });
        if(!task) return;
        var price=0, info_price=0, aplus_price=0;
        var isIA = task.product_type === 'Info + A Plus';
        var isInfo = task.product_type === 'Infographics';
        var isAplus = !isIA && !isInfo;
        var isSubtaskProduct = isIA || isInfo || isAplus;
        if(isSubtaskProduct){
            var infoInp  = document.getElementById('inv-info-'  + id);
            var aplusInp = document.getElementById('inv-aplus-' + id);
            info_price   = parseFloat(infoInp  ? infoInp.value  : 0)||0;
            aplus_price  = parseFloat(aplusInp ? aplusInp.value : 0)||0;
            price = info_price + aplus_price;
        } else {
            var inp = document.querySelector('.inv-price-field[data-id="'+id+'"]');
            price   = parseFloat(inp ? inp.value : 0)||0;
        }
        if(!price) return;
        _invCart[id] = {id, product_no:task.product_no, title:task.title, work_status:task.work_status, product_type:task.product_type, price, info_price, aplus_price};
        added++;
    });
    if(!added){ alert('Pehle products check karein aur price darj karein'); return; }
    renderInvTable(); renderCart(); calcInvTotal();
}

function removeFromCart(id){ delete _invCart[id]; renderInvTable(); renderCart(); calcInvTotal(); }

function renderCart(){
    var cs   = document.getElementById('inv-cart-section');
    if(!cs) return;
    var keys = Object.keys(_invCart);
    if(!keys.length){ cs.style.display='none'; cs.innerHTML=''; return; }
    var total = 0;
    var html  = '<div style="background:#082f49;border:2px solid #38bdf8;border-radius:8px;padding:12px;">'
        + '<div style="font-size:13px;font-weight:700;color:#7dd3fc;margin-bottom:8px;">🛒 Invoice Cart (' + keys.length + ' product' + (keys.length>1?'s':'') + ')</div>'
        + '<table class="inv-table"><thead><tr><th>#</th><th>Status</th><th>Title</th><th style="text-align:right;width:90px;">Info (USD)</th><th style="text-align:right;width:90px;">A+ (USD)</th><th style="text-align:right;width:100px;">Price (USD)</th><th style="width:28px;"></th></tr></thead><tbody>';
    keys.forEach(function(id){
        var it = _invCart[id];
        var p  = parseFloat(it.price)||0; total+=p;
        html += `<tr><td style="white-space:nowrap;">${it.product_no}</td><td style="white-space:nowrap;font-size:11px;color:#94a3b8;">${it.work_status||'-'}</td><td style="white-space:normal;word-break:break-word;">${it.title}</td><td style="text-align:right;color:#94a3b8;font-size:12px;">${parseFloat(it.info_price)>0?'$'+parseFloat(it.info_price).toFixed(2):'-'}</td><td style="text-align:right;color:#94a3b8;font-size:12px;">${parseFloat(it.aplus_price)>0?'$'+parseFloat(it.aplus_price).toFixed(2):'-'}</td><td style="text-align:right;color:#4ade80;">$${p.toFixed(2)}</td><td><button onclick="removeFromCart('${id}')" style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:14px;padding:0 4px;" title="Remove">✕</button></td></tr>`;
    });
    html += '<tr style="font-weight:bold;"><td colspan="4" style="text-align:right;color:#94a3b8;">Total</td><td colspan="2" style="text-align:right;color:#4ade80;">$'+total.toFixed(2)+'</td><td></td></tr></tbody></table></div>';
    cs.innerHTML = html; cs.style.display = 'block';
}

/* ── Create invoice ──────────────────────────── */
function createInvoice(){
    var clientId = document.getElementById('inv-client').value;
    var notes    = (document.getElementById('inv-notes').value||'').trim();
    if(!clientId){ alert('Client select karein'); return; }
    var items=[], seen={};
    Object.keys(_invCart).forEach(function(id){
        var ci=_invCart[id]; var p=parseFloat(ci.price)||0;
        if(p>0){ items.push({task_id:id,price:p,info_price:parseFloat(ci.info_price)||0,aplus_price:parseFloat(ci.aplus_price)||0,product_type:ci.product_type||''}); seen[id]=true; }
    });
    document.querySelectorAll('.inv-chk:checked').forEach(function(chk){
        var id=chk.getAttribute('data-id'); if(seen[id]) return;
        var task=_invTasks.find(function(t){ return String(t.id)===String(id); });
        var isSubtaskProduct = task && ['Info + A Plus', 'Infographics', 'A+'].includes(task.product_type);
        if(isSubtaskProduct){
            var infoInp  = document.getElementById('inv-info-'  + id);
            var aplusInp = document.getElementById('inv-aplus-' + id);
            var info_price   = parseFloat(infoInp  ? infoInp.value  : 0)||0;
            var aplus_price  = parseFloat(aplusInp ? aplusInp.value : 0)||0;
            var price = info_price + aplus_price;
            if(price>0) items.push({task_id:id,price,info_price,aplus_price,product_type:task.product_type});
        } else {
            var inp=document.querySelector('.inv-price-field[data-id="'+id+'"]');
            var price=parseFloat(inp?inp.value:0)||0;
            if(price>0) items.push({task_id:id,price,product_type:task?task.product_type:''});
        }
    });
    if(!items.length){ alert('Cart mein koi product nahi — pehle products select karein aur price darj kar ke "Add to Invoice" dabayein'); return; }
    var fd=new FormData();
    fd.append('action','create_invoice'); fd.append('client_id',clientId);
    fd.append('notes',notes); fd.append('items',JSON.stringify(items));
    fetch('index.php',{method:'POST',body:fd})
        .then(r=>{ var ct=r.headers.get('content-type')||''; if(!ct.includes('application/json')) return r.text().then(t=>{ throw new Error('Server error: '+t.substring(0,300)); }); return r.json(); })
        .then(r=>{
            if(r.success){
                alert('Invoice '+r.invoice_no+' generate ho gai!');
                _invCart={};
                document.getElementById('inv-client').value='';
                document.getElementById('inv-notes').value='';
                var pl=document.getElementById('inv-product-list'); if(pl){ pl.style.display='none'; pl.innerHTML=''; }
                var cs=document.getElementById('inv-cart-section'); if(cs){ cs.style.display='none'; cs.innerHTML=''; }
                document.getElementById('inv-total-preview').textContent='';
                loadInvoices(); loadTasks();
            } else { alert('Error: '+(r.message||'Unknown error')); }
        })
        .catch(function(err){ alert('Invoice error: '+err.message); });
}

/* ── Load / list invoices ────────────────────── */
function loadInvoices(){
    fetch('index.php?action=get_invoices').then(r=>r.json()).then(r=>{
        var box=document.getElementById('inv-list-container');
        if(!box) return;
        if(!r.data||!r.data.length){ box.innerHTML='<div style="color:#94a3b8;font-size:13px;">Koi invoice nahi mila.</div>'; return; }
        var html='<table class="inv-table"><tr><th>Invoice No</th><th>Client</th><th>Products</th><th>Total</th><th>Status</th><th>Date</th><th>Actions</th></tr>';
        r.data.forEach(function(inv){
            var status  = inv.status==='Paid' ? '<span class="inv-status-badge inv-status-paid">Paid</span>' : '<span class="inv-status-badge inv-status-pending">Pending</span>';
            var actions = `<button class="inv-btn inv-btn-gray" style="padding:4px 10px;font-size:11px;" onclick="showInvoiceDetail(${inv.id})">View</button> `;
            if(ROLE==='administrator'){
                if(inv.status==='Pending') actions+=`<button class="inv-btn inv-btn-green" style="padding:4px 10px;font-size:11px;" onclick="markInvoicePaid(${inv.id})">Mark Paid</button> `;
                actions+=`<button class="inv-btn inv-btn-red" style="padding:4px 10px;font-size:11px;" onclick="deleteInvoice(${inv.id})">Delete</button>`;
            }
            var date=inv.created_at?inv.created_at.substring(0,10):'-';
            html+=`<tr><td style="white-space:nowrap;">${inv.invoice_no}</td><td>${inv.client_name}</td><td style="text-align:center;">${inv.item_count}</td><td>USD ${parseFloat(inv.total_amount).toFixed(2)}</td><td>${status}</td><td>${date}</td><td style="white-space:nowrap;">${actions}</td></tr>`;
        });
        html+='</table>';
        box.innerHTML=html;
    });
}

/* ── Invoice detail ──────────────────────────── */
function showInvoiceDetail(id){
    var box=document.getElementById('inv-detail-box');
    box.innerHTML='<div style="color:#94a3b8;">Loading...</div>';
    box.className='inv-detail-box open';
    fetch('index.php?action=get_invoice_detail&id='+id).then(r=>r.json()).then(r=>{
        if(!r.success){ box.innerHTML='<div style="color:#f87171;">Error loading invoice.</div>'; return; }
        var inv=r.invoice;
        var status=inv.status==='Paid'?'<span class="inv-status-badge inv-status-paid">Paid</span>':'<span class="inv-status-badge inv-status-pending">Pending</span>';
        var html=`<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;"><div><strong style="color:#fff;font-size:16px;">${inv.invoice_no}</strong> &nbsp; ${status}</div><div style="color:#94a3b8;font-size:12px;">Client: <strong style="color:#e2e8f0;">${inv.client_name}</strong> &nbsp;|&nbsp; Date: ${inv.created_at?inv.created_at.substring(0,10):'-'}</div></div>`;
        if(inv.notes) html+=`<div style="color:#94a3b8;font-size:12px;margin-bottom:10px;">Notes: ${inv.notes}</div>`;
        html+='<table class="inv-table"><tr><th>#</th><th>Product No</th><th>Title</th><th style="text-align:right;">Price (USD)</th></tr>';
        var total=0;
        r.items.forEach(function(it,i){ var p=parseFloat(it.item_price)||0; total+=p; html+=`<tr><td>${i+1}</td><td>${it.product_no}</td><td style="white-space:normal;word-break:break-word;">${it.title}</td><td style="text-align:right;">${p.toFixed(2)}</td></tr>`; });
        html+=`<tr class="inv-total-row"><td colspan="3" style="text-align:right;">Total</td><td style="text-align:right;">USD ${total.toFixed(2)}</td></tr></table>`;
        html+=`<div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;"><button class="inv-btn" style="background:#1e40af;color:#fff;padding:6px 14px;" onclick="printInvoiceById(${inv.id},false)">🖨 Print Preview</button><button class="inv-btn" style="background:#7c3aed;color:#fff;padding:6px 14px;" onclick="downloadInvoicePDF(${inv.id})">⬇ Download PDF</button><button class="inv-btn" style="background:#0369a1;color:#fff;padding:6px 14px;" onclick="shareInvoice(${inv.id})">🔗 Share Invoice</button></div>`;
        if(ROLE==='administrator'){
            var actBtns='';
            if(inv.status==='Pending') actBtns+=`<button class="inv-btn inv-btn-green" onclick="markInvoicePaid(${inv.id})">✅ Mark as Paid</button> `;
            actBtns+=`<button class="inv-btn inv-btn-red" onclick="deleteInvoice(${inv.id})">🗑 Delete Invoice</button>`;
            html+=`<div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;">${actBtns}</div>`;
        }
        html+=`<div style="margin-top:10px;"><button class="inv-btn inv-btn-gray" style="font-size:11px;" onclick="document.getElementById('inv-detail-box').className='inv-detail-box';">Close</button></div>`;
        box.innerHTML=html;
    });
    box.scrollIntoView({behavior:'smooth',block:'nearest'});
}

/* ── Mark paid / delete ──────────────────────── */
function markInvoicePaid(id){
    _confirm('Invoice ko Paid mark karein? Tamam products ka status Paid ho jayega.', function(){
        var fd=new FormData(); fd.append('action','mark_invoice_paid'); fd.append('id',id);
        fetch('index.php',{method:'POST',body:fd}).then(r=>r.json()).then(r=>{
            if(r.success){ loadInvoices(); loadTasks(); document.getElementById('inv-detail-box').className='inv-detail-box'; }
            else alert('Failed');
        });
    });
}

function deleteInvoice(id){
    _confirm('Invoice permanently delete karein?\n\nIs invoice ke tamam products ka Invoiced/Paid badge remove ho jayega.', function(){
        var fd=new FormData(); fd.append('action','delete_invoice'); fd.append('id',id);
        fetch('index.php',{method:'POST',body:fd})
            .then(r=>{ var ct=r.headers.get('content-type')||''; if(!ct.includes('application/json')) return r.text().then(t=>{ throw new Error('Server returned HTML. PHP error: '+t.substring(0,200)); }); return r.json(); })
            .then(r=>{
                if(r.success){ loadInvoices(); loadTasks(); var el=document.getElementById('inv-detail-box'); if(el) el.className='inv-detail-box'; }
                else alert('Delete failed: '+(r.message||'Unknown error'));
            })
            .catch(function(err){ alert('Delete error: '+err.message); });
    });
}

/* ── Print / PDF ─────────────────────────────── */
function previewInvoice(){
    var clientSel=document.getElementById('inv-client');
    if(!clientSel||!clientSel.value){ alert('Pehle client select karein'); return; }
    var clientName=clientSel.options[clientSel.selectedIndex].text;
    var notes=(document.getElementById('inv-notes')||{value:''}).value.trim();
    var cartKeys=Object.keys(_invCart);
    if(!cartKeys.length){ alert('Pehle cart mein products add karein'); return; }
    var items=cartKeys.map(function(k){ return _invCart[k]; });
    var total=items.reduce(function(s,i){ return s+(parseFloat(i.price)||0); },0);
    var hasIA=items.some(function(i){ return (parseFloat(i.info_price)||0)>0||(parseFloat(i.aplus_price)||0)>0; });
    var today=new Date().toISOString().substring(0,10);
    var year=new Date().getFullYear();
    var html='<div class="inv-preview-header"><div><div class="inv-preview-company" style="font-size:22px;font-weight:900;letter-spacing:1px;background:linear-gradient(90deg,#e53e3e,#ed8936,#ecc94b,#48bb78,#4299e1,#9f7aea);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Deziner4you</div><div style="color:#475569;font-size:11px;margin-top:5px;line-height:1.7;">Canal Road, Lahore &ndash; PAK<br>&#128222; 0092-333-4879073 (WhatsApp) &nbsp;|&nbsp; Skype: ursmani007<br>&#9993; deziner4you@gmail.com &nbsp;|&nbsp; deziner4uae@gmail.com<br>&#127760; deziner4you.com &nbsp;|&nbsp; deziner4u &bull; deziner4you &bull; deziner4uae</div></div><div class="inv-preview-meta"><strong>INVOICE</strong><br>No: INV-'+year+'-XXXX (Preview)<br>Date: '+today+'<br><span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:bold;">PREVIEW ONLY</span></div></div><div class="inv-preview-to"><div style="color:#64748b;font-size:11px;margin-bottom:4px;">BILLED TO</div><strong>'+clientName+'</strong></div>';
    if(hasIA){
        html+='<table class="inv-preview-table"><thead><tr><th>#</th><th>Product No</th><th>Title</th><th style="text-align:right;">Info (USD)</th><th style="text-align:right;">A+ (USD)</th><th style="text-align:right;">Price (USD)</th></tr></thead><tbody>';
        items.forEach(function(it,i){ var ip=parseFloat(it.info_price)||0; var ap=parseFloat(it.aplus_price)||0; var p=parseFloat(it.price)||0; html+='<tr><td>'+(i+1)+'</td><td>'+it.product_no+'</td><td>'+it.title+'</td><td style="text-align:right;">'+(ip>0?ip.toFixed(2):'-')+'</td><td style="text-align:right;">'+(ap>0?ap.toFixed(2):'-')+'</td><td style="text-align:right;">'+p.toFixed(2)+'</td></tr>'; });
        html+='</tbody><tfoot><tr class="inv-preview-total-row"><td colspan="5" style="text-align:right;padding:12px;">Total Amount</td><td style="text-align:right;padding:12px;color:#16a34a;">USD '+total.toFixed(2)+'</td></tr></tfoot></table>';
    } else {
        html+='<table class="inv-preview-table"><thead><tr><th>#</th><th>Product No</th><th>Title</th><th style="text-align:right;">Price (USD)</th></tr></thead><tbody>';
        items.forEach(function(it,i){ html+='<tr><td>'+(i+1)+'</td><td>'+it.product_no+'</td><td>'+it.title+'</td><td style="text-align:right;">'+parseFloat(it.price).toFixed(2)+'</td></tr>'; });
        html+='</tbody><tfoot><tr class="inv-preview-total-row"><td colspan="3" style="text-align:right;padding:12px;">Total Amount</td><td style="text-align:right;padding:12px;color:#16a34a;">USD '+total.toFixed(2)+'</td></tr></tfoot></table>';
    }
    if(notes) html+='<div class="inv-preview-notes"><strong>Notes:</strong> '+notes+'</div>';
    html+='<div class="inv-preview-footer">This is a preview. Invoice number will be assigned upon generation.</div>';
    document.getElementById('inv-preview-content').innerHTML=html;
    document.getElementById('invPreviewModal').className='inv-preview-modal open';
}

function closeInvPreview(){ document.getElementById('invPreviewModal').className='inv-preview-modal'; }

document.addEventListener('click', function(e){
    var modal=document.getElementById('invPreviewModal');
    if(modal && e.target===modal) closeInvPreview();
});

function printInvoiceById(id, autoprint){
    fetch('index.php?action=get_invoice_detail&id='+id).then(r=>r.json()).then(r=>{
        if(!r.success){ alert('Could not load invoice.'); return; }
        var inv=r.invoice; var items=r.items||[];
        var total=items.reduce(function(s,it){ return s+parseFloat(it.item_price||0); },0);
        var hasIAprices=items.some(function(it){ return parseFloat(it.info_price||0)>0||parseFloat(it.aplus_price||0)>0; });
        var rows='';
        items.forEach(function(it,i){
            var p=parseFloat(it.item_price||0);
            if(hasIAprices){ var ip=parseFloat(it.info_price||0); var ap=parseFloat(it.aplus_price||0); rows+='<tr><td>'+(i+1)+'</td><td>'+it.product_no+'</td><td>'+it.title+'</td><td style="text-align:right;">'+(ip>0?ip.toFixed(2):'-')+'</td><td style="text-align:right;">'+(ap>0?ap.toFixed(2):'-')+'</td><td style="text-align:right;">'+p.toFixed(2)+'</td></tr>'; }
            else { rows+='<tr><td>'+(i+1)+'</td><td>'+it.product_no+'</td><td>'+it.title+'</td><td style="text-align:right;">'+p.toFixed(2)+'</td></tr>'; }
        });
        var notesHtml=inv.notes?'<div style="margin-top:18px;padding:12px;background:#f8fafc;border-radius:6px;font-size:12px;color:#475569;"><strong>Notes:</strong> '+inv.notes+'</div>':'';
        var statusColor=inv.status==='Paid'?'#16a34a':'#b45309';
        var html='<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Invoice '+inv.invoice_no+'</title><style>*{box-sizing:border-box;}body{font-family:Arial,sans-serif;color:#111;background:#fff;margin:0;padding:30px;}.hdr{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #e2e8f0;padding-bottom:20px;margin-bottom:24px;}.co{font-size:24px;font-weight:bold;color:#0f172a;}.co-sub{color:#64748b;font-size:12px;margin-top:4px;}.meta{text-align:right;font-size:13px;color:#475569;line-height:1.9;}.bill{margin-bottom:20px;font-size:13px;}.bill-lbl{color:#64748b;font-size:11px;margin-bottom:4px;}.bill-name{font-size:15px;font-weight:bold;color:#0f172a;}table{width:100%;border-collapse:collapse;font-size:13px;}th{background:#f1f5f9;color:#475569;padding:9px 12px;text-align:left;border-bottom:2px solid #e2e8f0;}td{padding:9px 12px;border-bottom:1px solid #f1f5f9;}.tr{font-weight:bold;font-size:14px;background:#f8fafc;border-top:2px solid #e2e8f0;}.foot{margin-top:24px;text-align:center;font-size:11px;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:14px;}.np{margin-bottom:20px;display:flex;gap:10px;}@page{margin:0;}@media print{.np{display:none;}body{padding:18mm 20mm;}html,body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}.co{background:none !important;-webkit-background-clip:initial !important;background-clip:initial !important;-webkit-text-fill-color:#1e40af !important;color:#1e40af !important;}}</style></head><body>'
            +'<div class="np"><button onclick="window.print()" style="padding:8px 18px;background:#1e40af;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;">🖨 Print / Save as PDF</button><button onclick="window.close()" style="padding:8px 18px;background:#64748b;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;">Close</button></div>'
            +'<div class="hdr"><div><div class="co" style="font-size:26px;font-weight:900;letter-spacing:1px;background:linear-gradient(90deg,#e53e3e,#ed8936,#ecc94b,#48bb78,#4299e1,#9f7aea);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Deziner4you</div><div class="co-sub">Canal Road, Lahore &ndash; PAK<br>&#128222; 0092-333-4879073 (WhatsApp) &nbsp;|&nbsp; Skype: ursmani007<br>&#9993; deziner4you@gmail.com &nbsp;|&nbsp; deziner4uae@gmail.com<br>&#127760; deziner4you.com &nbsp;|&nbsp; deziner4u &bull; deziner4you &bull; deziner4uae</div></div><div class="meta"><strong style="font-size:18px;">INVOICE</strong><br>No: <strong>'+inv.invoice_no+'</strong><br>Date: '+(inv.created_at?inv.created_at.substring(0,10):'-')+'<br>Status: <strong style="color:'+statusColor+';">'+inv.status+'</strong></div></div>'
            +'<div class="bill"><div class="bill-lbl">BILLED TO</div><div class="bill-name">'+inv.client_name+'</div></div>'
            +(hasIAprices?'<table><thead><tr><th>#</th><th>Product No</th><th>Title</th><th style="text-align:right;">Info (USD)</th><th style="text-align:right;">A+ (USD)</th><th style="text-align:right;">Price (USD)</th></tr></thead><tbody>'+rows+'</tbody><tfoot><tr class="tr"><td colspan="5" style="text-align:right;padding:12px;">Total Amount</td><td style="text-align:right;padding:12px;color:#16a34a;">USD '+total.toFixed(2)+'</td></tr></tfoot></table>':'<table><thead><tr><th>#</th><th>Product No</th><th>Title</th><th style="text-align:right;">Price (USD)</th></tr></thead><tbody>'+rows+'</tbody><tfoot><tr class="tr"><td colspan="3" style="text-align:right;padding:12px;">Total Amount</td><td style="text-align:right;padding:12px;color:#16a34a;">USD '+total.toFixed(2)+'</td></tr></tfoot></table>')
            +notesHtml+'<div class="foot">Deziner4you &mdash; deziner4you.com &mdash; deziner4you@gmail.com &mdash; Generated '+new Date().toLocaleDateString()+'</div></body></html>';
        var win=window.open('','_blank','width=860,height=720');
        if(!win){ alert('Popup blocked. Please allow popups for this site.'); return; }
        win.document.write(html); win.document.close();
        if(autoprint){ win.focus(); win.onload=function(){ win.print(); }; if(win.document.readyState==='complete') setTimeout(function(){ win.print(); },300); }
    });
}

function downloadInvoicePDF(id){ printInvoiceById(id, true); }

/* ── Share invoice ───────────────────────────── */
function shareInvoice(id){
    fetch('index.php?action=get_invoice_detail&id='+id).then(r=>r.json()).then(r=>{
        if(!r.success){ alert('Could not load invoice.'); return; }
        var inv=r.invoice;
        if(!inv.share_token){ alert('Share link not available for this invoice. Please delete and re-create it.'); return; }
        var base=window.location.href.split('?')[0];
        var link=base+'?action=public_invoice&id='+inv.id+'&token='+inv.share_token;
        var waLink='https://wa.me/?text='+encodeURIComponent('Invoice '+inv.invoice_no+' — View here: '+link);
        document.getElementById('inv-share-link').value=link;
        document.getElementById('inv-share-wa').href=waLink;
        document.getElementById('inv-share-modal').style.display='flex';
    });
}

function copyShareLink(){
    var inp=document.getElementById('inv-share-link');
    inp.select(); inp.setSelectionRange(0,9999);
    try{ document.execCommand('copy'); }catch(e){ navigator.clipboard.writeText(inp.value); }
    var btn=document.getElementById('inv-copy-btn');
    var orig=btn.textContent; btn.textContent='✓ Copied!'; btn.style.background='#16a34a';
    setTimeout(function(){ btn.textContent=orig; btn.style.background='#1e40af'; },2000);
}
