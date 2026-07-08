<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once __DIR__ . '/../core/lib/saascore.lib.php';
require_once __DIR__ . '/../class/service/KafoApiTokenService.php';
require_once __DIR__ . '/../class/service/KafoAuditLogService.php';

$langs->loadLangs(array('admin', 'kafoerpcontrol@kafoerpcontrol'));
if (!$user->admin && !$user->hasRight('kafoerpcontrol', 'apimanage')) accessforbidden();

$tokenService = new KafoApiTokenService($db);
$action = GETPOST('action', 'aZ09');
$message = '';
$visibleToken = '';

if ($action === 'toggle' && GETPOST('token') === currentToken()) {
    $enabled = GETPOSTINT('enabled') ? 1 : 0;
    dolibarr_set_const($db, 'SAASCORE_API_ENABLED', $enabled, 'yesno', 0, 'Enable kafo ERP Control API', $conf->entity);
    $audit = new KafoAuditLogService($db);
    $audit->logAction((int) $user->id, 0, 'api_toggle', 'api', 'enabled', null, (string) $enabled, 'API enabled flag changed', array('context' => 'admin/api.php'));
    $message = 'API status updated';
}

if ($action === 'rotate' && GETPOST('token') === currentToken()) {
    $visibleToken = $tokenService->rotateToken((int) $user->id);
    if ($visibleToken !== false) {
        $audit = new KafoAuditLogService($db);
        $audit->logAction((int) $user->id, 0, 'api_token_rotate', 'api_token', 'fixed_token', null, 'rotated', 'API token rotated', array('context' => 'admin/api.php'));
        $message = 'New token generated successfully';
    } else {
        setEventMessages('Token generation failed', null, 'errors');
    }
}

llxHeader('', 'Kafo ERP Control API');
print load_fiche_titre('Kafo ERP Control API', '', 'title_setup');
print dol_get_fiche_head(saascoreAdminPrepareHead(), 'api', 'kafo-ERP-Control', -1, 'generic');

if ($message !== '') {
    setEventMessages($message, null, 'mesgs');
}

print '<div class="opacitymedium">Base path: <strong>/custom/kafoerpcontrol/api/</strong></div><br>';
print '<form method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="toggle">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">API Settings</th></tr>';
print '<tr class="oddeven"><td width="280">Enabled</td><td><input type="checkbox" name="enabled" value="1" '.(!empty($conf->global->SAASCORE_API_ENABLED) ? 'checked' : '').'> <input class="button" type="submit" value="Save"></td></tr>';
print '<tr class="oddeven"><td>Last rotated at</td><td>'.dol_escape_htmltag((string) (!empty($conf->global->SAASCORE_API_TOKEN_LAST_ROTATED_AT) ? $conf->global->SAASCORE_API_TOKEN_LAST_ROTATED_AT : '-')).'</td></tr>';
print '<tr class="oddeven"><td>Last used at</td><td>'.dol_escape_htmltag((string) (!empty($conf->global->SAASCORE_API_TOKEN_LAST_USED_AT) ? $conf->global->SAASCORE_API_TOKEN_LAST_USED_AT : '-')).'</td></tr>';
print '<tr class="oddeven"><td>Last used IP</td><td>'.dol_escape_htmltag((string) (!empty($conf->global->SAASCORE_API_TOKEN_LAST_USED_IP) ? $conf->global->SAASCORE_API_TOKEN_LAST_USED_IP : '-')).'</td></tr>';
print '</table></form><br>';

print '<form method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="rotate">';
print '<input class="button button-edit" type="submit" value="Generate / Rotate 64-char token">';
print '</form><br>';

if ($visibleToken !== '') {
    print '<div class="info">';
    print '<strong>Copy this token now. It is the active Bearer token:</strong><br>';
    print '<textarea class="flat" style="width:100%;min-height:90px;">'.dol_escape_htmltag($visibleToken).'</textarea>';
    print '</div><br>';
}

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>Endpoint</th><th>Method</th><th>Description</th></tr>';
print '<tr class="oddeven"><td>/custom/kafoerpcontrol/api/users.php</td><td>GET</td><td>List users. Optional: user_id, include_roles=1, include_permissions=1</td></tr>';
print '<tr class="oddeven"><td>/custom/kafoerpcontrol/api/user_permissions.php</td><td>GET</td><td>Get one user permissions by user_id</td></tr>';
print '<tr class="oddeven"><td>/custom/kafoerpcontrol/api/user_permissions.php</td><td>POST/PUT/PATCH</td><td>Update direct permissions for one user via JSON body</td></tr>';
print '</table>';

print '<br><div><strong>Authorization header</strong><br><code>Authorization: Bearer YOUR_TOKEN</code></div>';
print '<br><div><strong>POST body example</strong><pre>{
  "user_id": 5,
  "replace": false,
  "permissions": [
    {"code": "takepos.use", "allowed": true},
    {"code": "takepos.admin", "allowed": false}
  ]
}</pre></div>';

print dol_get_fiche_end();
llxFooter();
$db->close();
