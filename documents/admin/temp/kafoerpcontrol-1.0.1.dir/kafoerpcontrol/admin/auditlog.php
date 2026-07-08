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
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once dol_buildpath('/kafoerpcontrol/core/lib/saascore.lib.php', 0);

$langs->loadLangs(array('admin', 'users', 'other', 'kafoerpcontrol@kafoerpcontrol'));
saascoreRequireAdminRight('read');
saascoreSyncKnownIntegrations($db);

$form = new Form($db);
$tableAudit = MAIN_DB_PREFIX . 'saas_audit_log';
$entityId = (int) $conf->entity;

function kafoAuditEscape($value)
{
    return dol_escape_htmltag((string) $value);
}

function kafoAuditDisplayUser($login, $firstname, $lastname)
{
    $fullName = trim((string) $firstname . ' ' . (string) $lastname);
    if ($fullName !== '' && (string) $login !== '') {
        return $login . ' - ' . $fullName;
    }
    if ((string) $login !== '') {
        return (string) $login;
    }
    if ($fullName !== '') {
        return $fullName;
    }
    return '-';
}

function kafoAuditTableExists($db, $table)
{
    $sql = "SHOW TABLES LIKE '" . $db->escape($table) . "'";
    $resql = $db->query($sql);
    return ($resql && $db->num_rows($resql) > 0);
}

function kafoAuditColumnExists($db, $table, $column)
{
    $sql = 'SHOW COLUMNS FROM ' . $table . " LIKE '" . $db->escape($column) . "'";
    $resql = $db->query($sql);
    return ($resql && $db->num_rows($resql) > 0);
}

function kafoAuditGetUserOptions($db, $entityId)
{
    $rows = array();
    $sql = 'SELECT rowid, login, firstname, lastname';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'user';
    $sql .= ' WHERE entity IN (0, ' . ((int) $entityId) . ')';
    $sql .= ' ORDER BY login ASC';

    $resql = $db->query($sql);
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $rows[(int) $obj->rowid] = kafoAuditDisplayUser($obj->login, $obj->firstname, $obj->lastname);
    }

    return $rows;
}

function kafoAuditGetDistinctValues($db, $tableAudit, $fieldExpression, $entityWhere)
{
    $values = array();
    $sql = 'SELECT DISTINCT ' . $fieldExpression . ' as value';
    $sql .= ' FROM ' . $tableAudit . ' as a';
    $sql .= ' WHERE ' . $entityWhere;
    $sql .= ' AND ' . $fieldExpression . " <> ''";
    $sql .= ' ORDER BY value ASC';

    $resql = $db->query($sql);
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $values[] = (string) $obj->value;
    }

    return $values;
}

$filterDateFrom = trim(GETPOST('date_from', 'alphanohtml'));
$filterDateTo = trim(GETPOST('date_to', 'alphanohtml'));
$filterActor = GETPOST('actor_userid', 'int');
$filterTarget = GETPOST('target_userid', 'int');
$filterActionType = trim(GETPOST('action_type', 'alphanohtml'));
$filterObjectType = trim(GETPOST('object_type', 'alphanohtml'));
$filterContext = trim(GETPOST('context_page', 'alphanohtml'));
$filterSearch = trim(GETPOST('search_text', 'restricthtml'));
$export = GETPOST('export', 'alpha');

$sortfield = GETPOST('sortfield', 'aZ09');
$sortorder = strtoupper(GETPOST('sortorder', 'aZ09'));
if ($sortorder !== 'ASC') {
    $sortorder = 'DESC';
}

$page = max(0, (int) GETPOST('page', 'int'));
$limit = (int) GETPOST('limit', 'int');
if ($limit <= 0) {
    $limit = 50;
}
$limit = min(200, $limit);
$offset = $page * $limit;

$rows = array();
$totalRows = 0;
$summary = (object) array(
    'total_logs_today' => 0,
    'perm_changes_today' => 0,
    'logins_today' => 0,
    'role_changes_today' => 0,
    'module_feature_updates_today' => 0,
);
$actionOptions = array();
$objectOptions = array();
$warningMessage = '';

$tableExists = kafoAuditTableExists($db, $tableAudit);
$colExists = array();
if ($tableExists) {
    $columnsToCheck = array(
        'entity', 'entity_id', 'datec', 'date_created', 'fk_user', 'fk_user_actor', 'fk_user_target',
        'action_type', 'action_code', 'object_type', 'target_type', 'object_key', 'target_code',
        'old_value', 'new_value', 'description', 'ip_address', 'context_page',
    );
    foreach ($columnsToCheck as $colName) {
        $colExists[$colName] = kafoAuditColumnExists($db, $tableAudit, $colName);
    }
}

if (!$tableExists) {
    $warningMessage = 'Audit table is not available yet. Re-enable the module or run upgrade SQL.';
} else {
    $auditService = saascoreGetAuditLogService($db);
    if (is_object($auditService) && method_exists($auditService, 'ensureSchema')) {
        $auditService->ensureSchema();
        foreach ($colExists as $colName => $v) {
            $colExists[$colName] = kafoAuditColumnExists($db, $tableAudit, $colName);
        }
    }

    $exprEntity = '0';
    if (!empty($colExists['entity'])) {
        $exprEntity = 'a.entity';
    } elseif (!empty($colExists['entity_id'])) {
        $exprEntity = 'a.entity_id';
    }

    $exprDate = 'NULL';
    if (!empty($colExists['datec']) && !empty($colExists['date_created'])) {
        $exprDate = 'COALESCE(a.datec, a.date_created)';
    } elseif (!empty($colExists['datec'])) {
        $exprDate = 'a.datec';
    } elseif (!empty($colExists['date_created'])) {
        $exprDate = 'a.date_created';
    }

    $exprActor = 'NULL';
    if (!empty($colExists['fk_user_actor']) && !empty($colExists['fk_user'])) {
        $exprActor = 'COALESCE(a.fk_user_actor, a.fk_user)';
    } elseif (!empty($colExists['fk_user_actor'])) {
        $exprActor = 'a.fk_user_actor';
    } elseif (!empty($colExists['fk_user'])) {
        $exprActor = 'a.fk_user';
    }

    $exprTarget = (!empty($colExists['fk_user_target']) ? 'a.fk_user_target' : 'NULL');

    $exprAction = "''";
    if (!empty($colExists['action_type']) && !empty($colExists['action_code'])) {
        $exprAction = 'COALESCE(a.action_type, a.action_code)';
    } elseif (!empty($colExists['action_type'])) {
        $exprAction = 'a.action_type';
    } elseif (!empty($colExists['action_code'])) {
        $exprAction = 'a.action_code';
    }

    $exprObjectType = "''";
    if (!empty($colExists['object_type']) && !empty($colExists['target_type'])) {
        $exprObjectType = 'COALESCE(a.object_type, a.target_type)';
    } elseif (!empty($colExists['object_type'])) {
        $exprObjectType = 'a.object_type';
    } elseif (!empty($colExists['target_type'])) {
        $exprObjectType = 'a.target_type';
    }

    $exprObjectKey = "''";
    if (!empty($colExists['object_key']) && !empty($colExists['target_code'])) {
        $exprObjectKey = 'COALESCE(a.object_key, a.target_code)';
    } elseif (!empty($colExists['object_key'])) {
        $exprObjectKey = 'a.object_key';
    } elseif (!empty($colExists['target_code'])) {
        $exprObjectKey = 'a.target_code';
    }

    $exprOldValue = (!empty($colExists['old_value']) ? 'a.old_value' : 'NULL');
    $exprNewValue = (!empty($colExists['new_value']) ? 'a.new_value' : 'NULL');
    $exprDescription = (!empty($colExists['description']) ? 'a.description' : 'NULL');
    $exprIp = (!empty($colExists['ip_address']) ? 'a.ip_address' : 'NULL');
    $exprContext = (!empty($colExists['context_page']) ? 'a.context_page' : "''");

    $sortMap = array(
        'datec' => $exprDate,
        'action_type' => $exprAction,
        'object_type' => $exprObjectType,
        'object_key' => $exprObjectKey,
        'context_page' => $exprContext,
    );
    if (!isset($sortMap[$sortfield])) {
        $sortfield = 'datec';
    }

    $orderExpr = $sortMap[$sortfield];
    if ($orderExpr === 'NULL' || $orderExpr === "''") {
        $orderExpr = 'a.rowid';
    }
    $orderBy = $orderExpr . ' ' . $sortorder . ', a.rowid DESC';

    $entityWhere = $exprEntity . ' IN (0, ' . $entityId . ')';
    $where = array($entityWhere);

    if ($filterDateFrom !== '') {
        if ($exprDate !== 'NULL') {
            $where[] = $exprDate . " >= '" . $db->escape($filterDateFrom . ' 00:00:00') . "'";
        } else {
            $where[] = '1 = 0';
        }
    }
    if ($filterDateTo !== '') {
        if ($exprDate !== 'NULL') {
            $where[] = $exprDate . " <= '" . $db->escape($filterDateTo . ' 23:59:59') . "'";
        } else {
            $where[] = '1 = 0';
        }
    }
    if ($filterActor > 0) {
        if ($exprActor !== 'NULL') {
            $where[] = $exprActor . ' = ' . ((int) $filterActor);
        } else {
            $where[] = '1 = 0';
        }
    }
    if ($filterTarget > 0) {
        if ($exprTarget !== 'NULL') {
            $where[] = $exprTarget . ' = ' . ((int) $filterTarget);
        } else {
            $where[] = '1 = 0';
        }
    }
    if ($filterActionType !== '') {
        if ($exprAction !== "''") {
            $where[] = $exprAction . " = '" . $db->escape($filterActionType) . "'";
        } else {
            $where[] = '1 = 0';
        }
    }
    if ($filterObjectType !== '') {
        if ($exprObjectType !== "''") {
            $where[] = $exprObjectType . " = '" . $db->escape($filterObjectType) . "'";
        } else {
            $where[] = '1 = 0';
        }
    }
    if ($filterContext !== '') {
        if ($exprContext !== "''") {
            $where[] = $exprContext . " LIKE '%" . $db->escape($filterContext) . "%'";
        } else {
            $where[] = '1 = 0';
        }
    }
    if ($filterSearch !== '') {
        $search = $db->escape($filterSearch);
        $searchParts = array();
        if ($exprDescription !== 'NULL') $searchParts[] = $exprDescription . " LIKE '%" . $search . "%'";
        if ($exprOldValue !== 'NULL') $searchParts[] = $exprOldValue . " LIKE '%" . $search . "%'";
        if ($exprNewValue !== 'NULL') $searchParts[] = $exprNewValue . " LIKE '%" . $search . "%'";
        if ($exprObjectKey !== "''") $searchParts[] = $exprObjectKey . " LIKE '%" . $search . "%'";
        if (!empty($searchParts)) {
            $where[] = '(' . implode(' OR ', $searchParts) . ')';
        } else {
            $where[] = '1 = 0';
        }
    }

    $whereSql = implode(' AND ', $where);

    $sqlCount = 'SELECT COUNT(*) as nb FROM ' . $tableAudit . ' as a WHERE ' . $whereSql;
    $resCount = $db->query($sqlCount);
    if ($resCount && ($objCount = $db->fetch_object($resCount))) {
        $totalRows = (int) $objCount->nb;
    }

    $sqlSelect = 'SELECT a.rowid,';
    $sqlSelect .= ' ' . $exprDate . ' as datec,';
    $sqlSelect .= ' ' . $exprActor . ' as actor_id,';
    $sqlSelect .= ' ' . $exprTarget . ' as target_id,';
    $sqlSelect .= ' ' . $exprAction . ' as action_type,';
    $sqlSelect .= ' ' . $exprObjectType . ' as object_type,';
    $sqlSelect .= ' ' . $exprObjectKey . ' as object_key,';
    $sqlSelect .= ' ' . $exprOldValue . ' as old_value,';
    $sqlSelect .= ' ' . $exprNewValue . ' as new_value,';
    $sqlSelect .= ' ' . $exprDescription . ' as description,';
    $sqlSelect .= ' ' . $exprIp . ' as ip_address,';
    $sqlSelect .= ' ' . $exprContext . ' as context_page,';
    $sqlSelect .= ' ua.login as actor_login, ua.firstname as actor_firstname, ua.lastname as actor_lastname,';
    $sqlSelect .= ' ut.login as target_login, ut.firstname as target_firstname, ut.lastname as target_lastname';
    $sqlSelect .= ' FROM ' . $tableAudit . ' as a';
    if ($exprActor !== 'NULL') {
        $sqlSelect .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'user as ua ON ua.rowid = ' . $exprActor;
    } else {
        $sqlSelect .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'user as ua ON 1 = 0';
    }
    if ($exprTarget !== 'NULL') {
        $sqlSelect .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'user as ut ON ut.rowid = ' . $exprTarget;
    } else {
        $sqlSelect .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'user as ut ON 1 = 0';
    }
    $sqlSelect .= ' WHERE ' . $whereSql;
    $sqlSelect .= ' ORDER BY ' . $orderBy;

    if ($export !== 'csv') {
        $sqlSelect .= ' LIMIT ' . ((int) $limit) . ' OFFSET ' . ((int) $offset);
    }

    $resql = $db->query($sqlSelect);
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $rows[] = $obj;
    }

    if ($exprDate !== 'NULL') {
        $todayStartTs = (function_exists('dol_get_first_hour') ? dol_get_first_hour(dol_now()) : strtotime(date('Y-m-d 00:00:00')));
        $todayEndTs = (function_exists('dol_get_last_hour') ? dol_get_last_hour(dol_now()) : strtotime(date('Y-m-d 23:59:59')));
        $todayStart = dol_print_date($todayStartTs, '%Y-%m-%d %H:%M:%S');
        $todayEnd = dol_print_date($todayEndTs, '%Y-%m-%d %H:%M:%S');
        $sqlSummary = 'SELECT';
        $sqlSummary .= ' COUNT(*) as total_logs_today,';
        $sqlSummary .= " SUM(CASE WHEN " . $exprAction . " IN ('permission_add','permission_remove') THEN 1 ELSE 0 END) as perm_changes_today,";
        $sqlSummary .= " SUM(CASE WHEN " . $exprAction . " = 'login_success' THEN 1 ELSE 0 END) as logins_today,";
        $sqlSummary .= " SUM(CASE WHEN " . $exprAction . " IN ('role_add','role_remove','role_create','role_delete') THEN 1 ELSE 0 END) as role_changes_today,";
        $sqlSummary .= " SUM(CASE WHEN " . $exprAction . " IN ('tenant.save.modules','tenant.save.features','tenant.save.limits','tenant.save.bundles','module_status_update','feature_flag_update','limit_update') THEN 1 ELSE 0 END) as module_feature_updates_today";
        $sqlSummary .= ' FROM ' . $tableAudit . ' as a';
        $sqlSummary .= ' WHERE ' . $entityWhere;
        $sqlSummary .= " AND " . $exprDate . " >= '" . $db->escape($todayStart) . "'";
        $sqlSummary .= " AND " . $exprDate . " <= '" . $db->escape($todayEnd) . "'";

        $resSummary = $db->query($sqlSummary);
        if ($resSummary && ($objSummary = $db->fetch_object($resSummary))) {
            $summary = $objSummary;
        }
    }

    $actionOptions = kafoAuditGetDistinctValues($db, $tableAudit, $exprAction, $entityWhere);
    $objectOptions = kafoAuditGetDistinctValues($db, $tableAudit, $exprObjectType, $entityWhere);
}

$userOptions = kafoAuditGetUserOptions($db, $entityId);

if ($export === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="kafoerp_audit_log_' . date('Ymd_His') . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, array('DateTime', 'Actor', 'TargetUser', 'ActionType', 'ObjectType', 'ObjectKey', 'OldValue', 'NewValue', 'Description', 'IP', 'ContextPage'));
    foreach ($rows as $row) {
        $actorLabel = kafoAuditDisplayUser(isset($row->actor_login) ? $row->actor_login : '', isset($row->actor_firstname) ? $row->actor_firstname : '', isset($row->actor_lastname) ? $row->actor_lastname : '');
        $targetLabel = kafoAuditDisplayUser(isset($row->target_login) ? $row->target_login : '', isset($row->target_firstname) ? $row->target_firstname : '', isset($row->target_lastname) ? $row->target_lastname : '');
        fputcsv($out, array(
            isset($row->datec) ? $row->datec : '',
            $actorLabel,
            $targetLabel,
            isset($row->action_type) ? $row->action_type : '',
            isset($row->object_type) ? $row->object_type : '',
            isset($row->object_key) ? $row->object_key : '',
            isset($row->old_value) ? $row->old_value : '',
            isset($row->new_value) ? $row->new_value : '',
            isset($row->description) ? $row->description : '',
            isset($row->ip_address) ? $row->ip_address : '',
            isset($row->context_page) ? $row->context_page : '',
        ));
    }
    fclose($out);
    exit;
}

$queryBase = array(
    'date_from' => $filterDateFrom,
    'date_to' => $filterDateTo,
    'actor_userid' => $filterActor,
    'target_userid' => $filterTarget,
    'action_type' => $filterActionType,
    'object_type' => $filterObjectType,
    'context_page' => $filterContext,
    'search_text' => $filterSearch,
    'sortfield' => $sortfield,
    'sortorder' => $sortorder,
    'limit' => $limit,
);

$totalPages = ($limit > 0 ? (int) ceil($totalRows / $limit) : 1);
if ($totalPages <= 0) {
    $totalPages = 1;
}

llxHeader('', $langs->trans('AuditLog'));
print load_fiche_titre($langs->trans('AuditLog'), '', 'title_setup');
print dol_get_fiche_head(saascoreAdminPrepareHead(), 'auditlog', 'kafo-ERP-Control', -1, 'generic');

if ($warningMessage !== '') {
    setEventMessages($warningMessage, null, 'warnings');
}

print '<style>
.kafo-audit-widgets{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin:10px 0 14px 0}
.kafo-audit-widget{border:1px solid #d8dce6;border-radius:6px;padding:10px;background:#fff}
.kafo-audit-widget .label{font-size:12px;color:#666}
.kafo-audit-widget .value{font-size:20px;font-weight:600;margin-top:6px}
.kafo-audit-filters td{padding:6px 8px}
.kafo-audit-cell{max-width:260px;white-space:normal;word-break:break-word}
.kafo-audit-nav{display:flex;gap:8px;align-items:center;margin:8px 0}
</style>';

print '<div class="kafo-audit-widgets">';
print '<div class="kafo-audit-widget"><div class="label">Total logs today</div><div class="value">' . ((int) $summary->total_logs_today) . '</div></div>';
print '<div class="kafo-audit-widget"><div class="label">Permission changes today</div><div class="value">' . ((int) $summary->perm_changes_today) . '</div></div>';
print '<div class="kafo-audit-widget"><div class="label">Logins today</div><div class="value">' . ((int) $summary->logins_today) . '</div></div>';
print '<div class="kafo-audit-widget"><div class="label">Role changes today</div><div class="value">' . ((int) $summary->role_changes_today) . '</div></div>';
print '<div class="kafo-audit-widget"><div class="label">Module/Feature updates today</div><div class="value">' . ((int) $summary->module_feature_updates_today) . '</div></div>';
print '</div>';

print '<form method="GET" action="' . kafoAuditEscape($_SERVER['PHP_SELF']) . '">';
print '<table class="noborder centpercent kafo-audit-filters">';
print '<tr class="liste_titre"><th colspan="6">Filters</th></tr>';

print '<tr class="oddeven">';
print '<td class="titlefield">Date from</td><td><input type="date" class="flat" name="date_from" value="' . kafoAuditEscape($filterDateFrom) . '"></td>';
print '<td class="titlefield">Date to</td><td><input type="date" class="flat" name="date_to" value="' . kafoAuditEscape($filterDateTo) . '"></td>';
print '<td class="titlefield">Search</td><td><input type="text" class="flat minwidth200" name="search_text" value="' . kafoAuditEscape($filterSearch) . '"></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td class="titlefield">Actor user</td><td>' . $form->selectarray('actor_userid', $userOptions, $filterActor, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200') . '</td>';
print '<td class="titlefield">Target user</td><td>' . $form->selectarray('target_userid', $userOptions, $filterTarget, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200') . '</td>';
print '<td class="titlefield">Context page</td><td><input type="text" class="flat minwidth200" name="context_page" value="' . kafoAuditEscape($filterContext) . '"></td>';
print '</tr>';

$actionSelect = array();
foreach ($actionOptions as $opt) {
    $actionSelect[$opt] = $opt;
}
$objectSelect = array();
foreach ($objectOptions as $opt) {
    $objectSelect[$opt] = $opt;
}

print '<tr class="oddeven">';
print '<td class="titlefield">Action type</td><td>' . $form->selectarray('action_type', $actionSelect, $filterActionType, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200') . '</td>';
print '<td class="titlefield">Object type</td><td>' . $form->selectarray('object_type', $objectSelect, $filterObjectType, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200') . '</td>';
print '<td class="titlefield">Rows per page</td><td><input type="number" min="10" max="200" class="flat minwidth100" name="limit" value="' . ((int) $limit) . '"></td>';
print '</tr>';

print '<tr class="oddeven"><td colspan="6">';
print '<input type="hidden" name="sortfield" value="' . kafoAuditEscape($sortfield) . '">';
print '<input type="hidden" name="sortorder" value="' . kafoAuditEscape($sortorder) . '">';
print '<input type="submit" class="button" value="' . $langs->trans('Filter') . '"> ';
print '<input type="submit" class="button" name="export" value="csv">';
print '</td></tr>';

print '</table>';
print '</form>';

print '<div class="kafo-audit-nav">';
print '<span>Page ' . ($page + 1) . ' / ' . $totalPages . '</span>';
if ($page > 0) {
    $queryPrev = $queryBase;
    $queryPrev['page'] = $page - 1;
    print '<a class="button" href="' . kafoAuditEscape($_SERVER['PHP_SELF']) . '?' . http_build_query($queryPrev) . '">Previous</a>';
}
if (($page + 1) < $totalPages) {
    $queryNext = $queryBase;
    $queryNext['page'] = $page + 1;
    print '<a class="button" href="' . kafoAuditEscape($_SERVER['PHP_SELF']) . '?' . http_build_query($queryNext) . '">Next</a>';
}
print '<span>Total: ' . ((int) $totalRows) . '</span>';
print '</div>';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>Date/Time</th>';
print '<th>Actor</th>';
print '<th>Target User</th>';
print '<th>Action Type</th>';
print '<th>Object Type</th>';
print '<th>Object Key</th>';
print '<th>Old Value</th>';
print '<th>New Value</th>';
print '<th>Description</th>';
print '<th>IP</th>';
print '<th>Page/Context</th>';
print '</tr>';

if (empty($rows)) {
    print '<tr class="oddeven"><td colspan="11">' . kafoAuditEscape($langs->trans('NoRecordFound')) . '</td></tr>';
} else {
    foreach ($rows as $row) {
        $actorLabel = kafoAuditDisplayUser(isset($row->actor_login) ? $row->actor_login : '', isset($row->actor_firstname) ? $row->actor_firstname : '', isset($row->actor_lastname) ? $row->actor_lastname : '');
        $targetLabel = kafoAuditDisplayUser(isset($row->target_login) ? $row->target_login : '', isset($row->target_firstname) ? $row->target_firstname : '', isset($row->target_lastname) ? $row->target_lastname : '');
        $dateValue = (isset($row->datec) && $row->datec !== null ? (string) $row->datec : '');
        $dateLabel = ($dateValue !== '' ? dol_print_date(dol_stringtotime($dateValue), 'dayhourlog') : '-');

        print '<tr class="oddeven">';
        print '<td>' . kafoAuditEscape($dateLabel) . '</td>';
        print '<td class="kafo-audit-cell">' . kafoAuditEscape($actorLabel) . '</td>';
        print '<td class="kafo-audit-cell">' . kafoAuditEscape($targetLabel) . '</td>';
        print '<td>' . kafoAuditEscape(isset($row->action_type) ? $row->action_type : '') . '</td>';
        print '<td>' . kafoAuditEscape(isset($row->object_type) ? $row->object_type : '') . '</td>';
        print '<td class="kafo-audit-cell">' . kafoAuditEscape(isset($row->object_key) ? $row->object_key : '') . '</td>';
        print '<td class="kafo-audit-cell">' . kafoAuditEscape(dol_trunc((string) (isset($row->old_value) ? $row->old_value : ''), 220, 'right', 'UTF-8', 1)) . '</td>';
        print '<td class="kafo-audit-cell">' . kafoAuditEscape(dol_trunc((string) (isset($row->new_value) ? $row->new_value : ''), 220, 'right', 'UTF-8', 1)) . '</td>';
        print '<td class="kafo-audit-cell">' . kafoAuditEscape(dol_trunc((string) (isset($row->description) ? $row->description : ''), 260, 'right', 'UTF-8', 1)) . '</td>';
        print '<td>' . kafoAuditEscape(isset($row->ip_address) ? $row->ip_address : '') . '</td>';
        print '<td class="kafo-audit-cell">' . kafoAuditEscape(isset($row->context_page) ? $row->context_page : '') . '</td>';
        print '</tr>';
    }
}

print '</table>';
print '</div>';

print dol_get_fiche_end();
llxFooter();
$db->close();

