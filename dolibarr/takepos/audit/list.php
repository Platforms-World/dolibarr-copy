<?php
require '../../main.inc.php';
require_once __DIR__ . '/../class/TakeposAccess.class.php';
require_once __DIR__ . '/../class/TakeposAudit.class.php';
$langs->loadLangs(array('admin', 'takepos', 'takeposcustom@takepos'));
restrictedArea($user, 'takepos', 0, '');
TakeposAudit::ensureTable($db);
if (empty($user->admin) && empty($user->rights->takepos->run)) accessforbidden($langs->trans('TakeposAuditLogAccessDenied'));
TakeposAccess::requireFrontendAccess(
    $db,
    $user,
    'takepos.audit.log',
    'takepos.use',
    isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null,
    $langs->trans('TakeposAuditLogAccessDenied'),
    array('page' => 'audit/list.php')
);
$rows = TakeposAudit::fetchRows($db, 300, array('event_code' => GETPOST('event_code', 'alphanohtml')));
llxHeader('', $langs->trans('TakeposAuditLogTitle'));
print load_fiche_titre($langs->trans('TakeposAuditLogTitle'));
print '<div class="tabsAction"><a class="butAction" href="' . DOL_URL_ROOT . '/takepos/audit/dashboard.php">' . dol_escape_htmltag($langs->trans('TakeposAuditOpenDashboard')) . '</a></div>';
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>' . dol_escape_htmltag($langs->trans('TakeposCommonId')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposCommonDate')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposExpenseUser')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposCommonTerminal')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposCommonEvent')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposCommonObject')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposCommonAmount')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposCommonDescription')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposCommonIpAddress')) . '</th></tr>';
foreach ($rows as $r) {
 print '<tr class="oddeven"><td>' . ((int) $r->rowid) . '</td><td>' . dol_escape_htmltag($r->datec) . '</td><td>' . dol_escape_htmltag($r->login) . '</td><td>' . (($r->terminal !== null) ? (int) $r->terminal : '') . '</td><td><span class="badge badge-status4">' . dol_escape_htmltag($r->event_code) . '</span></td><td>' . dol_escape_htmltag(trim((string) $r->object_type . ' #' . (string) $r->object_id, ' #')) . '</td><td class="right">' . ($r->amount_ttc !== null ? price($r->amount_ttc) : '') . '</td><td>' . dol_escape_htmltag($r->description) . '</td><td>' . dol_escape_htmltag($r->ip_address) . '</td></tr>';
}
print '</table></div>';
llxFooter();
