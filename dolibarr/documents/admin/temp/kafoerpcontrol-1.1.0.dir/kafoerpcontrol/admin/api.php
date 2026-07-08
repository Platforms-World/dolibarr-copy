<?php
if (!function_exists('kafoerpcontrolResolveMain')) {
    function kafoerpcontrolResolveMain()
    {
        $candidates = array(
            __DIR__ . '/../../../main.inc.php',
            dirname(__DIR__, 3) . '/main.inc.php',
            dirname(__DIR__, 4) . '/main.inc.php',
            dirname(__DIR__, 5) . '/main.inc.php',
        );

        foreach ($candidates as $candidate) {
            $resolved = realpath($candidate);
            if ($resolved !== false && is_file($resolved)) {
                return $resolved;
            }
        }

        return null;
    }
}
$maininc = kafoerpcontrolResolveMain();
if ($maininc === null) {
    http_response_code(500);
    print 'Unable to locate Dolibarr main.inc.php';
    exit;
}
require_once $maininc;
require_once dol_buildpath('/kafoerpcontrol/core/lib/saascore.lib.php', 0);
require_once dol_buildpath('/kafoerpcontrol/class/SaasApiAuthService.php', 0);
$langs->loadLangs(array('admin', 'kafoerpcontrol@kafoerpcontrol'));
saascoreRequireAdminRight('apimanage');

$action = GETPOST('action', 'aZ09');
$message = '';
$error = '';
$generatedToken = '';
$apiAuth = new SaasApiAuthService($db);

if (in_array($action, array('savefixed', 'createtoken', 'deletetoken'), true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenOk = function_exists('checkToken') ? checkToken() : true;
    if (!$tokenOk) {
        accessforbidden('Invalid CSRF token');
    }
}

if ($action === 'savefixed') {
    $fixedToken = trim(GETPOST('fixed_token', 'alphanohtml'));
    dolibarr_set_const($db, 'SAAS_API_FIXED_TOKEN', $fixedToken, 'chaine', 0, '', $conf->entity);
    $conf->global->SAAS_API_FIXED_TOKEN = $fixedToken;
    $message = $langs->trans('ApiFixedTokenSaved');
}

if ($action === 'createtoken') {
    $label = trim(GETPOST('label', 'restricthtml'));
    $plainToken = trim(GETPOST('plain_token', 'alphanohtml'));
    if ($plainToken === '') {
        $plainToken = $apiAuth->generateToken(64);
    }
    if ($label === '') {
        $label = 'API Token '.dol_print_date(dol_now(), '%Y%m%d%H%M%S');
    }

    $ok = $apiAuth->createToken(
        $conf->entity,
        $label,
        $plainToken,
        GETPOST('can_read', 'int') ? 1 : 0,
        GETPOST('can_write', 'int') ? 1 : 0,
        GETPOST('can_update', 'int') ? 1 : 0,
        trim(GETPOST('notes', 'restricthtml'))
    );

    if ($ok) {
        $generatedToken = $plainToken;
        $message = $langs->trans('ApiTokenCreated');
    } else {
        $error = $db->lasterror();
    }
}

if ($action === 'deletetoken') {
    $rowid = GETPOST('rowid', 'int');
    if ($apiAuth->deleteToken($conf->entity, $rowid)) {
        $message = $langs->trans('ApiTokenDeleted');
    } else {
        $error = $db->lasterror();
    }
}

$tokens = $apiAuth->getTokens($conf->entity);
$endpoint = dol_buildpath('/kafoerpcontrol/api/access.php', 2);

llxHeader('', $langs->trans('ApiAccess'));
print load_fiche_titre($langs->trans('ApiAccess'), '', 'title_setup');
print dol_get_fiche_head(saascoreAdminPrepareHead(), 'apiaccess', 'kafo-ERP-Control', -1, 'generic');

if ($message !== '') {
    print '<div class="ok">'.dol_escape_htmltag($message).'</div>';
}
if ($error !== '') {
    print '<div class="error">'.dol_escape_htmltag($error).'</div>';
}
if ($generatedToken !== '') {
    print '<div class="warning">Generated token (copy now, it will not be shown again): <strong>'.dol_escape_htmltag($generatedToken).'</strong></div>';
}

print '<div class="info">'.$langs->trans('ApiReadme').'</div>';

print '<h3>'.$langs->trans('ApiFixedToken').'</h3>';
print '<form method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="savefixed">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('ApiFixedToken').'</th><th style="width:140px">&nbsp;</th></tr>';
print '<tr class="oddeven"><td><input class="minwidth500" type="text" name="fixed_token" value="'.dol_escape_htmltag((string) (!empty($conf->global->SAAS_API_FIXED_TOKEN) ? $conf->global->SAAS_API_FIXED_TOKEN : '')).'" placeholder="paste fixed token here"></td>';
print '<td><input class="button button-save" type="submit" value="'.$langs->trans('ApiSaveFixedToken').'"></td></tr>';
print '</table></form>';

print '<h3>'.$langs->trans('ApiGeneratedTokens').'</h3>';
print '<form method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="createtoken">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('ApiLabel').'</th><th>'.$langs->trans('ApiToken').'</th><th>'.$langs->trans('ApiPermissions').'</th><th>'.$langs->trans('ApiNotes').'</th><th>&nbsp;</th></tr>';
print '<tr class="oddeven">';
print '<td><input type="text" name="label" class="minwidth200"></td>';
print '<td><input type="text" name="plain_token" class="minwidth200" placeholder="leave empty to auto-generate 64-char token"></td>';
print '<td>';
print '<label><input type="checkbox" name="can_read" value="1" checked> '.$langs->trans('ApiCanRead').'</label><br>';
print '<label><input type="checkbox" name="can_write" value="1"> '.$langs->trans('ApiCanWrite').'</label><br>';
print '<label><input type="checkbox" name="can_update" value="1"> '.$langs->trans('ApiCanUpdate').'</label>';
print '</td>';
print '<td><input type="text" name="notes" class="minwidth200"></td>';
print '<td><input class="button" type="submit" value="'.$langs->trans('ApiCreate64Token').'"></td>';
print '</tr></table></form><br>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>ID</th><th>'.$langs->trans('ApiLabel').'</th><th>Prefix</th><th>'.$langs->trans('ApiCanRead').'</th><th>'.$langs->trans('ApiCanWrite').'</th><th>'.$langs->trans('ApiCanUpdate').'</th><th>'.$langs->trans('ApiIsActive').'</th><th>'.$langs->trans('ApiLastUsedAt').'</th><th>'.$langs->trans('ApiNotes').'</th><th>&nbsp;</th></tr>';
foreach ($tokens as $obj) {
    print '<tr class="oddeven">';
    print '<td>'.((int) $obj->rowid).'</td>';
    print '<td>'.dol_escape_htmltag($obj->label).'</td>';
    print '<td>'.dol_escape_htmltag($obj->token_prefix).'...</td>';
    print '<td>'.((int) $obj->can_read).'</td>';
    print '<td>'.((int) $obj->can_write).'</td>';
    print '<td>'.((int) $obj->can_update).'</td>';
    print '<td>'.((int) $obj->is_active).'</td>';
    print '<td>'.dol_escape_htmltag((string) $obj->last_used_at).'</td>';
    print '<td>'.dol_escape_htmltag((string) $obj->notes).'</td>';
    print '<td><form method="POST" style="margin:0">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="deletetoken">';
    print '<input type="hidden" name="rowid" value="'.((int) $obj->rowid).'">';
    print '<input class="button button-delete" type="submit" value="'.$langs->trans('Delete').'">';
    print '</form></td>';
    print '</tr>';
}
print '</table>';

print '<h3>'.$langs->trans('ApiUsageExamples').'</h3>';
print '<div class="opacitymedium">';
print '<p><strong>'.$langs->trans('ApiEndpoint').':</strong> '.dol_escape_htmltag($endpoint).'</p>';
print '<pre style="white-space:pre-wrap">GET '.$endpoint.'?action=catalog&type=permissions
Header: X-API-Token: YOUR_TOKEN

GET '.$endpoint.'?action=user_permissions&fk_user=3
Header: X-API-Token: YOUR_TOKEN

POST '.$endpoint.'
Header: X-API-Token: YOUR_TOKEN
Body(JSON): {"action":"permission_set","fk_user":3,"permission_code":"takepos.use","allowed":1}</pre>';
print '</div>';

print dol_get_fiche_end();
llxFooter();
$db->close();
