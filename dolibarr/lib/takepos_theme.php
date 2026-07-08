<?php
/**
 * TakePOS Custom Theme Injector
 * Include this file at the top of any TakePOS PHP page to apply the custom styles.
 *
 * Usage:
 *   require_once __DIR__ . '/../lib/takepos_theme.php';
 *   // or from admin pages:
 *   require_once DOL_DOCUMENT_ROOT . '/takepos/lib/takepos_theme.php';
 *   takeposInjectTheme();   // call after llxHeader()
 */

if (!defined('DOL_DOCUMENT_ROOT')) {
    die('No direct access');
}

/**
 * Output a <link> tag that loads the custom TakePOS CSS theme.
 * Also injects the Google Fonts preconnect for best performance.
 *
 * @return void
 */
function takeposInjectTheme()
{
    $cssUrl = DOL_URL_ROOT . '/takepos/css/takepos_custom.css';
    $jsUrl  = DOL_URL_ROOT . '/takepos/js/takepos_custom.js';
    $gFonts = 'https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap';

    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link href="' . dol_escape_htmltag($gFonts) . '" rel="stylesheet">' . "\n";
    echo '<link rel="stylesheet" href="' . dol_escape_htmltag($cssUrl) . '">' . "\n";
    echo '<script src="' . dol_escape_htmltag($jsUrl) . '" defer></script>' . "\n";
}

/**
 * Return CSS variable block as an inline <style> tag.
 * Useful when Google Fonts is not available (offline POS installations).
 *
 * @return string
 */
function takeposGetInlineCssVars()
{
    return '<style>
:root {
  --bg-app:#f1f4f9;--bg-panel:#ffffff;--bg-soft:#f6f8fc;--bg-hover:#eef2f8;
  --bg-active:#e4ebf5;--bg-dark:#0f172a;--bg-dark-soft:#1e293b;
  --border:#e2e8f0;--border-strong:#cbd5e1;--border-focus:#94a3b8;
  --text:#0f172a;--text-muted:#475569;--text-soft:#94a3b8;--text-on-dark:#e2e8f0;
  --primary:#1e40af;--primary-hover:#1e3a8a;--primary-soft:#eff6ff;--primary-50:#f0f7ff;
  --success:#047857;--success-soft:#ecfdf5;--warning:#b45309;--warning-soft:#fffbeb;
  --danger:#b91c1c;--danger-soft:#fef2f2;--info:#0e7490;--info-soft:#ecfeff;
  --radius:8px;--radius-sm:6px;--radius-lg:12px;
  --shadow-xs:0 1px 2px rgba(15,23,42,.04);
  --shadow-sm:0 2px 4px rgba(15,23,42,.05),0 1px 2px rgba(15,23,42,.04);
  --shadow:0 4px 12px rgba(15,23,42,.06),0 2px 4px rgba(15,23,42,.04);
  --shadow-lg:0 12px 32px rgba(15,23,42,.10),0 4px 8px rgba(15,23,42,.05);
  --shadow-pop:0 20px 50px rgba(15,23,42,.15),0 8px 16px rgba(15,23,42,.08);
  --font-ar:"IBM Plex Sans Arabic","Segoe UI",system-ui,sans-serif;
  --font-en:"Inter","Segoe UI",system-ui,sans-serif;
  --font-num:"JetBrains Mono","Inter",monospace;
}
</style>';
}
