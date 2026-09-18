<?php /* Invoice panel — shown in invoices tab */ ?>
<div style="width:100%;margin:0;">

<?php if($user['role'] === 'administrator'): ?>
<!-- ── Create invoice (admin) ── -->
<div style="background:#0f2035;border:1px solid #1e3a5f;border-radius:10px;padding:18px;margin-bottom:20px;">
    <div style="font-size:13px;font-weight:700;color:#93c5fd;margin-bottom:14px;letter-spacing:.5px;">🧾 CREATE NEW INVOICE</div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;align-items:flex-end;">
        <div style="flex:1;min-width:180px;">
            <label style="display:block;color:#64748b;font-size:11px;margin-bottom:5px;">CLIENT</label>
            <select id="inv-client" onchange="loadUninvoicedTasks()" class="inv-select" style="width:100%;">
                <option value="">-- Select Client --</option>
            </select>
        </div>
        <div style="flex:2;min-width:220px;">
            <label style="display:block;color:#64748b;font-size:11px;margin-bottom:5px;">NOTES (optional)</label>
            <input id="inv-notes" type="text" placeholder="Invoice notes..." class="inv-input" style="width:100%;box-sizing:border-box;">
        </div>
    </div>

    <!-- Product selector -->
    <div id="inv-product-list" style="display:none;margin-bottom:14px;"></div>

    <!-- Cart -->
    <div id="inv-cart-section" style="display:none;margin-bottom:14px;"></div>

    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <button onclick="previewInvoice()" class="inv-btn" style="background:#7c3aed;color:#fff;">👁 Preview</button>
        <button onclick="createInvoice()" class="inv-btn inv-btn-green">🧾 Generate Invoice</button>
        <span id="inv-total-preview" style="font-size:13px;color:#4ade80;font-weight:bold;"></span>
    </div>
</div>
<?php endif; ?>

<!-- ── Invoice list ── -->
<div style="background:#0f2035;border:1px solid #1e3a5f;border-radius:10px;padding:18px;">
    <div style="font-size:13px;font-weight:700;color:#93c5fd;margin-bottom:14px;letter-spacing:.5px;">📋 INVOICES</div>
    <div style="overflow-x:auto;">
        <div id="inv-list-container"><div style="color:#475569;font-size:13px;">Loading...</div></div>
    </div>
</div>

<!-- ── Invoice detail ── -->
<div id="inv-detail-box" class="inv-detail-box" style="margin-top:16px;"></div>

</div>
