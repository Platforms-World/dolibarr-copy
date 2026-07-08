<?php
require_once __DIR__ . '/TakeposMigration.class.php';
require_once __DIR__ . '/TakeposAudit.class.php';
require_once __DIR__ . '/TakeposInputValidator.class.php';
require_once __DIR__ . '/TakeposTerminalService.class.php';

/**
 * Server-side sync queue service for offline replay.
 */
class TakeposSyncService
{
    const STATUS_PENDING = 'pending';
    const STATUS_SYNCING = 'syncing';
    const STATUS_SYNCED = 'synced';
    const STATUS_FAILED = 'failed';
    const STATUS_CONFLICT = 'conflict';

    const ACTION_SALE_SUBMIT = 'sale_submit';
    const ACTION_PAYMENT_META = 'payment_meta';
    const ACTION_CART_SNAPSHOT = 'cart_snapshot';

    public static function tableQueue()
    {
        return MAIN_DB_PREFIX . 'takepos_sync_queue';
    }

    public static function tableLog()
    {
        return MAIN_DB_PREFIX . 'takepos_sync_log';
    }

    private static function nowSql($db)
    {
        return "'" . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . "'";
    }

    private static function safeAudit($db, $user, $event, $severity, $data = array(), $description = '', $objectId = 0)
    {
        try {
            TakeposAudit::logEvent($db, $user, $event, $severity, $data, $description, 'sync_queue', (int) $objectId);
        } catch (Throwable $e) {
            if (function_exists('dol_syslog')) {
                dol_syslog('[TakePOS][Sync] Audit failure: ' . $e->getMessage(), LOG_WARNING);
            }
        }
    }

    /**
     * Webhook emission should never break POS flows.
     */
    private static function safeWebhookEmit($db, $entity, $eventCode, $payload = array(), $user = null)
    {
        try {
            $webhookClass = __DIR__ . '/TakeposWebhookService.class.php';
            if (!class_exists('TakeposWebhookService') && is_file($webhookClass)) {
                require_once $webhookClass;
            }
            if (class_exists('TakeposWebhookService') && method_exists('TakeposWebhookService', 'emitEvent')) {
                TakeposWebhookService::emitEvent($db, (int) $entity, (string) $eventCode, (array) $payload, $user);
            }
        } catch (Throwable $e) {
            if (function_exists('dol_syslog')) {
                dol_syslog('[TakePOS][Sync] Webhook emit failure: ' . $e->getMessage(), LOG_WARNING);
            }
        }
    }

    public static function ensureSchema($db)
    {
        $queue = self::tableQueue();
        $log = self::tableLog();

        $ok = true;
        $ok = $ok && TakeposMigration::ensureTable($db, $queue, "CREATE TABLE " . $queue . " ("
            . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
            . " entity INT NOT NULL DEFAULT 1,"
            . " action_type VARCHAR(64) NOT NULL,"
            . " payload_json LONGTEXT NULL,"
            . " local_ref VARCHAR(128) NULL,"
            . " idempotency_key VARCHAR(128) NOT NULL,"
            . " status VARCHAR(16) NOT NULL DEFAULT 'pending',"
            . " retry_count INT NOT NULL DEFAULT 0,"
            . " last_error VARCHAR(255) NULL,"
            . " fk_user INT NULL,"
            . " fk_store INT NULL,"
            . " fk_terminal INT NULL,"
            . " conflict_note TEXT NULL,"
            . " date_creation DATETIME NOT NULL,"
            . " date_last_attempt DATETIME NULL,"
            . " date_synced DATETIME NULL,"
            . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " UNIQUE KEY uk_takepos_sync_idem (entity, idempotency_key),"
            . " KEY idx_takepos_sync_status (entity, status),"
            . " KEY idx_takepos_sync_action (entity, action_type),"
            . " KEY idx_takepos_sync_user (entity, fk_user),"
            . " KEY idx_takepos_sync_created (entity, date_creation)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $ok = $ok && TakeposMigration::ensureTable($db, $log, "CREATE TABLE " . $log . " ("
            . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
            . " entity INT NOT NULL DEFAULT 1,"
            . " fk_queue INT NOT NULL,"
            . " event_code VARCHAR(64) NOT NULL,"
            . " message VARCHAR(255) NULL,"
            . " context_json LONGTEXT NULL,"
            . " fk_user INT NULL,"
            . " datec DATETIME NOT NULL,"
            . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " KEY idx_takepos_sync_log_queue (entity, fk_queue),"
            . " KEY idx_takepos_sync_log_event (entity, event_code),"
            . " KEY idx_takepos_sync_log_date (entity, datec)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (!$ok) {
            return false;
        }

        $queueCols = array(
            'entity' => "INT NOT NULL DEFAULT 1",
            'action_type' => "VARCHAR(64) NOT NULL",
            'payload_json' => "LONGTEXT NULL",
            'local_ref' => "VARCHAR(128) NULL",
            'idempotency_key' => "VARCHAR(128) NOT NULL",
            'status' => "VARCHAR(16) NOT NULL DEFAULT 'pending'",
            'retry_count' => "INT NOT NULL DEFAULT 0",
            'last_error' => "VARCHAR(255) NULL",
            'fk_user' => "INT NULL",
            'fk_store' => "INT NULL",
            'fk_terminal' => "INT NULL",
            'conflict_note' => "TEXT NULL",
            'date_creation' => "DATETIME NOT NULL",
            'date_last_attempt' => "DATETIME NULL",
            'date_synced' => "DATETIME NULL",
            'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($queueCols as $col => $def) {
            if (!TakeposMigration::ensureColumn($db, $queue, $col, $def)) {
                return false;
            }
        }

        $logCols = array(
            'entity' => "INT NOT NULL DEFAULT 1",
            'fk_queue' => "INT NOT NULL",
            'event_code' => "VARCHAR(64) NOT NULL",
            'message' => "VARCHAR(255) NULL",
            'context_json' => "LONGTEXT NULL",
            'fk_user' => "INT NULL",
            'datec' => "DATETIME NOT NULL",
            'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($logCols as $col => $def) {
            if (!TakeposMigration::ensureColumn($db, $log, $col, $def)) {
                return false;
            }
        }

        TakeposMigration::ensureIndex($db, $queue, 'uk_takepos_sync_idem', '(entity, idempotency_key)', 'UNIQUE');
        TakeposMigration::ensureIndex($db, $queue, 'idx_takepos_sync_status', '(entity, status)');
        TakeposMigration::ensureIndex($db, $queue, 'idx_takepos_sync_action', '(entity, action_type)');
        TakeposMigration::ensureIndex($db, $queue, 'idx_takepos_sync_user', '(entity, fk_user)');
        TakeposMigration::ensureIndex($db, $queue, 'idx_takepos_sync_created', '(entity, date_creation)');
        TakeposMigration::ensureIndex($db, $log, 'idx_takepos_sync_log_queue', '(entity, fk_queue)');
        TakeposMigration::ensureIndex($db, $log, 'idx_takepos_sync_log_event', '(entity, event_code)');
        TakeposMigration::ensureIndex($db, $log, 'idx_takepos_sync_log_date', '(entity, datec)');

        return true;
    }

    private static function queueLog($db, $entity, $queueId, $eventCode, $message = '', $context = array(), $fkUser = 0)
    {
        $json = json_encode(is_array($context) ? $context : array('value' => $context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = null;
        }

        $sql = "INSERT INTO " . self::tableLog() . " (entity, fk_queue, event_code, message, context_json, fk_user, datec) VALUES ("
            . ((int) $entity) . ", " . ((int) $queueId) . ", '" . $db->escape((string) $eventCode) . "', "
            . ($message !== '' ? "'" . $db->escape($message) . "'" : 'NULL') . ", "
            . ($json !== null ? "'" . $db->escape($json) . "'" : 'NULL') . ", "
            . ((int) $fkUser > 0 ? (int) $fkUser : 'NULL') . ", " . self::nowSql($db) . ")";
        $db->query($sql);
    }

    private static function payloadFromArray($payload)
    {
        if (!is_array($payload)) {
            $payload = array('value' => $payload);
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '{}';
        }
        return $json;
    }

    private static function resolveTerminalStoreContext($db, $entity)
    {
        $terminal = isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : 0;
        $storeId = 0;

        if ($terminal > 0) {
            $terminalObj = TakeposTerminalService::getTerminalByCode($db, (int) $entity, (string) $terminal);
            if ($terminalObj && !empty($terminalObj->fk_store)) {
                $storeId = (int) $terminalObj->fk_store;
            }
        }

        return array('terminal_id' => $terminal, 'store_id' => $storeId);
    }

    public static function makeIdempotencyKey($actionType, $localRef, $payload = array())
    {
        $raw = strtolower(trim((string) $actionType)) . '|' . trim((string) $localRef) . '|' . self::payloadFromArray((array) $payload);
        return hash('sha256', $raw);
    }

    public static function enqueue($db, $user, $actionType, $payload = array(), $localRef = '', $idempotencyKey = '')
    {
        self::ensureSchema($db);
        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $actionType = strtolower(trim((string) $actionType));
        if ($actionType === '') {
            throw new Exception('Queue action type is required.');
        }

        if (!in_array($actionType, array(self::ACTION_SALE_SUBMIT, self::ACTION_PAYMENT_META, self::ACTION_CART_SNAPSHOT), true)) {
            throw new Exception('Unsupported sync action type.');
        }

        $payload = is_array($payload) ? $payload : array();
        $localRef = trim((string) $localRef);
        if ($localRef === '') {
            $localRef = 'LOCAL-' . dol_print_date(dol_now(), '%Y%m%d-%H%M%S') . '-' . mt_rand(1000, 9999);
        }

        if ($idempotencyKey === '') {
            $idempotencyKey = self::makeIdempotencyKey($actionType, $localRef, $payload);
        }

        $json = self::payloadFromArray($payload);

        $sql = "SELECT rowid, status FROM " . self::tableQueue()
            . " WHERE entity = " . $entity
            . " AND idempotency_key = '" . $db->escape($idempotencyKey) . "'"
            . " LIMIT 1";
        $res = $db->query($sql);
        if ($res && ($obj = $db->fetch_object($res))) {
            return array('queue_id' => (int) $obj->rowid, 'status' => (string) $obj->status, 'duplicate' => true);
        }

        $ctx = self::resolveTerminalStoreContext($db, $entity);
        $terminalId = (int) $ctx['terminal_id'];
        $storeId = (int) $ctx['store_id'];

        $sql = "INSERT INTO " . self::tableQueue() . " (entity, action_type, payload_json, local_ref, idempotency_key, status, retry_count, fk_user, fk_store, fk_terminal, date_creation) VALUES ("
            . $entity . ", '" . $db->escape($actionType) . "', '" . $db->escape($json) . "', '" . $db->escape($localRef) . "', '" . $db->escape($idempotencyKey) . "', '" . self::STATUS_PENDING . "', 0, "
            . ((int) $user->id > 0 ? (int) $user->id : 'NULL') . ", "
            . ($storeId > 0 ? $storeId : 'NULL') . ", "
            . ($terminalId > 0 ? $terminalId : 'NULL') . ", " . self::nowSql($db) . ")";

        if (!$db->query($sql)) {
            throw new Exception($db->lasterror());
        }

        $queueId = (int) $db->last_insert_id(self::tableQueue());
        self::queueLog($db, $entity, $queueId, 'sync_queued', 'Queued for sync', array('action_type' => $actionType, 'local_ref' => $localRef, 'store_id' => $storeId), (int) $user->id);
        self::safeAudit($db, $user, 'sync_queued', TakeposAudit::SEVERITY_WARNING, array('queue_id' => $queueId, 'action_type' => $actionType, 'local_ref' => $localRef, 'store_id' => $storeId), 'Sync queued', $queueId);

        return array('queue_id' => $queueId, 'status' => self::STATUS_PENDING, 'duplicate' => false);
    }

    public static function getById($db, $entity, $queueId)
    {
        self::ensureSchema($db);
        $sql = "SELECT rowid, entity, action_type, payload_json, local_ref, idempotency_key, status, retry_count, last_error, fk_user, fk_store, fk_terminal, conflict_note, date_creation, date_last_attempt, date_synced"
            . " FROM " . self::tableQueue()
            . " WHERE entity = " . ((int) $entity)
            . " AND rowid = " . ((int) $queueId)
            . " LIMIT 1";
        $resql = $db->query($sql);
        return ($resql ? $db->fetch_object($resql) : null);
    }

    public static function statusForLocalRef($db, $entity, $localRef)
    {
        self::ensureSchema($db);
        $localRef = trim((string) $localRef);
        if ($localRef === '') {
            return null;
        }

        $sql = "SELECT rowid, action_type, local_ref, status, retry_count, last_error, date_creation, date_last_attempt, date_synced"
            . " FROM " . self::tableQueue()
            . " WHERE entity = " . ((int) $entity)
            . " AND local_ref = '" . $db->escape($localRef) . "'"
            . " ORDER BY rowid DESC LIMIT 1";
        $resql = $db->query($sql);
        if (!$resql) {
            return null;
        }

        $obj = $db->fetch_object($resql);
        return $obj ? $obj : null;
    }

    public static function listQueue($db, $entity, $filters = array(), $limit = 200)
    {
        self::ensureSchema($db);
        $limit = max(1, min(1000, (int) $limit));

        $sql = "SELECT rowid, action_type, local_ref, idempotency_key, status, retry_count, last_error, fk_user, fk_store, fk_terminal, conflict_note, date_creation, date_last_attempt, date_synced"
            . " FROM " . self::tableQueue()
            . " WHERE entity = " . ((int) $entity);

        if (!empty($filters['status'])) {
            $sql .= " AND status = '" . $db->escape((string) $filters['status']) . "'";
        }
        if (!empty($filters['action_type'])) {
            $sql .= " AND action_type = '" . $db->escape((string) $filters['action_type']) . "'";
        }

        $sql .= " ORDER BY rowid DESC LIMIT " . $limit;

        $rows = array();
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $rows[] = $obj;
            }
        }

        return $rows;
    }

    private static function updateStatus($db, $entity, $queueId, $status, $lastError = null, $retryCount = null, $conflictNote = null, $setSynced = false)
    {
        $sql = "UPDATE " . self::tableQueue() . " SET status='" . $db->escape((string) $status) . "'";
        $sql .= ", date_last_attempt=" . self::nowSql($db);

        if ($lastError !== null) {
            $sql .= ", last_error=" . ($lastError !== '' ? "'" . $db->escape((string) $lastError) . "'" : 'NULL');
        }
        if ($retryCount !== null) {
            $sql .= ", retry_count=" . ((int) $retryCount);
        }
        if ($conflictNote !== null) {
            $sql .= ", conflict_note=" . ($conflictNote !== '' ? "'" . $db->escape((string) $conflictNote) . "'" : 'NULL');
        }
        if ($setSynced) {
            $sql .= ", date_synced=" . self::nowSql($db);
        }

        $sql .= " WHERE entity = " . ((int) $entity) . " AND rowid = " . ((int) $queueId);

        if (!$db->query($sql)) {
            throw new Exception($db->lasterror());
        }
    }

    private static function decodePayload($payloadJson)
    {
        $decoded = json_decode((string) $payloadJson, true);
        return is_array($decoded) ? $decoded : array();
    }

    private static function processActionPayload($db, $entity, $queueRow, &$errorReason)
    {
        $payload = self::decodePayload($queueRow->payload_json);
        $actionType = (string) $queueRow->action_type;

        if ($actionType === self::ACTION_CART_SNAPSHOT) {
            return true;
        }

        if ($actionType === self::ACTION_SALE_SUBMIT) {
            $invoiceId = isset($payload['invoice_id']) ? (int) $payload['invoice_id'] : 0;
            if ($invoiceId <= 0) {
                $errorReason = 'Missing invoice_id';
                return false;
            }
            $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "facture WHERE entity = " . ((int) $entity) . " AND rowid = " . $invoiceId . " LIMIT 1";
            $res = $db->query($sql);
            if (!$res || !$db->fetch_object($res)) {
                $errorReason = 'Referenced invoice not found';
                return false;
            }
            return true;
        }

        if ($actionType === self::ACTION_PAYMENT_META) {
            $invoiceId = isset($payload['invoice_id']) ? (int) $payload['invoice_id'] : 0;
            if ($invoiceId <= 0) {
                $errorReason = 'Missing invoice_id for payment metadata';
                return false;
            }
            $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "facture WHERE entity = " . ((int) $entity) . " AND rowid = " . $invoiceId . " LIMIT 1";
            $res = $db->query($sql);
            if (!$res || !$db->fetch_object($res)) {
                $errorReason = 'Invoice for payment metadata not found';
                return false;
            }
            return true;
        }

        $errorReason = 'Unsupported action type';
        return false;
    }

    public static function processQueueEntry($db, $user, $queueId, $forceFromFailed = false)
    {
        self::ensureSchema($db);
        $entity = !empty($user->entity) ? (int) $user->entity : 1;

        $row = self::getById($db, $entity, (int) $queueId);
        if (!$row) {
            throw new Exception('Queue entry not found.');
        }

        $status = (string) $row->status;
        if (!$forceFromFailed && !in_array($status, array(self::STATUS_PENDING, self::STATUS_SYNCING), true)) {
            throw new Exception('Queue entry is not in processable state.');
        }
        if ($forceFromFailed && !in_array($status, array(self::STATUS_FAILED, self::STATUS_CONFLICT, self::STATUS_PENDING), true)) {
            throw new Exception('Queue entry cannot be retried from this state.');
        }

        self::updateStatus($db, $entity, (int) $row->rowid, self::STATUS_SYNCING);
        self::queueLog($db, $entity, (int) $row->rowid, 'sync_started', 'Sync started', array('force' => $forceFromFailed ? 1 : 0), (int) $user->id);
        self::safeAudit($db, $user, 'sync_started', TakeposAudit::SEVERITY_INFO, array('queue_id' => (int) $row->rowid, 'action_type' => (string) $row->action_type), 'Sync started', (int) $row->rowid);

        try {
            $duplicateSql = "SELECT rowid FROM " . self::tableQueue()
                . " WHERE entity = " . $entity
                . " AND idempotency_key = '" . $db->escape((string) $row->idempotency_key) . "'"
                . " AND status = '" . self::STATUS_SYNCED . "'"
                . " AND rowid <> " . ((int) $row->rowid)
                . " LIMIT 1";
            $dupRes = $db->query($duplicateSql);
            if ($dupRes && $db->fetch_object($dupRes)) {
                self::updateStatus($db, $entity, (int) $row->rowid, self::STATUS_CONFLICT, 'Idempotency key already synced', null, 'Duplicate idempotency key conflict');
                self::queueLog($db, $entity, (int) $row->rowid, 'sync_conflict_detected', 'Idempotency conflict detected', array('idempotency_key' => (string) $row->idempotency_key), (int) $user->id);
                self::safeAudit($db, $user, 'sync_conflict_detected', TakeposAudit::SEVERITY_WARNING, array('queue_id' => (int) $row->rowid, 'idempotency_key' => (string) $row->idempotency_key), 'Sync conflict detected', (int) $row->rowid);
                self::safeWebhookEmit($db, $entity, 'sync_failure', array(
                    'queue_id' => (int) $row->rowid,
                    'status' => self::STATUS_CONFLICT,
                    'error' => 'Idempotency key already synced',
                    'action_type' => (string) $row->action_type,
                    'local_ref' => (string) $row->local_ref,
                ), $user);
                return array('queue_id' => (int) $row->rowid, 'status' => self::STATUS_CONFLICT, 'message' => 'Idempotency conflict detected.');
            }

            $errorReason = '';
            $ok = self::processActionPayload($db, $entity, $row, $errorReason);
            if (!$ok) {
                $retryCount = ((int) $row->retry_count) + 1;
                self::updateStatus($db, $entity, (int) $row->rowid, self::STATUS_FAILED, $errorReason, $retryCount);
                self::queueLog($db, $entity, (int) $row->rowid, 'sync_failed', 'Sync failed', array('error' => $errorReason), (int) $user->id);
                self::safeAudit($db, $user, 'sync_failed', TakeposAudit::SEVERITY_WARNING, array('queue_id' => (int) $row->rowid, 'error' => $errorReason), 'Sync failed', (int) $row->rowid);
                self::safeWebhookEmit($db, $entity, 'sync_failure', array(
                    'queue_id' => (int) $row->rowid,
                    'status' => self::STATUS_FAILED,
                    'error' => (string) $errorReason,
                    'action_type' => (string) $row->action_type,
                    'local_ref' => (string) $row->local_ref,
                ), $user);
                return array('queue_id' => (int) $row->rowid, 'status' => self::STATUS_FAILED, 'message' => $errorReason);
            }

            self::updateStatus($db, $entity, (int) $row->rowid, self::STATUS_SYNCED, '', null, null, true);
            self::queueLog($db, $entity, (int) $row->rowid, 'sync_success', 'Sync success', array(), (int) $user->id);
            self::safeAudit($db, $user, 'sync_success', TakeposAudit::SEVERITY_INFO, array('queue_id' => (int) $row->rowid), 'Sync success', (int) $row->rowid);
            return array('queue_id' => (int) $row->rowid, 'status' => self::STATUS_SYNCED, 'message' => 'Synced successfully.');
        } catch (Throwable $e) {
            $retryCount = ((int) $row->retry_count) + 1;
            self::updateStatus($db, $entity, (int) $row->rowid, self::STATUS_FAILED, $e->getMessage(), $retryCount);
            self::queueLog($db, $entity, (int) $row->rowid, 'sync_failed', 'Sync runtime failure', array('error' => $e->getMessage()), (int) $user->id);
            self::safeAudit($db, $user, 'sync_failed', TakeposAudit::SEVERITY_WARNING, array('queue_id' => (int) $row->rowid, 'error' => $e->getMessage()), 'Sync runtime failure', (int) $row->rowid);
            self::safeWebhookEmit($db, $entity, 'sync_failure', array(
                'queue_id' => (int) $row->rowid,
                'status' => self::STATUS_FAILED,
                'error' => (string) $e->getMessage(),
                'action_type' => (string) $row->action_type,
                'local_ref' => (string) $row->local_ref,
            ), $user);
            throw $e;
        }
    }

    public static function processPending($db, $user, $limit = 20)
    {
        self::ensureSchema($db);
        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $limit = max(1, min(200, (int) $limit));

        $rows = array();
        $sql = "SELECT rowid FROM " . self::tableQueue()
            . " WHERE entity = " . $entity
            . " AND status = '" . self::STATUS_PENDING . "'"
            . " ORDER BY rowid ASC LIMIT " . $limit;
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $rows[] = (int) $obj->rowid;
            }
        }

        $out = array();
        foreach ($rows as $qid) {
            try {
                $out[] = self::processQueueEntry($db, $user, $qid, false);
            } catch (Throwable $e) {
                $out[] = array('queue_id' => (int) $qid, 'status' => self::STATUS_FAILED, 'message' => $e->getMessage());
            }
        }

        return $out;
    }

    public static function retry($db, $user, $queueId)
    {
        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $row = self::getById($db, $entity, (int) $queueId);
        if (!$row) {
            throw new Exception('Queue entry not found.');
        }

        self::queueLog($db, $entity, (int) $row->rowid, 'sync_retry', 'Retry requested', array(), (int) $user->id);
        self::safeAudit($db, $user, 'sync_retry', TakeposAudit::SEVERITY_WARNING, array('queue_id' => (int) $row->rowid), 'Sync retry', (int) $row->rowid);

        return self::processQueueEntry($db, $user, (int) $queueId, true);
    }

    public static function resolveConflict($db, $user, $queueId, $resolutionNote = '')
    {
        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $row = self::getById($db, $entity, (int) $queueId);
        if (!$row) {
            throw new Exception('Queue entry not found.');
        }
        if ((string) $row->status !== self::STATUS_CONFLICT) {
            throw new Exception('Queue entry is not in conflict state.');
        }

        self::updateStatus($db, $entity, (int) $row->rowid, self::STATUS_FAILED, (string) $row->last_error, (int) $row->retry_count, $resolutionNote);
        self::queueLog($db, $entity, (int) $row->rowid, 'sync_conflict_resolved', 'Conflict resolved to failed for manual retry', array('resolution_note' => $resolutionNote), (int) $user->id);
        self::safeAudit($db, $user, 'sync_conflict_resolved', TakeposAudit::SEVERITY_WARNING, array('queue_id' => (int) $row->rowid, 'resolution_note' => $resolutionNote), 'Sync conflict resolved', (int) $row->rowid);

        return array('queue_id' => (int) $row->rowid, 'status' => self::STATUS_FAILED);
    }

    public static function summary($db, $entity)
    {
        self::ensureSchema($db);
        $sql = "SELECT status, COUNT(*) AS nb FROM " . self::tableQueue()
            . " WHERE entity = " . ((int) $entity)
            . " GROUP BY status";
        $resql = $db->query($sql);

        $summary = array(
            self::STATUS_PENDING => 0,
            self::STATUS_SYNCING => 0,
            self::STATUS_SYNCED => 0,
            self::STATUS_FAILED => 0,
            self::STATUS_CONFLICT => 0,
        );

        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $st = (string) $obj->status;
                if (array_key_exists($st, $summary)) {
                    $summary[$st] = (int) $obj->nb;
                }
            }
        }

        return $summary;
    }
}
