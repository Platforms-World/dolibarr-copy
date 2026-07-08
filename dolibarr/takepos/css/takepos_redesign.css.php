<?php
/**
 * TakePOS Professional Redesign — overlay stylesheet (v2 — safe layout)
 *
 * v2 fixes:
 *   - Removes all margins/borders/padding additions to .div1..5 (they had
 *     fixed-percent widths + float:left + box-sizing:border-box, so any
 *     extra margin/border broke the row layout and left the page blank).
 *   - Adds explicit !important display:none on .modal so opening one modal
 *     doesn't leave the others visible inline.
 *   - Replaces "panel" treatment with inset borders / backgrounds that
 *     don't change box dimensions.
 *
 * Hybrid integration:
 *   - Re-skins existing class names; no PHP/HTML/JS logic changes.
 *
 * Load AFTER /takepos/css/pos.css.php so it can override.
 */

if (!defined('NOREQUIRESOC')) {
    define('NOREQUIRESOC', '1');
}
if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', 1);
}
if (!defined('NOLOGIN')) {
    define('NOLOGIN', 1);
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', 1);
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}

define('ISLOADEDBYSTEELSHEET', '1');

session_cache_limiter('public');

require_once __DIR__ . '/../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';

top_httphead('text/css');

if (empty($dolibarr_nocache)) {
    header('Cache-Control: max-age=10800, public, must-revalidate');
} else {
    header('Cache-Control: no-cache');
}
?>

/* ============================================================
   Webfonts
   ============================================================ */
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap');

/* ============================================================
   Design tokens
   ============================================================ */
:root {
    --tp-bg-app:       #f1f4f9;
    --tp-bg-panel:     #ffffff;
    --tp-bg-soft:      #f6f8fc;
    --tp-bg-hover:     #eef2f8;
    --tp-bg-active:    #e4ebf5;
    --tp-bg-dark:      #0f172a;
    --tp-bg-dark-soft: #1e293b;

    --tp-border:        #e2e8f0;
    --tp-border-strong: #cbd5e1;

    --tp-text:        #0f172a;
    --tp-text-muted:  #475569;
    --tp-text-soft:   #94a3b8;
    --tp-text-on-dark:#e2e8f0;

    --tp-primary:       #1e40af;
    --tp-primary-hover: #1e3a8a;
    --tp-primary-soft:  #eff6ff;
    --tp-primary-50:    #f0f7ff;

    --tp-success:      #047857;
    --tp-success-soft: #ecfdf5;
    --tp-warning:      #b45309;
    --tp-warning-soft: #fffbeb;
    --tp-danger:       #b91c1c;
    --tp-danger-soft:  #fef2f2;

    --tp-radius:    8px;
    --tp-radius-sm: 6px;
    --tp-radius-lg: 12px;

    --tp-shadow-xs: 0 1px 2px rgba(15,23,42,0.04);
    --tp-shadow-sm: 0 2px 4px rgba(15,23,42,0.05), 0 1px 2px rgba(15,23,42,0.04);
    --tp-shadow:    0 4px 12px rgba(15,23,42,0.06), 0 2px 4px rgba(15,23,42,0.04);
    --tp-shadow-lg: 0 12px 32px rgba(15,23,42,0.10), 0 4px 8px rgba(15,23,42,0.05);

    --tp-font-ar:  "IBM Plex Sans Arabic", "Segoe UI", system-ui, sans-serif;
    --tp-font-en:  "Inter", "Segoe UI", system-ui, sans-serif;
    --tp-font-num: "JetBrains Mono", "Inter", monospace;
}

/* ============================================================
   CRITICAL — keep modals hidden by default
   ============================================================ */
body.bodytakepos div.modal { display: none; }
body.bodytakepos div.modal.show,
body.bodytakepos div.modal[style*="display: block"],
body.bodytakepos div.modal[style*="display:block"] { display: block; }

/* ============================================================
   Base — body & typography
   ============================================================ */
body.bodytakepos {
    background-color: var(--tp-bg-app) !important;
    color: var(--tp-text);
    font-family: var(--tp-font-ar);
    font-size: 13.5px;
    line-height: 1.45;
    -webkit-font-smoothing: antialiased;
}

body.bodytakepos[dir="ltr"],
html[lang^="en"] body.bodytakepos {
    font-family: var(--tp-font-en);
}

body.bodytakepos button,
body.bodytakepos input,
body.bodytakepos select,
body.bodytakepos textarea {
    font-family: inherit;
}

body.bodytakepos #linecolht-span-total,
body.bodytakepos .takepos-pad-badge {
    font-family: var(--tp-font-num);
    font-feature-settings: "tnum" 1;
}

/* ============================================================
   Top app bar (#topnav)
   ============================================================ */
body.bodytakepos #topnav.topnav,
body.bodytakepos .topnav {
    background: var(--tp-bg-dark) !important;
    color: var(--tp-text-on-dark) !important;
    border-bottom: 0;
    box-shadow: 0 1px 0 rgba(15,23,42,0.6);
}

body.bodytakepos .topnav a {
    color: var(--tp-text-on-dark) !important;
    border-radius: var(--tp-radius-sm);
    transition: background 0.15s, color 0.15s;
}

body.bodytakepos .topnav a:hover:not(.nohover) {
    background: var(--tp-bg-dark-soft);
    color: #fff !important;
    text-decoration: none;
}

body.bodytakepos .topnav .topnav-terminalhour {
    color: #fff !important;
    background: rgba(255,255,255,0.04);
    border: 1px solid #334155;
}

body.bodytakepos .topnav .topnav-terminalhour .fa { color: #93c5fd; }

body.bodytakepos .topnav input[type="text"],
body.bodytakepos .topnav input#search {
    border: 1px solid #334155 !important;
    border-radius: var(--tp-radius-sm) !important;
    background: var(--tp-bg-dark-soft) !important;
    color: #fff !important;
    transition: all 0.15s;
}

body.bodytakepos .topnav input[type="text"]::placeholder { color: #64748b; }

body.bodytakepos .topnav input[type="text"]:focus {
    border-color: #3b82f6 !important;
    background: #0f172a !important;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.20);
}

body.bodytakepos .topnav .takepos-search-button,
body.bodytakepos .topnav button.button.smallpaddingimp {
    background: var(--tp-primary) !important;
    color: #fff !important;
    border: 1px solid var(--tp-primary) !important;
    border-radius: var(--tp-radius-sm) !important;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
}

body.bodytakepos .topnav .takepos-search-button:hover {
    background: var(--tp-primary-hover) !important;
}

body.bodytakepos .topnav .login_block_user {
    color: var(--tp-text-on-dark) !important;
}

body.bodytakepos .topnav .login_block_user a,
body.bodytakepos .topnav .login_block_user span {
    color: var(--tp-text-on-dark) !important;
}

body.bodytakepos #customerandsales,
body.bodytakepos #shoppingcart,
body.bodytakepos #moreinfo,
body.bodytakepos #infowarehouse {
    color: #cbd5e1 !important;
}

body.bodytakepos #customerandsales a,
body.bodytakepos #shoppingcart a {
    color: #93c5fd !important;
}

/* ============================================================
   Main layout — DO NOT touch widths/heights/floats/margins/padding.
   Only set background colors and inset borders so the original
   geometry survives.
   ============================================================ */
body.bodytakepos #takepos-main-layout {
    background: var(--tp-bg-app);
}

body.bodytakepos .row1,
body.bodytakepos .row1withhead,
body.bodytakepos .row2,
body.bodytakepos .row2withhead {
    background: transparent;
}

/* Cart panel (.div1) — inset look without changing dimensions */
body.bodytakepos .div1 {
    background: var(--tp-bg-panel);
    box-shadow: inset 0 0 0 1px var(--tp-border), var(--tp-shadow-xs);
    border-radius: var(--tp-radius);
}

/* Numeric pad column (.div2) */
body.bodytakepos .div2 {
    background: var(--tp-bg-panel);
    box-shadow: inset 0 0 0 1px var(--tp-border), var(--tp-shadow-xs);
    border-radius: var(--tp-radius);
}

/* Actions column (.div3) */
body.bodytakepos .div3 {
    background: var(--tp-bg-panel);
    box-shadow: inset 0 0 0 1px var(--tp-border), var(--tp-shadow-xs);
    border-radius: var(--tp-radius);
}

/* Categories panel (.div4) */
body.bodytakepos .div4 {
    background: var(--tp-bg-panel);
    box-shadow: inset 0 0 0 1px var(--tp-border), var(--tp-shadow-xs);
    border-radius: var(--tp-radius);
}

/* Products grid panel (.div5) */
body.bodytakepos .div5 {
    background: var(--tp-bg-panel);
    box-shadow: inset 0 0 0 1px var(--tp-border), var(--tp-shadow-xs);
    border-radius: var(--tp-radius);
}

/* ============================================================
   Cart table — #tablelines / .postablelines
   ============================================================ */
body.bodytakepos table.postablelines tr.liste_titre {
    background: var(--tp-bg-soft);
}

body.bodytakepos table.postablelines tr.liste_titre td {
    color: var(--tp-text-soft);
    font-weight: 700;
    font-size: 10.5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--tp-border);
    background: var(--tp-bg-soft);
}

body.bodytakepos table.postablelines tr.posinvoiceline td,
body.bodytakepos .posinvoiceline td {
    border-bottom: 1px solid var(--tp-border);
    background: var(--tp-bg-panel);
    transition: background 0.12s;
    vertical-align: middle;
    font-size: 12.5px;
    color: var(--tp-text);
}

body.bodytakepos .posinvoiceline:hover td { background: var(--tp-bg-soft); }

body.bodytakepos .posinvoiceline.selected td,
body.bodytakepos tr.selected td {
    background: var(--tp-primary-50) !important;
}

body.bodytakepos .postablelines td.linecolht {
    font-family: var(--tp-font-num);
    font-weight: 700;
    color: var(--tp-text);
    white-space: nowrap;
}

body.bodytakepos .postablelines td.linecolht .opacitymedium {
    color: var(--tp-text-soft);
    font-weight: 500;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

body.bodytakepos #linecolht-span-total {
    color: var(--tp-text);
    letter-spacing: -0.3px;
}

/* ============================================================
   Numeric pad — .calcbutton / .calcbutton2 / .calcbutton3
   Re-skin colors only. Don't touch width/height/margin (the
   original uses calc(25% - 2px) which the existing layout depends on).
   ============================================================ */
body.bodytakepos button.calcbutton,
body.bodytakepos button.calcbutton2,
body.bodytakepos button.calcbutton3 {
    background: var(--tp-bg-soft) !important;
    color: var(--tp-text) !important;
    border: 1px solid var(--tp-border) !important;
    border-radius: var(--tp-radius) !important;
    font-weight: 700;
    transition: background 0.12s, transform 0.06s, box-shadow 0.15s;
    box-shadow: var(--tp-shadow-xs);
}

body.bodytakepos button.calcbutton:hover,
body.bodytakepos button.calcbutton2:hover,
body.bodytakepos button.calcbutton3:hover {
    background: var(--tp-bg-hover) !important;
    border-color: var(--tp-border-strong) !important;
    box-shadow: var(--tp-shadow-sm);
}

body.bodytakepos button.calcbutton:active,
body.bodytakepos button.calcbutton2:active {
    background: var(--tp-bg-active) !important;
    transform: scale(0.97);
}

body.bodytakepos button.calcbutton.poscolorblue {
    background: var(--tp-primary) !important;
    color: #fff !important;
    border-color: var(--tp-primary) !important;
}

body.bodytakepos button.calcbutton.poscolorblue:hover {
    background: var(--tp-primary-hover) !important;
}

body.bodytakepos button.calcbutton2.poscolordelete {
    background: var(--tp-bg-panel) !important;
    color: var(--tp-danger) !important;
    border-color: #fecaca !important;
}

body.bodytakepos button.calcbutton2.poscolordelete:hover {
    background: var(--tp-danger-soft) !important;
}

body.bodytakepos button.calcbutton2.takepos-pad-qty {
    background: linear-gradient(180deg, #2563eb, #1d4ed8) !important;
    color: #fff !important;
    border-color: #1d4ed8 !important;
}
body.bodytakepos button.calcbutton2.takepos-pad-price {
    background: linear-gradient(180deg, #7c3aed, #6d28d9) !important;
    color: #fff !important;
    border-color: #6d28d9 !important;
}
body.bodytakepos button.calcbutton2.takepos-pad-discount {
    background: linear-gradient(180deg, #ea580c, #c2410c) !important;
    color: #fff !important;
    border-color: #c2410c !important;
}
body.bodytakepos button.calcbutton2.takepos-pad-qty:hover,
body.bodytakepos button.calcbutton2.takepos-pad-price:hover,
body.bodytakepos button.calcbutton2.takepos-pad-discount:hover {
    filter: brightness(1.05);
}

body.bodytakepos button.calcbutton2.clicked {
    background: var(--tp-primary-hover) !important;
    color: #fff !important;
    box-shadow: inset 0 2px 6px rgba(0,0,0,0.18);
}

body.bodytakepos .takepos-pad-badge {
    background: rgba(15,23,42,0.10);
    color: var(--tp-text-muted);
    font-family: var(--tp-font-num);
}

body.bodytakepos button.calcbutton2.takepos-pad-qty .takepos-pad-badge,
body.bodytakepos button.calcbutton2.takepos-pad-price .takepos-pad-badge,
body.bodytakepos button.calcbutton2.takepos-pad-discount .takepos-pad-badge,
body.bodytakepos button.calcbutton.poscolorblue .takepos-pad-badge {
    background: rgba(255,255,255,0.20);
    color: #fff;
}

body.bodytakepos button.calcbutton2.poscolordelete .takepos-pad-badge {
    background: var(--tp-danger-soft);
    color: var(--tp-danger);
}

/* ============================================================
   Action buttons (.actionbutton)
   ============================================================ */
body.bodytakepos button.actionbutton {
    background: var(--tp-bg-soft) !important;
    color: var(--tp-text) !important;
    border: 1px solid var(--tp-border) !important;
    border-radius: var(--tp-radius) !important;
    font-weight: 600;
    transition: all 0.15s;
    box-shadow: var(--tp-shadow-xs);
}

body.bodytakepos button.actionbutton:hover {
    background: var(--tp-bg-hover) !important;
    border-color: var(--tp-border-strong) !important;
    box-shadow: var(--tp-shadow-sm);
}

body.bodytakepos button.actionbutton.poscolorgreen,
body.bodytakepos button.actionbutton.posvalid {
    background: linear-gradient(180deg, #16a34a, #15803d) !important;
    color: #fff !important;
    border-color: #15803d !important;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(21,128,61,0.25), inset 0 1px 0 rgba(255,255,255,0.20);
}

body.bodytakepos button.actionbutton.poscolorgreen:hover,
body.bodytakepos button.actionbutton.posvalid:hover {
    background: linear-gradient(180deg, #15803d, #166534) !important;
}

body.bodytakepos button.actionbutton.poscolorblue {
    background: var(--tp-primary) !important;
    color: #fff !important;
    border-color: var(--tp-primary) !important;
}
body.bodytakepos button.actionbutton.poscolorblue:hover {
    background: var(--tp-primary-hover) !important;
}

body.bodytakepos button.actionbutton.poscolordelete {
    background: var(--tp-bg-panel) !important;
    color: var(--tp-danger) !important;
    border-color: #fecaca !important;
}
body.bodytakepos button.actionbutton.poscolordelete:hover {
    background: var(--tp-danger-soft) !important;
}

/* ============================================================
   Category / Product tiles — only colors/borders, no transforms
   that would break the calc()-based size math
   ============================================================ */
body.bodytakepos div.wrapper,
body.bodytakepos div.wrapper2 {
    background: var(--tp-bg-panel);
    border: 1px solid var(--tp-border);
    border-radius: var(--tp-radius) !important;
    overflow: hidden;
    box-shadow: var(--tp-shadow-xs);
    transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
    cursor: pointer;
}

body.bodytakepos div.wrapper:hover,
body.bodytakepos div.wrapper2:hover {
    box-shadow: var(--tp-shadow);
    border-color: var(--tp-primary);
}

body.bodytakepos div.wrapper.divempty,
body.bodytakepos div.wrapper2.divempty {
    background: transparent;
    border: 1px dashed var(--tp-border);
    box-shadow: none;
    cursor: default;
}

body.bodytakepos div.wrapper.divempty:hover,
body.bodytakepos div.wrapper2.divempty:hover {
    border-color: var(--tp-border);
    box-shadow: none;
}

body.bodytakepos div.wrapper.arrow,
body.bodytakepos div.wrapper2.arrow {
    background: var(--tp-bg-soft);
    color: var(--tp-text-muted);
    border-color: var(--tp-border);
}

body.bodytakepos div.wrapper.arrow:hover,
body.bodytakepos div.wrapper2.arrow:hover {
    background: var(--tp-primary);
    color: #fff;
    border-color: var(--tp-primary);
}

/* Description label overlay on tiles */
body.bodytakepos .div4 .wrapper .description,
body.bodytakepos .div5 .wrapper2 .description {
    background: linear-gradient(180deg, transparent 0%, rgba(15,23,42,0.86) 80%);
    color: #fff;
    font-weight: 600;
    line-height: 1.25;
}

body.bodytakepos .div4 .wrapper .description_content,
body.bodytakepos .div5 .wrapper2 .description_content {
    color: #fff;
    text-shadow: 0 1px 2px rgba(0,0,0,0.4);
}

body.bodytakepos .div5 .wrapper2 [id^="proprice"] {
    background: var(--tp-bg-dark);
    color: #fff;
    font-family: var(--tp-font-num);
    border-radius: var(--tp-radius-sm);
}

body.bodytakepos .takepos-missing-product-image-badge {
    background: var(--tp-warning) !important;
    border: 2px solid #fff !important;
}

/* ============================================================
   Modals — re-skin look, but DO NOT change display logic
   ============================================================ */
body.bodytakepos .modal {
    background-color: rgba(15,23,42,0.55) !important;
}

body.bodytakepos .modal .modal-content {
    background-color: var(--tp-bg-panel) !important;
    border: 1px solid var(--tp-border) !important;
    border-radius: var(--tp-radius-lg) !important;
    box-shadow: var(--tp-shadow-lg) !important;
    color: var(--tp-text);
}

body.bodytakepos .modal .modal-header {
    background: var(--tp-bg-dark) !important;
    color: #fff !important;
    border-radius: var(--tp-radius-lg) var(--tp-radius-lg) 0 0;
}

body.bodytakepos .modal .close {
    color: var(--tp-text-muted) !important;
    border-radius: var(--tp-radius-sm);
}

body.bodytakepos .modal .close:hover {
    background: var(--tp-bg-soft);
    color: var(--tp-text) !important;
}

body.bodytakepos .modal button.block,
body.bodytakepos .modal .button {
    border-radius: var(--tp-radius-sm) !important;
    font-weight: 600;
    transition: all 0.15s;
}

/* ============================================================
   Shortcuts launcher + drawer
   ============================================================ */
body.bodytakepos #takepos-shortcuts-launcher {
    background: var(--tp-bg-dark) !important;
    color: #fff !important;
    border: none !important;
    box-shadow: var(--tp-shadow-lg);
    transition: background 0.15s;
}

body.bodytakepos #takepos-shortcuts-launcher:hover {
    background: var(--tp-primary) !important;
}

body.bodytakepos #takepos-shortcuts-drawer {
    background: var(--tp-bg-panel) !important;
    box-shadow: var(--tp-shadow-lg);
}

body.bodytakepos .takepos-shortcuts-head {
    background: var(--tp-bg-dark) !important;
    color: #fff !important;
}

body.bodytakepos .takepos-shortcuts-title {
    color: #fff !important;
    font-weight: 700;
}

body.bodytakepos #takepos-shortcuts-close {
    background: rgba(255,255,255,0.08) !important;
    color: #fff !important;
    border: none !important;
    border-radius: var(--tp-radius-sm) !important;
}

body.bodytakepos #takepos-shortcuts-close:hover {
    background: rgba(255,255,255,0.18) !important;
}

body.bodytakepos .takepos-shortcut-section {
    border: 1px solid var(--tp-border) !important;
    border-radius: var(--tp-radius-lg) !important;
    background: var(--tp-bg-panel) !important;
    overflow: hidden;
}

body.bodytakepos .takepos-shortcut-header {
    background: var(--tp-bg-soft) !important;
    color: var(--tp-text) !important;
    font-weight: 700;
    transition: background 0.15s;
}

body.bodytakepos .takepos-shortcut-header:hover {
    background: var(--tp-bg-hover) !important;
}

body.bodytakepos .takepos-shortcut-header .chevron {
    color: var(--tp-text-soft);
    transition: transform 0.2s;
}

body.bodytakepos .takepos-shortcut-section.is-collapsed .chevron {
    transform: rotate(-90deg);
}

body.bodytakepos .takepos-shortcut-link {
    background: var(--tp-bg-soft) !important;
    border: 1px solid var(--tp-border) !important;
    border-radius: var(--tp-radius) !important;
    color: var(--tp-text) !important;
    font-weight: 600;
    text-decoration: none !important;
    transition: all 0.15s;
}

body.bodytakepos .takepos-shortcut-link:hover {
    background: var(--tp-bg-panel) !important;
    border-color: var(--tp-primary) !important;
    box-shadow: var(--tp-shadow-sm);
}

body.bodytakepos .takepos-shortcut-icon {
    background: var(--tp-bg-panel) !important;
    border: 1px solid var(--tp-border) !important;
    border-radius: var(--tp-radius-sm) !important;
    color: var(--tp-primary) !important;
    transition: all 0.15s;
}

body.bodytakepos .takepos-shortcut-link:hover .takepos-shortcut-icon {
    background: var(--tp-primary) !important;
    color: #fff !important;
    border-color: var(--tp-primary) !important;
}

/* ============================================================
   Toasts / feedback
   ============================================================ */
body.bodytakepos #takepos-feedback-bar {
    background: var(--tp-bg-dark);
    color: #fff;
    border-radius: var(--tp-radius);
    box-shadow: var(--tp-shadow-lg);
}

body.bodytakepos #takepos-hold-feedback {
    background: var(--tp-warning-soft);
    color: var(--tp-warning) !important;
    border-radius: var(--tp-radius-sm);
    border: 1px solid #fde68a;
    font-weight: 600;
}

/* ============================================================
   Generic helpers
   ============================================================ */
body.bodytakepos .opacitymedium { color: var(--tp-text-muted) !important; }

body.bodytakepos .basketselected {
    background: var(--tp-primary-50);
    color: var(--tp-primary);
    padding: 2px 8px;
    border-radius: 999px;
    font-weight: 700;
    font-size: 11px;
    font-family: var(--tp-font-num);
    border: 1px solid var(--tp-primary);
}

body.bodytakepos .basketnotselected {
    background: var(--tp-bg-dark-soft);
    color: var(--tp-text-on-dark);
    padding: 2px 8px;
    border-radius: 999px;
    font-weight: 600;
    font-size: 11px;
    font-family: var(--tp-font-num);
    border: 1px solid #334155;
}

body.bodytakepos *:focus-visible {
    outline: 2px solid var(--tp-primary);
    outline-offset: 2px;
}

/* ============================================================
   Print
   ============================================================ */
@media print {
    body.bodytakepos {
        background: #fff !important;
        font-family: var(--tp-font-en);
    }
    body.bodytakepos #topnav,
    body.bodytakepos #takepos-shortcuts-launcher,
    body.bodytakepos #takepos-shortcuts-drawer,
    body.bodytakepos .div2,
    body.bodytakepos .div3,
    body.bodytakepos .div4,
    body.bodytakepos .modal {
        display: none !important;
    }
}
