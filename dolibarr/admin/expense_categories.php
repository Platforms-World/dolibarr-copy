<?php
/**
 * Expense categories admin page.
 */
require '../../main.inc.php';

require_once __DIR__ . '/../lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/../class/TakeposAccess.class.php';
require_once __DIR__ . '/../class/TakeposExpenseService.class.php';

$langs->loadLangs(array('admin', 'main', 'cashdesk', 'takeposcustom@takepos'));

restrictedArea($user, 'takepos', 0, '');
TakeposAccess::requireFrontendAccess(
    $db,
    $user,
    'takepos.cash_control',
    'takepos.use',
    isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null,
    $langs->trans('TakeposExpenseCategoriesAccessDenied')
);

if (!TakeposExpenseService::canAdmin($db, $user)) {
    TakeposAccess::denyAccess($db, $user, $langs->trans('TakeposExpenseAdminPermissionRequired'), array('page' => 'admin/expense_categories.php', 'permission' => 'expensereport'));
}

$entity = !empty($user->entity) ? (int) $user->entity : 1;
TakeposExpenseService::ensureSchema($db);

$action = GETPOST('action', 'aZ09');
$editId = GETPOSTINT('id');
if ($editId <= 0) {
    $editId = GETPOSTINT('category_id');
}
$message = '';
$messageType = 'mesgs';

try {
    if ($action !== '' && GETPOST('token', 'alpha') !== (isset($_SESSION['newtoken']) ? $_SESSION['newtoken'] : '')) {
        throw new Exception($langs->trans('TakeposExpenseInvalidSecurityToken'));
    }

    if ($action === 'create') {
        $newId = TakeposExpenseService::saveCategory($db, $user, $entity, $_POST, 0);
        $message = sprintf($langs->trans('TakeposExpenseCategoryCreatedSuccess'), (int) $newId);
        $editId = $newId;
    }

    if ($action === 'update') {
        $categoryId = GETPOSTINT('category_id');
        TakeposExpenseService::saveCategory($db, $user, $entity, $_POST, $categoryId);
        $message = $langs->trans('TakeposExpenseCategoryUpdatedSuccess');
        $editId = $categoryId;
    }

    if ($action === 'disable') {
        $categoryId = GETPOSTINT('category_id');
        TakeposExpenseService::setCategoryStatus($db, $user, $entity, $categoryId, 0);
        $message = $langs->trans('TakeposExpenseCategoryDisabledSuccess');
        if ($editId === $categoryId) {
            $editId = 0;
        }
    }

    if ($action === 'enable') {
        $categoryId = GETPOSTINT('category_id');
        TakeposExpenseService::setCategoryStatus($db, $user, $entity, $categoryId, 1);
        $message = $langs->trans('TakeposExpenseCategoryEnabledSuccess');
    }
} catch (Throwable $e) {
    $message = $e->getMessage();
    $messageType = 'errors';
}

$categories = TakeposExpenseService::listCategories($db, $entity, false);
$editingCategory = ($editId > 0 ? TakeposExpenseService::getCategory($db, $entity, $editId) : null);

$formValues = array(
    'category_id' => ($editingCategory ? (int) $editingCategory->rowid : GETPOSTINT('category_id')),
    'label' => ($editingCategory ? (string) $editingCategory->label : (string) GETPOST('label', 'none')),
    'accountancy_code' => ($editingCategory ? (string) $editingCategory->accountancy_code : (string) GETPOST('accountancy_code', 'none')),
    'vat_default' => ($editingCategory ? (string) price2num((string) $editingCategory->vat_default, 'MU') : (GETPOST('vat_default', 'none') !== '' ? (string) GETPOST('vat_default', 'none') : '0')),
    'active' => ($editingCategory ? (int) $editingCategory->active : ($action !== '' ? GETPOSTINT('active') : 1)),
    'pos_visible' => ($editingCategory ? (int) $editingCategory->pos_visible : ($action !== '' ? GETPOSTINT('pos_visible') : 1)),
);

$selfUrl = DOL_URL_ROOT . '/takepos/admin/expense_categories.php';
$expensesUrl = DOL_URL_ROOT . '/takepos/expenses.php';

llxHeader('', $langs->trans('TakeposExpenseCategoriesTitle'));
print load_fiche_titre($langs->trans('TakeposExpenseCategoriesTitle'), '', 'title_setup');

print '<div class="tabsAction">';
print '<a class="butAction" href="' . dol_escape_htmltag($expensesUrl) . '">' . dol_escape_htmltag($langs->trans('TakeposExpensePosExpenses')) . '</a>';
print '<a class="butAction" href="' . dol_escape_htmltag($selfUrl) . '">' . dol_escape_htmltag($langs->trans('TakeposExpenseCategoriesRefresh')) . '</a>';
print '</div>';

if ($message !== '') {
    setEventMessages($message, null, $messageType);
}

print '<form method="POST" action="' . dol_escape_htmltag($selfUrl . ($editId > 0 ? '?id=' . ((int) $editId) : '')) . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="' . ($editingCategory ? 'update' : 'create') . '">';
print '<input type="hidden" name="category_id" value="' . ((int) $formValues['category_id']) . '">';
print '<table class="border centpercent">';
print '<tr class="liste_titre"><th colspan="2">' . dol_escape_htmltag($editingCategory ? $langs->trans('TakeposExpenseCategoriesUpdateTitle') : $langs->trans('TakeposExpenseCategoriesCreateTitle')) . '</th></tr>';
print '<tr><td class="titlefield">' . dol_escape_htmltag($langs->trans('TakeposCommonLabel')) . '</td><td><input type="text" name="label" required maxlength="128" class="minwidth300" value="' . dol_escape_htmltag($formValues['label']) . '"></td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposExpenseCategoriesAccountCode')) . '</td><td><input type="text" name="accountancy_code" required maxlength="64" class="minwidth200" value="' . dol_escape_htmltag($formValues['accountancy_code']) . '"></td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposExpenseCategoriesDefaultVatRate')) . '</td><td><input type="number" step="0.01" min="0" name="vat_default" value="' . dol_escape_htmltag($formValues['vat_default']) . '"></td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposCommonActive')) . '</td><td><select name="active"><option value="1"' . ((int) $formValues['active'] === 1 ? ' selected' : '') . '>' . dol_escape_htmltag($langs->trans('TakeposCommonYes')) . '</option><option value="0"' . ((int) $formValues['active'] === 0 ? ' selected' : '') . '>' . dol_escape_htmltag($langs->trans('TakeposCommonNo')) . '</option></select></td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposExpenseCategoriesVisibleInPos')) . '</td><td><select name="pos_visible"><option value="1"' . ((int) $formValues['pos_visible'] === 1 ? ' selected' : '') . '>' . dol_escape_htmltag($langs->trans('TakeposCommonYes')) . '</option><option value="0"' . ((int) $formValues['pos_visible'] === 0 ? ' selected' : '') . '>' . dol_escape_htmltag($langs->trans('TakeposCommonNo')) . '</option></select></td></tr>';
print '</table>';
print '<div class="tabsAction">';
print '<input type="submit" class="button button-save" value="' . dol_escape_htmltag($editingCategory ? $langs->trans('TakeposCommonUpdate') : $langs->trans('TakeposCommonSave')) . '">';
if ($editingCategory) {
    print '<a class="button button-cancel" href="' . dol_escape_htmltag($selfUrl) . '">' . dol_escape_htmltag($langs->trans('TakeposCommonCancel')) . '</a>';
}
print '</div>';
print '</form>';

print '<br><div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>' . dol_escape_htmltag($langs->trans('TakeposCommonLabel')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposExpenseCategoriesAccountCode')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposExpenseCategoriesDefaultVatRate')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposCommonActive')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposExpenseCategoriesPosVisible')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposExpenseCategoriesLastUpdate')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposCommonEdit')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposCommonStatus')) . '</th></tr>';
if (empty($categories)) {
    print '<tr class="oddeven"><td colspan="8">' . dol_escape_htmltag($langs->trans('TakeposExpenseCategoriesNoData')) . '</td></tr>';
} else {
    foreach ($categories as $category) {
        $toggleConfirm = ((int) $category->active === 1 ? $langs->trans('TakeposExpenseCategoryDisableConfirm') : $langs->trans('TakeposExpenseCategoryEnableConfirm'));
        $toggleLabel = ((int) $category->active === 1 ? $langs->trans('TakeposCommonDisable') : $langs->trans('TakeposCommonEnable'));
        print '<tr class="oddeven">';
        print '<td>' . dol_escape_htmltag($category->label) . '</td>';
        print '<td>' . dol_escape_htmltag($category->accountancy_code) . '</td>';
        print '<td>' . dol_escape_htmltag((string) price2num((string) $category->vat_default, 'MU')) . '%</td>';
        print '<td>' . dol_escape_htmltag((int) $category->active === 1 ? $langs->trans('TakeposCommonYes') : $langs->trans('TakeposCommonNo')) . '</td>';
        print '<td>' . dol_escape_htmltag((int) $category->pos_visible === 1 ? $langs->trans('TakeposCommonYes') : $langs->trans('TakeposCommonNo')) . '</td>';
        print '<td>' . dol_escape_htmltag(!empty($category->tms) ? (string) $category->tms : (!empty($category->datec) ? (string) $category->datec : '-')) . '</td>';
        print '<td><a class="button" href="' . dol_escape_htmltag($selfUrl . '?id=' . ((int) $category->rowid)) . '">' . dol_escape_htmltag($langs->trans('TakeposCommonEdit')) . '</a></td>';
        print '<td>';
        print '<form method="POST" action="' . dol_escape_htmltag($selfUrl) . '" style="display:inline-block;" onsubmit="return confirm(\'' . dol_escape_js($toggleConfirm) . '\');">';
        print '<input type="hidden" name="token" value="' . newToken() . '">';
        print '<input type="hidden" name="action" value="' . ((int) $category->active === 1 ? 'disable' : 'enable') . '">';
        print '<input type="hidden" name="category_id" value="' . ((int) $category->rowid) . '">';
        print '<input type="submit" class="button ' . ((int) $category->active === 1 ? 'button-cancel' : 'button') . '" value="' . dol_escape_htmltag($toggleLabel) . '">';
        print '</form>';
        print '</td>';
        print '</tr>';
    }
}
print '</table>';
print '</div>';

llxFooter();
$db->close();
