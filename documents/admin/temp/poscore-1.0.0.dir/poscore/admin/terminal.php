<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/poscore/lib/poscore_bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/custom/poscore/class/pos_terminal.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/poscore/class/service/PosRefService.php';

$form = new Form($db);
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$object = new PosTerminal($db);
if ($id > 0) {
    $object->fetch($id);
}

$isCreate = ($action === 'create' || empty($id));
$currentCount = 0;
$resCount = $db->query("SELECT COUNT(*) nb FROM " . MAIN_DB_PREFIX . "pos_terminal WHERE entity=" . ((int) $conf->entity));
if ($resCount && ($c = $db->fetch_object($resCount))) $currentCount = (int) $c->nb;

$guard = poscore_bootstrap_page($isCreate ? 'create_terminal' : 'edit_terminal', 'poscore->admin', null, $isCreate ? 'max_terminals' : null, $isCreate ? $currentCount : null);

if (($action === 'save' || $action === 'update') && GETPOST('token') === $_SESSION['newtoken']) {
    if ($isCreate) {
        $object->ref = trim(GETPOST('ref', 'alpha'));
        if ($object->ref === '') {
            $object->ref = PosRefService::nextRef($db, 'pos_terminal', getDolGlobalString('POSCORE_DEFAULT_TERMINAL_REF_PREFIX', 'TERM'), (int) $conf->entity);
        }
    }
    $object->label = trim(GETPOST('label', 'alphanohtml'));
    $object->warehouse_id = GETPOSTINT('warehouse_id') ?: null;
    $object->receipt_profile_id = GETPOSTINT('receipt_profile_id') ?: null;
    $object->status = GETPOSTINT('status') ? 1 : 0;
    $object->is_default = GETPOSTINT('is_default') ? 1 : 0;
    $object->note_public = GETPOST('note_public', 'restricthtml');
    $object->note_private = GETPOST('note_private', 'restricthtml');

    if ($object->label === '') {
        setEventMessages('Label is required', null, 'errors');
    } else {
        $res = $isCreate ? $object->create($user) : $object->update($user);
        if ($res > 0) {
            setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
            header('Location: ' . DOL_URL_ROOT . '/custom/poscore/admin/terminal_list.php');
            exit;
        }
        setEventMessages($object->error, null, 'errors');
    }
}

llxHeader('', $langs->trans('POSTerminalCard'));
print load_fiche_titre($isCreate ? $langs->trans('NewTerminal') : $langs->trans('TerminalCard'), '', 'cash-register');
print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . ($id > 0 ? '?id=' . ((int) $id) : '?action=create') . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="' . ($isCreate ? 'save' : 'update') . '">';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">Ref</td><td><input type="text" name="ref" value="' . dol_escape_htmltag($object->ref) . '"> <span class="opacitymedium">Leave empty to auto-generate</span></td></tr>';
print '<tr><td>Label</td><td><input class="minwidth400" type="text" name="label" value="' . dol_escape_htmltag($object->label) . '"></td></tr>';
print '<tr><td>Warehouse ID</td><td><input type="number" name="warehouse_id" value="' . ($object->warehouse_id ? (int) $object->warehouse_id : '') . '"></td></tr>';
print '<tr><td>Receipt profile ID</td><td><input type="number" name="receipt_profile_id" value="' . ($object->receipt_profile_id ? (int) $object->receipt_profile_id : '') . '"></td></tr>';
print '<tr><td>Status</td><td><input type="checkbox" name="status" value="1"' . (!isset($object->status) || (int) $object->status === 1 ? ' checked' : '') . '> Active</td></tr>';
print '<tr><td>Default</td><td><input type="checkbox" name="is_default" value="1"' . ((int) $object->is_default === 1 ? ' checked' : '') . '> Default terminal</td></tr>';
print '<tr><td>Public note</td><td><textarea class="quatrevingtpercent" name="note_public" rows="3">' . dol_escape_htmltag($object->note_public) . '</textarea></td></tr>';
print '<tr><td>Private note</td><td><textarea class="quatrevingtpercent" name="note_private" rows="3">' . dol_escape_htmltag($object->note_private) . '</textarea></td></tr>';
print '</table>';
print '<div class="tabsAction"><input type="submit" class="button button-save" value="' . $langs->trans('Save') . '"> <a class="button button-cancel" href="' . DOL_URL_ROOT . '/custom/poscore/admin/terminal_list.php">' . $langs->trans('Cancel') . '</a></div>';
print '</form>';
llxFooter();
$db->close();
