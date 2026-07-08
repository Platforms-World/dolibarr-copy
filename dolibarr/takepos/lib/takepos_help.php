<?php
if (!function_exists('takeposHelpResolveFile')) {
    function takeposHelpResolveFile($scriptPath)
    {
        $normalized = str_replace('\\', '/', (string) $scriptPath);
        $name = basename($normalized);
        $dir = basename(dirname($normalized));

        if ($name === 'phone.php' && defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
            return 'public_auto_order.html';
        }

        $map = array(
            'index.php' => 'index.html',
            'purchases.php' => 'purchases.html',
            'cheques.php' => 'cheques.html',
            'expenses.php' => 'expenses.html',
            'expense_ledger.php' => 'expense_ledger.html',
            'refunds.php' => 'refunds.html',
            'refund_details.php' => 'refund_details.html',
            'refund_receipt.php' => 'refund_receipt.html',
            'exchange.php' => 'exchange.html',
            'shifts.php' => 'shifts.html',
            'shift_details.php' => 'shift_details.html',
            'dashboard.php' => ($dir === 'audit' ? null : 'dashboard.html'),
            'loyalty.php' => ($dir === 'admin' ? 'admin_loyalty.html' : 'loyalty.html'),
            'sync_queue.php' => 'sync_queue.html',
            'customer_display.php' => 'customer_display.html',
            'floors.php' => 'floors.html',
            'split.php' => 'split.html',
            'freezone.php' => 'freezone.html',
            'phone.php' => 'phone.html',
            'setup.php' => 'admin_setup.html',
            'users.php' => 'admin_users.html',
            'stores.php' => 'admin_stores.html',
            'terminal.php' => 'admin_terminal.html',
            'devices.php' => 'admin_devices.html',
            'printers.php' => 'admin_printers.html',
            'receipt.php' => ($dir === 'admin' ? 'admin_receipt.html' : null),
            'appearance.php' => 'admin_appearance.html',
            'menu.php' => 'public_menu.html',
            'auto_order.php' => 'public_auto_order.html',
        );

        $resolved = isset($map[$name]) ? $map[$name] : null;
        if (empty($resolved)) {
            return 'index.html';
        }
        return $resolved;
    }
}

if (!function_exists('takeposHelpRender')) {
    function takeposHelpRender($langs, $scriptPath)
    {
        $helpFile = takeposHelpResolveFile($scriptPath);
        $fallbackFile = 'index.html';
        $helpDir = dirname(__DIR__) . '/help/';
        if (!is_file($helpDir . $helpFile)) {
            $helpFile = $fallbackFile;
        }

        $langCode = '';
        if (is_object($langs) && !empty($langs->defaultlang)) {
            $langCode = (string) $langs->defaultlang;
        }
        if ($langCode === '' && isset($_GET['langs'])) {
            $langCode = (string) $_GET['langs'];
        }
        $isArabic = (stripos($langCode, 'ar') === 0);

        $buttonLabel = is_object($langs) ? $langs->trans('TakeposHelpButton') : '';
        if ($buttonLabel === 'TakeposHelpButton' || $buttonLabel === '') {
            $buttonLabel = $isArabic ? 'مساعدة' : 'Help';
        }
        $titleLabel = is_object($langs) ? $langs->trans('TakeposHelpTitle') : '';
        if ($titleLabel === 'TakeposHelpTitle' || $titleLabel === '') {
            $titleLabel = $isArabic ? 'مساعدة الشاشة' : 'Screen Help';
        }
        $closeLabel = is_object($langs) ? $langs->trans('TakeposHelpClose') : '';
        if ($closeLabel === 'TakeposHelpClose' || $closeLabel === '') {
            $closeLabel = $isArabic ? 'إغلاق' : 'Close';
        }
        $openNewLabel = $isArabic ? 'فتح في نافذة جديدة' : 'Open in new tab';
        $loadingLabel = $isArabic ? 'جاري تحميل المساعدة...' : 'Loading help...';

        $helpUrl = DOL_URL_ROOT . '/takepos/help/' . rawurlencode($helpFile);
        $cssUrl = DOL_URL_ROOT . '/takepos/css/takepos_help.css?v=20260320b';
        $jsUrl = DOL_URL_ROOT . '/takepos/js/takepos_help.js?v=20260320b';
        $dirAttr = $isArabic ? 'rtl' : 'ltr';

        return ''
            . '<link rel="stylesheet" href="' . dol_escape_htmltag($cssUrl) . '">' 
            . '<div class="takepos-help-root" dir="' . dol_escape_htmltag($dirAttr) . '" '
            . 'data-help-url="' . dol_escape_htmltag($helpUrl) . '" '
            . 'data-help-button="' . dol_escape_htmltag($buttonLabel) . '" '
            . 'data-help-title="' . dol_escape_htmltag($titleLabel) . '" '
            . 'data-help-close="' . dol_escape_htmltag($closeLabel) . '" '
            . 'data-help-opennew="' . dol_escape_htmltag($openNewLabel) . '" '
            . 'data-help-loading="' . dol_escape_htmltag($loadingLabel) . '"></div>'
            . '<script src="' . dol_escape_htmltag($jsUrl) . '"></script>';
    }
}

if (!function_exists('takeposResolveTerminalIntConstant')) {
    function takeposResolveTerminalIntConstant($baseConstant, $terminalToken = '')
    {
        $baseConstant = (string) $baseConstant;
        $terminalToken = trim((string) $terminalToken);

        if ($terminalToken !== '') {
            $terminalValue = (int) getDolGlobalInt($baseConstant . $terminalToken);
            if ($terminalValue > 0) {
                return $terminalValue;
            }
        }

        return (int) getDolGlobalInt($baseConstant);
    }
}

if (!function_exists('takeposResolveTerminalStringConstant')) {
    function takeposResolveTerminalStringConstant($baseConstant, $terminalToken = '')
    {
        $baseConstant = (string) $baseConstant;
        $terminalToken = trim((string) $terminalToken);

        if ($terminalToken !== '') {
            $terminalValue = trim((string) getDolGlobalString($baseConstant . $terminalToken));
            if ($terminalValue !== '') {
                return $terminalValue;
            }
        }

        return trim((string) getDolGlobalString($baseConstant));
    }
}

if (!function_exists('takeposResolveTerminalThirdPartyId')) {
    function takeposResolveTerminalThirdPartyId($terminalToken = '')
    {
        return takeposResolveTerminalIntConstant('CASHDESK_ID_THIRDPARTY', $terminalToken);
    }
}

if (!function_exists('takeposResolveTerminalBankAccountId')) {
    function takeposResolveTerminalBankAccountId($paymentCode, $terminalToken = '')
    {
        return takeposResolveTerminalIntConstant('CASHDESK_ID_BANKACCOUNT_' . strtoupper((string) $paymentCode), $terminalToken);
    }
}
