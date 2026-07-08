<?php
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once __DIR__ . '/TakeposUserAccess.class.php';

class TakeposUserManager
{
    private $db;
    private $actor;

    private function trans($key, $fallback)
    {
        global $langs;

        if (is_object($langs)) {
            $langs->load('takeposcustom@takepos');
            $translated = $langs->trans($key);
            if ($translated !== $key) {
                return $translated;
            }
        }

        return $fallback;
    }

    public function __construct($db, $actor)
    {
        $this->db = $db;
        $this->actor = $actor;
    }

    private function getPreferredEntity()
    {
        $entity = 0;

        if (!empty($this->actor) && isset($this->actor->entity)) {
            $entity = (int) $this->actor->entity;
        }

        if ($entity <= 0 && !empty($this->actor) && !empty($this->actor->id)) {
            $sql = "SELECT entity FROM " . MAIN_DB_PREFIX . "user WHERE rowid = " . ((int) $this->actor->id) . " LIMIT 1";
            $resql = $this->db->query($sql);
            if ($resql && ($obj = $this->db->fetch_object($resql)) && isset($obj->entity)) {
                $entity = (int) $obj->entity;
            }
        }

        return ($entity > 0) ? $entity : 1;
    }

    public function getUsers()
    {
        $rows = array();

        // بعض بيئات Dolibarr / MultiCompany تحفظ المستخدمين بقيم entity مختلفة
        // لذلك لا نفلتر هنا حسب entity حتى لا يختفي المستخدم الجديد من القائمة.
        $sql = "SELECT rowid, entity, login, firstname, lastname, email, admin, statut"
            . " FROM " . MAIN_DB_PREFIX . "user"
            . " WHERE login IS NOT NULL AND login <> ''"
            . " ORDER BY login ASC";
        $resql = $this->db->query($sql);
        if (!$resql) {
            return $rows;
        }
        while ($obj = $this->db->fetch_object($resql)) {
            $rows[] = $obj;
        }
        return $rows;
    }

    public function getUserById($id)
    {
        $u = new User($this->db);
        if ($u->fetch((int) $id) > 0) {
            return $u;
        }
        return null;
    }

    public function getRightDefinitions()
    {
        return TakeposUserAccess::getOperationalRightDefinitions($this->db);
    }

    public function getGrantableRightIds()
    {
        return TakeposUserAccess::actorGrantableRightIds($this->db, $this->actor);
    }

    public function getUserAssignedRightIds($userId)
    {
        $ids = array();
        $pk = TakeposUserAccess::getRightsDefPrimaryKey($this->db);
        $rightsEntity = $this->resolveUserRightsEntity((int) $userId, $this->getPreferredEntity());
        $sql = "SELECT ur.fk_id"
            . " FROM " . MAIN_DB_PREFIX . "user_rights ur"
            . " INNER JOIN " . MAIN_DB_PREFIX . "rights_def rd ON rd." . $pk . " = ur.fk_id"
            . " WHERE ur.fk_user = " . ((int) $userId)
            . " AND ur.entity = " . ((int) $rightsEntity)
            . " AND rd.module IN ('takepos','produit','service','categorie')";
        $resql = $this->db->query($sql);
        if (!$resql) {
            return $ids;
        }
        while ($obj = $this->db->fetch_object($resql)) {
            $ids[] = (int) $obj->fk_id;
        }
        return $ids;
    }

    public function createUser($data, $requestedRightIds)
    {
        $login = trim((string) ($data['login'] ?? ''));
        $lastname = trim((string) ($data['lastname'] ?? ''));
        $firstname = trim((string) ($data['firstname'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($login === '' || $lastname === '' || $password === '') {
            throw new Exception($this->trans('TakeposAdminUsersErrorCreateRequired', 'Login, last name, and password are required.'));
        }
        if ($this->loginExists($login)) {
            throw new Exception($this->trans('TakeposAdminUsersErrorLoginExists', 'Login already exists.'));
        }

        $this->db->begin();
        try {
            $u = new User($this->db);
            $u->entity = $this->getPreferredEntity();
            $u->login = $login;
            $u->lastname = $lastname;
            $u->firstname = $firstname;
            $u->email = $email;
            $u->admin = 0;
            $u->statut = 1;
            $u->pass = $password;
            $u->pass_indatabase = '';
            $u->pass_indatabase_crypted = '';

            $res = $this->callCreateCompatible($u, $password);
            if ($res <= 0 || empty($u->id)) {
                $msg = !empty($u->error) ? $u->error : $this->trans('TakeposAdminUsersErrorCreateFailed', 'Failed to create user.');
                throw new Exception($msg);
            }

            $newUserId = (int) $u->id;
            $targetEntity = $this->forceUserEntity($newUserId, $this->getPreferredEntity());
            $this->syncTakeposRights($newUserId, $requestedRightIds, $targetEntity, true);
            $this->syncDependentAccess($newUserId, $targetEntity, true);

            $this->db->commit();
            return $newUserId;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw new Exception($e->getMessage());
        }
    }

    public function updateUserProfile($userId, $data)
    {
        $target = $this->requireManageableUser($userId);

        $login = trim((string) ($data['login'] ?? ''));
        $lastname = trim((string) ($data['lastname'] ?? ''));
        $firstname = trim((string) ($data['firstname'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));

        if ($login === '' || $lastname === '') {
            throw new Exception($this->trans('TakeposAdminUsersErrorUpdateRequired', 'Login and last name are required.'));
        }
        if ($this->loginExists($login, (int) $target->id)) {
            throw new Exception($this->trans('TakeposAdminUsersErrorLoginExists', 'Login already exists.'));
        }

        $targetEntity = $this->resolveUserRightsEntity((int) $target->id, $this->getPreferredEntity());

        $sql = "UPDATE " . MAIN_DB_PREFIX . "user SET"
            . " login='" . $this->db->escape($login) . "'"
            . ", firstname=" . $this->toSqlString($firstname)
            . ", lastname='" . $this->db->escape($lastname) . "'"
            . ", email=" . $this->toSqlString($email)
            . ", entity = " . ((int) $targetEntity)
            . ", admin = 0"
            . " WHERE rowid = " . ((int) $target->id);
        if (!$this->db->query($sql)) {
            throw new Exception($this->db->lasterror());
        }

        $this->forceUserEntity((int) $target->id, $targetEntity);

        return true;
    }

    public function updatePassword($userId, $newPassword)
    {
        $target = $this->requireManageableUser($userId);
        $newPassword = (string) $newPassword;
        if ($newPassword === '') {
            throw new Exception($this->trans('TakeposAdminUsersErrorPasswordRequired', 'New password is required.'));
        }

        $res = $this->callSetPasswordCompatible($target, $newPassword);
        if ($res <= 0 && !is_string($res)) {
            $msg = !empty($target->error) ? $target->error : $this->trans('TakeposAdminUsersErrorPasswordUpdateFailed', 'Failed to update password.');
            throw new Exception($msg);
        }

        return true;
    }

    public function setStatus($userId, $enable)
    {
        $target = $this->requireManageableUser($userId);
        if ((int) $target->id === (int) $this->actor->id && !$enable) {
            throw new Exception($this->trans('TakeposAdminUsersErrorDisableOwn', 'You cannot disable your own account from this screen.'));
        }

        $sql = "UPDATE " . MAIN_DB_PREFIX . "user SET statut = " . ((int) ($enable ? 1 : 0))
            . " WHERE rowid = " . ((int) $target->id);
        if (!$this->db->query($sql)) {
            throw new Exception($this->db->lasterror());
        }

        return true;
    }

    public function updateRights($userId, $requestedRightIds)
    {
        $target = $this->requireManageableUser($userId);
        $targetEntity = $this->forceUserEntity((int) $target->id, $this->resolveUserRightsEntity((int) $target->id, $this->getPreferredEntity()));
        $this->syncTakeposRights((int) $target->id, $requestedRightIds, $targetEntity, false);
        $this->syncDependentAccess((int) $target->id, $targetEntity, false, $requestedRightIds);
        return true;
    }

    private function requireManageableUser($userId)
    {
        $target = $this->getUserById($userId);
        if (!$target) {
            throw new Exception($this->trans('TakeposAdminUsersErrorNotFound', 'User not found.'));
        }

        $actorEntity = (int) $this->actor->entity;
        $targetEntity = (int) $target->entity;

        if (empty($this->actor->admin)) {
            $sameEntity = ($actorEntity === $targetEntity);
            $legacyPair = (($actorEntity === 0 && $targetEntity === 1) || ($actorEntity === 1 && $targetEntity === 0));
            if (!$sameEntity && !$legacyPair) {
                throw new Exception($this->trans('TakeposAdminUsersErrorOutsideEntity', 'User is outside your entity.'));
            }
        }

        if (!empty($target->admin) && empty($this->actor->admin)) {
            throw new Exception($this->trans('TakeposAdminUsersErrorManageAdmin', 'You cannot manage an administrator.'));
        }
        return $target;
    }

    private function syncTakeposRights($userId, $requestedRightIds, $rightsEntity = null, $forceRunOnCreate = false)
    {
        $grantable = $this->getGrantableRightIds();
        $clean = array();
        foreach ((array) $requestedRightIds as $id) {
            $id = (int) $id;
            if ($id > 0 && in_array($id, $grantable, true)) {
                $clean[] = $id;
            }
        }
        $clean = array_values(array_unique($clean));
        if ($forceRunOnCreate) {
            $clean = array_values(array_unique(array_merge($clean, $this->getDefaultOperationalRightIds())));
        }
        $rightsEntity = ($rightsEntity === null) ? $this->resolveUserRightsEntity((int) $userId, $this->getPreferredEntity()) : (int) $rightsEntity;

        $pk = TakeposUserAccess::getRightsDefPrimaryKey($this->db);
        $managedModules = array('takepos', 'produit', 'categorie');
        $sql = "DELETE ur FROM " . MAIN_DB_PREFIX . "user_rights ur"
            . " INNER JOIN " . MAIN_DB_PREFIX . "rights_def rd ON rd." . $pk . " = ur.fk_id"
            . " WHERE ur.fk_user = " . ((int) $userId)
            . " AND rd.module IN ('takepos','produit','service','categorie')";
        if (!$this->db->query($sql)) {
            throw new Exception($this->db->lasterror());
        }

        foreach ($clean as $rightId) {
            $sql = "INSERT INTO " . MAIN_DB_PREFIX . "user_rights (entity, fk_user, fk_id) VALUES ("
                . $rightsEntity . ", " . ((int) $userId) . ", " . ((int) $rightId) . ")";
            if (!$this->db->query($sql)) {
                throw new Exception($this->db->lasterror());
            }
        }
    }

    private function getMandatoryTakeposRightIds()
    {
        $ids = array();
        foreach ($this->getRightDefinitions() as $def) {
            $module = strtolower((string) ($def['module'] ?? ''));
            $perms = strtolower((string) ($def['perms'] ?? ''));
            if (($module === 'takepos' && $perms === 'run')
                || ($module === 'produit' && $perms === 'lire')
                || ($module === 'categorie' && $perms === 'lire')) {
                $ids[] = (int) $def['id'];
            }
        }
        return array_values(array_unique($ids));
    }

    private function getDefaultOperationalRightIds()
    {
        $ids = $this->getMandatoryTakeposRightIds();
        foreach ($this->getRightDefinitions() as $def) {
            if (!empty($def['recommended'])) {
                $ids[] = (int) $def['id'];
            }
        }
        return array_values(array_unique($ids));
    }

    private function syncDependentAccess($userId, $rightsEntity, $isCreate = false, $requestedRightIds = array())
    {
        $hasRun = false;
        $runIds = $this->getMandatoryTakeposRightIds();
        foreach ((array) $requestedRightIds as $id) {
            if (in_array((int) $id, $runIds, true)) {
                $hasRun = true;
                break;
            }
        }
        if ($isCreate && !empty($runIds)) {
            $hasRun = true;
        }

        if ($hasRun) {
            $this->grantRequiredCoreRights($userId, $rightsEntity);
            $this->grantRequiredSaasPermission($userId, $rightsEntity, 'takepos.use');
        } else {
            $this->removeCoreRights($userId);
            $this->removeSaasPermission($userId, 'takepos.use');
        }
    }

    private function grantRequiredCoreRights($userId, $rightsEntity)
    {
        if (!TakeposUserAccess::tableExists($this->db, 'rights_def')) {
            return;
        }

        $needles = array(
            array('module' => 'produit', 'perms' => 'lire'),
            array('module' => 'categorie', 'perms' => 'lire'),
        );

        $pk = TakeposUserAccess::getRightsDefPrimaryKey($this->db);
        foreach ($needles as $needle) {
            $sql = "SELECT " . $pk . " AS rid FROM " . MAIN_DB_PREFIX . "rights_def"
                . " WHERE module='" . $this->db->escape($needle['module']) . "'"
                . " AND perms='" . $this->db->escape($needle['perms']) . "'"
                . " LIMIT 1";
            $resql = $this->db->query($sql);
            if (!$resql || !($obj = $this->db->fetch_object($resql))) {
                continue;
            }
            $rid = (int) $obj->rid;
            if ($rid <= 0) {
                continue;
            }
            $sql = "INSERT IGNORE INTO " . MAIN_DB_PREFIX . "user_rights (entity, fk_user, fk_id) VALUES ("
                . ((int) $rightsEntity) . ", " . ((int) $userId) . ", " . $rid . ")";
            $this->db->query($sql);
        }
    }

    private function removeCoreRights($userId)
    {
        if (!TakeposUserAccess::tableExists($this->db, 'rights_def')) {
            return;
        }
        $pk = TakeposUserAccess::getRightsDefPrimaryKey($this->db);
        $sql = "DELETE ur FROM " . MAIN_DB_PREFIX . "user_rights ur"
            . " INNER JOIN " . MAIN_DB_PREFIX . "rights_def rd ON rd." . $pk . " = ur.fk_id"
            . " WHERE ur.fk_user = " . ((int) $userId)
            . " AND ((rd.module='produit' AND rd.perms='lire') OR (rd.module='categorie' AND rd.perms='lire'))";
        $this->db->query($sql);
    }

    private function grantRequiredSaasPermission($userId, $entityId, $permissionCode)
    {
        if (!TakeposUserAccess::tableExists($this->db, 'saas_user_permissions')) {
            return;
        }
        $table = MAIN_DB_PREFIX . 'saas_user_permissions';
        $cols = array();
        $resql = $this->db->query("SHOW COLUMNS FROM " . $table);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $cols[] = strtolower((string) $obj->Field);
            }
        }
        if (empty($cols)) {
            return;
        }
        $fields = array();
        $values = array();
        if (in_array('entity_id', $cols, true)) { $fields[] = 'entity_id'; $values[] = (int) $entityId; }
        if (in_array('fk_user', $cols, true)) { $fields[] = 'fk_user'; $values[] = (int) $userId; }
        elseif (in_array('user_id', $cols, true)) { $fields[] = 'user_id'; $values[] = (int) $userId; }
        else { return; }
        if (in_array('permission_code', $cols, true)) { $fields[] = 'permission_code'; $values[] = "'" . $this->db->escape($permissionCode) . "'"; }
        else { return; }
        if (in_array('allowed', $cols, true)) { $fields[] = 'allowed'; $values[] = 1; }
        if (in_array('date_created', $cols, true)) { $fields[] = 'date_created'; $values[] = "'" . $this->db->escape(date('Y-m-d H:i:s')) . "'"; }
        if (in_array('tms', $cols, true)) { $fields[] = 'tms'; $values[] = "'" . $this->db->escape(date('Y-m-d H:i:s')) . "'"; }
        $sql = "INSERT IGNORE INTO " . $table . " (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
        $this->db->query($sql);
    }

    private function removeSaasPermission($userId, $permissionCode)
    {
        if (!TakeposUserAccess::tableExists($this->db, 'saas_user_permissions')) {
            return;
        }
        $table = MAIN_DB_PREFIX . 'saas_user_permissions';
        $cols = array();
        $resql = $this->db->query("SHOW COLUMNS FROM " . $table);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $cols[] = strtolower((string) $obj->Field);
            }
        }
        if (empty($cols)) { return; }
        $whereUser = in_array('fk_user', $cols, true) ? "fk_user = " . ((int) $userId) : (in_array('user_id', $cols, true) ? "user_id = " . ((int) $userId) : '1=0');
        if ($whereUser === '1=0' || !in_array('permission_code', $cols, true)) { return; }
        $sql = "DELETE FROM " . $table . " WHERE " . $whereUser . " AND permission_code='" . $this->db->escape($permissionCode) . "'";
        $this->db->query($sql);
    }

    private function resolveUserRightsEntity($userId, $fallbackEntity)
    {
        $sql = "SELECT entity FROM " . MAIN_DB_PREFIX . "user WHERE rowid = " . ((int) $userId) . " LIMIT 1";
        $resql = $this->db->query($sql);
        if ($resql && ($obj = $this->db->fetch_object($resql))) {
            if (isset($obj->entity) && $obj->entity !== null) {
                $entity = (int) $obj->entity;
                if ($entity > 0) {
                    return $entity;
                }
            }
        }
        return ((int) $fallbackEntity > 0) ? (int) $fallbackEntity : $this->getPreferredEntity();
    }

    private function forceUserEntity($userId, $targetEntity)
    {
        $targetEntity = ((int) $targetEntity > 0) ? (int) $targetEntity : $this->getPreferredEntity();
        $userId = (int) $userId;

        $sql = "UPDATE " . MAIN_DB_PREFIX . "user SET entity = " . $targetEntity
            . " WHERE rowid = " . $userId
            . " AND (entity IS NULL OR entity = 0 OR entity <> " . $targetEntity . ")";
        $this->db->query($sql);

        if (TakeposUserAccess::tableExists($this->db, 'user_rights')) {
            $sql = "UPDATE " . MAIN_DB_PREFIX . "user_rights SET entity = " . $targetEntity
                . " WHERE fk_user = " . $userId . " AND (entity IS NULL OR entity = 0 OR entity <> " . $targetEntity . ")";
            $this->db->query($sql);
        }

        if (TakeposUserAccess::tableExists($this->db, 'saas_user_permissions')) {
            $table = MAIN_DB_PREFIX . 'saas_user_permissions';
            $resql = $this->db->query("SHOW COLUMNS FROM " . $table);
            $cols = array();
            if ($resql) {
                while ($obj = $this->db->fetch_object($resql)) {
                    $cols[] = strtolower((string) $obj->Field);
                }
            }
            $userCol = in_array('fk_user', $cols, true) ? 'fk_user' : (in_array('user_id', $cols, true) ? 'user_id' : '');
            if ($userCol !== '' && in_array('entity_id', $cols, true)) {
                $sql = "UPDATE " . $table . " SET entity_id = " . $targetEntity
                    . " WHERE " . $userCol . " = " . $userId . " AND (entity_id IS NULL OR entity_id = 0 OR entity_id <> " . $targetEntity . ")";
                $this->db->query($sql);
            }
        }

        return $targetEntity;
    }

    private function loginExists($login, $exceptId = 0)
    {
        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "user WHERE login='" . $this->db->escape($login) . "'";
        if ($exceptId > 0) {
            $sql .= " AND rowid <> " . ((int) $exceptId);
        }
        $sql .= " LIMIT 1";
        $resql = $this->db->query($sql);
        return ($resql && $this->db->num_rows($resql) > 0);
    }

    public function findUserByLogin($login)
    {
        $login = trim((string) $login);
        if ($login === '') {
            return null;
        }

        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "user WHERE login='" . $this->db->escape($login) . "' LIMIT 1";
        $resql = $this->db->query($sql);
        if (!$resql || !$this->db->num_rows($resql)) {
            return null;
        }

        $obj = $this->db->fetch_object($resql);
        return $this->getUserById((int) $obj->rowid);
    }

    private function toSqlString($value)
    {
        $value = trim((string) $value);
        return ($value === '') ? 'NULL' : ("'" . $this->db->escape($value) . "'");
    }

    private function callCreateCompatible($userObject, $password)
    {
        $attempts = array(
            function () use ($userObject, $password) { return $userObject->create($this->actor, $password); },
            function () use ($userObject) { return $userObject->create($this->actor); },
            function () use ($userObject, $password) { return $userObject->create($password); },
            function () use ($userObject) { return $userObject->create(); },
        );

        $last = 0;
        foreach ($attempts as $attempt) {
            try {
                $last = $attempt();
                if ($last > 0 && !empty($userObject->id)) {
                    if (!$this->passwordAlreadySet($userObject)) {
                        $this->callSetPasswordCompatible($userObject, $password);
                    }
                    return $last;
                }
            } catch (Throwable $e) {
                $last = 0;
            }
        }
        return $last;
    }

    private function callSetPasswordCompatible($userObject, $password)
    {
        $attempts = array(
            function () use ($userObject, $password) { return $userObject->setPassword($this->actor, $password, 0, 0); },
            function () use ($userObject, $password) { return $userObject->setPassword($this->actor, $password, 0); },
            function () use ($userObject, $password) { return $userObject->setPassword($this->actor, $password); },
            function () use ($userObject, $password) { return $userObject->setPassword($password); },
        );

        $last = 0;
        foreach ($attempts as $attempt) {
            try {
                $last = $attempt();
                if ((is_numeric($last) && $last > 0) || is_string($last)) {
                    return $last;
                }
            } catch (Throwable $e) {
                $last = 0;
            }
        }
        return $last;
    }

    private function passwordAlreadySet($userObject)
    {
        return !empty($userObject->pass_indatabase) || !empty($userObject->pass_indatabase_crypted);
    }
}
