<?php
/**
 * Central access bootstrap for TakePOS screens.
 */

if (!function_exists('takeposAccessGuardCurrent')) {
    require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAccess.class.php';

    function takeposGuardTerminalId()
    {
        return isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null;
    }

    function takeposCurrentRelativePath($scriptFile)
    {
        $base = str_replace('\\', '/', realpath(DOL_DOCUMENT_ROOT . '/takepos'));
        $path = str_replace('\\', '/', realpath($scriptFile) ?: $scriptFile);
        if ($base && strpos($path, $base) === 0) {
            return ltrim(substr($path, strlen($base)), '/');
        }
        return basename($path);
    }

    function takeposGuardConfig($relativePath)
    {
        $relativePath = ltrim((string) $relativePath, '/');

        $map = array(
            'smpcb.php' => array('mode' => 'frontend', 'feature' => 'takepos.smpcb', 'permission' => 'takepos.use'),
            'reduction.php' => array('mode' => 'frontend', 'feature' => 'takepos.discount', 'permission' => 'takepos.use'),
            'pay.php' => array('mode' => 'frontend', 'feature' => 'takepos.payment', 'permission' => 'takepos.use'),
            'freezone.php' => array('mode' => 'frontend', 'feature' => 'takepos.freezone', 'permission' => 'takepos.use'),
            'floors.php' => array('mode' => 'frontend', 'feature' => 'takepos.restaurant', 'permission' => 'takepos.use'),
            'split.php' => array('mode' => 'frontend', 'feature' => 'takepos.split', 'permission' => 'takepos.use'),
            'customer_display.php' => array('mode' => 'frontend', 'feature' => 'takepos.customer_display', 'permission' => 'takepos.use'),
            'send.php' => array('mode' => 'frontend', 'feature' => 'takepos.send', 'permission' => 'takepos.use'),
            'printbox.php' => array('mode' => 'frontend', 'feature' => 'takepos.receipt', 'permission' => 'takepos.use'),
            'phone.php' => array('mode' => 'frontend', 'feature' => 'takepos.frontend', 'permission' => 'takepos.use'),
            'css/pos.css.php' => array('mode' => 'frontend', 'feature' => 'takepos.frontend', 'permission' => 'takepos.use'),
            'ajax/lang_switch.php' => array('mode' => 'ajax', 'feature' => 'takepos.frontend', 'permission' => 'takepos.use'),
            'ajax/ajax.php' => array('mode' => 'ajax', 'feature' => 'takepos.frontend', 'permission' => 'takepos.use'),
            'ajax/hold.php' => array('mode' => 'ajax', 'feature' => 'takepos.frontend', 'permission' => 'takepos.use'),
            'ajax/checkstock.php' => array('mode' => 'ajax', 'feature' => 'takepos.catalog.stock_overview', 'permission' => 'takepos.use'),
            'ajax/product_variants.php' => array('mode' => 'ajax', 'feature' => 'takepos.run', 'permission' => 'takepos.run'),
            'api/v1/stock_check.php'       => array('mode' => 'ajax', 'feature' => 'takepos.run', 'permission' => 'takepos.run'),
            'api/v1/stock_add.php'         => array('mode' => 'ajax', 'feature' => 'takepos.run', 'permission' => 'takepos.run'),
            'api/v1/stock_badges.php'      => array('mode' => 'ajax', 'feature' => 'takepos.run', 'permission' => 'takepos.run'),
            'api/v1/product_variants.php'  => array('mode' => 'ajax', 'feature' => 'takepos.run', 'permission' => 'takepos.run'),
            'api/v1/manager_override.php'  => array('mode' => 'ajax', 'feature' => 'takepos.run', 'permission' => 'takepos.run'),
            // FIX (stock-branch-v9): Stock + expiry badge endpoint for product tiles
            'ajax/product_stock_badges.php' => array('mode' => 'ajax', 'feature' => 'takepos.frontend', 'permission' => 'takepos.use'),
            'admin/setup.php' => array('mode' => 'admin', 'feature' => 'takepos.admin.setup', 'permission' => 'takepos.admin'),
            'admin/other.php' => array('mode' => 'admin', 'feature' => 'takepos.admin.other', 'permission' => 'takepos.admin'),
            'admin/bar.php' => array('mode' => 'admin', 'feature' => 'takepos.admin.bar', 'permission' => 'takepos.admin'),
            'admin/printqr.php' => array('mode' => 'admin', 'feature' => 'takepos.admin.printqr', 'permission' => 'takepos.admin'),
            'admin/appearance.php' => array('mode' => 'admin', 'feature' => 'takepos.admin.appearance', 'permission' => 'takepos.admin'),
            'admin/receipt.php' => array('mode' => 'admin', 'feature' => 'takepos.admin.receipt', 'permission' => 'takepos.admin'),
            'admin/terminal.php' => array('mode' => 'admin', 'feature' => 'takepos.admin.terminal', 'permission' => 'takepos.admin'),
            'admin/orderprinters.php' => array('mode' => 'admin', 'feature' => 'takepos.admin.orderprinters', 'permission' => 'takepos.admin'),
            'public/menu.php' => array('mode' => 'public', 'feature' => 'takepos.public_menu', 'permission' => null),
            'public/auto_order.php' => array('mode' => 'public', 'feature' => 'takepos.auto_order', 'permission' => null),
            'genimg/index.php' => array('mode' => 'public', 'feature' => 'takepos.qr', 'permission' => null),
            'genimg/qr.php' => array('mode' => 'public', 'feature' => 'takepos.qr', 'permission' => null),
            'genimg/qr_text.php' => array('mode' => 'public', 'feature' => 'takepos.qr', 'permission' => null),
        );

        if (isset($map[$relativePath])) {
            return $map[$relativePath];
        }

        if (strpos($relativePath, 'admin/') === 0) {
            return array('mode' => 'admin', 'feature' => 'takepos.admin', 'permission' => 'takepos.admin');
        }
        if (strpos($relativePath, 'ajax/') === 0) {
            return array('mode' => 'ajax', 'feature' => 'takepos.frontend', 'permission' => 'takepos.use');
        }

        return array('mode' => 'frontend', 'feature' => 'takepos.frontend', 'permission' => 'takepos.use');
    }

    function takeposAccessGuardCurrent($db, $user = null, $scriptFile = null, $context = array())
    {
        $scriptFile = $scriptFile ?: __FILE__;
        $relativePath = takeposCurrentRelativePath($scriptFile);
        $cfg = takeposGuardConfig($relativePath);

        $context = is_array($context) ? $context : array();
        $context['page'] = $relativePath;

        if ($cfg['mode'] === 'public') {
            return TakeposAccess::requirePublicFeature($db, $cfg['feature'], null, $context);
        }

        if ($cfg['mode'] === 'admin') {
            return TakeposAccess::requireAdminAccess($db, $user, $cfg['feature'], $cfg['permission'], takeposGuardTerminalId(), null, $context);
        }

        if ($cfg['mode'] === 'ajax') {
            return TakeposAccess::requireAjaxAccess($db, $user, $cfg['feature'], $cfg['permission'], takeposGuardTerminalId(), $context);
        }

        return TakeposAccess::requireFrontendAccess($db, $user, $cfg['feature'], $cfg['permission'], takeposGuardTerminalId(), null, $context);
    }
}
