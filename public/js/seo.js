/*
=====================================================
ECO A+ PRO — seo.js
SEO Content module — self-contained with injected CSS
=====================================================
*/

/* ── Inject CSS once ─────────────────────────── */
(function(){
    if(document.getElementById('seo-styles')) return;
    var s = document.createElement('style');
    s.id  = 'seo-styles';
    s.textContent = `
.seo-wrap{border:1px solid #1e3a5f;border-radius:8px;overflow:hidden;margin-top:14px;font-size:13px;}
.seo-head-light{display:flex;align-items:center;justify-content:space-between;background:#e8f0fe;color:#0f172a;font-weight:800;font-size:13px;letter-spacing:.5px;padding:11px 18px;border-bottom:1px solid #c7d7f9;}
.seo-head-dark{display:flex;align-items:center;justify-content:space-between;background:#0d1b2e;color:#e2e8f0;font-weight:800;font-size:13px;letter-spacing:.5px;padding:11px 18px;border-bottom:1px solid #1e3a5f;}
.seo-divider-dark{background:#060d1a;color:#64748b;font-size:11px;font-weight:700;letter-spacing:.8px;padding:7px 18px;border-bottom:1px solid #0f2035;}
.seo-save-green{background:#16a34a;color:#fff;border:none;padding:6px 20px;border-radius:5px;font-size:12px;font-weight:800;letter-spacing:.5px;cursor:pointer;}
.seo-save-green:hover{background:#15803d;}
.seo-banner-cols{display:grid;grid-template-columns:1fr 2fr 1fr;background:#0d1b2e;border-bottom:1px solid #1e3a5f;}
.seo-col-head{padding:9px 12px;color:#22d3ee;font-size:11px;font-weight:800;letter-spacing:.7px;text-align:center;border-right:1px solid #1e3a5f;}
.seo-col-head:last-child{border-right:none;}
.seo-banner-row{display:grid;grid-template-columns:1fr 2fr 1fr;border-bottom:1px solid #0f2035;align-items:center;background:#071428;}
.seo-banner-row:last-child{border-bottom:none;}
.seo-banner-label{padding:13px 12px;color:#22d3ee;font-weight:800;font-size:13px;letter-spacing:.5px;text-align:center;border-right:1px solid #1e3a5f;}
.seo-banner-label:last-child{border-right:none;border-left:1px solid #1e3a5f;}
.seo-banner-content{padding:10px 12px;border-right:1px solid #1e3a5f;display:flex;flex-direction:column;gap:7px;}
.seo-field-group{display:flex;flex-direction:column;gap:3px;}
.seo-field-label{font-size:10px;color:#64748b;font-weight:600;letter-spacing:.3px;}
.seo-inp{padding:8px 11px;background:#ffffff;border:1px solid #cbd5e1;border-radius:4px;color:#0f172a;font-size:13px;outline:none;box-sizing:border-box;width:100%;transition:border-color .15s;}
.seo-inp:focus{border-color:#22d3ee;box-shadow:0 0 0 2px rgba(34,211,238,.15);}
.seo-inp[readonly]{background:#f1f5f9;color:#475569;cursor:default;border-color:#e2e8f0;}
.seo-inp-full{width:100%;}
.seo-textarea{resize:vertical;min-height:90px;font-family:inherit;}
.seo-info-list{background:#071428;}
.seo-info-row{display:flex;align-items:center;border-bottom:1px solid #0f2035;}
.seo-info-row:last-child{border-bottom:none;}
.seo-info-label{min-width:75px;padding:11px 14px;color:#22d3ee;font-weight:800;font-size:12px;letter-spacing:.5px;border-right:1px solid #1e3a5f;background:#0d1b2e;flex-shrink:0;}
.seo-info-row .seo-inp{border-radius:0;border:none;border-bottom:1px solid #e2e8f0;background:#ffffff;color:#0f172a;padding:11px 14px;}
.seo-info-row .seo-inp:focus{background:#f8faff;border-bottom-color:#22d3ee;}
.seo-byte-row{padding:5px 14px 10px;background:#071428;}
.seo-msg-row{padding:8px 18px;background:#071428;border-top:1px solid #1e3a5f;min-height:28px;}
.seo-ai-btn{background:linear-gradient(135deg,#7c3aed,#2563eb);color:#fff;border:none;padding:4px 10px;border-radius:4px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;flex-shrink:0;margin-top:3px;align-self:flex-start;}
.seo-ai-btn:hover{opacity:.85;}
.seo-ai-btn:disabled{opacity:.5;cursor:wait;}
@media(max-width:640px){
  .seo-banner-cols,.seo-banner-row{grid-template-columns:80px 1fr;}
  .seo-col-head:last-child,.seo-banner-label:last-child{display:none;}
}
    `;
    document.head.appendChild(s);
})();

/* ── Load SEO panel ──────────────────────────── */
function loadSeoPanel(taskId, readonly){
    var container = document.getElementById('seo-panel-' + taskId);
    if(!container) return;
    container.innerHTML = '<div style="padding:16px;color:#94a3b8;font-size:13px;">Loading...</div>';

    fetch('index.php?action=get_seo_content&task_id=' + taskId)
        .then(r => r.json())
        .then(r => {
            if(!r.success){
                container.innerHTML = '<div style="padding:12px;color:#f87171;">Error loading SEO content.</div>';
                return;
            }
            container.innerHTML = buildSeoForm(taskId, r.data, readonly);
        })
        .catch(function(){
            container.innerHTML = '<div style="padding:12px;color:#f87171;">Network error.</div>';
        });
}

/* ── Build form HTML ─────────────────────────── */
function buildSeoForm(taskId, data, readonly){
    var banners      = data.banners      || [];
    var infographics = data.infographics || [];
    var bst          = data.backend_search_terms || '';
    var ro           = readonly ? 'readonly' : '';

    /* ─── Section 1: A+ Banners ─── */
    var bannersRows = '';
    banners.forEach(function(b, idx){
        var isB2 = (b.id === 2);
        var centerContent = '';

        if(isB2){
            centerContent = `
<div class="seo-field-group" style="border-bottom:1px dashed #1e3a5f;padding-bottom:8px;margin-bottom:8px;">
  <div style="display:flex;gap:6px;align-items:center;">
    <input type="text" class="seo-inp" id="seo-b${idx}-link-${taskId}" value="${eh(b.image_link||'')}" placeholder="Paste banner ${b.id} image link (Google Drive / public URL)..." ${ro} style="font-size:11px;padding:5px 8px;">
    <label class="seo-ai-btn" style="margin-top:0;display:inline-flex;align-items:center;padding:5px 8px;cursor:pointer;" title="Upload local image">
      📤 Upload
      <input type="file" id="seo-b${idx}-file-${taskId}" accept="image/*" style="display:none;" onchange="handleSeoImageUpload(event, ${taskId}, 'banner_heading', ${idx})">
    </label>
  </div>
</div>
<div class="seo-field-group">
  <label class="seo-field-label">Sub Heading</label>
  <div style="display:flex;gap:6px;align-items:center;">
    <input type="text" class="seo-inp" id="seo-b${idx}-subheading-${taskId}" value="${eh(b.sub_heading||'')}" placeholder="Sub heading..." ${ro}>
    <button class="seo-ai-btn" onclick="triggerGeminiGenerate(${taskId}, 'banner_subheading', ${idx}, 'seo-b${idx}-subheading-${taskId}')" ${readonly ? 'disabled' : ''}>✨ Generate</button>
  </div>
</div>
<div class="seo-field-group">
  <label class="seo-field-label">Heading</label>
  <div style="display:flex;gap:6px;align-items:center;">
    <input type="text" class="seo-inp" id="seo-b${idx}-heading-${taskId}" value="${eh(b.heading||'')}" placeholder="Heading..." ${ro}>
    <button class="seo-ai-btn" onclick="triggerGeminiGenerate(${taskId}, 'banner_heading', ${idx}, 'seo-b${idx}-heading-${taskId}')" ${readonly ? 'disabled' : ''}>✨ Generate</button>
  </div>
</div>
<div class="seo-field-group">
  <label class="seo-field-label">Body Text</label>
  <div style="display:flex;gap:6px;align-items:flex-start;">
    <textarea class="seo-inp seo-textarea" id="seo-b${idx}-body-${taskId}" placeholder="Body text..." rows="2" ${ro}>${eh(b.body_text||'')}</textarea>
    <button class="seo-ai-btn" onclick="triggerGeminiGenerate(${taskId}, 'banner_body', ${idx}, 'seo-b${idx}-body-${taskId}')" ${readonly ? 'disabled' : ''}>✨ Generate</button>
  </div>
</div>`;
        } else {
            centerContent = `
<div class="seo-field-group">
  <div style="display:flex;gap:6px;align-items:center;margin-bottom:4px;">
    <input type="text" class="seo-inp" id="seo-b${idx}-link-${taskId}" value="${eh(b.image_link||'')}" placeholder="Paste banner ${b.id} image link (Google Drive / public URL)..." ${ro} style="font-size:11px;padding:5px 8px;">
    <label class="seo-ai-btn" style="margin-top:0;display:inline-flex;align-items:center;padding:5px 8px;cursor:pointer;" title="Upload local image">
      📤 Upload
      <input type="file" id="seo-b${idx}-file-${taskId}" accept="image/*" style="display:none;" onchange="handleSeoImageUpload(event, ${taskId}, 'banner_desktop', ${idx})">
    </label>
  </div>
  <div style="display:flex;gap:6px;align-items:center;">
    <input type="text" class="seo-inp" id="seo-b${idx}-desktop-${taskId}" value="${eh(b.desktop||'')}" placeholder="Alt text / content..." ${ro}>
    <button class="seo-ai-btn" onclick="triggerGeminiGenerate(${taskId}, 'banner_desktop', ${idx}, 'seo-b${idx}-desktop-${taskId}')" ${readonly ? 'disabled' : ''}>✨ Generate</button>
  </div>
</div>`;
        }

        bannersRows += `
<div class="seo-banner-row">
  <div class="seo-banner-label">BANNER ${b.id}</div>
  <div class="seo-banner-content">${centerContent}</div>
  <div class="seo-banner-label">MOBILE ${b.id}</div>
</div>`;
    });

    var saveBtn = readonly
        ? ''
        : `<button class="seo-save-green" onclick="saveSeoContent(${taskId})">SAVE</button>`;

    var section1 = `
<div class="seo-wrap">
  <div class="seo-head-light">
    <span>A + BANNERS ALT TEXT</span>
    ${saveBtn}
  </div>
  <div class="seo-banner-cols">
    <div class="seo-col-head">DESKTOP VERSION</div>
    <div class="seo-col-head">CONTENT</div>
    <div class="seo-col-head">MOBILE VERSION</div>
  </div>
  ${bannersRows}
</div>`;

    /* ─── Section 2: Infographics ─── */
    var infoRows = '';
    infographics.forEach(function(p, idx){
        infoRows += `
<div class="seo-info-row" style="display:flex;flex-direction:column;padding:10px 14px;border-bottom:1px solid #0f2035;gap:6px;background:#071428;">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <div style="color:#22d3ee;font-weight:800;font-size:12px;letter-spacing:.5px;">PAGE ${p.page}</div>
    <div style="display:flex;gap:6px;align-items:center;flex:1;max-width:400px;justify-content:flex-end;">
      <input type="text" class="seo-inp" id="seo-info${idx}-link-${taskId}" value="${eh(p.image_link||'')}" placeholder="Paste infographic page ${p.page} image link..." ${ro} style="font-size:11px;padding:4px 8px;flex:1;">
      <label class="seo-ai-btn" style="margin-top:0;display:inline-flex;align-items:center;padding:4px 8px;cursor:pointer;" title="Upload local image">
        📤 Upload
        <input type="file" id="seo-info${idx}-file-${taskId}" accept="image/*" style="display:none;" onchange="handleSeoImageUpload(event, ${taskId}, 'infographic', ${idx})">
      </label>
    </div>
  </div>
  <div style="display:flex;gap:6px;align-items:center;width:100%;">
    <input type="text" class="seo-inp seo-inp-full" id="seo-info${idx}-${taskId}" value="${eh(p.content||'')}" placeholder="Page ${p.page} content..." ${ro}>
    <button class="seo-ai-btn" onclick="triggerGeminiGenerate(${taskId}, 'infographic', ${idx}, 'seo-info${idx}-${taskId}')" ${readonly ? 'disabled' : ''}>✨ Generate</button>
  </div>
</div>`;
    });

    var section2 = `
<div class="seo-wrap" style="margin-top:0;border-top:none;">
  <div class="seo-head-dark">
    <span>INFOGRAPHICS IMAGE DESCRIPTIONS</span>
    ${saveBtn}
  </div>
  <div class="seo-info-list">${infoRows}</div>
</div>`;

    /* ─── Section 3: Backend Search Terms ─── */
    var section3 = `
<div class="seo-wrap" style="margin-top:0;border-top:none;">
  <div class="seo-divider-dark">BACKEND SEARCH TERMS</div>
  <div class="seo-head-light" style="border-radius:0;">
    <span>BACKEND SEARCH TERMS <small style="color:#64748b;font-size:11px;">(Amazon 250 Bytes Optimized)</small></span>
    ${saveBtn}
  </div>
  <div style="padding:8px 12px;background:#071428;display:flex;flex-direction:column;gap:6px;">
    <div style="display:flex;gap:6px;align-items:flex-start;">
      <textarea class="seo-inp seo-inp-full seo-textarea" id="seo-bst-${taskId}" placeholder="Backend search terms..." ${ro} style="flex:1;">${eh(bst)}</textarea>
      <button class="seo-ai-btn" onclick="triggerGeminiGenerate(${taskId}, 'backend_search_terms', null, 'seo-bst-${taskId}')" ${readonly ? 'disabled' : ''}>✨ Generate Terms</button>
    </div>
    <div class="seo-byte-row" id="seo-bst-bytes-${taskId}" style="padding:0;"></div>
  </div>
</div>`;

    /* ─── Save message ─── */
    var msgRow = readonly ? '' : `
<div class="seo-msg-row">
  <span id="seo-save-msg-${taskId}" style="font-size:12px;"></span>
</div>`;

    var pasteTools = readonly ? '' : `
<div class="seo-wrap" style="margin-bottom:10px;">
  <div class="seo-head-dark"><span>📋 PASTE SEO CONTENT</span><button class="seo-save-green" onclick="applySeoPaste(${taskId})">AUTO FILL FIELDS</button></div>
  <div style="padding:8px 12px;background:#071428;">
    <textarea class="seo-inp seo-textarea" id="seo-paste-${taskId}" rows="4" placeholder="Paste labeled SEO content or JSON here. Example: Banner 1 Alt Text: ...  |  Banner 2 Heading: ...  |  Page 1: ..."></textarea>
    <div style="color:#64748b;font-size:10px;margin-top:5px;">Recognized labels: Banner 1–4 Image Link, Alt Text, Sub Heading, Heading, Body Text; Page 1–8; Backend Search Terms.</div>
  </div>
</div>`;

    return pasteTools + section1 + section2 + section3 + msgRow;
}

/* Apply a structured AI/text paste to the existing SEO fields. JSON is also
   accepted when the generator returns the same shape used by saveSeoContent. */
function applySeoPaste(taskId){
    var source = document.getElementById('seo-paste-' + taskId);
    if(!source || !source.value.trim()){ alert('SEO content paste karein'); return; }
    var raw = source.value.trim();
    var applied = 0;

    function put(id, value){
        var el = document.getElementById(id);
        if(el && value !== undefined && value !== null && String(value).trim() !== ''){
            el.value = String(value).trim();
            el.dispatchEvent(new Event('input', {bubbles:true}));
            applied++;
        }
    }
    function applyStructured(data){
        if(!data || typeof data !== 'object') return;
        (data.banners || []).forEach(function(b, i){
            var idx = i;
            put('seo-b'+idx+'-link-'+taskId, b.image_link);
            put('seo-b'+idx+'-desktop-'+taskId, b.desktop);
            put('seo-b'+idx+'-subheading-'+taskId, b.sub_heading);
            put('seo-b'+idx+'-heading-'+taskId, b.heading);
            put('seo-b'+idx+'-body-'+taskId, b.body_text);
        });
        (data.infographics || []).forEach(function(p, i){ put('seo-info'+i+'-'+taskId, p.content); put('seo-info'+i+'-link-'+taskId, p.image_link); });
        put('seo-bst-'+taskId, data.backend_search_terms);
    }
    try {
        var parsed = JSON.parse(raw);
        applyStructured(parsed);
    } catch(e) {
        var lines = raw.split(/\r?\n/);
        var current = null, buffer = [];
        function flush(){
            if(!current) return;
            var value = buffer.join('\n').trim();
            if(current.type === 'banner') put('seo-b'+(current.index-1)+'-'+current.field+'-'+taskId, value);
            if(current.type === 'info') put('seo-info'+(current.index-1)+'-'+taskId, value);
            if(current.type === 'bst') put('seo-bst-'+taskId, value);
            current = null; buffer = [];
        }
        lines.forEach(function(line){
            var m = line.match(/^\s*Banner\s*([1-4])\s*(?:Image\s*Link|Image|Alt\s*Text|Sub\s*Heading|Heading|Body\s*Text)?\s*[:\-]\s*(.*)$/i);
            var p = line.match(/^\s*(?:Page|Infographic)\s*([1-8])\s*[:\-]\s*(.*)$/i);
            var s = line.match(/^\s*(?:Backend\s*Search\s*Terms|Search\s*Terms)\s*[:\-]\s*(.*)$/i);
            if(m){
                flush();
                var label = (line.match(/Banner\s*[1-4]\s*(.*?)(?:\s*[:\-]\s*)/i)||[])[1] || '';
                label = label.toLowerCase().replace(/[^a-z]/g,'');
                var field = label.indexOf('imagelink') >= 0 || label === 'image' ? 'link' : label.indexOf('subheading') >= 0 ? 'subheading' : label.indexOf('heading') >= 0 ? 'heading' : label.indexOf('bodytext') >= 0 ? 'body' : 'desktop';
                current = {type:'banner', index:parseInt(m[1],10), field:field}; buffer = [m[2]];
            } else if(p){ flush(); current={type:'info',index:parseInt(p[1],10)}; buffer=[p[2]]; }
            else if(s){ flush(); current={type:'bst'}; buffer=[s[1]]; }
            else if(current && line.trim()) buffer.push(line);
        });
        flush();
    }
    if(applied) alert(applied + ' SEO field(s) auto filled. Ab SAVE press karein.');
    else alert('Recognized labels nahi milay. Example format use karein: Banner 1 Alt Text: ...');
}

/* ── Save ────────────────────────────────────── */
function saveSeoContent(taskId){
    var banners = [];
    for(var idx = 0; idx < 4; idx++){
        var d  = document.getElementById('seo-b'+idx+'-desktop-'+taskId);
        var sh = document.getElementById('seo-b'+idx+'-subheading-'+taskId);
        var hd = document.getElementById('seo-b'+idx+'-heading-'+taskId);
        var bd = document.getElementById('seo-b'+idx+'-body-'+taskId);
        var l  = document.getElementById('seo-b'+idx+'-link-'+taskId);
        if(!d && !sh) continue;
        banners.push({
            id:          idx + 1,
            desktop:     d  ? d.value  : '',
            mobile:      '',
            sub_heading: sh ? sh.value : '',
            heading:     hd ? hd.value : '',
            body_text:   bd ? bd.value : '',
            image_link:  l  ? l.value  : '',
        });
    }

    var infographics = [];
    for(var idx = 0; idx < 8; idx++){
        var el = document.getElementById('seo-info'+idx+'-'+taskId);
        var l  = document.getElementById('seo-info'+idx+'-link-'+taskId);
        if(!el) continue;
        infographics.push({
            page: idx + 1,
            content: el.value,
            image_link: l ? l.value : '',
        });
    }

    var bstEl   = document.getElementById('seo-bst-'+taskId);
    var notesEl = document.getElementById('seo-notes-'+taskId);
    var msg     = document.getElementById('seo-save-msg-'+taskId);

    var payload = {
        banners:              banners,
        infographics:         infographics,
        backend_search_terms: bstEl   ? bstEl.value   : '',
        seo_notes:            notesEl ? notesEl.value  : '',
    };

    var fd = new FormData();
    fd.append('action',   'save_seo_content');
    fd.append('task_id',  taskId);
    fd.append('seo_data', JSON.stringify(payload));

    if(msg){ msg.textContent = '⏳ Saving...'; msg.style.color = '#94a3b8'; }

    fetch('index.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(r => {
            if(!msg) return;
            msg.textContent = r.success ? '✅ Saved successfully!' : '❌ ' + (r.message||'Error');
            msg.style.color = r.success ? '#4ade80' : '#f87171';
            setTimeout(function(){ msg.textContent = ''; }, 3000);
        });
}

/* ── Byte counter ────────────────────────────── */
function updateByteCounter(taskId){
    var el    = document.getElementById('seo-bst-' + taskId);
    var cntEl = document.getElementById('seo-bst-bytes-' + taskId);
    if(!el || !cntEl) return;
    var bytes = new TextEncoder().encode(el.value).length;
    var color = bytes > 250 ? '#f87171' : bytes > 220 ? '#fbbf24' : '#4ade80';
    cntEl.innerHTML = '<span style="color:'+color+';font-size:11px;font-weight:700;">'+bytes+' / 250 bytes</span>';
}
document.addEventListener('input', function(e){
    if(e.target && e.target.id && e.target.id.indexOf('seo-bst-') === 0){
        updateByteCounter(e.target.id.replace('seo-bst-',''));
    }
});

/* ── HTML escape helper ──────────────────────── */
function eh(str){
    if(!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

/* ── Gemini Multi-modal Helpers ──────────────── */
var UPLOADED_IMGS = {};

function handleSeoImageUpload(event, taskId, section, idx) {
    var file = event.target.files[0];
    if (!file) return;

    var reader = new FileReader();
    reader.onload = function(e) {
        var base64 = e.target.result;
        var key = taskId + '_' + section + '_' + (idx !== null ? idx : 'none');
        UPLOADED_IMGS[key] = base64;
        
        var fileLabel = event.target.parentElement;
        if (fileLabel) {
            var origText = fileLabel.innerHTML;
            fileLabel.innerHTML = '✅ Loaded';
            fileLabel.style.background = '#16a34a';
            setTimeout(function() {
                fileLabel.innerHTML = origText;
                fileLabel.style.background = '';
            }, 3000);
        }
    };
    reader.readAsDataURL(file);
}

function triggerGeminiGenerate(taskId, section, idx, targetInputId) {
    var task = ALL_TASKS.find(function(t){ return t.id == taskId; });
    var title = task ? task.title : '';
    if (!title) { alert('Product title not found'); return; }

    var targetEl = document.getElementById(targetInputId);
    if (!targetEl) return;

    var key = taskId + '_' + section + '_' + (idx !== null ? idx : 'none');
    var base64Image = UPLOADED_IMGS[key] || null;

    var imageLink = '';
    if (!base64Image && idx !== null) {
        var linkId = '';
        if (section.startsWith('banner')) {
            linkId = 'seo-b' + idx + '-link-' + taskId;
        } else if (section === 'infographic') {
            linkId = 'seo-info' + idx + '-link-' + taskId;
        }
        var linkEl = document.getElementById(linkId);
        if (linkEl) imageLink = linkEl.value.trim();
    }

    var origVal = targetEl.value;
    targetEl.value = '⏳ Generating...';
    targetEl.disabled = true;

    var fd = new FormData();
    fd.append('action', 'generate_seo_ai');
    fd.append('section', section);
    fd.append('product_title', title);
    fd.append('context', origVal);
    if (base64Image) {
        fd.append('image_base64', base64Image);
    }
    if (imageLink) {
        fd.append('image_link', imageLink);
    }

    fetch('index.php', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(r => {
            targetEl.disabled = false;
            if (r.success) {
                targetEl.value = r.generated;
                if (typeof updateByteCounter === 'function') {
                    updateByteCounter(taskId);
                }
            } else {
                targetEl.value = origVal;
                alert('Gemini Error: ' + (r.message || 'Unknown error'));
            }
        })
        .catch(function(err) {
            targetEl.disabled = false;
            targetEl.value = origVal;
            alert('Request failed: ' + err.message);
        });
}
