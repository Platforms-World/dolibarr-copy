<?php
/**
 * lib/takepos_billing_shortcut.php
 *
 * يُضيف اختصار "نظام الفوترة" إلى لوحة Shortcuts في TakePOS
 * يمكن استدعاء هذا الملف من hook أو من ملف shortcuts.php
 *
 * طريقة التفعيل: أضف في نهاية ملف shortcuts.php أو من خلال Hook:
 *   require_once DOL_DOCUMENT_ROOT.'/takepos/lib/takepos_billing_shortcut.php';
 *   $shortcuts = array_merge($shortcuts, takeposBillingCountryShortcuts());
 */

if (!defined('DOL_DOCUMENT_ROOT')) {
    exit('Not allowed');
}

/**
 * إرجاع مصفوفة اختصارات نظام الفوترة
 *
 * @return array
 */
function takeposBillingCountryShortcuts()
{
    global $langs, $conf;
    $langs->loadLangs(array("cashdesk"));

    $currentCountry = getDolGlobalString('TAKEPOS_BILLING_COUNTRY', '');
    $label = 'نظام الفوترة';
    if ($currentCountry === 'JO') {
        $label = '🇯🇴 فوترة أردنية';
    } elseif ($currentCountry === 'SA') {
        $label = '🇸🇦 فوترة ZATCA';
    }

    return array(
        array(
            'id'       => 'billing_country',
            'label'    => $label,
            'url'      => DOL_URL_ROOT . '/takepos/admin/billing_country.php',
            'icon'     => 'receipt',
            'category' => 'catalog', // يظهر ضمن قسم Catalog & Inventory
            'target'   => '_blank',
        ),
    );
}

/**
 * بناء HTML لزر الاختصار (يُضاف مباشرةً في واجهة TakePOS إن لزم)
 *
 * @return string HTML
 */
function takeposBillingCountryShortcutButton()
{
    global $conf;
    $currentCountry = getDolGlobalString('TAKEPOS_BILLING_COUNTRY', '');

    $label = 'نظام الفوترة';
    $badge = '';
    if ($currentCountry === 'JO') {
        $label = 'نظام الفوترة';
        $badge = '<span style="background:#009e3c;color:white;font-size:9px;padding:1px 5px;border-radius:8px;margin-right:4px;">🇯🇴 أردن</span>';
    } elseif ($currentCountry === 'SA') {
        $label = 'نظام الفوترة';
        $badge = '<span style="background:#006c35;color:white;font-size:9px;padding:1px 5px;border-radius:8px;margin-right:4px;">🇸🇦 ZATCA</span>';
    }

    $url = DOL_URL_ROOT . '/takepos/admin/billing_country.php';

    return '
    <div class="shortcut-item" onclick="window.open(\'' . $url . '\', \'_blank\')"
         style="cursor:pointer; padding:10px; border:1px solid #ddd; border-radius:8px;
                margin:5px; display:inline-flex; align-items:center; gap:8px;
                background:#fff; transition:box-shadow 0.2s;"
         onmouseover="this.style.boxShadow=\'0 2px 8px rgba(0,0,0,0.15)\'"
         onmouseout="this.style.boxShadow=\'none\'"
         title="إعداد نظام الفوترة (أردن / سعودية)">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1565c0" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="16" y1="13" x2="8" y2="13"/>
            <line x1="16" y1="17" x2="8" y2="17"/>
            <polyline points="10 9 9 9 8 9"/>
        </svg>
        ' . $badge . htmlspecialchars($label) . '
    </div>';
}
