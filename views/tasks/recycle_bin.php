<?php
/*
=====================================================
ECO A+ PRO — Recycle Bin View
=====================================================
*/
?>
<div style="max-width:1200px; margin:0 auto; display:flex; flex-direction:column; gap:20px;">
    
    <!-- Header banner -->
    <div style="background: linear-gradient(135deg, #0d1b2e 0%, #162033 100%); border: 1px solid #1e3a5f; border-radius: 12px; padding: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
        <div>
            <h2 style="margin: 0; font-size: 20px; font-weight: 600; color: #e2e8f0; letter-spacing: 0.5px;">
                🗑️ Recycle Bin
            </h2>
            <p style="margin: 4px 0 0; font-size: 13px; color: #94a3b8;">
                Ghalti se delete kiye gaye products ko restore karein ya permanent delete karein.
            </p>
        </div>
        <button id="empty-bin-btn" onclick="emptyRecycleBin()" class="adminbtn" style="background:#dc2626; color:#fff; border:none; padding:8px 16px; border-radius:6px; font-weight:bold; cursor:pointer; display:none; transition:background 0.2s;">
            Empty Bin
        </button>
    </div>

    <!-- Deleted tasks container -->
    <div id="deleted-tasks-list" style="display:flex; flex-direction:column; gap:12px; padding:10px 0;">
        <div style="text-align:center; padding:40px; color:#64748b; font-size:14px; background:#0f2035; border:1px solid #1e3a5f; border-radius:10px;">
            Loading Recycle Bin...
        </div>
    </div>
    
</div>
