<?php
/*
 * TakePOS API v1 - open-cart diagnostic.
 * GET /takepos/api/v1/cart_debug.php  (Authorization: Bearer <token>)
 *
 * Shows the token's entity/user, and the raw draft POS invoices in the DB so we
 * can see exactly why an open cart is or isn't returned by the login context.
 * Delete this file once resolved.
 */
require_once __DIR__ . '/bootstrap.php';

takeposApiRequireMethod(array('GET'));

$auth   = takeposApiAuth($db, 'read', 'takepos.api_layer');
$entity = (int) $auth['entity'];
$user   = $auth['user'];
$userId = (int) (isset($user->id) ? $user->id : 0);
$isAdmin = !empty($user->admin);

$facture = MAIN_DB_PREFIX . 'facture';

// 1) Broadest possible look: every draft that looks like a POS cart, ALL entities,
//    ANY author. This is the ground truth of what exists.
$rawRows = array();
$sql = 'SELECT rowid, entity, ref, fk_statut, module_source, pos_source, fk_user_author, total_ttc, datec'
    . ' FROM ' . $facture
    . " WHERE fk_statut = 0 AND (module_source = 'takepos' OR ref LIKE '(PROV%')"
    . ' ORDER BY rowid DESC LIMIT 50';
$res = $db->query($sql);
if ($res) {
    while ($o = $db->fetch_object($res)) {
        $rawRows[] = array(
            'rowid' => (int) $o->rowid,
            'entity' => (int) $o->entity,
            'ref' => (string) $o->ref,
            'fk_statut' => (int) $o->fk_statut,
            'module_source' => (string) $o->module_source,
            'pos_source' => (string) $o->pos_source,
            'fk_user_author' => (is_null($o->fk_user_author) ? null : (int) $o->fk_user_author),
            'total_ttc' => (float) $o->total_ttc,
            'datec' => (string) $o->datec,
        );
    }
}

// 2) Count using the EXACT criteria the login context uses, for this token.
function takeposCartDebugCount($db, $facture, $entity, $userId, $isAdmin, $useAuthor)
{
    $where = ' WHERE f.entity = ' . (int) $entity
        . ' AND f.fk_statut = 0'
        . " AND (f.module_source = 'takepos' OR f.ref LIKE '(PROV-POS%')";
    if ($useAuthor && !$isAdmin) {
        $where .= ' AND f.fk_user_author = ' . (int) $userId;
    }
    $r = $db->query('SELECT COUNT(*) AS nb FROM ' . $facture . ' f' . $where);
    if ($r && ($o = $db->fetch_object($r))) {
        return (int) $o->nb;
    }
    return -1;
}

$counts = array(
    'context_exact_for_this_token' => takeposCartDebugCount($db, $facture, $entity, $userId, $isAdmin, true),
    'ignoring_author'              => takeposCartDebugCount($db, $facture, $entity, $userId, $isAdmin, false),
    'this_entity_any_posdraft'     => (function () use ($db, $facture, $entity) {
        $r = $db->query('SELECT COUNT(*) AS nb FROM ' . $facture . " f WHERE f.entity = " . (int) $entity . " AND f.fk_statut = 0 AND (f.module_source='takepos' OR f.ref LIKE '(PROV-POS%')");
        return ($r && ($o = $db->fetch_object($r))) ? (int) $o->nb : -1;
    })(),
);

// 3) What the resolver actually returns (if _context.php is deployed).
$resolverOut = 'not_loaded';
$ctxFile = __DIR__ . '/_context.php';
if (is_readable($ctxFile)) {
    require_once $ctxFile;
    if (function_exists('takeposApiResolveUserOpenCarts')) {
        $resolverOut = takeposApiResolveUserOpenCarts($db, $entity, $user, 50);
    } else {
        $resolverOut = 'function_missing';
    }
}

takeposApiSuccess(array(
    'build' => 'cart-debug-1',
    'token' => array(
        'entity' => $entity,
        'user_id' => $userId,
        'user_login' => (isset($user->login) ? (string) $user->login : null),
        'user_admin' => ($isAdmin ? 1 : 0),
    ),
    'conf_entity' => (isset($conf->entity) ? (int) $conf->entity : null),
    'getEntity_invoice' => (function_exists('getEntity') ? getEntity('invoice') : null),
    'counts' => $counts,
    'raw_pos_drafts_all_entities' => $rawRows,
    'resolver_output' => $resolverOut,
), array('entity' => $entity));
