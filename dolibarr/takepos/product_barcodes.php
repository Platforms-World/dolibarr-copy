<?php
/**
 * Product barcode alias manager for TakePOS.
 */
require '../main.inc.php';

require_once __DIR__ . '/lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/class/TakeposAccess.class.php';
require_once __DIR__ . '/class/TakeposProductBarcodeService.class.php';

$langs->loadLangs(array('main', 'products', 'admin', 'takeposcustom@takepos'));

$sessionTerminalToken = isset($_SESSION['takeposterminal']) ? (string) $_SESSION['takeposterminal'] : '1';
$entity = !empty($user->entity) ? (int) $user->entity : 1;
$pageUrl = DOL_URL_ROOT . '/takepos/product_barcodes.php';

TakeposAccess::requireFrontendAccess(
    $db,
    $user,
    'takepos.catalog.manage_products',
    'takepos.use',
    (int) $sessionTerminalToken,
    $langs->trans('TakeposProductBarcodeAccessDenied'),
    array('page' => 'product_barcodes.php')
);

if (!TakeposProductBarcodeService::canRead($user)) {
    TakeposAccess::denyAccess($db, $user, $langs->trans('TakeposProductBarcodeAccessDenied'), array('page' => 'product_barcodes.php', 'permission' => 'produit.lire'));
}

TakeposProductBarcodeService::ensureSchema($db);

$canWrite = TakeposProductBarcodeService::canWrite($user);
$action = GETPOST('action', 'aZ09');
$search = trim((string) GETPOST('search', 'none'));
$productId = GETPOSTINT('product_id');
$editAliasId = GETPOSTINT('edit_alias_id');
$message = '';
$messageType = 'mesgs';

try {
    if ($action !== '' && GETPOST('token', 'alpha') !== (isset($_SESSION['newtoken']) ? (string) $_SESSION['newtoken'] : '')) {
        throw new Exception($langs->trans('TakeposExpenseInvalidSecurityToken'));
    }

    if ($action === 'add_alias') {
        if (!$canWrite) {
            throw new Exception($langs->trans('TakeposProductBarcodeAccessDenied'));
        }

        $productId = GETPOSTINT('product_id');
        TakeposProductBarcodeService::addAlias($db, $user, $productId, GETPOST('barcode_alias', 'none'));
        $message = $langs->trans('TakeposProductBarcodeSaved');
    }

    if ($action === 'update_alias') {
        if (!$canWrite) {
            throw new Exception($langs->trans('TakeposProductBarcodeAccessDenied'));
        }

        $productId = GETPOSTINT('product_id');
        $editAliasId = GETPOSTINT('alias_id');
        TakeposProductBarcodeService::updateAlias($db, $user, $productId, $editAliasId, GETPOST('barcode_alias', 'none'));
        $message = $langs->trans('TakeposCommonUpdated');
        $editAliasId = 0;
    }

    if ($action === 'delete_alias') {
        if (!$canWrite) {
            throw new Exception($langs->trans('TakeposProductBarcodeAccessDenied'));
        }

        $productId = GETPOSTINT('product_id');
        TakeposProductBarcodeService::deleteAlias($db, $user, $productId, GETPOSTINT('alias_id'));
        $message = $langs->trans('TakeposProductBarcodeDeleted');
    }
} catch (Throwable $e) {
    $message = $e->getMessage();
    $messageType = 'errors';
}

$selectedProduct = ($productId > 0 ? TakeposProductBarcodeService::getProduct($db, $entity, $productId) : null);
$aliases = ($selectedProduct ? TakeposProductBarcodeService::listAliases($db, $entity, (int) $selectedProduct->rowid) : array());
$editingAlias = ($selectedProduct && $editAliasId > 0 ? TakeposProductBarcodeService::getAlias($db, $entity, (int) $selectedProduct->rowid, $editAliasId) : null);
$products = TakeposProductBarcodeService::searchProducts($db, $entity, $search, 50);

if ($productId > 0 && !$selectedProduct && $message === '') {
    $message = $langs->trans('TakeposProductBarcodeProductNotFound');
    $messageType = 'errors';
}
if ($selectedProduct && $editAliasId > 0 && !$editingAlias && $message === '') {
    $message = $langs->trans('TakeposProductBarcodeAliasRequired');
    $messageType = 'errors';
}

$selfUrl = $pageUrl;
$manageProductsUrl = DOL_URL_ROOT . '/takepos/workspace.php?key=manage_products';

llxHeader('', $langs->trans('TakeposProductBarcodeTitle'));
print load_fiche_titre($langs->trans('TakeposProductBarcodeTitle'), '', 'title_setup');

print '<div class="tabsAction">';
print '<a class="butAction" href="' . dol_escape_htmltag($selfUrl) . '">' . dol_escape_htmltag($langs->trans('TakeposCommonReset')) . '</a>';
print '<a class="butAction" href="' . dol_escape_htmltag($manageProductsUrl) . '">' . dol_escape_htmltag($langs->trans('TakeposShortcutManageProducts')) . '</a>';
print '</div>';

if ($message !== '') {
    setEventMessages($message, null, $messageType);
}

print '<form method="GET" action="' . dol_escape_htmltag($selfUrl) . '">';
print '<table class="border centpercent">';
print '<tr class="liste_titre"><th colspan="2">' . dol_escape_htmltag($langs->trans('TakeposProductBarcodeSearchProducts')) . '</th></tr>';
print '<tr><td class="titlefield">' . dol_escape_htmltag($langs->trans('TakeposCommonSearch')) . '</td><td><input type="text" name="search" class="minwidth300" maxlength="128" value="' . dol_escape_htmltag($search) . '" placeholder="' . dol_escape_htmltag($langs->trans('TakeposProductBarcodeSearchPlaceholder')) . '">';
if ($selectedProduct) {
    print '<input type="hidden" name="product_id" value="' . ((int) $selectedProduct->rowid) . '">';
}
print '</td></tr>';
print '</table>';
print '<div class="tabsAction"><input type="submit" class="button button-save" value="' . dol_escape_htmltag($langs->trans('TakeposHistoryApplyFilters')) . '"></div>';
print '</form>';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>' . dol_escape_htmltag($langs->trans('Ref')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposCommonLabel')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposProductBarcodePrimary')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposCommonActions')) . '</th></tr>';
if (empty($products)) {
    print '<tr class="oddeven"><td colspan="4">' . dol_escape_htmltag($langs->trans('TakeposHistoryNoData')) . '</td></tr>';
} else {
    foreach ($products as $product) {
        $selectParams = array(
            'search' => $search,
            'product_id' => (int) $product->rowid,
        );
        $productCardUrl = DOL_URL_ROOT . '/product/card.php?id=' . ((int) $product->rowid);
        print '<tr class="oddeven">';
        print '<td>' . dol_escape_htmltag((string) $product->ref) . '</td>';
        print '<td>' . dol_escape_htmltag((string) $product->label) . '</td>';
        print '<td>' . dol_escape_htmltag((string) $product->barcode) . '</td>';
        print '<td><a class="button" href="' . dol_escape_htmltag($selfUrl . '?' . http_build_query($selectParams)) . '">' . dol_escape_htmltag($langs->trans('TakeposCommonSelect')) . '</a> <a class="button" href="' . dol_escape_htmltag($productCardUrl) . '">' . dol_escape_htmltag($langs->trans('TakeposCommonOpen')) . '</a></td>';
        print '</tr>';
    }
}
print '</table>';
print '</div>';

if ($selectedProduct) {
    print '<br>';
    print '<table class="border centpercent">';
    print '<tr class="liste_titre"><th colspan="2">' . dol_escape_htmltag($langs->trans('TakeposProductBarcodeTitle')) . '</th></tr>';
    print '<tr><td class="titlefield">' . dol_escape_htmltag($langs->trans('Ref')) . '</td><td>' . dol_escape_htmltag((string) $selectedProduct->ref) . '</td></tr>';
    print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposCommonLabel')) . '</td><td>' . dol_escape_htmltag((string) $selectedProduct->label) . '</td></tr>';
    print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposProductBarcodePrimary')) . '</td><td>' . dol_escape_htmltag((string) $selectedProduct->barcode) . '</td></tr>';
    print '</table>';

    if ($canWrite) {
        $formQuery = array('search' => $search, 'product_id' => (int) $selectedProduct->rowid);
        if ($editingAlias) {
            $formQuery['edit_alias_id'] = (int) $editingAlias->rowid;
        }
        print '<form method="POST" action="' . dol_escape_htmltag($selfUrl . '?' . http_build_query($formQuery)) . '">';
        print '<input type="hidden" name="token" value="' . newToken() . '">';
        print '<input type="hidden" name="action" value="' . ($editingAlias ? 'update_alias' : 'add_alias') . '">';
        print '<input type="hidden" name="product_id" value="' . ((int) $selectedProduct->rowid) . '">';
        if ($editingAlias) {
            print '<input type="hidden" name="alias_id" value="' . ((int) $editingAlias->rowid) . '">';
        }
        print '<table class="border centpercent">';
        print '<tr class="liste_titre"><th colspan="2">' . dol_escape_htmltag($editingAlias ? $langs->trans('TakeposCommonEdit') : $langs->trans('TakeposProductBarcodeAdd')) . '</th></tr>';
        print '<tr><td class="titlefield">' . dol_escape_htmltag($langs->trans('TakeposProductBarcodeValue')) . '</td><td><input type="text" name="barcode_alias" class="minwidth300" maxlength="190" value="' . dol_escape_htmltag($editingAlias ? (string) $editingAlias->barcode : '') . '"></td></tr>';
        print '</table>';
        print '<div class="tabsAction"><input type="submit" class="button button-save" value="' . dol_escape_htmltag($editingAlias ? $langs->trans('TakeposCommonUpdate') : $langs->trans('TakeposProductBarcodeAdd')) . '">';
        if ($editingAlias) {
            print ' <a class="button button-cancel" href="' . dol_escape_htmltag($selfUrl . '?' . http_build_query(array('search' => $search, 'product_id' => (int) $selectedProduct->rowid))) . '">' . dol_escape_htmltag($langs->trans('TakeposCommonCancel')) . '</a>';
        }
        print '</div>';
        print '</form>';
    }

    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><th>' . dol_escape_htmltag($langs->trans('TakeposProductBarcodeAliases')) . '</th><th>' . dol_escape_htmltag($langs->trans('DateCreation')) . '</th><th>' . dol_escape_htmltag($langs->trans('DateModification')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposCommonActions')) . '</th></tr>';
    if (empty($aliases)) {
        print '<tr class="oddeven"><td colspan="4">' . dol_escape_htmltag($langs->trans('TakeposProductBarcodeNoAliases')) . '</td></tr>';
    } else {
        foreach ($aliases as $alias) {
            $editParams = array(
                'search' => $search,
                'product_id' => (int) $selectedProduct->rowid,
                'edit_alias_id' => (int) $alias->rowid,
            );
            print '<tr class="oddeven">';
            print '<td>' . dol_escape_htmltag((string) $alias->barcode) . '</td>';
            print '<td>' . dol_escape_htmltag((string) $alias->date_creation) . '</td>';
            print '<td>' . dol_escape_htmltag((string) $alias->tms) . '</td>';
            print '<td>';
            if ($canWrite) {
                print '<a class="button" href="' . dol_escape_htmltag($selfUrl . '?' . http_build_query($editParams)) . '">' . dol_escape_htmltag($langs->trans('TakeposCommonEdit')) . '</a> ';
                print '<form method="POST" action="' . dol_escape_htmltag($selfUrl . '?' . http_build_query(array('search' => $search, 'product_id' => (int) $selectedProduct->rowid))) . '" style="display:inline-block;" onsubmit="return confirm(\'' . dol_escape_js($langs->trans('TakeposProductBarcodeDeleteConfirm')) . '\');">';
                print '<input type="hidden" name="token" value="' . newToken() . '">';
                print '<input type="hidden" name="action" value="delete_alias">';
                print '<input type="hidden" name="product_id" value="' . ((int) $selectedProduct->rowid) . '">';
                print '<input type="hidden" name="alias_id" value="' . ((int) $alias->rowid) . '">';
                print '<input type="submit" class="button button-cancel" value="' . dol_escape_htmltag($langs->trans('TakeposProductBarcodeDelete')) . '">';
                print '</form>';
            } else {
                print '-';
            }
            print '</td>';
            print '</tr>';
        }
    }
    print '</table>';
    print '</div>';
}

llxFooter();
$db->close();
