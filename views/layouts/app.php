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
<link rel="stylesheet" href="<?= $publicUrl ?>/css/app.css">

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
.card .head {
    position: relative;
    background: #162033;
    border-radius: 8px 8px 0 0;
    cursor: pointer;
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
.card.urgent-card .head { background: #1a0a00; }

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
var MODULE_PERMS = <?= json_encode(
    $user['role'] === 'administrator'
        ? array_fill_keys(array_keys(ModulePermission::MODULES),
              array_fill_keys(['view','add','edit','delete'], true))
        : ModulePermission::getForUser($user)
) ?>;
<?php
$role_bulk_action = false;
if(current_user()){
    $u = current_user();
    if($u['role'] === 'administrator' || $u['username'] === 'ilyaeco'){
        $role_bulk_action = true;
    } else {
        $perm_row = db()->prepare("SELECT settings FROM eco_permissions WHERE role=?");
        $perm_row->execute([$u['role']]);
        $perm_settings = json_decode($perm_row->fetchColumn() ?: '{}', true);
        $role_bulk_action = !empty($perm_settings['bulk_action']);
    }
}
?>
var HAS_BULK_ACTION = <?= $role_bulk_action ? 'true' : 'false' ?>;
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
              AND t.status = 'Generated' AND t.work_status NOT IN ('Work Done', 'Info Done')
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

<!-- ── JS Modules ── -->
<?php $v = '2.7.6'; ?>
<script src="<?= $publicUrl ?>/js/core.js?v=<?= $v ?>"></script>
<script src="<?= $publicUrl ?>/js/tasks.js?v=2.7.9"></script>
<script src="<?= $publicUrl ?>/js/admin.js?v=<?= $v ?>"></script>
<script src="<?= $publicUrl ?>/js/invoices.js?v=<?= $v ?>"></script>
<script src="<?= $publicUrl ?>/js/payroll.js?v=2.7.11"></script>
<script src="<?= $publicUrl ?>/js/seo.js?v=<?= $v ?>"></script>
<script src="<?= $publicUrl ?>/js/pwa.js?v=<?= $v ?>"></script>

<script>
(function() {
    // Theme toggle logic
    var btn = document.getElementById('theme-toggle-btn');
    if (btn) {
        var updateBtn = function() {
            var isLight = document.documentElement.classList.contains('light-theme');
            btn.innerHTML = isLight ? '🌙 Dark Theme' : '☀️ Light Theme';
        };
        updateBtn();
        btn.addEventListener('click', function() {
            var isLight = document.documentElement.classList.contains('light-theme');
            if (isLight) {
                document.documentElement.classList.remove('light-theme');
                localStorage.setItem('theme', 'dark');
            } else {
                document.documentElement.classList.add('light-theme');
                localStorage.setItem('theme', 'light');
            }
            updateBtn();
        });
    }

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
