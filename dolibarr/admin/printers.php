<?php
/**
 * Printer profiles admin page.
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
    'takepos.printer_profiles',
    'takepos.device.manage',
    isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null,
    $langs->trans('TakeposAdminPrintersAccessDenied')
);

TakeposAudit::logEvent($db, $user, 'printer_profiles_opened', TakeposAudit::SEVERITY_INFO, array('page' => 'admin/printers.php'), 'Printer profiles opened');

llxHeader('', $langs->trans('TakeposAdminPrintersTitle'));
print load_fiche_titre($langs->trans('TakeposAdminPrintersTitle'));
?>
<div id="takepos-printer-config"
     data-token="<?php echo dol_escape_htmltag(newToken()); ?>"
     data-endpoint="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/ajax/device.php'); ?>"></div>

<style>
.tp-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; }
.tp-panel { border:1px solid #d9dfe8; border-radius:8px; padding:12px; margin-bottom:14px; background:#fff; }
.tp-table { width:100%; border-collapse:collapse; }
.tp-table th, .tp-table td { border:1px solid #e5e7eb; padding:6px 8px; font-size:13px; }
.tp-table th { background:#f7f8fa; text-align:left; }
.tp-actions { margin-top:10px; display:flex; gap:8px; flex-wrap:wrap; }
.tp-message { margin:8px 0 12px; font-weight:600; }
</style>

<div class="tp-panel">
    <h3><?php echo dol_escape_htmltag($langs->trans('TakeposAdminPrinterProfile')); ?></h3>
    <div class="tp-grid">
        <div>
            <label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminProfileId')); ?></label>
            <input type="number" id="printer_profile_id" min="0" value="0" class="flat minwidth100">
        </div>
        <div>
            <label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminProfileCode')); ?></label>
            <input type="text" id="printer_profile_code" class="flat minwidth200" placeholder="PRN_MAIN">
        </div>
        <div>
            <label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminLabel')); ?></label>
            <input type="text" id="printer_label" class="flat minwidth200" placeholder="Main Counter Printer">
        </div>
        <div>
            <label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminDriver')); ?></label>
            <select id="printer_driver_type" class="flat minwidth180"></select>
        </div>
        <div>
            <label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminTargetUri')); ?></label>
            <input type="text" id="printer_target_uri" class="flat minwidth250" placeholder="tcp://192.168.1.30:9100">
        </div>
        <div>
            <label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminCopies')); ?></label>
            <input type="number" id="printer_copies" min="1" max="20" value="1" class="flat minwidth80">
        </div>
        <div>
            <label><?php echo dol_escape_htmltag($langs->trans('TakeposCommonActive')); ?></label>
            <select id="printer_active" class="flat minwidth100"><option value="1"><?php echo dol_escape_htmltag($langs->trans('TakeposAdminActiveYes')); ?></option><option value="0"><?php echo dol_escape_htmltag($langs->trans('TakeposAdminActiveNo')); ?></option></select>
        </div>
    </div>
    <div style="margin-top:10px;">
        <label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminSettingsJson')); ?></label>
        <textarea id="printer_settings_json" class="flat" style="width:100%;min-height:90px;">{}</textarea>
    </div>
    <div class="tp-actions">
        <button type="button" class="button button-save" id="btn_save_printer_profile"><?php echo dol_escape_htmltag($langs->trans('TakeposAdminSavePrinterProfile')); ?></button>
        <button type="button" class="button" id="btn_reload_printer_profiles"><?php echo dol_escape_htmltag($langs->trans('TakeposAdminReloadProfiles')); ?></button>
    </div>
</div>

<div class="tp-panel">
    <h3><?php echo dol_escape_htmltag($langs->trans('TakeposAdminTestPrint')); ?></h3>
    <div class="tp-grid">
        <div>
            <label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminPrinterProfileSelect')); ?></label>
            <select id="test_profile_id" class="flat minwidth260"></select>
        </div>
        <div>
            <label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminTerminalOptional')); ?></label>
            <input type="number" id="test_terminal_id" min="0" value="0" class="flat minwidth100">
        </div>
    </div>
    <div style="margin-top:10px;">
        <label><?php echo dol_escape_htmltag($langs->trans('TakeposAdminTestContent')); ?></label>
        <textarea id="test_content" class="flat" style="width:100%;min-height:80px;"><?php echo dol_escape_htmltag($langs->trans('TakeposPrinterTestDefault')); ?></textarea>
    </div>
    <div class="tp-actions">
        <button type="button" class="button" id="btn_test_printer"><?php echo dol_escape_htmltag($langs->trans('TakeposAdminSendTestPrint')); ?></button>
    </div>
</div>

<div id="printer_message" class="tp-message"></div>

<div class="tp-panel">
    <h3><?php echo dol_escape_htmltag($langs->trans('TakeposAdminPrinterProfiles')); ?></h3>
    <table id="table_printer_profiles" class="tp-table"></table>
</div>

<script>
(function () {
    'use strict';

    var cfg = document.getElementById('takepos-printer-config');
    if (!cfg) return;

    var endpoint = cfg.getAttribute('data-endpoint') || '';
    var token = cfg.getAttribute('data-token') || '';
    var i18n = <?php echo json_encode(array(
        'id' => $langs->trans('TakeposAdminId'),
        'code' => $langs->trans('TakeposCommonCode'),
        'label' => $langs->trans('TakeposAdminLabel'),
        'driver' => $langs->trans('TakeposAdminDriver'),
        'target' => $langs->trans('TakeposAdminTargetUri'),
        'copies' => $langs->trans('TakeposAdminCopies'),
        'active' => $langs->trans('TakeposCommonActive'),
        'settings' => $langs->trans('TakeposAdminSettings'),
        'unableLoadLookups' => $langs->trans('TakeposAdminUnableLoadPrinterLookups'),
        'unableLoadProfiles' => $langs->trans('TakeposAdminUnableLoadPrinterProfiles'),
        'unableSaveProfile' => $langs->trans('TakeposAdminUnableSavePrinterProfile'),
        'profileSaved' => $langs->trans('TakeposAdminPrinterProfileSaved'),
        'unableSendTest' => $langs->trans('TakeposAdminUnableSendPrinterTest'),
        'testSubmitted' => $langs->trans('TakeposAdminPrinterTestSubmitted')
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function byId(id) { return document.getElementById(id); }
    function safe(v) { return (v === null || v === undefined) ? '' : String(v); }
    function qs(params) { var usp = new URLSearchParams(); Object.keys(params || {}).forEach(function (k) { if (params[k] !== '' && params[k] !== null && params[k] !== undefined) usp.append(k, params[k]); }); return usp.toString(); }
    function call(params) { return fetch(endpoint + '?' + qs(params), { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); }); }

    function msg(text, ok) {
        var n = byId('printer_message');
        if (!n) return;
        n.style.color = ok ? '#1f7a45' : '#8b1f1f';
        n.textContent = text || '';
    }

    function fillDrivers(drivers) {
        var s = byId('printer_driver_type');
        s.innerHTML = '';
        (drivers || []).forEach(function (driver) {
            var o = document.createElement('option');
            o.value = driver;
            o.textContent = driver;
            s.appendChild(o);
        });
    }

    function fillPrinterSelect(rows) {
        var s = byId('test_profile_id');
        s.innerHTML = '';
        (rows || []).forEach(function (r) {
            var o = document.createElement('option');
            o.value = r.rowid;
            o.textContent = safe(r.profile_code) + ' - ' + safe(r.label) + ' (' + safe(r.driver_type) + ')';
            s.appendChild(o);
        });
    }

    function renderProfiles(rows) {
        var t = byId('table_printer_profiles');
        var html = '<thead><tr><th>' + i18n.id + '</th><th>' + i18n.code + '</th><th>' + i18n.label + '</th><th>' + i18n.driver + '</th><th>' + i18n.target + '</th><th>' + i18n.copies + '</th><th>' + i18n.active + '</th><th>' + i18n.settings + '</th></tr></thead><tbody>';
        (rows || []).forEach(function (r) {
            html += '<tr>'
                + '<td>' + safe(r.rowid) + '</td>'
                + '<td>' + safe(r.profile_code) + '</td>'
                + '<td>' + safe(r.label) + '</td>'
                + '<td>' + safe(r.driver_type) + '</td>'
                + '<td>' + safe(r.target_uri) + '</td>'
                + '<td>' + safe(r.copies) + '</td>'
                + '<td>' + safe(r.active) + '</td>'
                + '<td><code>' + safe(r.settings_json) + '</code></td>'
                + '</tr>';
        });
        html += '</tbody>';
        t.innerHTML = html;
    }

    function loadLookups() {
        return call({ action: 'lookups' }).then(function (res) {
            if (!res || !res.success) {
                msg((res && res.message) ? res.message : i18n.unableLoadLookups, false);
                return;
            }
            fillDrivers(res.printer_drivers || []);
        });
    }

    function loadProfiles() {
        return call({ action: 'list_printers' }).then(function (res) {
            if (!res || !res.success) {
                msg((res && res.message) ? res.message : i18n.unableLoadProfiles, false);
                return;
            }
            renderProfiles(res.rows || []);
            fillPrinterSelect(res.rows || []);
        });
    }

    byId('btn_save_printer_profile').addEventListener('click', function () {
        call({
            action: 'save_printer',
            token: token,
            profile_id: byId('printer_profile_id').value,
            profile_code: byId('printer_profile_code').value,
            label: byId('printer_label').value,
            driver_type: byId('printer_driver_type').value,
            target_uri: byId('printer_target_uri').value,
            copies: byId('printer_copies').value,
            active: byId('printer_active').value,
            settings_json: byId('printer_settings_json').value
        }).then(function (res) {
            if (!res || !res.success) {
                msg((res && res.message) ? res.message : i18n.unableSaveProfile, false);
                return;
            }
            msg(i18n.profileSaved, true);
            loadProfiles();
        });
    });

    byId('btn_test_printer').addEventListener('click', function () {
        call({
            action: 'test_printer',
            token: token,
            profile_id: byId('test_profile_id').value,
            terminal_id: byId('test_terminal_id').value,
            content: byId('test_content').value
        }).then(function (res) {
            if (!res || !res.success) {
                msg((res && res.message) ? res.message : i18n.unableSendTest, false);
                return;
            }
            msg(i18n.testSubmitted, true);
        });
    });

    byId('btn_reload_printer_profiles').addEventListener('click', loadProfiles);

    loadLookups().then(loadProfiles);
})();
</script>
<?php
print takeposHelpRender($langs, __FILE__);

llxFooter();
$db->close();
