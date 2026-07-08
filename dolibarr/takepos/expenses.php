<?php
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}

require '../main.inc.php';

require_once __DIR__ . '/lib/takepos_lang.php';
require_once __DIR__ . '/lib/takepos_help.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/class/TakeposAccess.class.php';
require_once __DIR__ . '/class/TakeposAudit.class.php';
require_once __DIR__ . '/class/TakeposUserAccess.class.php';
require_once __DIR__ . '/class/TakeposInputValidator.class.php';
require_once __DIR__ . '/class/TakeposExpenseService.class.php';
require_once __DIR__ . '/class/TakeposTerminalService.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

$langs->loadLangs(array('cashdesk', 'main', 'banks', 'admin', 'takeposcustom@takepos'));

$sessionTerminalToken = isset($_SESSION['takeposterminal']) ? (string) $_SESSION['takeposterminal'] : '1';
$pageUrl = DOL_URL_ROOT . '/takepos/expenses.php';
TakeposAccess::requireFrontendAccess(
    $db,
    $user,
    'takepos.cash_control',
    'takepos.use',
    (int) $sessionTerminalToken,
    $langs->trans('TakeposExpenseAccessDenied'),
    array('page' => 'expenses.php')
);

if (!TakeposExpenseService::canRead($db, $user)) {
    TakeposAccess::denyAccess($db, $user, $langs->trans('TakeposExpenseReadPermissionRequired'), array('page' => 'expenses.php', 'permission' => 'takepos.expense.read'));
}

TakeposAudit::logEvent($db, $user, 'expense_screen_opened', TakeposAudit::SEVERITY_INFO, array('page' => 'expenses.php'), 'POS expense screen opened');

TakeposExpenseService::ensureSchema($db);

$entity = !empty($user->entity) ? (int) $user->entity : 1;
$form = new Form($db);
$canCreateExpense = TakeposExpenseService::canCreate($db, $user);
$canPostExpense = TakeposExpenseService::canPost($db, $user);
$canAdminExpense = TakeposExpenseService::canAdmin($db, $user);
$currentTerminal = TakeposExpenseService::getCurrentTerminal($db, $user, $sessionTerminalToken);
$terminals = TakeposExpenseService::listAccessibleTerminals($db, $user, true);
$categories = TakeposExpenseService::listCategories($db, $entity, true);
$paymentSources = TakeposExpenseService::listPaymentSources();
$bankAccounts = TakeposExpenseService::listBankAccounts($db, $entity);
$bankAccountMap = array();
foreach ($bankAccounts as $bankAccountRow) {
    $bankAccountMap[(int) $bankAccountRow->rowid] = trim((string) $bankAccountRow->ref . ' - ' . (string) $bankAccountRow->label);
}

$messages = array();
$errors = array();
$expenseId = GETPOSTINT('id');
$action = GETPOST('action', 'alpha');

if (!empty($_GET['result'])) {
    $resultFlag = (string) GETPOST('result', 'alpha');
    if ($resultFlag === 'saved') {
        $messages[] = $langs->trans('TakeposExpenseSavedSuccess');
    } elseif ($resultFlag === 'posted') {
        $messages[] = $langs->trans('TakeposExpenseSavedPostedSuccess');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) GETPOST('token', 'alpha');
    $sessionToken = isset($_SESSION['newtoken']) ? (string) $_SESSION['newtoken'] : '';
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        $errors[] = $langs->trans('TakeposExpenseInvalidCsrf');
    } else {
        try {
            if ($action === 'save') {
                if (!$canCreateExpense) {
                    throw new Exception($langs->trans('TakeposExpenseCreatePermissionRequiredToSave'));
                }

                $expenseId = TakeposExpenseService::saveExpense($db, $user, $_POST, GETPOSTINT('expense_id'));
                header('Location: ' . $pageUrl . '?id=' . ((int) $expenseId) . '&result=saved');
                exit;
            }

            if ($action === 'save_post') {
                $existingExpenseId = GETPOSTINT('expense_id');
                if ($existingExpenseId > 0 && !$canCreateExpense && $canPostExpense) {
                    $expenseId = $existingExpenseId;
                } else {
                    if (!$canCreateExpense) {
                        throw new Exception($langs->trans('TakeposExpenseCreatePermissionRequiredBeforePosting'));
                    }
                    $expenseId = TakeposExpenseService::saveExpense($db, $user, $_POST, $existingExpenseId);
                }

                if (!$canPostExpense) {
                    throw new Exception($langs->trans('TakeposExpensePostingPermissionRequired'));
                }

                TakeposExpenseService::postExpense($db, $user, $expenseId, $sessionTerminalToken);
                header('Location: ' . $pageUrl . '?id=' . ((int) $expenseId) . '&result=posted');
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
            if (GETPOSTINT('expense_id') > 0) {
                $expenseId = GETPOSTINT('expense_id');
            }
        }
    }
}

$currentExpense = ($expenseId > 0 ? TakeposExpenseService::getExpenseById($db, $entity, $expenseId) : null);
$recentExpenses = TakeposExpenseService::listExpenses($db, $entity, array('fk_user' => (int) $user->id), 12);
$categoryOptions = array();
foreach ($categories as $category) {
    $categoryOptions[(int) $category->rowid] = $category;
}

$defaultCategory = reset($categories);
$selectedCategoryId = ($currentExpense ? (int) $currentExpense->fk_category : (GETPOSTINT('fk_category') > 0 ? GETPOSTINT('fk_category') : (!empty($defaultCategory->rowid) ? (int) $defaultCategory->rowid : 0)));
$selectedCategory = ($selectedCategoryId > 0 && isset($categoryOptions[$selectedCategoryId])) ? $categoryOptions[$selectedCategoryId] : null;

$formValues = array(
    'expense_id' => ($currentExpense ? (int) $currentExpense->rowid : GETPOSTINT('expense_id')),
    'date_expense' => ($currentExpense ? date('Y-m-d\TH:i', strtotime((string) $currentExpense->date_expense)) : (GETPOST('date_expense', 'none') !== '' ? (string) GETPOST('date_expense', 'none') : date('Y-m-d\TH:i'))),
    'fk_terminal' => ($currentExpense ? (int) $currentExpense->fk_terminal : (GETPOSTINT('fk_terminal') > 0 ? GETPOSTINT('fk_terminal') : (!empty($currentTerminal->rowid) ? (int) $currentTerminal->rowid : 0))),
    'fk_category' => $selectedCategoryId,
    'description' => ($currentExpense ? (string) $currentExpense->description : (string) GETPOST('description', 'none')),
    'amount_ttc' => ($currentExpense ? price((float) $currentExpense->amount_ttc, 0, '', 1, 0, 0, '', 0, 0) : (string) GETPOST('amount_ttc', 'none')),
    'vat_rate' => ($currentExpense ? (string) price2num((string) $currentExpense->vat_rate, 'MU') : (GETPOST('vat_rate', 'none') !== '' ? (string) GETPOST('vat_rate', 'none') : ($selectedCategory ? (string) price2num((string) $selectedCategory->vat_default, 'MU') : '0'))),
    'payment_source' => ($currentExpense ? (string) $currentExpense->payment_source : (GETPOST('payment_source', 'alpha') !== '' ? (string) GETPOST('payment_source', 'alpha') : TakeposExpenseService::SOURCE_CASH_REGISTER)),
    'note_private' => ($currentExpense ? (string) $currentExpense->note_private : (string) GETPOST('note_private', 'none')),
    'external_ref' => ($currentExpense ? (string) $currentExpense->external_ref : (string) GETPOST('external_ref', 'none')),
    'fk_bank_account' => ($currentExpense ? (int) $currentExpense->fk_bank_account : GETPOSTINT('fk_bank_account')),
);

$selectedTerminalToken = $sessionTerminalToken;
if ($currentExpense && !empty($currentExpense->terminal_code)) {
    $selectedTerminalToken = (string) $currentExpense->terminal_code;
} elseif (!empty($formValues['fk_terminal'])) {
    foreach ($terminals as $terminalOption) {
        if ((int) $terminalOption->rowid === (int) $formValues['fk_terminal']) {
            $selectedTerminalToken = (string) $terminalOption->terminal_code;
            break;
        }
    }
}
$currentCashBankAccountId = (int) getDolGlobalInt('CASHDESK_ID_BANKACCOUNT_CASH' . $selectedTerminalToken);
$currentCashBankAccountLabel = ($currentCashBankAccountId > 0 && isset($bankAccountMap[$currentCashBankAccountId])) ? $bankAccountMap[$currentCashBankAccountId] : '';

$title = $langs->trans('TakeposExpenseTitle');
$head = '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
$arrayofcss = array('/takepos/css/workspace.css');
top_htmlhead($head, $title, 0, 0, array(), $arrayofcss);
?>
<body class="takepos-workspace-reports-body">
<div class="takepos-workspace-reports-page">
    <h2 class="takepos-workspace-title"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseTitle')); ?></h2>

    <?php foreach ($messages as $message) { ?>
        <div class="ok"><?php echo dol_escape_htmltag($message); ?></div>
    <?php } ?>
    <?php foreach ($errors as $errorMessage) { ?>
        <div class="error"><?php echo dol_escape_htmltag($errorMessage); ?></div>
    <?php } ?>

    <?php if (empty($categories)) { ?>
        <div class="warning">
            <?php echo dol_escape_htmltag($langs->trans('TakeposExpenseNoActiveCategories')); ?>
            <?php if ($canAdminExpense) { ?>
                <a href="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/workspace.php?key=admin_expense_categories'); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseConfigureCategories')); ?></a>.
            <?php } else { ?>
                <?php echo dol_escape_htmltag($langs->trans('TakeposExpenseAskAdminConfigure')); ?>
            <?php } ?>
        </div>
    <?php } ?>

    <section class="takepos-workspace-panel">
        <h3><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseDetails')); ?></h3>
        <form method="post" action="<?php echo dol_escape_htmltag($pageUrl . ($expenseId > 0 ? '?id=' . ((int) $expenseId) : '')); ?>">
            <input type="hidden" name="token" value="<?php echo dol_escape_htmltag(newToken()); ?>">
            <input type="hidden" name="expense_id" value="<?php echo (int) $formValues['expense_id']; ?>">

            <div class="takepos-workspace-filter-grid">
                <div>
                    <label for="date_expense"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseDate')); ?></label>
                    <input type="datetime-local" id="date_expense" name="date_expense" value="<?php echo dol_escape_htmltag($formValues['date_expense']); ?>"<?php echo $canAdminExpense ? '' : ' readonly'; ?>>
                </div>
                <div>
                    <label for="fk_terminal"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseTerminal')); ?></label>
                    <select id="fk_terminal" name="fk_terminal">
                        <?php foreach ($terminals as $terminal) { ?>
                            <option value="<?php echo (int) $terminal->rowid; ?>"<?php echo ((int) $formValues['fk_terminal'] === (int) $terminal->rowid ? ' selected' : ''); ?>>
                                <?php echo dol_escape_htmltag($terminal->terminal_code . ' - ' . $terminal->label); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div>
                    <label><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseUser')); ?></label>
                    <input type="text" value="<?php echo dol_escape_htmltag(!empty($user->login) ? $user->login : $user->firstname . ' ' . $user->lastname); ?>" readonly>
                </div>
                <div>
                    <label for="fk_category"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseCategory')); ?></label>
                    <select id="fk_category" name="fk_category">
                        <option value=""><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseSelectCategory')); ?></option>
                        <?php foreach ($categories as $category) { ?>
                            <?php $catLabel = $category->label . (trim((string) $category->accountancy_code) !== '' ? ' [' . trim((string) $category->accountancy_code) . ']' : ''); ?>
                            <option value="<?php echo (int) $category->rowid; ?>" data-vat-default="<?php echo dol_escape_htmltag((string) price2num((string) $category->vat_default, 'MU')); ?>"<?php echo ((int) $formValues['fk_category'] === (int) $category->rowid ? ' selected' : ''); ?>>
                                <?php echo dol_escape_htmltag($catLabel); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div>
                    <label for="description"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseDescription')); ?></label>
                    <input type="text" id="description" name="description" maxlength="255" value="<?php echo dol_escape_htmltag($formValues['description']); ?>" required>
                </div>
                <div>
                    <label for="amount_ttc"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseAmount')); ?></label>
                    <input type="number" id="amount_ttc" name="amount_ttc" min="0.01" step="0.01" value="<?php echo dol_escape_htmltag($formValues['amount_ttc'] !== '' ? $formValues['amount_ttc'] : '0.00'); ?>" required>
                </div>
                <div>
                    <label for="vat_rate"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseVatRate')); ?></label>
                    <input type="number" id="vat_rate" name="vat_rate" min="0" max="100" step="0.01" value="<?php echo dol_escape_htmltag($formValues['vat_rate']); ?>" required>
                </div>
                <div>
                    <label for="payment_source"><?php echo dol_escape_htmltag($langs->trans('TakeposExpensePaymentSource')); ?></label>
                    <select id="payment_source" name="payment_source">
                        <?php foreach ($paymentSources as $sourceCode => $sourceLabel) { ?>
                            <option value="<?php echo dol_escape_htmltag($sourceCode); ?>"<?php echo ($formValues['payment_source'] === $sourceCode ? ' selected' : ''); ?>>
                                <?php echo dol_escape_htmltag($sourceLabel); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div>
                    <label for="fk_bank_account"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseBankSourceAccount')); ?></label>
                    <select id="fk_bank_account" name="fk_bank_account">
                        <option value=""><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseUseTerminalCashAccount')); ?></option>
                        <?php foreach ($bankAccounts as $bankAccount) { ?>
                            <option value="<?php echo (int) $bankAccount->rowid; ?>"<?php echo ((int) $formValues['fk_bank_account'] === (int) $bankAccount->rowid ? ' selected' : ''); ?>>
                                <?php echo dol_escape_htmltag(trim((string) $bankAccount->ref . ' - ' . (string) $bankAccount->label)); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div>
                    <label for="external_ref"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseReferenceReceipt')); ?></label>
                    <input type="text" id="external_ref" name="external_ref" maxlength="128" value="<?php echo dol_escape_htmltag($formValues['external_ref']); ?>">
                </div>
                <div style="grid-column: 1 / -1;">
                    <label for="note_private"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseNote')); ?></label>
                    <textarea id="note_private" name="note_private" rows="3"><?php echo dol_escape_htmltag($formValues['note_private']); ?></textarea>
                </div>
            </div>

            <div class="takepos-workspace-filter-actions">
                <?php if ($canCreateExpense && !empty($categories)) { ?>
                    <button type="submit" name="action" value="save" class="button"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseSaveDraft')); ?></button>
                <?php } ?>
                <?php if ($canPostExpense && !empty($categories)) { ?>
                    <button type="submit" name="action" value="save_post" class="button button-save"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseSaveAndPost')); ?></button>
                <?php } ?>
                <button type="button" class="button button-cancel" onclick="takeposCloseExpenseScreen();"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonCancelBack')); ?></button>
            </div>
        </form>
    </section>

    <section class="takepos-workspace-panel">
        <h3><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseAccountingSummary')); ?></h3>
        <div class="takepos-workspace-card" style="max-width:none;">
            <div class="pos-expense-summary-grid">
                <div><strong><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseCategoryAccount')); ?></strong><br><?php echo dol_escape_htmltag($currentExpense && $currentExpense->accountancy_code !== '' ? $currentExpense->accountancy_code : ($selectedCategory ? trim((string) $selectedCategory->accountancy_code) : $langs->trans('TakeposExpenseNotMapped'))); ?></div>
                <div><strong><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseTerminalCashAccount')); ?></strong><br><?php echo dol_escape_htmltag($currentCashBankAccountLabel !== '' ? $currentCashBankAccountLabel : $langs->trans('TakeposExpenseNotConfigured')); ?></div>
                <div><strong><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseAttachment')); ?></strong><br><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseAttachmentPlaceholder')); ?></div>
            </div>
        </div>
    </section>

    <section class="takepos-workspace-panel">
        <h3><?php echo dol_escape_htmltag($langs->trans('TakeposExpensePostingStatus')); ?></h3>
        <?php if ($currentExpense) { ?>
            <div class="takepos-workspace-table-wrap">
                <table class="takepos-workspace-table">
                    <tbody>
                    <tr><th><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseRef')); ?></th><td><?php echo dol_escape_htmltag($currentExpense->ref); ?></td></tr>
                    <tr><th><?php echo dol_escape_htmltag($langs->trans('TakeposCommonStatus')); ?></th><td><?php echo dol_escape_htmltag(TakeposExpenseService::statusLabel((int) $currentExpense->status)); ?></td></tr>
                    <tr><th><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseCategory')); ?></th><td><?php echo dol_escape_htmltag($currentExpense->category_label); ?></td></tr>
                    <tr><th><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseAccountingAccount')); ?></th><td><?php echo dol_escape_htmltag($currentExpense->accountancy_code !== '' ? $currentExpense->accountancy_code : $langs->trans('TakeposExpenseNotMapped')); ?></td></tr>
                    <tr><th><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseAmountTtc')); ?></th><td><?php echo price((float) $currentExpense->amount_ttc); ?></td></tr>
                    <tr><th><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseAmountHt')); ?></th><td><?php echo price((float) $currentExpense->amount_ht); ?></td></tr>
                    <tr><th><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseVat')); ?></th><td><?php echo price((float) $currentExpense->amount_tva); ?> (<?php echo dol_escape_htmltag((string) price2num((string) $currentExpense->vat_rate, 'MU')); ?>%)</td></tr>
                    <tr><th><?php echo dol_escape_htmltag($langs->trans('TakeposExpensePaymentSource')); ?></th><td><?php echo dol_escape_htmltag(isset($paymentSources[$currentExpense->payment_source]) ? $paymentSources[$currentExpense->payment_source] : $currentExpense->payment_source); ?></td></tr>
                    <tr><th><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseTerminal')); ?></th><td><?php echo dol_escape_htmltag($currentExpense->terminal_code . ' - ' . $currentExpense->terminal_label); ?></td></tr>
                    <tr><th><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseStore')); ?></th><td><?php echo dol_escape_htmltag($currentExpense->store_label !== '' ? $currentExpense->store_label : '-'); ?></td></tr>
                    <tr><th><?php echo dol_escape_htmltag($langs->trans('TakeposExpensePosted')); ?></th><td><?php echo (((int) $currentExpense->status === TakeposExpenseService::STATUS_POSTED) ? dol_escape_htmltag($langs->trans('TakeposCommonYes')) : dol_escape_htmltag($langs->trans('TakeposCommonNo'))); ?></td></tr>
                    <tr><th><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseDatePosted')); ?></th><td><?php echo dol_escape_htmltag(!empty($currentExpense->date_posted) ? (string) $currentExpense->date_posted : '-'); ?></td></tr>
                    <tr><th><?php echo dol_escape_htmltag($langs->trans('TakeposExpensePostedBy')); ?></th><td><?php echo dol_escape_htmltag(!empty($currentExpense->posted_user_login) ? (string) $currentExpense->posted_user_login : '-'); ?></td></tr>
                    <tr><th><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseBankCashLink')); ?></th><td><?php echo dol_escape_htmltag((!empty($currentExpense->bank_account_ref) ? $currentExpense->bank_account_ref . ' - ' . $currentExpense->bank_account_label : '-')); ?></td></tr>
                    <tr><th><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseVariousPaymentId')); ?></th><td><?php echo (!empty($currentExpense->fk_payment_various) ? (int) $currentExpense->fk_payment_various : '-'); ?></td></tr>
                    <tr><th><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseBankLineId')); ?></th><td><?php echo (!empty($currentExpense->fk_bank_line) ? (int) $currentExpense->fk_bank_line : '-'); ?></td></tr>
                    <tr><th><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseCashLedgerLink')); ?></th><td><?php echo (!empty($currentExpense->fk_cash_movement) ? (int) $currentExpense->fk_cash_movement : '-'); ?></td></tr>
                    </tbody>
                </table>
            </div>
        <?php } else { ?>
            <div class="opacitymedium"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseSaveToViewPostingStatus')); ?></div>
        <?php } ?>
    </section>

    <section class="takepos-workspace-panel">
        <h3><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseRecentExpenses')); ?></h3>
        <div class="takepos-workspace-table-wrap">
            <table class="takepos-workspace-table">
                <thead>
                <tr>
                    <th><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseReference')); ?></th>
                    <th><?php echo dol_escape_htmltag($langs->trans('TakeposCommonDate')); ?></th>
                    <th><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseCategory')); ?></th>
                    <th><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseDescription')); ?></th>
                    <th class="right"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseAmount')); ?></th>
                    <th><?php echo dol_escape_htmltag($langs->trans('TakeposCommonStatus')); ?></th>
                    <th><?php echo dol_escape_htmltag($langs->trans('TakeposCommonOpen')); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($recentExpenses)) { ?>
                    <tr><td colspan="7"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseNoRecordsYet')); ?></td></tr>
                <?php } else { ?>
                    <?php foreach ($recentExpenses as $row) { ?>
                        <tr>
                            <td><?php echo dol_escape_htmltag($row->ref); ?></td>
                            <td><?php echo dol_escape_htmltag($row->date_expense); ?></td>
                            <td><?php echo dol_escape_htmltag($row->category_label); ?></td>
                            <td><?php echo dol_escape_htmltag($row->description); ?></td>
                            <td class="right"><?php echo price((float) $row->amount_ttc); ?></td>
                            <td><?php echo dol_escape_htmltag(TakeposExpenseService::statusLabel((int) $row->status)); ?></td>
                            <td><a class="button" href="<?php echo dol_escape_htmltag($pageUrl . '?id=' . ((int) $row->rowid)); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonView')); ?></a></td>
                        </tr>
                    <?php } ?>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<style>
.pos-expense-summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}
.pos-expense-summary-grid > div {
    background: #f7f9fd;
    border: 1px solid #d7dfef;
    border-radius: 10px;
    padding: 12px;
}
.takepos-workspace-panel textarea,
.takepos-workspace-panel input,
.takepos-workspace-panel select {
    width: 100%;
}
@media (max-width: 767px) {
    .pos-expense-summary-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
(function () {
    function byId(id) { return document.getElementById(id); }

    window.takeposCloseExpenseScreen = function () {
        if (window.parent && window.parent.jQuery && window.parent.jQuery.colorbox) {
            window.parent.jQuery.colorbox.close();
            return;
        }
        window.location.href = '<?php echo dol_escape_js(DOL_URL_ROOT . '/takepos/index.php'); ?>';
    };

    var categorySelect = byId('fk_category');
    var vatInput = byId('vat_rate');
    if (categorySelect && vatInput) {
        categorySelect.addEventListener('change', function () {
            var selected = categorySelect.options[categorySelect.selectedIndex];
            if (!selected) return;
            var vatDefault = selected.getAttribute('data-vat-default');
            if (vatDefault !== null && vatDefault !== '') {
                vatInput.value = vatDefault;
            }
        });
    }
})();
</script>
<?php echo takeposHelpRender($langs, __FILE__); ?>
</body>
<?php
llxFooter();
$db->close();
