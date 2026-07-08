<?php
/**
 * Trigger: Auto-provision POS permissions + redirect POS users to TakePOS after login.
 */

require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';

class InterfaceTakeposUserProvision extends DolibarrTriggers
{
    public $name        = 'TakeposUserProvision';
    public $description = 'Auto-grant TakePOS permissions and redirect POS users to TakePOS after login';
    public $version     = '1.3.0';
    public $picto       = 'takepos@takepos';

    const CASHIER_PERMISSIONS = array(
        'takepos.use',
        'takepos.shift.open',
        'takepos.shift.close',
        'takepos.shift.force_close',
        'takepos.shift.review',
        'takepos.cash.paidin',
        'takepos.cash.paidout',
        'takepos.cash.safedrop',
        'takepos.cash.count',
        'takepos.cash.reconcile',
        'takepos.expense.read',
        'takepos.expense.create',
        'takepos.refund.view',
        'takepos.analytics.view',
        'takepos.customer.view',
        'takepos.action.reports_view',
        'takepos.offline.use',
        'takepos.sync.manage',
        'takepos.sync.retry',
        'takepos.api.read',
        'takepos.shift.force_close',
    );

    public function __construct($db)
    {
        parent::__construct($db);
    }

    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        if (in_array($action, array('USER_CREATE', 'USER_ENABLEDISABLE'), true)) {
            if (!$this->kafoActive()) return 0;
            if (!is_object($object) || empty($object->id)) return 0;
            if (!empty($object->admin)) return 0;
            try {
                $entity = (int) ($object->entity > 0 ? $object->entity : $conf->entity);
                $this->provisionUser((int) $object->id, $entity);
                $this->grantTakeposRunRight((int) $object->id, $entity);
            } catch (Throwable $e) {
                dol_syslog('[TakePOS][UserProvision] Failed: ' . $e->getMessage(), LOG_WARNING);
            }

            if ($action === 'USER_CREATE') {
                try {
                    $this->pushToLaravel($object, $user);
                } catch (Throwable $e) {
                    dol_syslog('[TakePOS][UserProvision] Webhook failed: ' . $e->getMessage(), LOG_WARNING);
                }
            }

            return 1;
        }

        if ($action === 'USER_LOGIN') {
            if (!$this->kafoActive()) return 0;
            if (!is_object($object) || empty($object->id)) return 0;
            if (!empty($object->admin)) return 0;

            $entity = !empty($object->entity) ? (int) $object->entity : 1;

            if ($this->userIsPosOnly($object->id, $entity)) {
                $_SESSION['kafo_pos_redirect'] = DOL_URL_ROOT . '/takepos/index.php';
            }
            return 1;
        }

        return 0;
    }

    // منح takepos.run فقط — الصلاحيات الأخرى تأتي من الـ role
    private function grantTakeposRunRight($userId, $entity)
    {
        // جيب ID صلاحية takepos.run ديناميكياً حسب كل نسخة
        $sql = "SELECT id FROM " . MAIN_DB_PREFIX . "rights_def WHERE module = 'takepos' AND perms = 'run' LIMIT 1";
        $res = $this->db->query($sql);
        if (!$res || !($obj = $this->db->fetch_object($res))) return;

        $rightId = (int) $obj->id;
        $urTable = MAIN_DB_PREFIX . 'user_rights';

        $check = "SELECT COUNT(*) AS cnt FROM " . $urTable
            . " WHERE fk_user = " . $userId
            . " AND fk_id = " . $rightId
            . " AND entity = " . $entity;
        $res2 = $this->db->query($check);
        if ($res2 && ($obj2 = $this->db->fetch_object($res2)) && (int)$obj2->cnt > 0) return;

        $this->db->query("INSERT INTO " . $urTable . " (entity, fk_user, fk_id) VALUES (" . $entity . ", " . $userId . ", " . $rightId . ")");
        dol_syslog('[TakePOS][UserProvision] takepos.run granted to user ' . $userId, LOG_INFO);
    }

    private function pushToLaravel($object, $creator)
    {
        $laravelUrl = 'http://127.0.0.1:8000';
        $sql = "SELECT value FROM " . MAIN_DB_PREFIX . "const WHERE name = 'KAFO_LARAVEL_URL'";
        $res = $this->db->query($sql);
        if ($res && ($row = $this->db->fetch_object($res)) && !empty($row->value)) {
            $laravelUrl = rtrim($row->value, '/');
        }

        $webhookSecret = '2083806';
        $sql2 = "SELECT value FROM " . MAIN_DB_PREFIX . "const WHERE name = 'KAFO_WEBHOOK_SECRET'";
        $res2 = $this->db->query($sql2);
        if ($res2 && ($row2 = $this->db->fetch_object($res2)) && !empty($row2->value)) {
            $webhookSecret = $row2->value;
        }

        $payload = json_encode([
            'new_user_id'       => (int) $object->id,
            'creator_id'        => (int) $creator->id,
            'login'             => $object->login ?? '',
            'firstname'         => $object->firstname ?? '',
            'lastname'          => $object->lastname ?? '',
            'email'             => !empty($object->email)
                ? $object->email
                : ($object->login ?? 'user' . $object->id) . '@dolibarr.local',
            'admin'             => (int) ($object->admin ?? 0),
            'office_phone'      => $object->office_phone ?? '',
            'address'           => $object->address ?? '',
            'town'              => $object->town ?? '',
            'dolibarr_api_key'  => $object->api_key ?? '',
            'plain_password'    => $object->pass_indatabase ?? '',
            'creator_login'     => $creator->login ?? '',
            'creator_firstname' => $creator->firstname ?? '',
            'creator_lastname'  => $creator->lastname ?? '',
            'creator_email'     => $creator->email ?? '',
            'creator_phone'     => $creator->office_phone ?? '',
            'creator_address'   => $creator->address ?? '',
            'creator_town'      => $creator->town ?? '',
        ]);

        $ch = curl_init($laravelUrl . '/api/webhook/user');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Webhook-Secret: ' . $webhookSecret,
            ],
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    private function kafoActive()
    {
        global $conf;
        if (function_exists('isModEnabled') && isModEnabled('kafoerpcontrol')) return true;
        return !empty($conf->global->MAIN_MODULE_KAFOERPCONTROL);
    }

    private function userIsPosOnly($userId, $entity)
    {
        if (!$this->tableExists('saas_user_permissions')) return false;

        $table = MAIN_DB_PREFIX . 'saas_user_permissions';
        $sql = "SELECT COUNT(*) AS cnt FROM " . $table
            . " WHERE entity_id IN (0, " . (int)$entity . ")"
            . " AND fk_user = " . (int)$userId
            . " AND permission_code = 'takepos.use'"
            . " AND allowed = 1";
        $res = $this->db->query($sql);
        if ($res && ($obj = $this->db->fetch_object($res))) {
            return (int)$obj->cnt > 0;
        }
        return false;
    }

    private function tableExists($suffix)
    {
        $table = MAIN_DB_PREFIX . $suffix;
        $resql = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape($table) . "'");
        return ($resql && $this->db->num_rows($resql) > 0);
    }

    private function provisionUser($userId, $entity)
    {
        if (!$this->tableExists('saas_user_permissions')) return;

        $table   = MAIN_DB_PREFIX . 'saas_user_permissions';
        $cols    = array();
        $resCols = $this->db->query("SHOW COLUMNS FROM " . $table);
        if ($resCols) {
            while ($obj = $this->db->fetch_object($resCols)) { $cols[] = strtolower((string)$obj->Field); }
        }

        $userCol = in_array('fk_user', $cols, true) ? 'fk_user' : (in_array('user_id', $cols, true) ? 'user_id' : '');
        if ($userCol === '' || !in_array('permission_code', $cols, true)) return;

        $hasEntityCol = in_array('entity_id', $cols, true);
        $hasAllowed   = in_array('allowed', $cols, true);
        $hasDateCol   = in_array('date_created', $cols, true);
        $hasTmsCol    = in_array('tms', $cols, true);
        $now          = date('Y-m-d H:i:s');

        foreach (self::CASHIER_PERMISSIONS as $permCode) {
            $checkSql = "SELECT COUNT(*) AS cnt FROM " . $table
                . " WHERE " . $userCol . " = " . $userId
                . " AND permission_code = '" . $this->db->escape($permCode) . "'";
            if ($hasEntityCol) $checkSql .= " AND entity_id = " . $entity;
            $resCheck = $this->db->query($checkSql);
            if ($resCheck && ($row = $this->db->fetch_object($resCheck)) && (int)$row->cnt > 0) continue;

            $fields = array(); $values = array();
            if ($hasEntityCol) { $fields[] = 'entity_id'; $values[] = $entity; }
            $fields[] = $userCol;           $values[] = $userId;
            $fields[] = 'permission_code';  $values[] = "'" . $this->db->escape($permCode) . "'";
            if ($hasAllowed)  { $fields[] = 'allowed';       $values[] = 1; }
            if ($hasDateCol)  { $fields[] = 'date_created';  $values[] = "'" . $this->db->escape($now) . "'"; }
            if ($hasTmsCol)   { $fields[] = 'tms';           $values[] = "'" . $this->db->escape($now) . "'"; }

            $this->db->query("INSERT INTO " . $table . " (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")");
        }
        dol_syslog('[TakePOS][UserProvision] Provisioned user ' . $userId, LOG_INFO);
    }
}