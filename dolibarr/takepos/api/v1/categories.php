<?php
/**
 * TakePOS API v1 — Categories
 *
 * GET /takepos/api/v1/categories.php
 *   List all product categories (optionally filtered).
 *
 * GET /takepos/api/v1/categories.php?id=5
 *   Fetch a single category by ID.
 *
 * Auth: Bearer token (standard API v1 auth, scope: read / takepos.api_layer)
 *
 * Query params:
 *   id            INT     optional  return a single category by its rowid
 *   parent_id     INT     optional  filter to children of this parent (0 = top-level)
 *   q             STRING  optional  full-text search on label / description
 *   active_only   INT     optional  1 = skip categories with status = 0
 *   limit         INT     optional  page size (1-100, default 50)
 *   offset        INT     optional  pagination offset (default 0)
 *   _debug        INT     optional  1 = include raw SQL and category type counts in meta (remove in production)
 */
require_once __DIR__ . '/bootstrap.php';

takeposApiRequireMethod(array('GET'));

$auth   = takeposApiAuth($db, 'read', 'takepos.api_layer');
$entity = (int) $auth['entity'];

// ── Input validation ──────────────────────────────────────────────────────────
$id         = GETPOSTINT('id');
$parentId   = GETPOST('parent_id', 'none');
$q          = TakeposInputValidator::normalizeUtf8Text(GETPOST('q', 'none'), 190, true);
$activeOnly = (GETPOSTINT('active_only') > 0);
$debug      = (GETPOSTINT('_debug') > 0);
$limit      = GETPOSTINT('limit');
$offset     = GETPOSTINT('offset');

if ($limit <= 0)  $limit  = 50;
if ($limit > 100) $limit  = 100;
if ($offset < 0)  $offset = 0;

// ── Debug: what category types actually exist? ────────────────────────────────
$debugInfo = array();
if ($debug) {
    // Count all categories regardless of type/entity
    $sqlAll = 'SELECT type, entity, COUNT(*) as cnt FROM ' . MAIN_DB_PREFIX . 'categorie GROUP BY type, entity ORDER BY cnt DESC';
    $resAll = $db->query($sqlAll);
    $typeCounts = array();
    if ($resAll) {
        while ($r = $db->fetch_object($resAll)) {
            $typeCounts[] = array('type' => (int)$r->type, 'entity' => (int)$r->entity, 'count' => (int)$r->cnt);
        }
    }
    $debugInfo['category_type_counts'] = $typeCounts;
    $debugInfo['entity_filter_value']  = getEntity('categorie');
    $debugInfo['current_entity']       = $entity;

    // Check if ref column exists (added in later Dolibarr versions)
    $sqlRef = 'SHOW COLUMNS FROM ' . MAIN_DB_PREFIX . 'categorie LIKE ' . chr(39) . 'ref' . chr(39);
    $resRef = $db->query($sqlRef);
    $debugInfo['ref_column_exists'] = ($resRef && $db->num_rows($resRef) > 0);
}

// ── SQL ───────────────────────────────────────────────────────────────────────
// Check whether the 'visible' column exists to avoid fatal SQL errors on older Dolibarr
$hasVisibleCol = true;
$sqlChkCol = 'SHOW COLUMNS FROM ' . MAIN_DB_PREFIX . 'categorie LIKE ' . chr(39) . 'visible' . chr(39);
$resChkCol = $db->query($sqlChkCol);
if (!$resChkCol || $db->num_rows($resChkCol) === 0) {
    $hasVisibleCol = false;
}

$sql  = 'SELECT c.rowid, c.label, c.description, c.fk_parent, c.color,';
// 'position' column also varies by Dolibarr version — make it safe
$sql .= ' 0 AS position,';
$sql .= ' (SELECT COUNT(*) FROM ' . MAIN_DB_PREFIX . 'categorie_product cp WHERE cp.fk_categorie = c.rowid) AS product_count,';
$sql .= ($hasVisibleCol ? ' COALESCE(c.visible, 1)' : ' 1') . ' AS status';
$sql .= ' FROM ' . MAIN_DB_PREFIX . 'categorie c';
$sql .= ' WHERE c.entity IN (' . getEntity('categorie') . ')';
// type = 0 is product categories in Dolibarr core.
// Some older installs store them differently — we only filter by type when not in debug mode
// so you can see what types actually exist via ?_debug=1
$sql .= ' AND c.type = 0';

if ($id > 0) {
    $sql .= ' AND c.rowid = ' . (int) $id;
}
if ($activeOnly && $hasVisibleCol) {
    $sql .= ' AND COALESCE(c.visible, 1) = 1';
}
if ($parentId !== '' && $parentId !== null) {
    $pInt = (int) $parentId;
    if ($pInt === 0) {
        $sql .= ' AND (c.fk_parent IS NULL OR c.fk_parent = 0)';
    } else {
        $sql .= ' AND c.fk_parent = ' . $pInt;
    }
}
if ($q !== '') {
    $escaped = $db->escape($q);
    $sql .= ' AND (c.label LIKE ' . chr(39) . '%' . $escaped . '%' . chr(39)
        . ' OR c.description LIKE ' . chr(39) . '%' . $escaped . '%' . chr(39)
        . ' OR c.ref LIKE ' . chr(39) . '%' . $escaped . '%' . chr(39) . ')';
}

$sql .= ' ORDER BY c.label ASC, c.rowid ASC';
$sql .= $db->plimit($limit, $offset);

if ($debug) {
    $debugInfo['sql'] = $sql;
}

// ── Format helper ─────────────────────────────────────────────────────────────
function takeposApiFormatCategory($row)
{
    return array(
        'id'            => (int)    $row->rowid,
        'ref'           => '',
        'label'         => (string) $row->label,
        'description'   => (string) ($row->description ?? ''),
        'parent_id'     => (empty($row->fk_parent) ? null : (int) $row->fk_parent),
        'color'         => (empty($row->color)      ? null : (string) $row->color),
        'position'      => (int)    ($row->position ?? 0),
        'product_count' => (int)    ($row->product_count ?? 0),
        'status'        => (int)    ($row->status ?? 1),
    );
}

// ── Execute ───────────────────────────────────────────────────────────────────
$rows  = array();
$resql = $db->query($sql);
if ($debug) {
    $debugInfo['db_error'] = $resql ? null : $db->lasterror();
}
if ($resql) {
    while ($row = $db->fetch_object($resql)) {
        $rows[] = takeposApiFormatCategory($row);
    }
}

// ── Response ──────────────────────────────────────────────────────────────────
if ($id > 0) {
    if (empty($rows)) {
        takeposApiError('NOT_FOUND', 'Category not found.', 404);
    }
    takeposApiAuditAccess($db, $auth, 'categories.show', array('category_id' => $id));
    $meta = array('entity' => $entity);
    if ($debug) $meta['_debug'] = $debugInfo;
    takeposApiSuccess($rows[0], $meta);
}

takeposApiAuditAccess($db, $auth, 'categories.index', array(
    'q'           => $q,
    'parent_id'   => ($parentId !== '' && $parentId !== null ? (int) $parentId : null),
    'active_only' => ($activeOnly ? 1 : 0),
    'count'       => count($rows),
));
$meta = array(
    'entity' => $entity,
    'count'  => count($rows),
    'limit'  => $limit,
    'offset' => $offset,
);
if ($debug) $meta['_debug'] = $debugInfo;
takeposApiSuccess($rows, $meta);