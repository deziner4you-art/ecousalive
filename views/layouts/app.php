<?php
/*
=====================================================
ECO A+ PRO — Main App Layout
=====================================================
*/
$publicUrl = PUBLIC_URL;
$role      = $user['role'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<script>
(function() {
    var savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'light') {
        document.documentElement.classList.add('light-theme');
    }
})();
</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#081223">
<title><?= htmlspecialchars(APP_NAME) ?></title>
<link rel="manifest" href="<?= $publicUrl ?>/assets/manifest.json">
<link rel="icon"     href="<?= $publicUrl ?>/assets/icon-192.png" type="image/png">

<!-- Google Fonts & Material Symbols -->
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<style>
@import url('https://fonts.googleapis.com/css2?family=Geist:wght@400;600;700&family=JetBrains+Mono:wght@500;600&display=swap');
</style>

<?php if(defined('GA_ID') && GA_ID): ?>
<!-- Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars(GA_ID) ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){ dataLayer.push(arguments); }
gtag('js', new Date());
gtag('config', <?= json_encode(GA_ID) ?>);
</script>
<?php endif; ?>

<!-- Tailwind CDN with Stitch design system tokens -->
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script>
tailwind.config = {
    darkMode: "class",
    theme: {
        extend: {
            colors: {
                'eco-dark'  : '#081223',
                'eco-card'  : '#162033',
                'eco-border': '#1e3a5f',
                "inverse-primary": "#005ac2",
                "background": "#0b1326",
                "primary-fixed-dim": "#adc6ff",
                "outline": "#8c909f",
                "tertiary-container": "#00a572",
                "surface-dim": "#0b1326",
                "on-surface": "#dae2fd",
                "secondary-container": "#ec6a06",
                "surface-container-lowest": "#060e20",
                "secondary": "#ffb690",
                "on-secondary-fixed": "#341100",
                "on-tertiary-fixed": "#002113",
                "on-primary-fixed-variant": "#004395",
                "error-container": "#93000a",
                "surface-tint": "#adc6ff",
                "surface-container": "#171f33",
                "secondary-fixed": "#ffdbca",
                "on-error": "#690005",
                "surface-container-low": "#131b2e",
                "surface-container-high": "#222a3d",
                "surface-variant": "#2d3449",
                "on-surface-variant": "#c2c6d6",
                "primary-fixed": "#d8e2ff",
                "on-primary-container": "#00285d",
                "surface-bright": "#31394d",
                "on-tertiary-fixed-variant": "#005236",
                "inverse-on-surface": "#283044",
                "surface-container-highest": "#2d3449",
                "on-background": "#dae2fd",
                "inverse-surface": "#dae2fd",
                "on-secondary": "#552100",
                "outline-variant": "#424754",
                "on-secondary-fixed-variant": "#783200",
                "error": "#ffb4ab",
                "surface": "#0b1326",
                "on-primary-fixed": "#001a42",
                "primary": "#adc6ff",
                "on-secondary-container": "#4a1c00",
                "on-error-container": "#ffdad6",
                "tertiary-fixed-dim": "#4edea3",
                "on-tertiary": "#003824",
                "tertiary": "#4edea3",
                "tertiary-fixed": "#6ffbbe",
                "primary-container": "#4d8eff",
                "on-primary": "#002e6a",
                "on-tertiary-container": "#00311f",
                "secondary-fixed-dim": "#ffb690"
            },
            borderRadius: {
                "DEFAULT": "0.125rem",
                "lg": "0.25rem",
                "xl": "0.5rem",
                "full": "0.75rem"
            },
            spacing: {
                "container-margin": "16px",
                "stack-gap": "8px",
                "gutter-x": "12px",
                "touch-target-min": "44px",
                "base": "4px"
            }
        }
    }
};
</script>

<!-- App CSS (cards, status badges, task styles from monolith) -->
<link rel="stylesheet" href="<?= $publicUrl ?>/css/app.css?v=2.8.6">

<style>
/* ── Topbar ────────────────────────────────────── */
#topbar {
    position: fixed;
    top: 0; left: 0; right: 0;
    z-index: 1000;
    background: #0a1628;
    border-bottom: 1px solid #1e3a5f;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    height: 52px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.4);
}

/* Logo */
#topbar .nav-logo {
    font-size: 15px;
    font-weight: 900;
    letter-spacing: 1.5px;
    background: linear-gradient(90deg, #3b82f6, #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    white-space: nowrap;
    margin-right: 8px;
    padding-right: 12px;
    border-right: 1px solid #1e3a5f;
}

/* Nav left group */
#topbar .nav-left {
    display: flex;
    align-items: center;
    gap: 4px;
    overflow-x: auto;
    flex: 1;
    min-width: 0;
}

/* Tab buttons */
#topbar .tab-btn {
    background: transparent;
    border: none;
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 6px;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.15s, color 0.15s;
    letter-spacing: 0.3px;
}
#topbar .tab-btn:hover {
    background: #162033;
    color: #93c5fd;
}
#topbar .tab-btn.active {
    background: #1e3a5f;
    color: #60a5fa;
    border-bottom: 2px solid #3b82f6;
}

/* ── Vendor Browser Tabs Bar ── */
.vendor-nav-divider {
    width: 1px;
    height: 22px;
    background: #1e3a5f;
    margin: 0 8px;
    flex-shrink: 0;
}
.vendor-browser-tabs-container {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    overflow-x: auto;
    max-width: 100%;
    scrollbar-width: none;
    -ms-overflow-style: none;
    padding: 2px 2px 0 2px;
}
.vendor-browser-tabs-container::-webkit-scrollbar {
    display: none;
}
.vendor-browser-tab {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    font-size: 12px;
    font-weight: 600;
    color: #94a3b8;
    background: #0f1d32;
    border: 1px solid #1e3a5f;
    border-top: 2.5px solid transparent;
    border-radius: 6px 6px 3px 3px;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.15s ease;
    user-select: none;
    height: 31px;
    box-sizing: border-box;
}
.vendor-browser-tab:hover {
    background: #162742;
    color: #e2e8f0;
    border-color: #2b4c77;
}
.vendor-browser-tab.active {
    background: #192a45;
    color: #ffffff;
    border-color: #3b82f6;
    border-top: 2.5px solid #38bdf8;
    box-shadow: 0 2px 8px rgba(0,0,0,0.35);
}
.vendor-tab-color-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0;
}
.vendor-tab-count {
    background: rgba(0, 0, 0, 0.4);
    border-radius: 10px;
    padding: 1px 6px;
    font-size: 10px;
    font-weight: 700;
    color: #93c5fd;
    line-height: 1.2;
}
.vendor-browser-tab.active .vendor-tab-count {
    background: rgba(56, 189, 248, 0.25);
    color: #e0f2fe;
}
.vendor-browser-tab-add {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 0 9px;
    height: 28px;
    background: #0f1d32;
    border: 1px dashed #334155;
    border-radius: 5px;
    color: #94a3b8;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
    flex-shrink: 0;
    margin-left: 2px;
}
.vendor-browser-tab-add:hover {
    background: #1e3a5f;
    border-color: #38bdf8;
    color: #38bdf8;
    transform: translateY(-1px);
}

/* Nav right group */
#topbar .nav-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
    margin-left: 12px;
}

/* Username badge */
#topbar .nav-user {
    font-size: 12px;
    font-weight: 700;
    color: #93c5fd;
    background: #162033;
    border: 1px solid #1e3a5f;
    padding: 4px 12px;
    border-radius: 20px;
    white-space: nowrap;
    letter-spacing: 0.3px;
}

/* ── Notification Area ── */
.nav-notif-container {
    position: relative;
    display: inline-flex;
    align-items: center;
}
.nav-notif-btn {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #162033;
    border: 1px solid #1e3a5f;
    color: #94a3b8;
    cursor: pointer;
    transition: all 0.2s ease;
    padding: 0;
    outline: none;
}
.nav-notif-btn:hover {
    background: #1e2e47;
    color: #38bdf8;
    border-color: #38bdf8;
    box-shadow: 0 0 10px rgba(56, 189, 248, 0.3);
}
.notif-bell-svg {
    width: 18px;
    height: 18px;
    transition: transform 0.2s ease;
}
.nav-notif-btn:hover .notif-bell-svg {
    transform: rotate(15deg);
}
.notif-badge {
    position: absolute;
    top: -4px;
    right: -5px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #ffffff;
    font-size: 10px;
    font-weight: 700;
    line-height: 1;
    min-width: 18px;
    height: 18px;
    padding: 0 4px;
    border-radius: 9px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #0a1628;
    box-shadow: 0 0 8px rgba(239, 68, 68, 0.6);
    font-family: 'JetBrains Mono', monospace, sans-serif;
    pointer-events: none;
    animation: notifPulse 2.5s infinite;
}
@keyframes notifPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); box-shadow: 0 0 12px rgba(239, 68, 68, 0.85); }
}

/* ── Notification Dropdown ── */
.notif-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    width: 360px;
    max-width: calc(100vw - 20px);
    background: #0d1829;
    border: 1px solid #1e3a5f;
    border-radius: 12px;
    box-shadow: 0 18px 36px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(255, 255, 255, 0.05);
    z-index: 2000;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.notif-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px 8px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}
.notif-header-left {
    display: flex;
    align-items: center;
    gap: 8px;
}
.notif-title {
    font-size: 13px;
    font-weight: 700;
    color: #f1f5f9;
    letter-spacing: 0.3px;
}
.notif-count-pill {
    font-size: 11px;
    font-weight: 700;
    background: rgba(56, 189, 248, 0.15);
    color: #38bdf8;
    padding: 2px 7px;
    border-radius: 12px;
    border: 1px solid rgba(56, 189, 248, 0.3);
}
.notif-close-btn {
    background: transparent;
    border: none;
    color: #64748b;
    font-size: 14px;
    cursor: pointer;
    padding: 2px 6px;
    border-radius: 4px;
    line-height: 1;
}
.notif-close-btn:hover {
    color: #f1f5f9;
    background: rgba(255, 255, 255, 0.1);
}
.notif-subtitle {
    padding: 0 16px 10px;
    font-size: 11px;
    color: #94a3b8;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}
.notif-list {
    max-height: 350px;
    overflow-y: auto;
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.notif-item {
    background: #132034;
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    padding: 8px 10px;
    cursor: pointer;
    transition: all 0.15s ease;
    text-align: left;
}
.notif-item:hover {
    background: #1a2c47;
    border-color: rgba(56, 189, 248, 0.4);
    transform: translateX(2px);
}
.notif-item-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    margin-bottom: 4px;
}
.notif-item-prodno {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    font-weight: 700;
    color: #38bdf8;
    background: rgba(56, 189, 248, 0.12);
    padding: 1px 5px;
    border-radius: 4px;
}
.notif-item-tag {
    font-size: 10px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.notif-item-tag.work-done {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, 0.3);
}
.notif-item-tag.info-done {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.4);
}
.notif-item-tag.in-qa {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.3);
}
.notif-item-tag.working {
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.3);
}
.notif-item-tag.pending {
    background: rgba(168, 85, 247, 0.15);
    color: #c084fc;
    border: 1px solid rgba(168, 85, 247, 0.3);
}
.notif-item-title {
    font-size: 12px;
    color: #cbd5e1;
    line-height: 1.35;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 320px;
}
.notif-item-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 3px;
    font-size: 10px;
    color: #64748b;
}
.notif-empty {
    padding: 28px 16px;
    text-align: center;
    color: #64748b;
    font-size: 12px;
}
.notif-footer {
    padding: 8px 12px;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
    background: rgba(10, 20, 35, 0.7);
    display: flex;
    justify-content: center;
}
.notif-action-btn {
    font-size: 11px;
    font-weight: 600;
    color: #38bdf8;
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
    transition: all 0.15s;
}
.notif-action-btn:hover {
    background: rgba(56, 189, 248, 0.12);
    color: #7dd3fc;
}

/* Light theme overrides */
html.light-theme .nav-notif-btn {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #475569;
}
html.light-theme .nav-notif-btn:hover {
    background: #e2e8f0;
    color: #0284c7;
    border-color: #0284c7;
}
html.light-theme .notif-badge {
    border-color: #ffffff;
}
html.light-theme .notif-dropdown {
    background: #ffffff;
    border-color: #cbd5e1;
    box-shadow: 0 18px 36px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0, 0, 0, 0.05);
}
html.light-theme .notif-title {
    color: #0f172a;
}
html.light-theme .notif-header {
    border-bottom-color: #f1f5f9;
}
html.light-theme .notif-subtitle {
    color: #64748b;
    border-bottom-color: #f1f5f9;
}
html.light-theme .notif-item {
    background: #f8fafc;
    border-color: #e2e8f0;
}
html.light-theme .notif-item:hover {
    background: #f0f9ff;
    border-color: #bae6fd;
}
html.light-theme .notif-item-title {
    color: #334155;
}
html.light-theme .notif-footer {
    background: #f8fafc;
    border-top-color: #e2e8f0;
}

/* Card highlight pulse when clicked from notification */
@keyframes cardNotifHighlight {
    0% { outline: 3px solid #38bdf8; box-shadow: 0 0 20px rgba(56, 189, 248, 0.8); }
    50% { outline: 3px solid #38bdf8; box-shadow: 0 0 30px rgba(56, 189, 248, 1); }
    100% { outline: 3px solid transparent; box-shadow: none; }
}
.card-notif-highlight {
    animation: cardNotifHighlight 2.5s ease-out;
}

/* Logout */
#topbar .nav-logout {
    font-size: 12px;
    font-weight: 700;
    color: #f87171;
    background: transparent;
    border: 1px solid #7f1d1d;
    padding: 4px 12px;
    border-radius: 6px;
    text-decoration: none;
    white-space: nowrap;
    transition: background 0.15s;
}
#topbar .nav-logout:hover {
    background: #3b0000;
}

/* Spacer below fixed topbar */
#header-spacer { height: 52px; }

/* Body background */
body { background: #081223; margin: 0; padding: 0; color: #e2e8f0; }

/* ── Card: header styling ────────────────────── */
.card {
    width: 100% !important;
    box-sizing: border-box !important;
}
.card .head {
    position: relative;
    background: #162033;
    border-radius: 8px 8px 0 0;
    cursor: pointer;
    width: 100% !important;
    box-sizing: border-box !important;
    display: flex !important;
    align-items: center !important;
}
.card .head .title {
    flex: 1 1 auto !important;
    max-width: none !important;
    box-sizing: border-box !important;
}
.card .head .card-meta {
    margin-left: auto !important;
    flex-shrink: 0 !important;
}
.card .head .toggle {
    flex-shrink: 0 !important;
}
.pid {
    width: 190px !important;
    min-width: 190px !important;
    background: #1d4ed8 !important; /* Normal Blue */
    color: #ffffff !important;
}
.pid, .pid div {
    font-family: 'Calibri', 'Segoe UI', Arial, sans-serif !important;
}
.pid.urgent-pid {
    background: #ef4444 !important; /* Urgent Red */
    color: #ffffff !important;
}

/* Accordion toggle handle - Normal is BLUE, Urgent is RED */
.card .head .toggle,
.toggle {
    background: #1d4ed8 !important; /* Normal Blue handle */
    color: #ffffff !important;
    border: none !important;
    border-left: 1px solid #1e40af !important;
    cursor: pointer;
    transition: background 0.15s ease;
}
.card .head .toggle:hover,
.toggle:hover {
    background: #2563eb !important;
}

/* Urgent card & toggle styling */
.card.urgent-card {
    border: 1px solid #ef4444 !important;
    animation: none !important;
    box-shadow: 0 0 10px rgba(239, 68, 68, 0.2) !important;
}
.card.urgent-card .head {
    background: #162033;
}
.card.urgent-card .head .toggle,
.card.urgent-card .toggle,
.toggle.urgent-toggle {
    background: #ef4444 !important; /* Urgent Red handle */
    color: #ffffff !important;
    border-left: 1px solid #dc2626 !important;
}
.card.urgent-card .head .toggle:hover,
.card.urgent-card .toggle:hover,
.toggle.urgent-toggle:hover {
    background: #dc2626 !important;
}
.card.urgent-card.open .head .toggle,
.card.urgent-card.open .toggle {
    background: #b91c1c !important;
    border-left-color: #991b1b !important;
}

/* Priority badges */
.badge-priority {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 11px;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 4px;
    white-space: nowrap;
    letter-spacing: 0.3px;
    line-height: 1.2;
}
.badge-priority.p1 {
    background: #b91c1c;
    border: 1px solid #ef4444;
    color: #ffffff;
    box-shadow: 0 0 8px rgba(239, 68, 68, 0.5);
}
.badge-priority.p2 {
    background: #c2410c;
    border: 1px solid #f97316;
    color: #ffffff;
    box-shadow: 0 0 8px rgba(249, 115, 22, 0.4);
}
.badge-priority.p3 {
    background: #b45309;
    border: 1px solid #f59e0b;
    color: #ffffff;
    box-shadow: 0 0 8px rgba(245, 158, 11, 0.4);
}
.badge-priority.p0 {
    background: #ef4444;
    border: 1px solid #dc2626;
    color: #ffffff;
}

.card.open {
    overflow: visible !important;
}
.card.open .head {
    position: sticky;
    top: 122px; /* 97px + 25px gap */
    z-index: 30;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2);
    background-color: #4A7DFF !important;
    transition: background-color 0.2s;
}
.card.open .head .toggle {
    background-color: #3b65d6 !important;
    border-left-color: #4A7DFF !important;
    color: #fff !important;
}

/* ── Card: close bar — mobile only ───────────── */
.card-close-bar {
    display: none; /* hidden on desktop */
}

/* ── Filter bar ──────────────────────────────── */
.filter-bar {
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 6px !important;
    padding: 8px 12px !important;
    background: #0a1628;
    border-bottom: 1px solid #1e3a5f;
    position: sticky;
    top: 52px;
    z-index: 40;
}
.filter-input,
.filter-select {
    min-width: 0;
    flex: 1 1 140px;
    padding: 7px 10px;
    background: #162033;
    border: 1px solid #1e3a5f;
    border-radius: 6px;
    color: #e2e8f0;
    font-size: 13px;
    outline: none;
}
#search, input#search {
    background: #ffffff !important;
    color: #000000 !important;
    font-weight: 600 !important;
}
#search::placeholder, input#search::placeholder {
    color: #64748b !important;
    opacity: 1 !important;
    font-weight: normal !important;
}

/* ── Bulk Action Bar Sticky ──────────────────── */
#bulk-action-bar {
    position: sticky !important;
    top: 104px;
    z-index: 38 !important;
    background: #1e293b !important;
    backdrop-filter: blur(12px) !important;
    -webkit-backdrop-filter: blur(12px) !important;
    box-shadow: 0 8px 24px -4px rgba(0, 0, 0, 0.6), 0 4px 8px -2px rgba(0, 0, 0, 0.4) !important;
}

/* ── Pager & Load More Button ───────────────── */
.load-more-btn:hover {
    filter: brightness(1.1);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(37, 99, 235, 0.5) !important;
}
.load-more-btn:active {
    transform: translateY(0);
}

/* ── Group Badge & Placeholder Slot ─────────── */
.badge-group-slot {
    width: 74px !important;
    min-width: 74px !important;
    max-width: 74px !important;
    height: 22px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-sizing: border-box !important;
    white-space: nowrap !important;
    font-size: 11.5px !important;
    font-weight: 700 !important;
    border-radius: 12px !important;
    cursor: pointer;
    flex-shrink: 0 !important;
}
.badge-group-placeholder {
    visibility: hidden !important;
    pointer-events: none !important;
    border: none !important;
    background: transparent !important;
    user-select: none !important;
}

/* ── Content editor — WHITE background ────────── */
.editor {
    background: #ffffff !important;
    color: #1a1a2e !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 6px;
    padding: 14px 16px !important;
    font-size: 14px !important;
    line-height: 1.7 !important;
    min-height: 80px;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.08);
}
.editor[contenteditable="true"] {
    border-color: #2563eb !important;
    background: #f8faff !important;
    box-shadow: 0 0 0 2px rgba(37,99,235,0.15) !important;
}
.editor.locked {
    background: #f1f5f9 !important;
    color: #334155 !important;
}

/* ── Multi-Box Content Grid UI (Infographics & A+ Banners) ── */
.content-grid-wrap {
    margin: 8px 0 12px 0;
    width: 100%;
    box-sizing: border-box;
}
.content-grid-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 14px;
    margin-bottom: 8px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .5px;
}
.content-grid-header.info-header {
    background: #082f49;
    color: #38bdf8;
    border: 1px solid #0284c7;
}
.content-grid-header.aplus-header {
    background: #1e1b4b;
    color: #c084fc;
    border: 1px solid #7c3aed;
}
.content-grid-header.locked-header {
    background: #0f172a;
    color: #94a3b8;
    border: 1px solid #334155;
}
.content-grid-6 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    width: 100%;
    box-sizing: border-box;
}
.content-grid-4 {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    width: 100%;
    box-sizing: border-box;
}
@media (max-width: 1100px) {
    .content-grid-6 {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 700px) {
    .content-grid-6, .content-grid-4 {
        grid-template-columns: 1fr;
    }
}
.content-card-box {
    border: 1.5px solid #2563eb;
    border-radius: 6px;
    overflow: hidden;
    background: #ffffff;
    display: flex;
    flex-direction: column;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: border-color .15s, box-shadow .15s;
}
.content-card-box:focus-within {
    border-color: #1d4ed8;
    box-shadow: 0 0 0 2px rgba(37,99,235,0.25);
}
.content-card-box.is-locked {
    border-color: #64748b;
}
.content-box-head {
    display: flex;
    align-items: stretch;
    height: 32px;
    min-height: 32px;
    border-bottom: 1.5px solid #2563eb;
    box-sizing: border-box;
}
.content-card-box.is-locked .content-box-head {
    border-bottom-color: #64748b;
}
.content-box-badge {
    background: #2563eb;
    color: #ffffff;
    font-size: 12px;
    font-weight: 700;
    padding: 0 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
    border-right: 1px solid #1d4ed8;
    user-select: none;
}
.content-card-box.is-locked .content-box-badge {
    background: #475569;
    border-right-color: #334155;
    color: #e2e8f0;
}
.content-box-title {
    background: #dbeafe;
    color: #1e3a8a;
    font-size: 11.5px;
    font-weight: 700;
    padding: 0 10px;
    display: flex;
    align-items: center;
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.content-card-box.is-locked .content-box-title {
    background: #e2e8f0;
    color: #334155;
}
.content-box-body {
    background: #ffffff;
    color: #0f172a;
    font-size: 13px;
    line-height: 1.6;
    padding: 12px 14px;
    min-height: 150px;
    max-height: 320px;
    overflow-y: auto;
    outline: none;
    white-space: pre-wrap;
    word-break: break-word;
    font-family: inherit;
    box-sizing: border-box;
    flex: 1;
}
.content-box-body[contenteditable="true"]:focus {
    background: #f8faff;
}
.content-box-body.locked, .content-box-body[contenteditable="false"] {
    background: #f8fafc;
    color: #334155;
    cursor: default;
}
.content-extra-bar {
    margin-top: 10px;
    background: #0f172a;
    border: 1px dashed #334155;
    border-radius: 6px;
    padding: 10px 14px;
}
.content-extra-title {
    font-size: 11px;
    font-weight: 700;
    color: #94a3b8;
    margin-bottom: 6px;
}
.content-extra-body {
    background: #ffffff;
    color: #0f172a;
    font-size: 12.5px;
    line-height: 1.5;
    padding: 8px 12px;
    border-radius: 4px;
    border: 1px solid #cbd5e1;
    min-height: 48px;
    outline: none;
    white-space: pre-wrap;
}


/* ── Toggle button bigger tap area ───────────── */
.card .toggle {
    min-width: 40px;
    min-height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* ── SEO Content Module ──────────────────────── */
.seo-wrap {
    border: 1px solid #1e3a5f;
    border-radius: 8px;
    overflow: hidden;
    margin-top: 14px;
    font-size: 13px;
}

/* Light header (A+ Banners, Backend Search Terms subhead) */
.seo-head-light {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #e8f0fe;
    color: #0f172a;
    font-weight: 800;
    font-size: 13px;
    letter-spacing: .5px;
    padding: 12px 18px;
    border-bottom: 1px solid #c7d7f9;
}

/* Dark header (Infographics) */
.seo-head-dark {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #0d1b2e;
    color: #e2e8f0;
    font-weight: 800;
    font-size: 13px;
    letter-spacing: .5px;
    padding: 12px 18px;
    border-bottom: 1px solid #1e3a5f;
}

/* Pure dark divider (Backend Search Terms label) */
.seo-divider-dark {
    background: #060d1a;
    color: #94a3b8;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .8px;
    padding: 8px 18px;
    border-bottom: 1px solid #0f2035;
}

/* Green save button */
.seo-save-green {
    background: #16a34a;
    color: #fff;
    border: none;
    padding: 6px 20px;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: .5px;
    cursor: pointer;
    transition: background .15s;
}
.seo-save-green:hover { background: #15803d; }

/* Banner 3-column grid */
.seo-banner-cols {
    display: grid;
    grid-template-columns: 1fr 2fr 1fr;
    background: #0d1b2e;
    border-bottom: 1px solid #1e3a5f;
}
.seo-col-head {
    padding: 10px 14px;
    color: #22d3ee;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .7px;
    text-align: center;
    border-right: 1px solid #1e3a5f;
}
.seo-col-head:last-child { border-right: none; }

.seo-banner-row {
    display: grid;
    grid-template-columns: 1fr 2fr 1fr;
    border-bottom: 1px solid #0f2035;
    align-items: center;
    background: #071428;
}
.seo-banner-row:last-child { border-bottom: none; }

.seo-banner-label {
    padding: 14px;
    color: #22d3ee;
    font-weight: 800;
    font-size: 13px;
    letter-spacing: .5px;
    text-align: center;
    border-right: 1px solid #1e3a5f;
}
.seo-banner-label:last-child { border-right: none; border-left: 1px solid #1e3a5f; }

.seo-banner-content {
    padding: 10px 14px;
    border-right: 1px solid #1e3a5f;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* Field groups inside banner content */
.seo-field-group { display: flex; flex-direction: column; gap: 3px; }
.seo-field-label {
    font-size: 10px;
    color: #64748b;
    font-weight: 600;
    letter-spacing: .3px;
    text-transform: capitalize;
}

/* Inputs */
.seo-inp {
    padding: 8px 11px;
    background: #0a1628;
    border: 1px solid #1e3a5f;
    border-radius: 4px;
    color: #e2e8f0;
    font-size: 13px;
    outline: none;
    box-sizing: border-box;
    transition: border-color .15s;
    width: 100%;
}
.seo-inp:focus  { border-color: #22d3ee; }
.seo-inp[readonly] { background: #060f1c; color: #475569; cursor: default; }
.seo-inp-full  { width: 100%; }
.seo-textarea  { resize: vertical; min-height: 90px; font-family: inherit; }

/* Infographics list */
.seo-info-list { background: #071428; }
.seo-info-row {
    display: flex;
    align-items: center;
    gap: 0;
    border-bottom: 1px solid #0f2035;
}
.seo-info-row:last-child { border-bottom: none; }
.seo-info-label {
    min-width: 80px;
    padding: 12px 14px;
    color: #22d3ee;
    font-weight: 800;
    font-size: 12px;
    letter-spacing: .5px;
    border-right: 1px solid #1e3a5f;
    background: #0d1b2e;
}
.seo-info-row .seo-inp { border-radius: 0; border: none; border-bottom: none; background: #071428; padding: 12px 14px; }
.seo-info-row .seo-inp:focus { background: #091830; border-bottom: 1px solid #22d3ee; }

/* Backend section padding */
.seo-wrap > div:last-of-type > .seo-textarea { border-radius: 0; }
.seo-byte-row { padding: 5px 14px 10px; background: #071428; }

/* Save message row */
.seo-msg-row {
    padding: 8px 18px;
    background: #071428;
    border-top: 1px solid #1e3a5f;
    min-height: 28px;
}

/* Mobile: hide right column */
@media (max-width: 640px) {
    .seo-banner-cols,
    .seo-banner-row { grid-template-columns: 80px 1fr; }
    .seo-banner-label:last-child,
    .seo-col-head:last-child  { display: none; }
}

/* ── Module Permissions Table ───────────────── */
.mp-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    min-width: 600px;
}
.mp-table th, .mp-table td {
    padding: 8px 10px;
    border: 1px solid #1e3a5f;
    text-align: center;
}
.mp-th-role {
    background: #0a1628;
    color: #93c5fd;
    font-weight: 700;
    text-align: left;
    min-width: 110px;
    font-size: 11px;
    letter-spacing: .4px;
}
.mp-th-module {
    background: #1e3a5f;
    color: #60a5fa;
    font-weight: 800;
    font-size: 11px;
    letter-spacing: .5px;
    text-transform: uppercase;
}
.mp-th-action {
    background: #0d1f38;
    color: #64748b;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .3px;
    padding: 5px 8px;
}
.mp-td-role {
    background: #0a1628;
    color: #e2e8f0;
    font-weight: 700;
    text-align: left;
    font-size: 12px;
    padding: 10px 12px;
}
.mp-td-cb {
    background: #071428;
    vertical-align: middle;
}
.mp-td-cb input[type="checkbox"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
    accent-color: #2563eb;
}
.mp-table tbody tr:hover .mp-td-cb {
    background: #0d1e35;
}
.mp-table tbody tr:hover .mp-td-role {
    color: #60a5fa;
}

/* ── Mobile breakpoint ───────────────────────── */
@media (max-width: 640px) {
    #topbar { padding: 0 8px; height: 50px; }
    #header-spacer { height: 50px; }
    .card .head   { top: 50px; }
    .filter-bar   { top: 50px; }

    #topbar .tab-btn  { font-size: 11px; padding: 5px 7px; }
    #topbar .nav-logo { font-size: 13px; padding-right: 8px; margin-right: 4px; }
    #topbar .nav-user { display: none; }

    .card { margin: 4px 6px; }

    /* Show close bar only on mobile */
    .card-close-bar {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 11px 12px;
        background: #1e3a5f;
        color: #93c5fd;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        border-bottom: 1px solid #2563eb;
        border-radius: 4px;
        margin-bottom: 12px;
        user-select: none;
        -webkit-user-select: none;
        letter-spacing: 0.5px;
    }
    .card-close-bar:active { background: #2563eb; color: #fff; }

    .editor { font-size: 13px !important; padding: 12px !important; }
}

/* ── Branding Logo ──────────────────────────────── */
#topbar .nav-logo-container {
    display: flex;
    align-items: center;
    gap: 8px;
    padding-right: 12px;
    margin-right: 8px;
    border-right: 1px solid #1e3a5f;
    flex-shrink: 0;
}
#topbar .nav-logo-img {
    height: 28px;
    width: 28px;
    border-radius: 6px;
    object-fit: contain;
}
#topbar .nav-logo {
    font-size: 15px;
    font-weight: 900;
    letter-spacing: 1px;
    background: linear-gradient(90deg, #22d3ee, #3b82f6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    white-space: nowrap;
    border-right: none !important;
    padding-right: 0 !important;
    margin-right: 0 !important;
}

/* ── Theme Toggler Button ───────────────────────── */
#topbar .nav-theme-toggle {
    font-size: 12px;
    font-weight: 700;
    color: #60a5fa;
    background: transparent;
    border: 1px solid #1e3a5f;
    padding: 4px 12px;
    border-radius: 6px;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.15s, border-color 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    outline: none;
}
#topbar .nav-theme-toggle:hover {
    background: #162033;
    border-color: #3b82f6;
}

/* ── Light Theme Overrides ──────────────────────── */
html.light-theme, html.light-theme body {
    background: #f1f5f9 !important;
    color: #1e293b !important;
}

/* Overriding inline backgrounds */
html.light-theme div[style*="background: #0f2035"],
html.light-theme div[style*="background:#0f2035"],
html.light-theme div[style*="background: #162033"],
html.light-theme div[style*="background:#162033"],
html.light-theme div[style*="background: linear-gradient(135deg, #0d1b2e 0%, #162033 100%)"],
html.light-theme div[style*="background: linear-gradient(135deg, rgb(13, 27, 46) 0%, rgb(22, 32, 51) 100%)"],
html.light-theme div[style*="background:#111827"],
html.light-theme div[style*="background: #111827"] {
    background: #ffffff !important;
    border-color: #cbd5e1 !important;
    color: #1e293b !important;
}

html.light-theme div[style*="background: #0a1628"],
html.light-theme div[style*="background:#0a1628"],
html.light-theme div[style*="background: #071428"],
html.light-theme div[style*="background:#071428"] {
    background: #f8fafc !important;
    border-color: #cbd5e1 !important;
    color: #1e293b !important;
}

/* Inputs, selects, textareas */
html.light-theme input,
html.light-theme select,
html.light-theme textarea,
html.light-theme input[style*="background:#0a1628"],
html.light-theme select[style*="background:#0a1628"],
html.light-theme select[style*="background: #0a1628"],
html.light-theme input[style*="background: #0a1628"] {
    background: #ffffff !important;
    color: #1e293b !important;
    border-color: #cbd5e1 !important;
}

html.light-theme input::placeholder {
    color: #94a3b8 !important;
}

/* Borders */
html.light-theme div[style*="border-color:#1e3a5f"],
html.light-theme div[style*="border-color: #1e3a5f"],
html.light-theme div[style*="border: 1px solid #1e3a5f"],
html.light-theme div[style*="border:1px solid #1e3a5f"] {
    border-color: #cbd5e1 !important;
}

/* Topbar */
html.light-theme #topbar {
    background: #ffffff !important;
    border-bottom: 1px solid #cbd5e1 !important;
    box-shadow: 0 2px 12px rgba(0,0,0,0.05) !important;
}
html.light-theme #topbar .nav-logo-container {
    border-right-color: #cbd5e1 !important;
}
html.light-theme #topbar .nav-user {
    background: #f1f5f9 !important;
    border-color: #cbd5e1 !important;
    color: #2563eb !important;
}
html.light-theme #topbar .tab-btn {
    color: #475569 !important;
}
html.light-theme #topbar .tab-btn:hover {
    background: #f1f5f9 !important;
    color: #2563eb !important;
}
html.light-theme #topbar .tab-btn.active {
    background: #e2e8f0 !important;
    color: #2563eb !important;
    border-bottom-color: #2563eb !important;
}
html.light-theme #topbar .nav-theme-toggle {
    color: #2563eb !important;
    border-color: #cbd5e1 !important;
}
html.light-theme #topbar .nav-theme-toggle:hover {
    background: #f1f5f9 !important;
    border-color: #2563eb !important;
}

/* Text colors */
html.light-theme span[style*="color:#cbd5e1"],
html.light-theme span[style*="color: #cbd5e1"],
html.light-theme td[style*="color: #cbd5e1"],
html.light-theme td[style*="color:#cbd5e1"],
html.light-theme div[style*="color:#f1f5f9"],
html.light-theme div[style*="color: #f1f5f9"],
html.light-theme div[style*="color:#e2e8f0"],
html.light-theme div[style*="color: #e2e8f0"],
html.light-theme div[style*="color: #cbd5e1"],
html.light-theme div[style*="color:#cbd5e1"] {
    color: #1e293b !important;
}
html.light-theme td[style*="color: #e2e8f0"],
html.light-theme td[style*="color:#e2e8f0"] {
    color: #0f172a !important;
}
html.light-theme span[style*="color:#93c5fd"],
html.light-theme span[style*="color: #93c5fd"],
html.light-theme div[style*="color:#93c5fd"],
html.light-theme div[style*="color: #93c5fd"],
html.light-theme h2[style*="color: #e2e8f0"],
html.light-theme h2[style*="color:#e2e8f0"],
html.light-theme p[style*="color: #94a3b8"],
html.light-theme p[style*="color:#94a3b8"] {
    color: #1e293b !important;
}
html.light-theme div[style*="color:#60a5fa"],
html.light-theme div[style*="color: #60a5fa"] {
    color: #2563eb !important;
}

/* Tables */
html.light-theme .inv-table th,
html.light-theme .mp-table th {
    background: #e2e8f0 !important;
    color: #1e293b !important;
    border-bottom-color: #cbd5e1 !important;
}
html.light-theme .inv-table td,
html.light-theme .mp-table td {
    color: #1e293b !important;
    border-bottom-color: #cbd5e1 !important;
}
html.light-theme .inv-table tr,
html.light-theme .mp-table tr {
    border-bottom-color: #cbd5e1 !important;
}
html.light-theme .inv-table tr:hover,
html.light-theme .mp-table tr:hover {
    background: #f8fafc !important;
}
html.light-theme div[onclick="toggleLogsAccordion()"] {
    background: #f1f5f9 !important;
    border-bottom: 1px solid #cbd5e1 !important;
}
html.light-theme #logs-accordion-content {
    background: #ffffff !important;
    border-top: 1px solid #cbd5e1 !important;
}
html.light-theme select {
    background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236B7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3E%3C/svg%3E") !important;
    background-position: right 0.5rem center !important;
    background-repeat: no-repeat !important;
    background-size: 1.5em 1.5em !important;
    padding-right: 2.5rem !important;
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    appearance: none !important;
}

html.light-theme .card {
    background: #ffffff !important;
    border-color: #cbd5e1 !important;
    border-bottom: 6px solid #2563eb !important;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06) !important;
}
html.light-theme .card .head {
    background: #ffffff !important;
}
html.light-theme .card.open .head {
    background: #2563eb !important;
    color: #ffffff !important;
}
html.light-theme .card.open .head .title-text {
    color: #ffffff !important;
}
html.light-theme .card.open .head .toggle {
    background: #1d4ed8 !important;
    color: #ffffff !important;
    border-left-color: #3b82f6 !important;
}
html.light-theme .card .head .title {
    color: #0f172a !important;
}
html.light-theme .card .head .title-text {
    color: #0f172a !important;
}
html.light-theme .card .head .toggle {
    background: #f1f5f9 !important;
    border-left-color: #cbd5e1 !important;
    color: #475569 !important;
}
html.light-theme .card.urgent-card .toggle,
html.light-theme .toggle.urgent-toggle {
    background: #ef4444 !important;
    color: #ffffff !important;
    border-left-color: #dc2626 !important;
}
html.light-theme .filter-bar {
    background: #ffffff !important;
    border-bottom-color: #cbd5e1 !important;
}
html.light-theme .filter-input,
html.light-theme .filter-select {
    background: #f8fafc !important;
    border-color: #cbd5e1 !important;
    color: #0f172a !important;
}
html.light-theme .filter-select option {
    background: #ffffff !important;
    color: #0f172a !important;
}
html.light-theme .body {
    background: #f8fafc !important;
    color: #1e293b !important;
}
html.light-theme .time-elapsed-badge {
    background: rgba(239, 68, 68, 0.12) !important;
    border-color: #f87171 !important;
    color: #dc2626 !important;
}
html.light-theme .nav-theme-toggle {
    background: #f1f5f9 !important;
    color: #2563eb !important;
    border-color: #cbd5e1 !important;
}
html.light-theme #bulk-action-bar {
    background: #ffffff !important;
    border-color: #cbd5e1 !important;
    box-shadow: 0 8px 24px -4px rgba(0, 0, 0, 0.12), 0 4px 8px -2px rgba(0, 0, 0, 0.06) !important;
}
html.light-theme #bulk-action-bar span,
html.light-theme #bulk-action-bar label {
    color: #1e293b !important;
}
html.light-theme #bulk-action-select {
    background: #f8fafc !important;
    border-color: #cbd5e1 !important;
    color: #0f172a !important;
}
html.light-theme .pager-container {
    background: #ffffff !important;
    border-color: #cbd5e1 !important;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06) !important;
}
html.light-theme .pager-container strong {
    color: #0f172a !important;
}
html.light-theme #pageSizeSelect {
    background: #f8fafc !important;
    border-color: #cbd5e1 !important;
    color: #0f172a !important;
}
html.light-theme .load-more-btn {
    background: linear-gradient(135deg, #2563eb, #6366f1) !important;
    color: #ffffff !important;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25) !important;
}
html.light-theme .badge-group-slot:not(.badge-group-placeholder) {
    background: #f3e8ff !important;
    border-color: #c084fc !important;
    color: #6b21a8 !important;
}
</style>
</head>
<body>

<!-- ── PHP → JS bridge ── -->
<script>
var ROLE       = <?= json_encode($user['role'])    ?>;
var USER_ID    = <?= json_encode((int)$user['id']) ?>;
var USERNAME   = <?= json_encode($user['username']) ?>;
var APP_URL    = <?= json_encode(APP_URL)           ?>;
var PUBLIC_URL = <?= json_encode(PUBLIC_URL)        ?>;
var CSRF_TOKEN = <?= json_encode(csrf_token())      ?>;
/* Module permissions for current user (admin = all true) */
<?php
$currentUserPerms = ($user['role'] === 'administrator')
    ? array_fill_keys(array_keys(ModulePermission::MODULES),
          array_fill_keys(['view','add','edit','delete','rename','group'], true))
    : ModulePermission::getForUser($user);
$canUserRenameProduct = ($user['role'] === 'administrator') || !empty($currentUserPerms['products']['rename']);
$canUserGroupProduct = ($user['role'] === 'administrator') || !empty($currentUserPerms['products']['group']);
?>
var MODULE_PERMS = <?= json_encode($currentUserPerms) ?>;
var CAN_RENAME_PRODUCT = <?= $canUserRenameProduct ? 'true' : 'false' ?>;
var CAN_GROUP_PRODUCT = <?= $canUserGroupProduct ? 'true' : 'false' ?>;
<?php
$role_bulk_action = false;
if(current_user()){
    $u = current_user();
    $uname = strtolower($u['username'] ?? '');
    if($u['role'] === 'administrator' || $uname === 'ilyaeco' || $uname === 'irfan' || $u['role'] === 'ai_work' || $u['role'] === 'eco_listing' || $uname === 'ecolisting' || !empty($canUserGroupProduct)){
        $role_bulk_action = true;
    } else {
        $settings = null;
        try {
            $uperm_stmt = db()->prepare("SELECT settings FROM eco_user_permissions WHERE user_id=?");
            $uperm_stmt->execute([(int)$u['id']]);
            $settings = $uperm_stmt->fetchColumn();
        } catch(Exception $e){}
        if ($settings === false || $settings === null) {
            $perm_row = db()->prepare("SELECT settings FROM eco_permissions WHERE role=?");
            $perm_row->execute([$u['role']]);
            $settings = $perm_row->fetchColumn();
        }
        $perm_settings = json_decode($settings ?: '{}', true);
        $role_bulk_action = !empty($perm_settings['bulk_action']);
    }
}
?>
var HAS_BULK_ACTION = <?= $role_bulk_action ? 'true' : 'false' ?>;
<?php
$allVendors = Vendor::getAll();
$defaultVendor = Vendor::getDefault();
?>
var ALL_VENDORS = <?= json_encode($allVendors) ?>;
var DEFAULT_VENDOR_ID = <?= json_encode($defaultVendor ? (int)$defaultVendor['id'] : null) ?>;
var ACTIVE_VENDOR_ID = localStorage.getItem('d4u_active_vendor_id') || 'all';
</script>

<?php
/* ── Notification Count & Items (Server-Side Initial Calculation) ── */
$notif_count = 0;
$notif_subtitle = 'Notifications';
$notif_items = [];
try {
    $uid = (int)$user['id'];
    $urole = $user['role'];
    $uname = strtolower($user['username']);
    
    if ($urole === 'worker' || $urole === 'ai_work') {
        $notif_subtitle = 'Products assigned to you';
        $n_stmt = db()->prepare("
            SELECT t.id, t.product_no, t.title, t.work_status, t.product_type, t.is_urgent
            FROM wp_eco_aplus_tasks t
            LEFT JOIN eco_tool_assignments a ON a.task_id = t.id
            WHERE t.deleted_at IS NULL AND t.status != 'Hold'
              AND t.work_status NOT IN ('Work Done', 'Info Done')
              AND (a.worker_id = ? OR t.info_worker_id = ? OR t.aplus_worker_id = ? OR t.ai_worked_by = ?)
            GROUP BY t.id
            ORDER BY t.is_urgent DESC, t.id DESC
            LIMIT 50
        ");
        $n_stmt->execute([$uid, $uid, $uid, $uid]);
        $notif_items = $n_stmt->fetchAll(PDO::FETCH_ASSOC);
        $notif_count = count($notif_items);
    } elseif ($urole === 'eco_listing' || $uname === 'ecolisting') {
        $notif_subtitle = 'Completed products to list (Info Done / Work Done)';
        $n_stmt = db()->query("
            SELECT t.id, t.product_no, t.title, t.work_status, t.product_type, t.is_urgent
            FROM wp_eco_aplus_tasks t
            WHERE t.deleted_at IS NULL AND t.status != 'Hold'
              AND t.work_status IN ('Work Done', 'Info Done')
              AND t.published_at IS NULL
            ORDER BY t.is_urgent DESC, t.id DESC
            LIMIT 50
        ");
        $notif_items = $n_stmt->fetchAll(PDO::FETCH_ASSOC);
        $notif_count = count($notif_items);
    } elseif ($urole === 'qa') {
        $notif_subtitle = 'Products currently in QA review';
        $n_stmt = db()->query("
            SELECT t.id, t.product_no, t.title, t.work_status, t.product_type, t.is_urgent
            FROM wp_eco_aplus_tasks t
            WHERE t.deleted_at IS NULL AND t.status != 'Hold'
              AND t.work_status = 'In QA'
            ORDER BY t.is_urgent DESC, t.id DESC
            LIMIT 50
        ");
        $notif_items = $n_stmt->fetchAll(PDO::FETCH_ASSOC);
        $notif_count = count($notif_items);
    } elseif ($urole === 'seo_manager' || $urole === 'd4u_writer') {
        $notif_subtitle = 'Products pending content writing & SEO review';
        $n_stmt = db()->query("
            SELECT t.id, t.product_no, t.title, t.work_status, t.product_type, t.is_urgent
            FROM wp_eco_aplus_tasks t
            WHERE t.deleted_at IS NULL AND t.status != 'Hold'
              AND (t.work_status = 'SEO Review' OR (t.status = 'Pending' AND t.product_type != 'Infographics'))
            ORDER BY t.is_urgent DESC, t.id DESC
            LIMIT 50
        ");
        $notif_items = $n_stmt->fetchAll(PDO::FETCH_ASSOC);
        $notif_count = count($notif_items);
    } elseif ($urole === 'eco_client') {
        $notif_subtitle = 'Products in Generated awaiting review';
        $n_stmt = db()->query("
            SELECT t.id, t.product_no, t.title, t.work_status, t.product_type, t.is_urgent
            FROM wp_eco_aplus_tasks t
            WHERE t.deleted_at IS NULL AND t.status != 'Hold'
              AND (t.status = 'Generated' OR t.status = 'All Generated') AND t.work_status NOT IN ('Work Done', 'Info Done')
            ORDER BY t.is_urgent DESC, t.id DESC
            LIMIT 50
        ");
        $notif_items = $n_stmt->fetchAll(PDO::FETCH_ASSOC);
        $notif_count = count($notif_items);
    } elseif ($urole === 'administrator') {
        $notif_subtitle = 'Products requiring QA or ready to publish';
        $n_stmt = db()->query("
            SELECT t.id, t.product_no, t.title, t.work_status, t.product_type, t.is_urgent
            FROM wp_eco_aplus_tasks t
            WHERE t.deleted_at IS NULL AND t.status != 'Hold'
              AND (t.work_status = 'In QA' OR (t.work_status IN ('Work Done', 'Info Done') AND t.published_at IS NULL))
            ORDER BY t.is_urgent DESC, t.id DESC
            LIMIT 50
        ");
        $notif_items = $n_stmt->fetchAll(PDO::FETCH_ASSOC);
        $notif_count = count($notif_items);
    }
} catch(Exception $e) {
    $notif_count = 0;
    $notif_items = [];
}
?>

<!-- ══════════════════════════════════════════════
     TOP NAVIGATION BAR
     ══════════════════════════════════════════════ -->
<div id="topbar">

    <!-- Left: Logo + Tabs -->
    <div class="nav-left">
        <div class="nav-logo-container">
            <img src="<?= $publicUrl ?>/assets/icon-192.png" alt="D4U FLOW Logo" class="nav-logo-img">
            <span class="nav-logo">D4U FLOW</span>
        </div>

        <?php 
        $is_admin = ($role === 'administrator');
        $is_ilyaeco = ($user['username'] === 'ilyaeco');
        ?>
        <?php if($is_admin): ?>
            <button class="tab-btn active" id="tab-products"  onclick="switchTab('products')">📦 Products</button>
            <button class="tab-btn"        id="tab-admin"     onclick="switchTab('admin')">👥 Users</button>
            <button class="tab-btn"        id="tab-analytics" onclick="switchTab('analytics')">📊 Dashboard</button>
            <button class="tab-btn"        id="tab-recycleBin" onclick="switchTab('recycleBin')">🗑️ Recycle Bin</button>
            <button class="tab-btn"        id="tab-invoices"  onclick="switchTab('invoices')">🧾 Invoices</button>
            <button class="tab-btn"        id="tab-payroll"   onclick="switchTab('payroll')">💰 Payroll</button>

        <?php elseif($is_ilyaeco): ?>
            <button class="tab-btn active" id="tab-products"  onclick="switchTab('products')">📦 Products</button>
            <button class="tab-btn"        id="tab-analytics" onclick="switchTab('analytics')">📊 Dashboard</button>

        <?php elseif(in_array($role, ['worker','qa','d4u_writer','seo_manager','ai_work'])): ?>
            <button class="tab-btn active" id="tab-products" onclick="switchTab('products')">📦 Products</button>
            <button class="tab-btn"        id="tab-analytics" onclick="switchTab('analytics')">📊 Dashboard</button>
            <button class="tab-btn"        id="tab-payroll" onclick="switchTab('payroll')">💰 Payroll</button>

        <?php elseif($role === 'eco_client'): ?>
            <button class="tab-btn active" id="tab-products" onclick="switchTab('products')">📦 Products</button>
            <button class="tab-btn"        id="tab-analytics" onclick="switchTab('analytics')">📊 Dashboard</button>
            <button class="tab-btn"        id="tab-invoices" onclick="switchTab('invoices')">🧾 Invoices</button>

        <?php else: ?>
            <button class="tab-btn active" id="tab-products" onclick="switchTab('products')">📦 Products</button>
            <button class="tab-btn"        id="tab-analytics" onclick="switchTab('analytics')">📊 Dashboard</button>
        <?php endif; ?>

        <!-- Vendor Browser Tabs Bar -->
        <div class="vendor-nav-divider"></div>
        <div id="vendor-browser-tabs-bar" class="vendor-browser-tabs-container"></div>
    </div>

    <!-- Right: Notifications + Username + Logout -->
    <div class="nav-right">
        <!-- Notification Area -->
        <div class="nav-notif-container" id="nav-notif-container">
            <button type="button" class="nav-notif-btn" id="nav-notif-btn" onclick="toggleNotifDropdown(event)" title="Notifications" aria-label="Notifications">
                <svg class="notif-bell-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <span class="notif-badge" id="notif-badge" style="display: <?= $notif_count > 0 ? 'inline-flex' : 'none' ?>;"><?= $notif_count ?></span>
            </button>
            <div class="notif-dropdown" id="notif-dropdown" style="display:none;" onclick="event.stopPropagation()">
                <div class="notif-header">
                    <div class="notif-header-left">
                        <span class="notif-title">🔔 Notifications</span>
                        <span class="notif-count-pill" id="notif-count-pill"><?= $notif_count ?></span>
                    </div>
                    <button type="button" class="notif-close-btn" onclick="closeNotifDropdown(event)" title="Close">✕</button>
                </div>
                <div class="notif-subtitle" id="notif-subtitle"><?= htmlspecialchars($notif_subtitle) ?></div>
                <div class="notif-list" id="notif-list">
                    <?php if(empty($notif_items)): ?>
                        <div class="notif-empty">✨ No pending notifications</div>
                    <?php else: ?>
                        <?php foreach($notif_items as $item): 
                            $statusClass = 'pending';
                            if ($item['work_status'] === 'Work Done') $statusClass = 'work-done';
                            elseif ($item['work_status'] === 'Info Done') $statusClass = 'info-done';
                            elseif ($item['work_status'] === 'In QA') $statusClass = 'in-qa';
                            elseif ($item['work_status'] === 'Working' || $item['work_status'] === 'Info Working') $statusClass = 'working';
                        ?>
                            <div class="notif-item" onclick="openNotifTask(<?= (int)$item['id'] ?>, '<?= htmlspecialchars(addslashes($item['product_no'])) ?>')">
                                <div class="notif-item-header">
                                    <span class="notif-item-prodno"><?= htmlspecialchars($item['product_no']) ?></span>
                                    <span class="notif-item-tag <?= $statusClass ?>"><?= htmlspecialchars($item['work_status'] ?: 'Pending') ?></span>
                                </div>
                                <div class="notif-item-title"><?= htmlspecialchars($item['title'] ?: 'Untitled Product') ?></div>
                                <?php if(!empty($item['product_type'])): ?>
                                    <div class="notif-item-meta">📦 <?= htmlspecialchars($item['product_type']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="notif-footer" id="notif-footer">
                    <?php if ($urole === 'eco_listing' || $uname === 'ecolisting'): ?>
                        <button type="button" class="notif-action-btn" onclick="filterCompletedTasks()">📦 View Completed Products</button>
                    <?php elseif ($urole === 'worker' || $urole === 'ai_work'): ?>
                        <button type="button" class="notif-action-btn" onclick="filterMyTasks()">📋 View My Assigned Tasks</button>
                    <?php else: ?>
                        <button type="button" class="notif-action-btn" onclick="switchTab('products'); closeNotifDropdown();">📦 Go to Products</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <span class="nav-user">👤 <?= htmlspecialchars($user['username']) ?></span>
        <button id="theme-toggle-btn" class="nav-theme-toggle"></button>
        <a href="index.php?action=logout" class="nav-logout">
            <span class="logout-text">Logout</span>
            <span class="material-symbols-outlined logout-icon" style="display:none;">logout</span>
        </a>
    </div>

</div>

<!-- Spacer -->
<div id="header-spacer"></div>

<!-- ── Dashboard view (role-specific) ── -->
<?php require_once ROOT . '/views/dashboard/' . $dashboardView . '.php'; ?>

<!-- ── PWA Install banner ── -->
<div id="install-banner" style="display:none;position:fixed;bottom:20px;left:20px;right:20px;background:#1d4ed8;color:#fff;padding:15px 20px;border-radius:12px;align-items:center;justify-content:space-between;z-index:9999;box-shadow:0 10px 30px rgba(0,0,0,.4);gap:15px;">
    <div><strong>📱 Install ECO A+ App</strong><br><small>Add to home screen</small></div>
    <div style="display:flex;gap:10px;">
        <button onclick="installApp()" style="background:#fff;color:#1d4ed8;border:none;padding:8px 16px;border-radius:8px;font-weight:bold;cursor:pointer;">Install</button>
        <button onclick="document.getElementById('install-banner').style.display='none'" style="background:transparent;color:#fff;border:1px solid rgba(255,255,255,.4);padding:8px 12px;border-radius:8px;cursor:pointer;">✕</button>
    </div>
</div>

<!-- ── Invoice preview modal ── -->
<div class="inv-preview-modal" id="invPreviewModal">
    <div class="inv-preview-inner">
        <button class="inv-preview-close" onclick="closeInvPreview()">✕</button>
        <div id="inv-preview-content"></div>
    </div>
</div>

<!-- ── Share invoice modal ── -->
<div id="inv-share-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#1e293b;border-radius:12px;padding:28px;width:90%;max-width:520px;position:relative;">
        <button onclick="document.getElementById('inv-share-modal').style.display='none';" style="position:absolute;top:12px;right:14px;background:none;border:none;color:#94a3b8;font-size:20px;cursor:pointer;">✕</button>
        <h3 style="margin:0 0 16px;color:#f1f5f9;font-size:16px;">🔗 Share Invoice</h3>
        <p style="color:#94a3b8;font-size:12px;margin:0 0 10px;">Copy the link below or send directly via WhatsApp.</p>
        <div style="display:flex;gap:8px;margin-bottom:16px;">
            <input id="inv-share-link" type="text" readonly style="flex:1;background:#0f172a;border:1px solid #334155;border-radius:6px;padding:8px 10px;color:#e2e8f0;font-size:12px;">
            <button id="inv-copy-btn" onclick="copyShareLink()" style="background:#1e40af;color:#fff;border:none;border-radius:6px;padding:8px 14px;cursor:pointer;font-size:12px;white-space:nowrap;">📋 Copy</button>
        </div>
        <a id="inv-share-wa" href="#" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:8px;background:#16a34a;color:#fff;text-decoration:none;border-radius:8px;padding:10px 18px;font-size:13px;font-weight:600;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="#fff"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            Share via WhatsApp
        </a>
    </div>
</div>

<!-- ── Vendor Management Modal (Admin / authorized users) ── -->
<div id="vendorManagementModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.78);z-index:99999;align-items:center;justify-content:center;backdrop-filter:blur(3px);">
    <div style="background:#0f172a;border:1px solid #1e3a5f;border-radius:12px;width:95%;max-width:540px;padding:24px;box-shadow:0 25px 35px -5px rgba(0,0,0,0.6);color:#f1f5f9;position:relative;max-height:90vh;overflow-y:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid #1e3a5f;padding-bottom:12px;">
            <div style="font-size:16px;font-weight:700;color:#38bdf8;display:flex;align-items:center;gap:8px;">
                <span>🏢 Vendor Management</span>
            </div>
            <button onclick="closeVendorModal()" style="background:transparent;border:none;color:#94a3b8;font-size:24px;cursor:pointer;line-height:1;" title="Close">&times;</button>
        </div>

        <!-- Add New Vendor Section -->
        <div style="background:#09111e;border:1px solid #1e3a5f;border-radius:8px;padding:14px;margin-bottom:18px;">
            <div style="font-size:12px;font-weight:700;color:#93c5fd;margin-bottom:10px;text-transform:uppercase;letter-spacing:0.5px;">➕ Add New Vendor</div>
            <div style="display:flex;gap:10px;margin-bottom:10px;flex-wrap:wrap;">
                <input type="text" id="vm-new-name" placeholder="Vendor Name (e.g. Vendor B) *" style="flex:2;min-width:160px;background:#0a1628;border:1px solid #334155;border-radius:6px;color:#f8fafc;padding:8px 12px;font-size:13px;outline:none;">
                <input type="text" id="vm-new-code" placeholder="Code (e.g. VB)" style="flex:1;min-width:90px;background:#0a1628;border:1px solid #334155;border-radius:6px;color:#f8fafc;padding:8px 12px;font-size:13px;outline:none;">
            </div>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;flex-wrap:wrap;">
                <span style="font-size:12px;color:#94a3b8;font-weight:600;">Tab Badge Color:</span>
                <input type="color" id="vm-new-color" value="#0284c7" style="background:transparent;border:none;width:32px;height:32px;cursor:pointer;border-radius:4px;">
                <div style="display:flex;gap:6px;">
                    <span onclick="document.getElementById('vm-new-color').value='#0284c7'" style="width:18px;height:18px;border-radius:50%;background:#0284c7;cursor:pointer;display:inline-block;" title="Blue"></span>
                    <span onclick="document.getElementById('vm-new-color').value='#8b5cf6'" style="width:18px;height:18px;border-radius:50%;background:#8b5cf6;cursor:pointer;display:inline-block;" title="Purple"></span>
                    <span onclick="document.getElementById('vm-new-color').value='#10b981'" style="width:18px;height:18px;border-radius:50%;background:#10b981;cursor:pointer;display:inline-block;" title="Green"></span>
                    <span onclick="document.getElementById('vm-new-color').value='#f59e0b'" style="width:18px;height:18px;border-radius:50%;background:#f59e0b;cursor:pointer;display:inline-block;" title="Amber"></span>
                    <span onclick="document.getElementById('vm-new-color').value='#ec4899'" style="width:18px;height:18px;border-radius:50%;background:#ec4899;cursor:pointer;display:inline-block;" title="Pink"></span>
                    <span onclick="document.getElementById('vm-new-color').value='#06b6d4'" style="width:18px;height:18px;border-radius:50%;background:#06b6d4;cursor:pointer;display:inline-block;" title="Cyan"></span>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;">
                <button type="button" onclick="saveNewVendor()" style="background:#2563eb;color:#fff;border:none;padding:7px 16px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                    <span>✅ Create Vendor</span>
                </button>
            </div>
        </div>

        <!-- Vendor List Section -->
        <div>
            <div style="font-size:12px;font-weight:700;color:#94a3b8;margin-bottom:10px;text-transform:uppercase;letter-spacing:0.5px;">Existing Vendors</div>
            <div id="vm-vendor-list" style="display:flex;flex-direction:column;gap:8px;">
                <!-- Populated dynamically by JS -->
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;margin-top:20px;border-top:1px solid #1e3a5f;padding-top:14px;">
            <button type="button" onclick="closeVendorModal()" style="background:#334155;color:#cbd5e1;border:none;padding:8px 18px;border-radius:6px;font-size:13px;cursor:pointer;font-weight:600;">Close</button>
        </div>
    </div>
</div>

<!-- ── JS Modules ── -->
<?php $v = '2.8.9'; ?>
<script src="<?= $publicUrl ?>/js/core.js?v=<?= $v ?>"></script>
<script src="<?= $publicUrl ?>/js/tasks.js?v=3.3.8"></script>
<script src="<?= $publicUrl ?>/js/admin.js?v=<?= $v ?>"></script>
<script src="<?= $publicUrl ?>/js/invoices.js?v=<?= $v ?>"></script>
<script src="<?= $publicUrl ?>/js/payroll.js?v=2.7.13"></script>
<script src="<?= $publicUrl ?>/js/seo.js?v=<?= $v ?>"></script>
<script src="<?= $publicUrl ?>/js/pwa.js?v=<?= $v ?>"></script>

<script>
(function() {
    // Theme toggle logic (syncs both topbar and filter bar buttons)
    window.updateAllThemeButtons = function() {
        var isLight = document.documentElement.classList.contains('light-theme');
        var text = isLight ? '🌙 Dark Theme' : '☀️ Light Theme';
        var topBtn = document.getElementById('theme-toggle-btn');
        if (topBtn) topBtn.innerHTML = text;
        var fltBtn = document.getElementById('filter-theme-btn');
        if (fltBtn) fltBtn.innerHTML = text;
    };

    window.toggleTheme = function() {
        var isLight = document.documentElement.classList.contains('light-theme');
        if (isLight) {
            document.documentElement.classList.remove('light-theme');
            localStorage.setItem('theme', 'dark');
        } else {
            document.documentElement.classList.add('light-theme');
            localStorage.setItem('theme', 'light');
        }
        window.updateAllThemeButtons();
    };

    var topBtn = document.getElementById('theme-toggle-btn');
    if (topBtn) {
        topBtn.addEventListener('click', window.toggleTheme);
    }
    window.updateAllThemeButtons();

    // Silent heartbeat ping every 30 seconds
    setInterval(function() {
        if (typeof USER_ID !== 'undefined' && USER_ID) {
            var body = 'csrf_token=' + encodeURIComponent(CSRF_TOKEN);
            fetch('index.php?action=refresh_session', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            }).catch(function(err) {});
        }
    }, 30000);

    // Sync mobile navigation bar tabs dynamically
    var originalSwitchTab = window.switchTab;
    window.switchTab = function(name) {
        if (typeof originalSwitchTab === 'function') {
            originalSwitchTab(name);
        }
        // Update active class on mobile tab buttons
        document.querySelectorAll('.mob-tab-btn').forEach(function(btn) {
            btn.classList.remove('active', 'bg-[#4d8eff]', 'text-[#00285d]');
            btn.classList.add('text-[#c2c6d6]', 'hover:bg-[#2d3449]/30');
            var icon = btn.querySelector('.material-symbols-outlined');
            if (icon) {
                icon.style.fontVariationSettings = "'FILL' 0";
            }
        });
        var activeBtn = document.getElementById('mob-tab-' + name);
        if (activeBtn) {
            activeBtn.classList.add('active', 'bg-[#4d8eff]', 'text-[#00285d]');
            activeBtn.classList.remove('text-[#c2c6d6]', 'hover:bg-[#2d3449]/30');
            var icon = activeBtn.querySelector('.material-symbols-outlined');
            if (icon) {
                icon.style.fontVariationSettings = "'FILL' 1";
            }
        }
    };
    
    // Trigger initial tab active state for mobile
    var currentActiveTab = 'products';
    var activeTabBtn = document.querySelector('.tab-btn.active');
    if (activeTabBtn) {
        currentActiveTab = activeTabBtn.id.replace('tab-', '');
    }
    setTimeout(function() {
        window.switchTab(currentActiveTab);
    }, 100);
})();
</script>

<!-- ── Bottom Navigation Bar (Mobile Only) ── -->
<div id="mobile-bottom-nav" class="fixed bottom-0 left-0 right-0 z-50 bg-[#0b1326]/95 backdrop-blur-md border-t border-[#424754] flex justify-around items-center h-16 pb-safe sm:hidden px-2">
    <!-- Products -->
    <div class="mob-tab-btn active flex flex-col items-center justify-center bg-[#4d8eff] text-[#00285d] rounded-full px-4 py-1 cursor-pointer transition-all duration-200" onclick="switchTab('products')" id="mob-tab-products">
        <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">inventory_2</span>
        <span class="text-[9px] font-semibold mt-0.5">Products</span>
    </div>

    <!-- Users (Admin only) -->
    <?php if($is_admin): ?>
    <div class="mob-tab-btn flex flex-col items-center justify-center text-[#c2c6d6] hover:bg-[#2d3449]/30 rounded-full px-4 py-1 cursor-pointer transition-all duration-200" onclick="switchTab('admin')" id="mob-tab-admin">
        <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 0;">group</span>
        <span class="text-[9px] font-semibold mt-0.5">Users</span>
    </div>
    <?php endif; ?>

    <!-- Dashboard -->
    <div class="mob-tab-btn flex flex-col items-center justify-center text-[#c2c6d6] hover:bg-[#2d3449]/30 rounded-full px-4 py-1 cursor-pointer transition-all duration-200" onclick="switchTab('analytics')" id="mob-tab-analytics">
        <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 0;">dashboard</span>
        <span class="text-[9px] font-semibold mt-0.5">Dashboard</span>
    </div>

    <!-- Invoices (Admin or Client) -->
    <?php if($is_admin || $role === 'eco_client'): ?>
    <div class="mob-tab-btn flex flex-col items-center justify-center text-[#c2c6d6] hover:bg-[#2d3449]/30 rounded-full px-4 py-1 cursor-pointer transition-all duration-200" onclick="switchTab('invoices')" id="mob-tab-invoices">
        <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 0;">payments</span>
        <span class="text-[9px] font-semibold mt-0.5">Invoices</span>
    </div>
    <?php endif; ?>

    <!-- Payroll (Admin or Workers) -->
    <?php if($is_admin || in_array($role, ['worker','qa','d4u_writer','seo_manager','ai_work'])): ?>
    <div class="mob-tab-btn flex flex-col items-center justify-center text-[#c2c6d6] hover:bg-[#2d3449]/30 rounded-full px-4 py-1 cursor-pointer transition-all duration-200" onclick="switchTab('payroll')" id="mob-tab-payroll">
        <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 0;">payments</span>
        <span class="text-[9px] font-semibold mt-0.5">Payroll</span>
    </div>
    <?php endif; ?>
    
    <!-- Recycle Bin (Admin only) -->
    <?php if($is_admin): ?>
    <div class="mob-tab-btn flex flex-col items-center justify-center text-[#c2c6d6] hover:bg-[#2d3449]/30 rounded-full px-4 py-1 cursor-pointer transition-all duration-200" onclick="switchTab('recycleBin')" id="mob-tab-recycleBin">
        <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 0;">delete</span>
        <span class="text-[9px] font-semibold mt-0.5">Recycle</span>
    </div>
    <?php endif; ?>
</div>

<div id="footer-spacer" class="h-16 sm:hidden"></div>

</body>
</html>
