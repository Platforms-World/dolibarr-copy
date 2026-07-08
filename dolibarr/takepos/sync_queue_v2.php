<?php
/**
 * Sync queue viewer / manager page.
 */
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');

require '../main.inc.php';

require_once __DIR__ . '/lib/takepos_lang.php';
require_once __DIR__ . '/lib/takepos_help.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/class/TakeposAccess.class.php';
require_once __DIR__ . '/class/TakeposAudit.class.php';

$langs->loadLangs(array('cashdesk', 'main', 'takeposcustom@takepos'));

$terminal = isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null;
TakeposAccess::requireFrontendAccess($db, $user, 'takepos.sync_queue', 'takepos.sync.manage', $terminal, $langs->trans('TakeposWorkspaceSyncAccessDenied'), array('page' => 'sync_queue_v2.php'));
TakeposAudit::logEvent($db, $user, 'sync_queue_opened', TakeposAudit::SEVERITY_INFO, array('view' => 'sync_queue'), 'Sync queue page opened');

$head = '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
$arrayofcss = array('/takepos/css/workspace_v2.css');
top_htmlhead($head, $langs->trans('TakeposSyncQueueTitle'), 0, 0, array(), $arrayofcss);
?>
<body class="kfv2-body">
<?php
$v2PageTitle = $langs->trans('TakeposSyncQueueTitle');
$v2PageIcon  = 'fa-cloud-arrow-up';
$v2BackUrl   = DOL_URL_ROOT . '/takepos/pos_v2.php';
require __DIR__ . '/topbar_v2.php';
?>
<div class="kfv2-page-body" style="max-width:1460px;margin:0 auto;padding:22px 26px 48px">
    <h2 style="display:none"><?php echo dol_escape_htmltag($langs->trans('TakeposSyncQueueTitle')); ?></h2>

    <div id="takepos-sync-config"
         data-token="<?php echo dol_escape_htmltag(newToken()); ?>"
         data-endpoint="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/ajax/sync.php'); ?>"></div>
    <div id="takepos-sync-feedback" class="kfv2-msg hidden" role="status" aria-live="polite"></div>

    <section class="kfv2-card-block">
        <div class="kfv2-card-block-head"><h3 style="margin:0;font-size:14.5px"><?php echo dol_escape_htmltag($langs->trans('TakeposSyncQueueStatus')); ?></h3></div><div class="kfv2-card-block-body">
        <div class="kfv2-kpis" id="sync_cards"></div>
        <div class="kfv2-actions">
            <button type="button" id="btn_refresh" class="kfv2-btn kfv2-btn-outline"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonRefresh')); ?></button>
            <button type="button" id="btn_process_pending" class="kfv2-btn kfv2-btn-primary"><?php echo dol_escape_htmltag($langs->trans('TakeposSyncQueueProcessPending')); ?></button>
        </div>
    </section>

    <section class="kfv2-card-block">
        <div class="kfv2-card-block-head"><h3 style="margin:0;font-size:14.5px"><?php echo dol_escape_htmltag($langs->trans('TakeposSyncQueueFilters')); ?></h3></div><div class="kfv2-card-block-body">
        <div class="kfv2-form-grid">
            <div><label for="filter_status"><?php echo dol_escape_htmltag($langs->trans('TakeposSyncQueueStatus')); ?></label><select id="filter_status"><option value=""><?php echo dol_escape_htmltag($langs->trans('TakeposReportsAll')); ?></option><option value="pending"><?php echo dol_escape_htmltag($langs->trans('TakeposSyncQueuePending')); ?></option><option value="syncing"><?php echo dol_escape_htmltag($langs->trans('TakeposSyncQueueSyncing')); ?></option><option value="synced"><?php echo dol_escape_htmltag($langs->trans('TakeposSyncQueueSynced')); ?></option><option value="failed"><?php echo dol_escape_htmltag($langs->trans('TakeposSyncQueueFailed')); ?></option><option value="conflict"><?php echo dol_escape_htmltag($langs->trans('TakeposSyncQueueConflict')); ?></option></select></div>
            <div><label for="filter_action"><?php echo dol_escape_htmltag($langs->trans('TakeposSyncQueueActionType')); ?></label><select id="filter_action"><option value=""><?php echo dol_escape_htmltag($langs->trans('TakeposReportsAll')); ?></option><option value="sale_submit">sale_submit</option><option value="payment_meta">payment_meta</option><option value="cart_snapshot">cart_snapshot</option></select></div>
        </div>
        <div class="kfv2-actions">
            <button type="button" id="btn_reset" class="kfv2-btn kfv2-btn-outline"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerResetFilters')); ?></button>
        </div>
    </section>

    <section class="kfv2-card-block">
        <div class="kfv2-card-block-head"><h3 style="margin:0;font-size:14.5px"><?php echo dol_escape_htmltag($langs->trans('TakeposSyncQueueQueueEntries')); ?></h3></div><div class="kfv2-card-block-body">
        <div class="kfv2-table-wrap"><table id="table_queue" class="kfv2-table"></table></div>
    </section>
</div>

<script>
(function () {
    'use strict';
    var i18n = <?php echo json_encode(array(
        'pending' => $langs->trans('TakeposSyncQueuePending'),
        'syncing' => $langs->trans('TakeposSyncQueueSyncing'),
        'synced' => $langs->trans('TakeposSyncQueueSynced'),
        'failed' => $langs->trans('TakeposSyncQueueFailed'),
        'conflict' => $langs->trans('TakeposSyncQueueConflict'),
        'process' => $langs->trans('TakeposSyncQueueProcess'),
        'retry' => $langs->trans('TakeposSyncQueueRetry'),
        'resolve' => $langs->trans('TakeposSyncQueueResolve'),
        'resolutionNote' => $langs->trans('TakeposSyncQueueResolutionNote'),
        'reviewedConflict' => $langs->trans('TakeposSyncQueueReviewedConflict'),
        'id' => $langs->trans('TakeposSyncQueueId'),
        'action' => $langs->trans('TakeposSyncQueueAction'),
        'localRef' => $langs->trans('TakeposSyncQueueLocalRef'),
        'status' => $langs->trans('TakeposSyncQueueStatus'),
        'retryCount' => $langs->trans('TakeposSyncQueueRetryCount'),
        'lastError' => $langs->trans('TakeposSyncQueueLastError'),
        'created' => $langs->trans('TakeposSyncQueueCreated'),
        'lastAttempt' => $langs->trans('TakeposSyncQueueLastAttempt'),
        'syncedAt' => $langs->trans('TakeposSyncQueueSyncedAt'),
        'noData' => $langs->trans('TakeposReportsNoDataAvailable'),
        'resetFilters' => $langs->trans('TakeposExpenseLedgerResetFilters')
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var cfg = document.getElementById('takepos-sync-config');
    if (!cfg) return;

    var token = cfg.getAttribute('data-token') || '';
    var endpoint = cfg.getAttribute('data-endpoint') || '';
    var feedbackNode = document.getElementById('takepos-sync-feedback');

    function byId(id) { return document.getElementById(id); }
    function safe(v) { return (v === null || v === undefined) ? '' : String(v); }
    function qs(params) { var usp = new URLSearchParams(); Object.keys(params || {}).forEach(function (k) { if (params[k] !== '' && params[k] !== null && params[k] !== undefined) usp.append(k, params[k]); }); return usp.toString(); }
    function parseJsonPayload(txt) {
        var cleaned = (txt || '').replace(/^\uFEFF/, '').trim();
        try {
            return JSON.parse(cleaned);
        } catch (e) {
            var first = cleaned.indexOf('{');
            var last = cleaned.lastIndexOf('}');
            if (first >= 0 && last > first) {
                return JSON.parse(cleaned.slice(first, last + 1));
            }
            throw e;
        }
    }
    function callJson(params) { return fetch(endpoint + '?' + qs(params), { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.text().then(function (txt) { return parseJsonPayload(txt); }); }); }

    function showFeedback(level, message) {
        if (!feedbackNode) {
            return;
        }
        if (!message) {
            feedbackNode.className = 'takepos-workspace-feedback hidden';
            feedbackNode.textContent = '';
            return;
        }

        feedbackNode.className = 'takepos-workspace-feedback ' + (level || 'info');
        feedbackNode.textContent = message;
    }

    function renderCards(summary) {
        var node = byId('sync_cards');
        var keys = ['pending', 'syncing', 'synced', 'failed', 'conflict'];
        var html = '';
        keys.forEach(function (k) {
            html += '<div class="kfv2-kpi"><div class="kk">' + safe(i18n[k] || k) + '</div><div class="kv num">' + safe(summary && summary[k] ? summary[k] : 0) + '</div></div>';
        });
        node.innerHTML = html;
    }

    function actionButtons(row) {
        var html = '<button type="button" class="button btn-process" data-id="' + row.rowid + '">' + safe(i18n.process) + '</button> ';
        if (row.status === 'failed') {
            html += '<button type="button" class="button btn-retry" data-id="' + row.rowid + '">' + safe(i18n.retry) + '</button> ';
        }
        if (row.status === 'conflict') {
            html += '<button type="button" class="button btn-resolve" data-id="' + row.rowid + '">' + safe(i18n.resolve) + '</button>';
        }
        return html;
    }

    function renderTable(rows) {
        var t = byId('table_queue');
        var html = '<thead><tr><th>' + safe(i18n.id) + '</th><th>' + safe(i18n.action) + '</th><th>' + safe(i18n.localRef) + '</th><th>' + safe(i18n.status) + '</th><th>' + safe(i18n.retryCount) + '</th><th>' + safe(i18n.lastError) + '</th><th>' + safe(i18n.created) + '</th><th>' + safe(i18n.lastAttempt) + '</th><th>' + safe(i18n.syncedAt) + '</th><th>' + safe(i18n.action) + '</th></tr></thead><tbody>';
        if (!(rows || []).length) {
            html += '<tr><td colspan="10" class="takepos-workspace-empty">' + safe(i18n.noData) + '</td></tr>';
        } else {
            (rows || []).forEach(function (r) {
                html += '<tr>'
                    + '<td>' + safe(r.rowid) + '</td>'
                    + '<td>' + safe(r.action_type) + '</td>'
                    + '<td>' + safe(r.local_ref) + '</td>'
                    + '<td>' + safe(r.status) + '</td>'
                    + '<td>' + safe(r.retry_count) + '</td>'
                    + '<td>' + safe(r.last_error) + '</td>'
                    + '<td>' + safe(r.date_creation) + '</td>'
                    + '<td>' + safe(r.date_last_attempt) + '</td>'
                    + '<td>' + safe(r.date_synced) + '</td>'
                    + '<td>' + actionButtons(r) + '</td>'
                    + '</tr>';
            });
        }
        html += '</tbody>';
        t.innerHTML = html;

        t.querySelectorAll('.btn-process').forEach(function (btn) {
            btn.addEventListener('click', function () {
                callJson({ action: 'process_one', token: token, queue_id: btn.getAttribute('data-id') }).then(function (res) {
                    showFeedback((res && res.success) ? 'success' : 'error', res && res.message ? res.message : '');
                    load();
                });
            });
        });
        t.querySelectorAll('.btn-retry').forEach(function (btn) {
            btn.addEventListener('click', function () {
                callJson({ action: 'retry', token: token, queue_id: btn.getAttribute('data-id') }).then(function (res) {
                    showFeedback((res && res.success) ? 'success' : 'error', res && res.message ? res.message : '');
                    load();
                });
            });
        });
        t.querySelectorAll('.btn-resolve').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var note = window.prompt(i18n.resolutionNote, i18n.reviewedConflict);
                if (note === null) return;
                callJson({ action: 'resolve_conflict', token: token, queue_id: btn.getAttribute('data-id'), note: note }).then(function (res) {
                    showFeedback((res && res.success) ? 'success' : 'error', res && res.message ? res.message : '');
                    load();
                });
            });
        });
    }

    function load() {
        callJson({ action: 'list', status: byId('filter_status').value, action_type: byId('filter_action').value }).then(function (res) {
            if (!res || !res.success) {
                showFeedback('error', res && res.message ? res.message : '');
                return;
            }
            renderCards(res.summary || {});
            renderTable(res.rows || []);
        });
    }

    function resetFilters() {
        byId('filter_status').value = '';
        byId('filter_action').value = '';
        showFeedback('info', i18n.resetFilters);
        load();
    }

    byId('btn_refresh').addEventListener('click', load);
    byId('btn_process_pending').addEventListener('click', function () {
        callJson({ action: 'process_pending', token: token, limit: 50 }).then(function (res) {
            showFeedback((res && res.success) ? 'success' : 'error', res && res.message ? res.message : '');
            load();
        });
    });
    byId('btn_reset').addEventListener('click', resetFilters);
    byId('filter_status').addEventListener('change', load);
    byId('filter_action').addEventListener('change', load);

    load();
})();
</script>
<?php echo takeposHelpRender($langs, __FILE__); ?>
</body>
<?php
llxFooter();
$db->close();
