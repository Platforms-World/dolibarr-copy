<?php
/**
 * UTF-8 / Arabic data audit for catalog and customer tables.
 */
if (!defined('NOREQUIREUSER')) {
    define('NOREQUIREUSER', '0');
}

require '../main.inc.php';

require_once __DIR__ . '/../lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/../class/TakeposAccess.class.php';
require_once __DIR__ . '/../class/TakeposAudit.class.php';
require_once __DIR__ . '/../class/TakeposInputValidator.class.php';
require_once __DIR__ . '/../class/TakeposUtf8.class.php';

$langs->loadLangs(array('admin', 'main', 'cashdesk', 'takeposcustom@takepos'));

$terminal = isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null;
TakeposAccess::requireAdminAccess($db, $user, 'takepos.admin.setup', 'takepos.admin', $terminal, $langs->trans('TakeposUtf8AccessDenied'), array('page' => 'admin/utf8.php'));

TakeposUtf8::bootstrapConnection($db);

$action = GETPOST('action', 'aZ09');
$token = TakeposInputValidator::normalizeUtf8Text(GETPOST('token', 'none'), 128, true);
$sessionToken = isset($_SESSION['newtoken']) ? (string) $_SESSION['newtoken'] : '';

$tables = array(
    MAIN_DB_PREFIX . 'categorie',
    MAIN_DB_PREFIX . 'categorie_lang',
    MAIN_DB_PREFIX . 'product',
    MAIN_DB_PREFIX . 'product_lang',
    MAIN_DB_PREFIX . 'societe',
    MAIN_DB_PREFIX . 'takepos_store',
    MAIN_DB_PREFIX . 'takepos_terminal',
    MAIN_DB_PREFIX . 'takepos_refund_reason',
);

$applyReport = array();
$message = '';
$error = '';

if ($action === 'apply') {
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        $error = $langs->trans('TakeposUtf8InvalidCsrf');
    } else {
        $applyReport = TakeposUtf8::convertTablesToUtf8mb4($db, $tables, 'utf8mb4_unicode_ci');
        $changed = 0;
        foreach ($applyReport as $entry) {
            if (!empty($entry['changed'])) {
                $changed++;
            }
        }

        $message = $langs->trans('TakeposUtf8MigrationExecuted', $changed);
        TakeposAudit::logEvent(
            $db,
            $user,
            'utf8_catalog_fix_applied',
            TakeposAudit::SEVERITY_WARNING,
            array('changed_tables' => $changed, 'total_tables' => count($applyReport)),
            'UTF-8 catalog migration applied',
            'admin'
        );
    }
}

$audit = TakeposUtf8::auditTableCharsets($db, $tables);
TakeposAudit::logEvent(
    $db,
    $user,
    'utf8_audit_opened',
    TakeposAudit::SEVERITY_INFO,
    array('tables' => array_values($tables)),
    'UTF-8 audit page opened',
    'admin'
);

llxHeader('', $langs->trans('TakeposUtf8PageTitle'));

print load_fiche_titre($langs->trans('TakeposUtf8AuditTitle'));

print '<div class="opacitymedium" style="margin-bottom:10px;">' . dol_escape_htmltag($langs->trans('TakeposUtf8AuditDescription')) . '</div>';

if ($message !== '') {
    print '<div class="ok">' . dol_escape_htmltag($message) . '</div>';
}
if ($error !== '') {
    print '<div class="error">' . dol_escape_htmltag($error) . '</div>';
}

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>' . dol_escape_htmltag($langs->trans('TakeposUtf8Table')) . '</th>';
print '<th>' . dol_escape_htmltag($langs->trans('TakeposUtf8Exists')) . '</th>';
print '<th>' . dol_escape_htmltag($langs->trans('TakeposUtf8Charset')) . '</th>';
print '<th>' . dol_escape_htmltag($langs->trans('TakeposUtf8Collation')) . '</th>';
print '<th>' . dol_escape_htmltag($langs->trans('TakeposUtf8Status')) . '</th>';
print '</tr>';

foreach ($tables as $table) {
    $meta = isset($audit[$table]) ? $audit[$table] : array('exists' => false, 'charset' => '', 'collation' => '');
    $exists = !empty($meta['exists']);
    $charset = isset($meta['charset']) ? (string) $meta['charset'] : '';
    $collation = isset($meta['collation']) ? (string) $meta['collation'] : '';
    $ok = ($exists && $charset === 'utf8mb4');

    print '<tr class="oddeven">';
    print '<td>' . dol_escape_htmltag($table) . '</td>';
    print '<td>' . ($exists ? dol_escape_htmltag($langs->trans('TakeposCommonYes')) : dol_escape_htmltag($langs->trans('TakeposCommonNo'))) . '</td>';
    print '<td>' . dol_escape_htmltag($charset) . '</td>';
    print '<td>' . dol_escape_htmltag($collation) . '</td>';
    print '<td>' . ($ok ? '<span class="badge badge-status4">OK</span>' : '<span class="badge badge-status8">' . dol_escape_htmltag($langs->trans('TakeposUtf8NeedsConversion')) . '</span>') . '</td>';
    print '</tr>';
}

print '</table>';

print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '" style="margin-top:12px;">';
print '<input type="hidden" name="token" value="' . dol_escape_htmltag(newToken()) . '">';
print '<input type="hidden" name="action" value="apply">';
print '<button type="submit" class="button button-save">' . dol_escape_htmltag($langs->trans('TakeposUtf8ConvertButton')) . '</button>';
print '</form>';

if (!empty($applyReport)) {
    print '<h3 style="margin-top:16px;">' . dol_escape_htmltag($langs->trans('TakeposUtf8LastApplyReport')) . '</h3>';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><th>' . dol_escape_htmltag($langs->trans('TakeposUtf8Table')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposCommonChanged')) . '</th><th>' . dol_escape_htmltag($langs->trans('Error')) . '</th></tr>';
    foreach ($applyReport as $entry) {
        print '<tr class="oddeven">';
        print '<td>' . dol_escape_htmltag((string) $entry['table']) . '</td>';
        print '<td>' . (!empty($entry['changed']) ? dol_escape_htmltag($langs->trans('TakeposCommonYes')) : dol_escape_htmltag($langs->trans('TakeposCommonNo'))) . '</td>';
        print '<td>' . dol_escape_htmltag((string) $entry['error']) . '</td>';
        print '</tr>';
    }
    print '</table>';
}

llxFooter();
$db->close();
