<?php
/**
 * API and webhook settings page.
 */
require '../../main.inc.php';

require_once __DIR__ . '/../lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/../class/TakeposAccess.class.php';
require_once __DIR__ . '/../class/TakeposAudit.class.php';
require_once __DIR__ . '/../class/TakeposUserAccess.class.php';
require_once __DIR__ . '/../class/TakeposApiService.class.php';
require_once __DIR__ . '/../class/TakeposWebhookService.class.php';

if (empty($user->id)) {
    accessforbidden();
}

$langs->loadLangs(array('admin', 'main', 'cashdesk', 'takeposcustom@takepos'));

$entity = !empty($user->entity) ? (int) $user->entity : 1;
$apiFeatureEnabled = TakeposAccess::isFeatureEnabledForCurrentEntity($db, 'takepos.api_layer');
$webhookFeatureEnabled = TakeposAccess::isFeatureEnabledForCurrentEntity($db, 'takepos.webhooks');
$canApiRead = (!empty($user->admin) || TakeposUserAccess::userHasPermission($db, $user, 'takepos.api.read'));
$canApiWrite = (!empty($user->admin) || TakeposUserAccess::userHasPermission($db, $user, 'takepos.api.write'));
$canWebhookManage = (!empty($user->admin) || TakeposUserAccess::userHasPermission($db, $user, 'takepos.webhook.manage'));

if (!$apiFeatureEnabled && !$webhookFeatureEnabled) {
    accessforbidden($langs->trans('TakeposAdminApiWebhookFeaturesDisabled'));
}
if (empty($user->admin) && !$canApiRead && !$canApiWrite && !$canWebhookManage) {
    accessforbidden($langs->trans('TakeposAdminApiWebhooksAccessDenied'));
}
$message = '';
$messageType = 'mesgs';
$createdToken = '';

$action = GETPOST('action', 'aZ09');

try {
    if ($action !== '' && GETPOST('token', 'alpha') !== $_SESSION['newtoken']) {
        throw new Exception($langs->trans('TakeposCommonInvalidSecurityToken'));
    }

    if ($action === 'create_api_token') {
        if (!$apiFeatureEnabled) {
            throw new Exception($langs->trans('TakeposAdminFeatureDisabled', 'takepos.api_layer'));
        }
        if (!$canApiWrite) {
            throw new Exception($langs->trans('TakeposAdminPermissionRequired', 'takepos.api.write'));
        }

        $label = GETPOST('api_token_label', 'none');
        $scopes = GETPOST('api_scopes', 'array');
        if (!is_array($scopes)) {
            $scopes = array();
        }
        $created = TakeposApiService::createToken($db, $user, $entity, $label, $scopes);
        $createdToken = (string) $created['token'];
        $message = $langs->trans('TakeposAdminApiTokenCreated');
    }

    if ($action === 'toggle_api_token') {
        if (!$apiFeatureEnabled) {
            throw new Exception($langs->trans('TakeposAdminFeatureDisabled', 'takepos.api_layer'));
        }
        if (!$canApiWrite) {
            throw new Exception($langs->trans('TakeposAdminPermissionRequired', 'takepos.api.write'));
        }

        $tokenId = GETPOSTINT('api_token_id');
        $active = GETPOSTINT('api_token_active');
        TakeposApiService::setTokenActive($db, $user, $entity, $tokenId, $active);
        $message = $langs->trans('TakeposAdminApiTokenStatusUpdated');
    }

    if ($action === 'save_webhook') {
        if (!$webhookFeatureEnabled) {
            throw new Exception($langs->trans('TakeposAdminFeatureDisabled', 'takepos.webhooks'));
        }
        if (!$canWebhookManage) {
            throw new Exception($langs->trans('TakeposAdminPermissionRequired', 'takepos.webhook.manage'));
        }
        $webhookId = GETPOSTINT('webhook_id');
        $code = GETPOST('webhook_code', 'aZ09');
        $label = GETPOST('webhook_label', 'none');
        $url = GETPOST('webhook_url', 'none');
        $events = GETPOST('webhook_events', 'none');
        $secret = GETPOST('webhook_secret', 'none');
        $headers = GETPOST('webhook_headers_json', 'none');
        $verifySsl = GETPOSTINT('webhook_verify_ssl');
        $timeout = GETPOSTINT('webhook_timeout_sec');
        $active = GETPOSTINT('webhook_active');

        TakeposWebhookService::saveWebhook($db, $user, $entity, $webhookId, $code, $label, $url, $events, $secret, $headers, $verifySsl, $timeout, $active);
        $message = $langs->trans('TakeposAdminWebhookSaved');
    }
} catch (Throwable $e) {
    $message = $e->getMessage();
    $messageType = 'errors';
}

$tokens = (($apiFeatureEnabled && ($canApiRead || $canApiWrite)) ? TakeposApiService::listTokens($db, $entity) : array());
$hooks = (($webhookFeatureEnabled && $canWebhookManage) ? TakeposWebhookService::listWebhooks($db, $entity, false) : array());

llxHeader('', $langs->trans('TakeposAdminApiWebhooksTitle'));
print load_fiche_titre($langs->trans('TakeposAdminApiWebhooksTitle'));

if ($message !== '') {
    setEventMessages($message, null, $messageType);
}
if ($createdToken !== '') {
    print '<div class="warning">';
    print '<strong>' . dol_escape_htmltag($langs->trans('TakeposAdminCopyApiTokenNow')) . '</strong> ' . dol_escape_htmltag($langs->trans('TakeposAdminApiTokenShownOnce')) . '<br>';
    print '<code>' . dol_escape_htmltag($createdToken) . '</code>';
    print '</div>';
}
?>
<style>
.tp-panel { border:1px solid #d9dfe8; border-radius:8px; padding:12px; margin-bottom:14px; background:#fff; }
.tp-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; }
.tp-table { width:100%; border-collapse:collapse; }
.tp-table th, .tp-table td { border:1px solid #e5e7eb; padding:6px 8px; font-size:13px; }
.tp-table th { background:#f7f8fa; text-align:left; }
</style>

<div class="tp-panel">
    <h3><?php echo dol_escape_htmltag($langs->trans('TakeposAdminCreateApiToken')); ?></h3>
    <form method="POST" action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>">
        <input type="hidden" name="token" value="<?php echo newToken(); ?>">
        <input type="hidden" name="action" value="create_api_token">
        <div class="tp-grid">
            <div>
                <label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminTokenLabel')); ?></label><br>
                <input type="text" name="api_token_label" class="flat minwidth300" required>
            </div>
            <div>
                <label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminScopes')); ?></label><br>
                <label><input type="checkbox" name="api_scopes[]" value="read" checked> <?php echo dol_escape_htmltag($langs->trans('TakeposAdminApiReadScope')); ?></label>
                <label style="margin-left:10px;"><input type="checkbox" name="api_scopes[]" value="write"> <?php echo dol_escape_htmltag($langs->trans('TakeposAdminApiWriteScope')); ?></label>
            </div>
        </div>
        <div style="margin-top:10px;"><input type="submit" class="button button-save" value="<?php echo dol_escape_htmltag($langs->trans('TakeposAdminCreateToken')); ?>"></div>
    </form>
</div>

<div class="tp-panel">
    <h3><?php echo dol_escape_htmltag($langs->trans('TakeposAdminApiTokens')); ?></h3>
    <table class="tp-table">
        <thead><tr><th><?php echo dol_escape_htmltag($langs->trans('TakeposAdminId')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposCommonLabel')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposAdminScopes')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposCommonActive')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposAdminCreated')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposAdminLastUse')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposAdminAction')); ?></th></tr></thead>
        <tbody>
        <?php foreach ($tokens as $tokenRow) { ?>
            <tr>
                <td><?php echo (int) $tokenRow->rowid; ?></td>
                <td><?php echo dol_escape_htmltag($tokenRow->token_label); ?></td>
                <td><?php echo dol_escape_htmltag($tokenRow->scope_csv); ?></td>
                <td><?php echo dol_escape_htmltag(((int) $tokenRow->active ? $langs->trans('TakeposCommonYes') : $langs->trans('TakeposCommonNo'))); ?></td>
                <td><?php echo dol_escape_htmltag((string) $tokenRow->date_creation); ?></td>
                <td><?php echo dol_escape_htmltag((string) $tokenRow->date_last_use); ?></td>
                <td>
                    <form method="POST" action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>" style="margin:0;">
                        <input type="hidden" name="token" value="<?php echo newToken(); ?>">
                        <input type="hidden" name="action" value="toggle_api_token">
                        <input type="hidden" name="api_token_id" value="<?php echo (int) $tokenRow->rowid; ?>">
                        <input type="hidden" name="api_token_active" value="<?php echo ((int) $tokenRow->active ? 0 : 1); ?>">
                        <input type="submit" class="button" value="<?php echo dol_escape_htmltag(((int) $tokenRow->active ? $langs->trans('TakeposCommonDisable') : $langs->trans('TakeposCommonEnable'))); ?>">
                    </form>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div>

<div class="tp-panel">
    <h3><?php echo dol_escape_htmltag($langs->trans('TakeposAdminCreateUpdateWebhook')); ?></h3>
    <form method="POST" action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>">
        <input type="hidden" name="token" value="<?php echo newToken(); ?>">
        <input type="hidden" name="action" value="save_webhook">
        <div class="tp-grid">
            <div><label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminWebhookId')); ?></label><br><input type="number" name="webhook_id" value="0" class="flat minwidth100"></div>
            <div><label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminWebhookCode')); ?></label><br><input type="text" name="webhook_code" class="flat minwidth200" placeholder="ERP_SYNC" required></div>
            <div><label><?php echo dol_escape_htmltag($langs->trans('TakeposCommonLabel')); ?></label><br><input type="text" name="webhook_label" class="flat minwidth250" required></div>
            <div><label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminTargetUrl')); ?></label><br><input type="text" name="webhook_url" class="flat minwidth300" placeholder="https://example.com/webhooks/takepos" required></div>
            <div><label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminEventsCsv')); ?></label><br><input type="text" name="webhook_events" class="flat minwidth300" value="sale_completed,refund_completed,shift_opened,shift_closed,sync_failure"></div>
            <div><label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminSecretKeep')); ?></label><br><input type="text" name="webhook_secret" class="flat minwidth250"></div>
            <div><label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminVerifySsl')); ?></label><br><select name="webhook_verify_ssl" class="flat"><option value="1"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonYes')); ?></option><option value="0"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonNo')); ?></option></select></div>
            <div><label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminTimeoutSec')); ?></label><br><input type="number" name="webhook_timeout_sec" class="flat" min="1" max="60" value="8"></div>
            <div><label><?php echo dol_escape_htmltag($langs->trans('TakeposCommonActive')); ?></label><br><select name="webhook_active" class="flat"><option value="1"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonYes')); ?></option><option value="0"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonNo')); ?></option></select></div>
        </div>
        <div style="margin-top:10px;">
            <label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminHeadersJson')); ?></label><br>
            <textarea name="webhook_headers_json" class="flat" style="width:100%;min-height:90px;">{"X-App":"TakePOS"}</textarea>
        </div>
        <div style="margin-top:10px;"><input type="submit" class="button button-save" value="<?php echo dol_escape_htmltag($langs->trans('TakeposAdminSaveWebhook')); ?>"></div>
    </form>
</div>

<div class="tp-panel">
    <h3><?php echo dol_escape_htmltag($langs->trans('TakeposAdminRegisteredWebhooks')); ?></h3>
    <table class="tp-table">
        <thead><tr><th><?php echo dol_escape_htmltag($langs->trans('TakeposAdminId')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposCommonCode')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposCommonLabel')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposAdminUrl')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposAdminEvents')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposCommonActive')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposAdminLastStatus')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposAdminLastError')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposAdminLastSent')); ?></th></tr></thead>
        <tbody>
        <?php foreach ($hooks as $hook) { ?>
            <tr>
                <td><?php echo (int) $hook->rowid; ?></td>
                <td><?php echo dol_escape_htmltag($hook->webhook_code); ?></td>
                <td><?php echo dol_escape_htmltag($hook->label); ?></td>
                <td><?php echo dol_escape_htmltag($hook->target_url); ?></td>
                <td><?php echo dol_escape_htmltag($hook->events_csv); ?></td>
                <td><?php echo dol_escape_htmltag(((int) $hook->active ? $langs->trans('TakeposCommonYes') : $langs->trans('TakeposCommonNo'))); ?></td>
                <td><?php echo dol_escape_htmltag((string) $hook->last_status); ?></td>
                <td><?php echo dol_escape_htmltag((string) $hook->last_error); ?></td>
                <td><?php echo dol_escape_htmltag((string) $hook->date_last_sent); ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div>

<?php
llxFooter();
$db->close();
