<?php
/**
 * Device settings admin page.
 */
require '../../main.inc.php';
require_once __DIR__ . '/../lib/takepos_help.php';

require_once __DIR__ . '/../lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/../class/TakeposAccess.class.php';
require_once __DIR__ . '/../class/TakeposAudit.class.php';

if (empty($user->id)) {
    accessforbidden();
}

$langs->loadLangs(array('admin', 'main', 'cashdesk', 'takeposcustom@takepos'));

TakeposAccess::requireAdminAccess(
    $db,
    $user,
    'takepos.device_layer',
    'takepos.device.manage',
    isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null,
    $langs->trans('TakeposAdminDevicesAccessDenied')
);

TakeposAudit::logEvent($db, $user, 'device_settings_opened', TakeposAudit::SEVERITY_INFO, array('page' => 'admin/devices.php'), 'Device settings opened');

llxHeader('', $langs->trans('TakeposAdminDevicesTitle'));
print load_fiche_titre($langs->trans('TakeposAdminDevicesTitle'));
?>
<div id="takepos-device-config"
     data-token="<?php echo dol_escape_htmltag(newToken()); ?>"
     data-endpoint="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/ajax/device.php'); ?>"></div>

<style>
.takepos-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; }
.takepos-panel { border:1px solid #d9dfe8; border-radius:8px; padding:12px; margin-bottom:14px; background:#fff; }
.takepos-table { width:100%; border-collapse:collapse; }
.takepos-table th, .takepos-table td { border:1px solid #e5e7eb; padding:6px 8px; font-size:13px; }
.takepos-table th { background:#f7f8fa; text-align:left; }
.takepos-actions { margin-top:10px; display:flex; gap:8px; flex-wrap:wrap; }
.takepos-message { margin:8px 0 12px; font-weight:600; }
</style>

<div class="takepos-panel">
    <h3><?php echo dol_escape_htmltag($langs->trans('TakeposAdminDeviceProfile')); ?></h3>
    <div class="takepos-grid">
        <div>
            <label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminDeviceProfileId')); ?></label>
            <input type="number" id="device_profile_id" min="0" value="0" class="flat minwidth100">
        </div>
        <div>
            <label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminDeviceCode')); ?></label>
            <input type="text" id="device_code" class="flat minwidth200" placeholder="SCANNER_MAIN">
        </div>
        <div>
            <label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminLabel')); ?></label>
            <input type="text" id="device_label" class="flat minwidth200" placeholder="Main Scanner">
        </div>
        <div>
            <label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminDeviceType')); ?></label>
            <select id="device_type" class="flat minwidth200"></select>
        </div>
        <div>
            <label><?php echo dol_escape_htmltag($langs->trans('TakeposCommonActive')); ?></label>
            <select id="device_active" class="flat minwidth120"><option value="1"><?php echo dol_escape_htmltag($langs->trans('TakeposAdminActiveYes')); ?></option><option value="0"><?php echo dol_escape_htmltag($langs->trans('TakeposAdminActiveNo')); ?></option></select>
        </div>
    </div>
    <div style="margin-top:10px;">
        <label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminSettingsJson')); ?></label>
        <textarea id="device_settings_json" class="flat" style="width:100%;min-height:90px;">{}</textarea>
    </div>
    <div class="takepos-actions">
        <button type="button" class="button button-save" id="btn_save_device_profile"><?php echo dol_escape_htmltag($langs->trans('TakeposAdminSaveDeviceProfile')); ?></button>
        <button type="button" class="button" id="btn_reload_device_profiles"><?php echo dol_escape_htmltag($langs->trans('TakeposAdminReloadProfiles')); ?></button>
    </div>
</div>

<div class="takepos-panel">
    <h3><?php echo dol_escape_htmltag($langs->trans('TakeposAdminTerminalDeviceBinding')); ?></h3>
    <div class="takepos-grid">
        <div>
            <label><?php echo dol_escape_htmltag($langs->trans('TakeposCommonTerminal')); ?></label>
            <select id="binding_terminal" class="flat minwidth200"></select>
        </div>
        <div>
            <label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminDeviceProfileSelect')); ?></label>
            <select id="binding_profile" class="flat minwidth200"></select>
        </div>
        <div>
            <label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminBindingType')); ?></label>
            <select id="binding_type" class="flat minwidth200"></select>
        </div>
        <div>
            <label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminPriority')); ?></label>
            <input type="number" id="binding_priority" class="flat minwidth100" min="1" value="1">
        </div>
        <div>
            <label><?php echo dol_escape_htmltag($langs->trans('TakeposCommonActive')); ?></label>
            <select id="binding_active" class="flat minwidth120"><option value="1"><?php echo dol_escape_htmltag($langs->trans('TakeposAdminActiveYes')); ?></option><option value="0"><?php echo dol_escape_htmltag($langs->trans('TakeposAdminActiveNo')); ?></option></select>
        </div>
    </div>
    <div class="takepos-actions">
        <button type="button" class="button button-save" id="btn_bind_terminal"><?php echo dol_escape_htmltag($langs->trans('TakeposAdminSaveBinding')); ?></button>
        <button type="button" class="button" id="btn_reload_bindings"><?php echo dol_escape_htmltag($langs->trans('TakeposAdminReloadBindings')); ?></button>
    </div>
</div>

<div class="takepos-panel">
    <h3><?php echo dol_escape_htmltag($langs->trans('TakeposAdminCustomerDisplayTest')); ?></h3>
    <div class="takepos-grid">
        <div>
            <label><?php echo dol_escape_htmltag($langs->trans('TakeposCommonTerminal')); ?></label>
            <select id="display_terminal" class="flat minwidth200"></select>
        </div>
        <div style="grid-column:span 2;">
            <label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminMessage')); ?></label>
            <input type="text" id="display_message" class="flat minwidth300" value="<?php echo dol_escape_htmltag($langs->trans('TakeposAdminDisplayTestMessage')); ?>">
        </div>
    </div>
    <div class="takepos-actions">
        <button type="button" class="button" id="btn_test_display"><?php echo dol_escape_htmltag($langs->trans('TakeposAdminSendDisplayTest')); ?></button>
    </div>
</div>

<div id="device_message" class="takepos-message"></div>

<div class="takepos-panel">
    <h3><?php echo dol_escape_htmltag($langs->trans('TakeposAdminDeviceProfiles')); ?></h3>
    <table id="table_device_profiles" class="takepos-table"></table>
</div>

<div class="takepos-panel">
    <h3><?php echo dol_escape_htmltag($langs->trans('TakeposAdminTerminalBindings')); ?></h3>
    <table id="table_device_bindings" class="takepos-table"></table>
</div>

<script>
(function () {
    'use strict';

    var cfg = document.getElementById('takepos-device-config');
    if (!cfg) return;

    var endpoint = cfg.getAttribute('data-endpoint') || '';
    var token = cfg.getAttribute('data-token') || '';
    var lookups = { terminals: [], profiles: [], binding_types: [], device_types: [] };
    var i18n = <?php echo json_encode(array(
        'id' => $langs->trans('TakeposAdminId'),
        'code' => $langs->trans('TakeposCommonCode'),
        'label' => $langs->trans('TakeposAdminLabel'),
        'type' => $langs->trans('TakeposCommonType'),
        'active' => $langs->trans('TakeposCommonActive'),
        'settings' => $langs->trans('TakeposAdminSettings'),
        'terminal' => $langs->trans('TakeposCommonTerminal'),
        'binding' => $langs->trans('TakeposAdminBinding'),
        'priority' => $langs->trans('TakeposAdminPriority'),
        'profile' => $langs->trans('TakeposAdminProfile'),
        'unableLoadDeviceProfiles' => $langs->trans('TakeposAdminUnableLoadDeviceProfiles'),
        'unableLoadTerminalBindings' => $langs->trans('TakeposAdminUnableLoadTerminalBindings'),
        'unableLoadLookups' => $langs->trans('TakeposAdminUnableLoadLookups'),
        'unableSaveDeviceProfile' => $langs->trans('TakeposAdminUnableSaveDeviceProfile'),
        'deviceProfileSaved' => $langs->trans('TakeposAdminDeviceProfileSaved'),
        'unableSaveBinding' => $langs->trans('TakeposAdminUnableSaveBinding'),
        'bindingSaved' => $langs->trans('TakeposAdminBindingSaved'),
        'unableSendDisplayTest' => $langs->trans('TakeposAdminUnableSendDisplayTest'),
        'displayTestSent' => $langs->trans('TakeposAdminDisplayTestSent')
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function byId(id) { return document.getElementById(id); }
    function safe(v) { return (v === null || v === undefined) ? '' : String(v); }
    function qs(params) { var usp = new URLSearchParams(); Object.keys(params || {}).forEach(function (k) { if (params[k] !== '' && params[k] !== null && params[k] !== undefined) usp.append(k, params[k]); }); return usp.toString(); }
    function call(params) { return fetch(endpoint + '?' + qs(params), { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); }); }

    function msg(text, ok) {
        var n = byId('device_message');
        if (!n) return;
        n.style.color = ok ? '#1f7a45' : '#8b1f1f';
        n.textContent = text || '';
    }

    function fillSelect(selectId, rows, valueKey, labelBuilder) {
        var select = byId(selectId);
        if (!select) return;
        select.innerHTML = '';
        (rows || []).forEach(function (row) {
            var option = document.createElement('option');
            option.value = row[valueKey];
            option.textContent = labelBuilder(row);
            select.appendChild(option);
        });
    }

    function renderProfiles(rows) {
        var t = byId('table_device_profiles');
        var html = '<thead><tr><th>' + i18n.id + '</th><th>' + i18n.code + '</th><th>' + i18n.label + '</th><th>' + i18n.type + '</th><th>' + i18n.active + '</th><th>' + i18n.settings + '</th></tr></thead><tbody>';
        (rows || []).forEach(function (r) {
            html += '<tr>'
                + '<td>' + safe(r.rowid) + '</td>'
                + '<td>' + safe(r.device_code) + '</td>'
                + '<td>' + safe(r.label) + '</td>'
                + '<td>' + safe(r.device_type) + '</td>'
                + '<td>' + safe(r.active) + '</td>'
                + '<td><code>' + safe(r.settings_json) + '</code></td>'
                + '</tr>';
        });
        html += '</tbody>';
        t.innerHTML = html;
    }

    function renderBindings(rows) {
        var t = byId('table_device_bindings');
        var html = '<thead><tr><th>' + i18n.id + '</th><th>' + i18n.terminal + '</th><th>' + i18n.binding + '</th><th>' + i18n.priority + '</th><th>' + i18n.profile + '</th><th>' + i18n.type + '</th><th>' + i18n.active + '</th></tr></thead><tbody>';
        (rows || []).forEach(function (r) {
            html += '<tr>'
                + '<td>' + safe(r.rowid) + '</td>'
                + '<td>' + safe(r.terminal_code) + ' - ' + safe(r.terminal_label) + '</td>'
                + '<td>' + safe(r.binding_type) + '</td>'
                + '<td>' + safe(r.priority) + '</td>'
                + '<td>' + safe(r.device_code) + ' - ' + safe(r.profile_label) + '</td>'
                + '<td>' + safe(r.device_type) + '</td>'
                + '<td>' + safe(r.active) + '</td>'
                + '</tr>';
        });
        html += '</tbody>';
        t.innerHTML = html;
    }

    function loadProfiles() {
        return call({ action: 'list_profiles' }).then(function (res) {
            if (!res || !res.success) {
                msg((res && res.message) ? res.message : i18n.unableLoadDeviceProfiles, false);
                return;
            }
            lookups.profiles = res.rows || [];
            renderProfiles(lookups.profiles);
            fillSelect('binding_profile', lookups.profiles, 'rowid', function (r) { return safe(r.device_code) + ' - ' + safe(r.label) + ' (' + safe(r.device_type) + ')'; });
        });
    }

    function loadBindings() {
        return call({ action: 'list_bindings', terminal_id: byId('binding_terminal').value || '' }).then(function (res) {
            if (!res || !res.success) {
                msg((res && res.message) ? res.message : i18n.unableLoadTerminalBindings, false);
                return;
            }
            renderBindings(res.rows || []);
        });
    }

    function loadLookups() {
        return call({ action: 'lookups' }).then(function (res) {
            if (!res || !res.success) {
                msg((res && res.message) ? res.message : i18n.unableLoadLookups, false);
                return;
            }
            lookups.terminals = res.terminals || [];
            lookups.binding_types = res.binding_types || [];
            lookups.device_types = res.device_types || [];

            fillSelect('binding_terminal', lookups.terminals, 'rowid', function (r) { return safe(r.terminal_code) + ' - ' + safe(r.label); });
            fillSelect('display_terminal', lookups.terminals, 'rowid', function (r) { return safe(r.terminal_code) + ' - ' + safe(r.label); });

            fillSelect('binding_type', lookups.binding_types.map(function (x) { return { id: x, label: x }; }), 'id', function (r) { return r.label; });
            fillSelect('device_type', lookups.device_types.map(function (x) { return { id: x, label: x }; }), 'id', function (r) { return r.label; });
        });
    }

    byId('btn_save_device_profile').addEventListener('click', function () {
        call({
            action: 'save_profile',
            token: token,
            profile_id: byId('device_profile_id').value,
            device_code: byId('device_code').value,
            label: byId('device_label').value,
            device_type: byId('device_type').value,
            active: byId('device_active').value,
            settings_json: byId('device_settings_json').value
        }).then(function (res) {
            if (!res || !res.success) {
                msg((res && res.message) ? res.message : i18n.unableSaveDeviceProfile, false);
                return;
            }
            msg(i18n.deviceProfileSaved, true);
            loadProfiles();
        });
    });

    byId('btn_bind_terminal').addEventListener('click', function () {
        call({
            action: 'bind_terminal',
            token: token,
            terminal_id: byId('binding_terminal').value,
            profile_id: byId('binding_profile').value,
            binding_type: byId('binding_type').value,
            priority: byId('binding_priority').value,
            active: byId('binding_active').value
        }).then(function (res) {
            if (!res || !res.success) {
                msg((res && res.message) ? res.message : i18n.unableSaveBinding, false);
                return;
            }
            msg(i18n.bindingSaved, true);
            loadBindings();
        });
    });

    byId('btn_test_display').addEventListener('click', function () {
        call({
            action: 'test_display',
            token: token,
            terminal_id: byId('display_terminal').value,
            message: byId('display_message').value
        }).then(function (res) {
            if (!res || !res.success) {
                msg((res && res.message) ? res.message : i18n.unableSendDisplayTest, false);
                return;
            }
            msg(i18n.displayTestSent, true);
        });
    });

    byId('btn_reload_device_profiles').addEventListener('click', loadProfiles);
    byId('btn_reload_bindings').addEventListener('click', loadBindings);
    byId('binding_terminal').addEventListener('change', loadBindings);

    loadLookups().then(function () {
        return loadProfiles();
    }).then(function () {
        return loadBindings();
    });
})();
</script>
<?php
print takeposHelpRender($langs, __FILE__);

llxFooter();
$db->close();
