<?php
/*
=====================================================
ECO A+ PRO — Login Form
Matches original form field names exactly.
=====================================================
*/
?>
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:#081223;padding:20px;">
<div style="width:100%;max-width:380px;">

    <!-- Logo / title -->
    <div style="text-align:center;margin-bottom:32px;">
        <div style="font-size:28px;font-weight:900;letter-spacing:2px;background:linear-gradient(90deg,#3b82f6,#8b5cf6);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">ECO A+ PRO</div>
        <div style="color:#475569;font-size:13px;margin-top:6px;">Content Management System</div>
    </div>

    <!-- Error message -->
    <?php if(!empty($loginError)): ?>
    <div style="background:#3b0000;border:1px solid #dc2626;border-radius:8px;padding:12px 16px;margin-bottom:20px;color:#fca5a5;font-size:13px;text-align:center;">
        <?= htmlspecialchars($loginError) ?>
    </div>
    <?php endif; ?>

    <!-- Login card -->
    <div style="background:#162033;border:1px solid #1e3a5f;border-radius:12px;padding:32px 28px;box-shadow:0 20px 60px rgba(0,0,0,0.5);">
        <form method="POST" action="index.php">
            <input type="hidden" name="action" value="login">

            <div style="margin-bottom:20px;">
                <label style="display:block;color:#94a3b8;font-size:12px;font-weight:600;letter-spacing:.5px;margin-bottom:8px;">USERNAME</label>
                <input
                    type="text"
                    name="username"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="Enter username"
                    style="width:100%;box-sizing:border-box;padding:12px 14px;background:#0a1628;border:1px solid #1e3a5f;border-radius:8px;color:#e2e8f0;font-size:14px;outline:none;transition:border-color .2s;"
                    onfocus="this.style.borderColor='#3b82f6'"
                    onblur="this.style.borderColor='#1e3a5f'"
                >
            </div>

            <div style="margin-bottom:28px;">
                <label style="display:block;color:#94a3b8;font-size:12px;font-weight:600;letter-spacing:.5px;margin-bottom:8px;">PASSWORD</label>
                <input
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Enter password"
                    style="width:100%;box-sizing:border-box;padding:12px 14px;background:#0a1628;border:1px solid #1e3a5f;border-radius:8px;color:#e2e8f0;font-size:14px;outline:none;transition:border-color .2s;"
                    onfocus="this.style.borderColor='#3b82f6'"
                    onblur="this.style.borderColor='#1e3a5f'"
                >
            </div>

            <button
                type="submit"
                style="width:100%;padding:13px;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;letter-spacing:.5px;transition:opacity .2s;"
                onmouseover="this.style.opacity='0.9'"
                onmouseout="this.style.opacity='1'"
            >
                LOGIN
            </button>
        </form>
    </div>

    <div style="text-align:center;margin-top:24px;color:#334155;font-size:11px;">
        <?= htmlspecialchars(APP_NAME) ?> v<?= htmlspecialchars(APP_VERSION) ?>
    </div>
</div>
</div>
