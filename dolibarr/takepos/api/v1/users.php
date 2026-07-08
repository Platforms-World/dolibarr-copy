<?php
/*
 * TakePOS API v1 - Users (POS operator management)
 * GET   : list users, show one (?id=), or right definitions (?rights=1)
 * POST  : create user (action=create)
 * PATCH : update profile (action=profile), password (action=password),
 *         status (action=status), or rights (action=rights)
 * Component: TakeposUserManager
 *
 * NOTE: the acting token must belong to a user allowed to administer POS users.
 */
require_once __DIR__ . '/bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposUserManager.class.php';

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET');
if (!in_array($method, array('GET', 'POST', 'PATCH'), true)) {
    takeposApiError('METHOD_NOT_ALLOWED', 'Method not allowed.', 405, null, array(), array('Allow: GET, POST, PATCH'));
}

$auth = takeposApiAuth($db, ($method === 'GET' ? 'read' : 'write'), 'takepos.api_layer');
$entity = (int) $auth['entity'];
$actor = $auth['user'];

if (empty($actor->id)) {
    takeposApiError('FORBIDDEN', 'User management requires a token bound to a real operator account.', 403);
}

$manager = new TakeposUserManager($db, $actor);

function takeposApiUserPayload($row)
{
    return array(
        'id' => (int) $row->rowid,
        'login' => (string) $row->login,
        'firstname' => (!empty($row->firstname) ? (string) $row->firstname : null),
        'lastname' => (!empty($row->lastname) ? (string) $row->lastname : null),
        'email' => (!empty($row->email) ? (string) $row->email : null),
        'admin' => (int) $row->admin,
        'active' => (int) $row->statut,
    );
}

if ($method === 'POST' || $method === 'PATCH') {
    $body = takeposApiRequestBody();
    $action = isset($body['action']) ? strtolower(trim((string) $body['action'])) : ($method === 'POST' ? 'create' : '');

    try {
        if ($action === 'create') {
            $data = array(
                'login' => isset($body['login']) ? (string) $body['login'] : '',
                'firstname' => isset($body['firstname']) ? (string) $body['firstname'] : '',
                'lastname' => isset($body['lastname']) ? (string) $body['lastname'] : '',
                'email' => isset($body['email']) ? (string) $body['email'] : '',
                'password' => isset($body['password']) ? (string) $body['password'] : '',
            );
            $rightIds = isset($body['right_ids']) && is_array($body['right_ids']) ? array_map('intval', $body['right_ids']) : array();
            $newId = $manager->createUser($data, $rightIds);
            takeposApiAuditAccess($db, $auth, 'users.create', array('user_id' => (int) $newId));
            takeposApiSuccess(array('id' => (int) $newId), array('entity' => $entity), 201);
        }

        $userId = (int) takeposApiRequestRequireField($body, 'user_id');

        if ($action === 'profile') {
            $data = array(
                'login' => isset($body['login']) ? (string) $body['login'] : '',
                'firstname' => isset($body['firstname']) ? (string) $body['firstname'] : '',
                'lastname' => isset($body['lastname']) ? (string) $body['lastname'] : '',
                'email' => isset($body['email']) ? (string) $body['email'] : '',
            );
            $manager->updateUserProfile($userId, $data);
            takeposApiAuditAccess($db, $auth, 'users.profile', array('user_id' => $userId));
            takeposApiSuccess(array('id' => $userId, 'updated' => true), array('entity' => $entity));
        }
        if ($action === 'password') {
            $newPassword = (string) takeposApiRequestRequireField($body, 'password');
            $manager->updatePassword($userId, $newPassword);
            takeposApiAuditAccess($db, $auth, 'users.password', array('user_id' => $userId));
            takeposApiSuccess(array('id' => $userId, 'password_updated' => true), array('entity' => $entity));
        }
        if ($action === 'status') {
            $enable = !empty($body['enable']);
            $manager->setStatus($userId, $enable);
            takeposApiAuditAccess($db, $auth, 'users.status', array('user_id' => $userId, 'enable' => ($enable ? 1 : 0)));
            takeposApiSuccess(array('id' => $userId, 'active' => ($enable ? 1 : 0)), array('entity' => $entity));
        }
        if ($action === 'rights') {
            $rightIds = isset($body['right_ids']) && is_array($body['right_ids']) ? array_map('intval', $body['right_ids']) : array();
            $manager->updateRights($userId, $rightIds);
            takeposApiAuditAccess($db, $auth, 'users.rights', array('user_id' => $userId, 'count' => count($rightIds)));
            takeposApiSuccess(array('id' => $userId, 'right_ids' => $manager->getUserAssignedRightIds($userId)), array('entity' => $entity));
        }

        takeposApiError('INVALID_PARAMETER', 'Unknown action.', 422);
    } catch (Throwable $e) {
        takeposApiError('USER_OPERATION_FAILED', $e->getMessage(), 422);
    }
}

// GET
if (GETPOSTINT('rights') === 1) {
    takeposApiAuditAccess($db, $auth, 'users.rights_def', array());
    takeposApiSuccess(array(
        'definitions' => $manager->getRightDefinitions(),
        'grantable_right_ids' => $manager->getGrantableRightIds(),
    ), array('entity' => $entity));
}

$id = GETPOSTINT('id');
if ($id > 0) {
    $u = $manager->getUserById($id);
    if (!$u) {
        takeposApiError('NOT_FOUND', 'User not found.', 404);
    }
    takeposApiAuditAccess($db, $auth, 'users.show', array('user_id' => $id));
    takeposApiSuccess(array(
        'id' => (int) $u->id,
        'login' => (string) $u->login,
        'firstname' => (string) $u->firstname,
        'lastname' => (string) $u->lastname,
        'email' => (string) $u->email,
        'admin' => (int) $u->admin,
        'active' => (int) $u->statut,
        'right_ids' => $manager->getUserAssignedRightIds($id),
    ), array('entity' => $entity));
}

$rows = array();
foreach ($manager->getUsers() as $row) {
    $rows[] = takeposApiUserPayload($row);
}

takeposApiAuditAccess($db, $auth, 'users.index', array('count' => count($rows)));
takeposApiSuccess($rows, array('entity' => $entity, 'count' => count($rows)));
