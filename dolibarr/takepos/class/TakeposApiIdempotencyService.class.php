<?php
require_once __DIR__ . '/TakeposMigration.class.php';

class TakeposApiIdempotencyService
{
    public static function tableName()
    {
        return MAIN_DB_PREFIX . 'takepos_api_idempotency';
    }

    public static function ensureSchema($db)
    {
        $table = self::tableName();
        $ok = TakeposMigration::ensureTable($db, $table, "CREATE TABLE " . $table . " ("
            . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
            . " entity INT NOT NULL DEFAULT 1,"
            . " idempotency_key VARCHAR(190) NOT NULL,"
            . " endpoint VARCHAR(80) NOT NULL,"
            . " invoice_id INT NOT NULL DEFAULT 0,"
            . " amount DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " response_json LONGTEXT NULL,"
            . " http_code INT NOT NULL DEFAULT 200,"
            . " fk_user INT NULL,"
            . " datec DATETIME NOT NULL,"
            . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " UNIQUE KEY uk_takepos_api_idem (entity, endpoint, idempotency_key),"
            . " KEY idx_takepos_api_idem_invoice (entity, endpoint, invoice_id),"
            . " KEY idx_takepos_api_idem_user (entity, fk_user, datec)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (!$ok) {
            return false;
        }

        $columns = array(
            'entity' => "INT NOT NULL DEFAULT 1",
            'idempotency_key' => "VARCHAR(190) NOT NULL",
            'endpoint' => "VARCHAR(80) NOT NULL",
            'invoice_id' => "INT NOT NULL DEFAULT 0",
            'amount' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'response_json' => "LONGTEXT NULL",
            'http_code' => "INT NOT NULL DEFAULT 200",
            'fk_user' => "INT NULL",
            'datec' => "DATETIME NOT NULL",
            'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($columns as $column => $definition) {
            if (!TakeposMigration::ensureColumn($db, $table, $column, $definition)) {
                return false;
            }
        }

        if (!TakeposMigration::ensureIndex($db, $table, 'uk_takepos_api_idem', '(entity, endpoint, idempotency_key)', 'UNIQUE')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $table, 'idx_takepos_api_idem_invoice', '(entity, endpoint, invoice_id)')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $table, 'idx_takepos_api_idem_user', '(entity, fk_user, datec)')) {
            return false;
        }

        return true;
    }

    public static function normalizeKey($key)
    {
        $key = trim((string) $key);
        if ($key === '') {
            return '';
        }

        return substr($key, 0, 190);
    }

    public static function amountValue($amount)
    {
        return (float) price2num($amount, 'MU');
    }

    public static function findRecord($db, $entity, $endpoint, $idempotencyKey)
    {
        self::ensureSchema($db);

        $key = self::normalizeKey($idempotencyKey);
        if ($key === '') {
            return null;
        }

        $sql = "SELECT rowid, entity, idempotency_key, endpoint, invoice_id, amount, response_json, http_code, fk_user, datec"
            . " FROM " . self::tableName()
            . " WHERE entity = " . ((int) $entity)
            . " AND endpoint = '" . $db->escape((string) $endpoint) . "'"
            . " AND idempotency_key = '" . $db->escape($key) . "'"
            . " LIMIT 1";
        $resql = $db->query($sql);

        return ($resql ? $db->fetch_object($resql) : null);
    }

    public static function loadReplayPayload($db, $entity, $endpoint, $idempotencyKey, $invoiceId, $amount)
    {
        $record = self::findRecord($db, $entity, $endpoint, $idempotencyKey);
        if (!$record) {
            return null;
        }

        $requestedInvoiceId = (int) $invoiceId;
        $requestedAmount = self::amountValue($amount);
        $storedAmount = self::amountValue($record->amount);

        if ($requestedInvoiceId > 0 && (int) $record->invoice_id > 0 && (int) $record->invoice_id !== $requestedInvoiceId) {
            throw new TakeposApiException('IDEMPOTENCY_KEY_CONFLICT', 'Idempotency key was already used for another invoice.', 409);
        }
        if (abs($storedAmount - $requestedAmount) > 0.000001) {
            throw new TakeposApiException('IDEMPOTENCY_KEY_CONFLICT', 'Idempotency key was already used for another amount.', 409);
        }

        $payload = json_decode((string) $record->response_json, true);
        if (!is_array($payload) || empty($payload)) {
            return null;
        }

        return array(
            'payload' => $payload,
            'http_code' => !empty($record->http_code) ? (int) $record->http_code : 200,
        );
    }

    public static function storeResponse($db, $user, $entity, $endpoint, $idempotencyKey, $invoiceId, $amount, $responsePayload, $httpCode = 200)
    {
        self::ensureSchema($db);

        $key = self::normalizeKey($idempotencyKey);
        if ($key === '') {
            return false;
        }

        $responseJson = json_encode((array) $responsePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($responseJson === false) {
            throw new Exception('Failed to encode idempotency response payload.');
        }

        $sql = "INSERT INTO " . self::tableName()
            . " (entity, idempotency_key, endpoint, invoice_id, amount, response_json, http_code, fk_user, datec) VALUES ("
            . ((int) $entity) . ", '"
            . $db->escape($key) . "', '"
            . $db->escape((string) $endpoint) . "', "
            . ((int) $invoiceId) . ", "
            . self::amountValue($amount) . ", '"
            . $db->escape($responseJson) . "', "
            . ((int) $httpCode) . ", "
            . (!empty($user->id) ? (int) $user->id : 'NULL') . ", '"
            . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . "')";

        if ($db->query($sql)) {
            return true;
        }

        $existing = self::findRecord($db, $entity, $endpoint, $key);
        if ($existing) {
            return true;
        }

        throw new Exception($db->lasterror());
    }
}
