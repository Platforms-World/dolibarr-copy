<?php
require_once __DIR__ . '/TakeposMigration.class.php';
require_once __DIR__ . '/TakeposAudit.class.php';

/**
 * Outgoing webhook registry and dispatcher.
 */
class TakeposWebhookService
{
    private static function trans($key, $fallback)
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

    public static function tableWebhook()
    {
        return MAIN_DB_PREFIX . 'takepos_webhook';
    }

    public static function tableWebhookLog()
    {
        return MAIN_DB_PREFIX . 'takepos_webhook_log';
    }

    private static function nowSql($db)
    {
        return "'" . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . "'";
    }

    private static function safeAudit($db, $user, $eventCode, $severity, $data = array(), $description = '', $objectType = '', $objectId = 0)
    {
        try {
            TakeposAudit::logEvent($db, $user, $eventCode, $severity, $data, $description, $objectType, (int) $objectId);
        } catch (Throwable $e) {
            if (function_exists('dol_syslog')) {
                dol_syslog('[TakePOS][Webhook] Audit failure: ' . $e->getMessage(), LOG_WARNING);
            }
        }
    }

    public static function ensureSchema($db)
    {
        $webhook = self::tableWebhook();
        $log = self::tableWebhookLog();

        $ok = true;
        $ok = $ok && TakeposMigration::ensureTable($db, $webhook, "CREATE TABLE " . $webhook . " ("
            . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
            . " entity INT NOT NULL DEFAULT 1,"
            . " webhook_code VARCHAR(48) NOT NULL,"
            . " label VARCHAR(128) NOT NULL,"
            . " target_url VARCHAR(255) NOT NULL,"
            . " secret_key VARCHAR(255) NULL,"
            . " events_csv TEXT NOT NULL,"
            . " headers_json LONGTEXT NULL,"
            . " verify_ssl TINYINT(1) NOT NULL DEFAULT 1,"
            . " timeout_sec INT NOT NULL DEFAULT 8,"
            . " active TINYINT(1) NOT NULL DEFAULT 1,"
            . " fk_created_by INT NULL,"
            . " date_creation DATETIME NOT NULL,"
            . " date_last_sent DATETIME NULL,"
            . " last_status VARCHAR(24) NULL,"
            . " last_error VARCHAR(255) NULL,"
            . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " UNIQUE KEY uk_takepos_webhook_code (entity, webhook_code),"
            . " KEY idx_takepos_webhook_active (entity, active),"
            . " KEY idx_takepos_webhook_last (entity, date_last_sent)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $ok = $ok && TakeposMigration::ensureTable($db, $log, "CREATE TABLE " . $log . " ("
            . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
            . " entity INT NOT NULL DEFAULT 1,"
            . " fk_webhook INT NOT NULL,"
            . " event_code VARCHAR(64) NOT NULL,"
            . " payload_json LONGTEXT NULL,"
            . " response_code INT NULL,"
            . " response_body LONGTEXT NULL,"
            . " success TINYINT(1) NOT NULL DEFAULT 0,"
            . " date_creation DATETIME NOT NULL,"
            . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " KEY idx_takepos_webhook_log_hook (entity, fk_webhook, date_creation),"
            . " KEY idx_takepos_webhook_log_event (entity, event_code, date_creation),"
            . " KEY idx_takepos_webhook_log_success (entity, success, date_creation)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (!$ok) {
            return false;
        }

        $webhookCols = array(
            'entity' => "INT NOT NULL DEFAULT 1",
            'webhook_code' => "VARCHAR(48) NOT NULL",
            'label' => "VARCHAR(128) NOT NULL",
            'target_url' => "VARCHAR(255) NOT NULL",
            'secret_key' => "VARCHAR(255) NULL",
            'events_csv' => "TEXT NOT NULL",
            'headers_json' => "LONGTEXT NULL",
            'verify_ssl' => "TINYINT(1) NOT NULL DEFAULT 1",
            'timeout_sec' => "INT NOT NULL DEFAULT 8",
            'active' => "TINYINT(1) NOT NULL DEFAULT 1",
            'fk_created_by' => "INT NULL",
            'date_creation' => "DATETIME NOT NULL",
            'date_last_sent' => "DATETIME NULL",
            'last_status' => "VARCHAR(24) NULL",
            'last_error' => "VARCHAR(255) NULL",
            'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($webhookCols as $column => $definition) {
            if (!TakeposMigration::ensureColumn($db, $webhook, $column, $definition)) {
                return false;
            }
        }

        $logCols = array(
            'entity' => "INT NOT NULL DEFAULT 1",
            'fk_webhook' => "INT NOT NULL",
            'event_code' => "VARCHAR(64) NOT NULL",
            'payload_json' => "LONGTEXT NULL",
            'response_code' => "INT NULL",
            'response_body' => "LONGTEXT NULL",
            'success' => "TINYINT(1) NOT NULL DEFAULT 0",
            'date_creation' => "DATETIME NOT NULL",
            'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($logCols as $column => $definition) {
            if (!TakeposMigration::ensureColumn($db, $log, $column, $definition)) {
                return false;
            }
        }

        TakeposMigration::ensureIndex($db, $webhook, 'uk_takepos_webhook_code', '(entity, webhook_code)', 'UNIQUE');
        TakeposMigration::ensureIndex($db, $webhook, 'idx_takepos_webhook_active', '(entity, active)');
        TakeposMigration::ensureIndex($db, $webhook, 'idx_takepos_webhook_last', '(entity, date_last_sent)');
        TakeposMigration::ensureIndex($db, $log, 'idx_takepos_webhook_log_hook', '(entity, fk_webhook, date_creation)');
        TakeposMigration::ensureIndex($db, $log, 'idx_takepos_webhook_log_event', '(entity, event_code, date_creation)');
        TakeposMigration::ensureIndex($db, $log, 'idx_takepos_webhook_log_success', '(entity, success, date_creation)');

        return true;
    }

    public static function normalizeWebhookCode($code)
    {
        $clean = strtoupper(trim((string) $code));
        if ($clean === '') {
            return '';
        }
        if (!preg_match('/^[A-Z0-9_-]{2,48}$/', $clean)) {
            return '';
        }

        return $clean;
    }

    private static function normalizeEvents($events)
    {
        if (is_string($events)) {
            $events = explode(',', $events);
        }

        $out = array();
        foreach ((array) $events as $one) {
            $event = strtolower(trim((string) $one));
            if ($event === '') {
                continue;
            }
            if (!preg_match('/^[a-z0-9_\*\.:-]{1,64}$/', $event)) {
                continue;
            }
            $out[] = $event;
        }

        $out = array_values(array_unique($out));
        if (empty($out)) {
            $out[] = '*';
        }

        return $out;
    }

    private static function normalizeHeadersJson($headers)
    {
        if (is_array($headers)) {
            $json = json_encode($headers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return ($json === false ? '{}' : $json);
        }

        $raw = trim((string) $headers);
        if ($raw === '') {
            return '{}';
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return '{}';
        }

        $json = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return ($json === false ? '{}' : $json);
    }

    public static function listWebhooks($db, $entity, $activeOnly = false)
    {
        self::ensureSchema($db);

        $sql = "SELECT rowid, entity, webhook_code, label, target_url, events_csv, headers_json, verify_ssl, timeout_sec, active, fk_created_by, date_creation, date_last_sent, last_status, last_error"
            . " FROM " . self::tableWebhook()
            . " WHERE entity = " . ((int) $entity);
        if ($activeOnly) {
            $sql .= " AND active = 1";
        }
        $sql .= " ORDER BY rowid DESC";

        $rows = array();
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $rows[] = $obj;
            }
        }

        return $rows;
    }

    public static function getWebhookById($db, $entity, $webhookId)
    {
        self::ensureSchema($db);

        $sql = "SELECT rowid, entity, webhook_code, label, target_url, secret_key, events_csv, headers_json, verify_ssl, timeout_sec, active, fk_created_by, date_creation, date_last_sent, last_status, last_error"
            . " FROM " . self::tableWebhook()
            . " WHERE entity = " . ((int) $entity)
            . " AND rowid = " . ((int) $webhookId)
            . " LIMIT 1";
        $resql = $db->query($sql);
        return ($resql ? $db->fetch_object($resql) : null);
    }

    public static function saveWebhook($db, $user, $entity, $webhookId, $webhookCode, $label, $targetUrl, $events, $secretKey = '', $headers = '{}', $verifySsl = 1, $timeoutSec = 8, $active = 1)
    {
        self::ensureSchema($db);

        $entity = (int) $entity;
        if ($entity <= 0) {
            $entity = !empty($user->entity) ? (int) $user->entity : 1;
        }

        $webhookCode = self::normalizeWebhookCode($webhookCode);
        if ($webhookCode === '') {
            throw new Exception(self::trans('TakeposWebhookErrorCodeInvalid', 'Webhook code is invalid.'));
        }

        $label = trim((string) $label);
        if ($label === '') {
            throw new Exception(self::trans('TakeposWebhookErrorLabelRequired', 'Webhook label is required.'));
        }

        $targetUrl = trim((string) $targetUrl);
        if ($targetUrl === '' || !preg_match('/^https?:\/\//i', $targetUrl)) {
            throw new Exception(self::trans('TakeposWebhookErrorUrlInvalid', 'Webhook URL must start with http:// or https://'));
        }

        $eventsList = self::normalizeEvents($events);
        $eventsCsv = implode(',', $eventsList);
        $headersJson = self::normalizeHeadersJson($headers);
        $verifySsl = ((int) $verifySsl > 0 ? 1 : 0);
        $timeoutSec = (int) $timeoutSec;
        if ($timeoutSec <= 0) {
            $timeoutSec = 8;
        }
        if ($timeoutSec > 60) {
            $timeoutSec = 60;
        }
        $active = ((int) $active > 0 ? 1 : 0);

        $secretKey = trim((string) $secretKey);
        $webhookId = (int) $webhookId;

        if ($webhookId > 0) {
            $current = self::getWebhookById($db, $entity, $webhookId);
            if (!$current) {
                throw new Exception(self::trans('TakeposWebhookErrorNotFound', 'Webhook was not found.'));
            }

            if ($secretKey === '') {
                $secretSql = "secret_key = secret_key";
            } else {
                $secretSql = "secret_key = '" . $db->escape($secretKey) . "'";
            }

            $sql = "UPDATE " . self::tableWebhook() . " SET"
                . " webhook_code = '" . $db->escape($webhookCode) . "'"
                . ", label = '" . $db->escape($label) . "'"
                . ", target_url = '" . $db->escape($targetUrl) . "'"
                . ", " . $secretSql
                . ", events_csv = '" . $db->escape($eventsCsv) . "'"
                . ", headers_json = '" . $db->escape($headersJson) . "'"
                . ", verify_ssl = " . $verifySsl
                . ", timeout_sec = " . $timeoutSec
                . ", active = " . $active
                . " WHERE rowid = " . $webhookId
                . " AND entity = " . $entity;
            if (!$db->query($sql)) {
                throw new Exception($db->lasterror());
            }
        } else {
            $sql = "INSERT INTO " . self::tableWebhook() . " (entity, webhook_code, label, target_url, secret_key, events_csv, headers_json, verify_ssl, timeout_sec, active, fk_created_by, date_creation) VALUES ("
                . $entity . ", '" . $db->escape($webhookCode) . "', '" . $db->escape($label) . "', '" . $db->escape($targetUrl) . "', "
                . ($secretKey !== '' ? "'" . $db->escape($secretKey) . "'" : 'NULL') . ", '" . $db->escape($eventsCsv) . "', '" . $db->escape($headersJson) . "', "
                . $verifySsl . ", " . $timeoutSec . ", " . $active . ", " . (!empty($user->id) ? (int) $user->id : 'NULL') . ", " . self::nowSql($db) . ")";
            if (!$db->query($sql)) {
                throw new Exception($db->lasterror());
            }
            $webhookId = (int) $db->last_insert_id(self::tableWebhook());

            self::safeAudit(
                $db,
                $user,
                'webhook_created',
                TakeposAudit::SEVERITY_WARNING,
                array('webhook_id' => $webhookId, 'webhook_code' => $webhookCode, 'events' => $eventsList),
                self::trans('TakeposWebhookCreatedAudit', 'Webhook created'),
                'webhook',
                $webhookId
            );

            return $webhookId;
        }

        self::safeAudit(
            $db,
            $user,
            'webhook_updated',
            TakeposAudit::SEVERITY_WARNING,
            array('webhook_id' => $webhookId, 'webhook_code' => $webhookCode, 'events' => $eventsList),
            self::trans('TakeposWebhookUpdatedAudit', 'Webhook updated'),
            'webhook',
            $webhookId
        );

        return $webhookId;
    }

    private static function decodeHeaders($headersJson)
    {
        $decoded = json_decode((string) $headersJson, true);
        return is_array($decoded) ? $decoded : array();
    }

    private static function eventMatches($eventsCsv, $eventCode)
    {
        $eventCode = strtolower(trim((string) $eventCode));
        if ($eventCode === '') {
            return false;
        }

        $tokens = self::normalizeEvents((string) $eventsCsv);
        if (in_array('*', $tokens, true)) {
            return true;
        }

        return in_array($eventCode, $tokens, true);
    }

    private static function sendHttpRequest($url, $payloadJson, $headers, $timeoutSec, $verifySsl)
    {
        $responseBody = '';
        $responseCode = 0;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $headerRows = array();
            foreach ((array) $headers as $k => $v) {
                $headerRows[] = $k . ': ' . $v;
            }

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerRows);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, (int) $timeoutSec);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, ($verifySsl ? true : false));
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, ($verifySsl ? 2 : 0));

            $responseBody = curl_exec($ch);
            if ($responseBody === false) {
                $responseBody = curl_error($ch);
            }
            $responseCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return array('code' => $responseCode, 'body' => (string) $responseBody);
        }

        $headerLines = "";
        foreach ((array) $headers as $k => $v) {
            $headerLines .= $k . ': ' . $v . "\r\n";
        }

        $context = stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => $headerLines,
                'content' => $payloadJson,
                'timeout' => (int) $timeoutSec,
                'ignore_errors' => true,
            ),
            'ssl' => array(
                'verify_peer' => ($verifySsl ? true : false),
                'verify_peer_name' => ($verifySsl ? true : false),
            ),
        ));

        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody === false) {
            $responseBody = 'HTTP request failed';
        }

        global $http_response_header;
        if (is_array($http_response_header) && !empty($http_response_header[0])) {
            if (preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
                $responseCode = (int) $m[1];
            }
        }

        return array('code' => $responseCode, 'body' => (string) $responseBody);
    }

    private static function logDelivery($db, $entity, $webhookId, $eventCode, $payloadJson, $responseCode, $responseBody, $success)
    {
        $sql = "INSERT INTO " . self::tableWebhookLog()
            . " (entity, fk_webhook, event_code, payload_json, response_code, response_body, success, date_creation) VALUES ("
            . ((int) $entity) . ", " . ((int) $webhookId) . ", '" . $db->escape((string) $eventCode) . "', "
            . "'" . $db->escape((string) $payloadJson) . "', "
            . ((int) $responseCode > 0 ? (int) $responseCode : 'NULL') . ", "
            . "'" . $db->escape(substr((string) $responseBody, 0, 64000)) . "', "
            . ((int) $success > 0 ? 1 : 0) . ", " . self::nowSql($db) . ")";
        $db->query($sql);
    }

    public static function emitEvent($db, $entity, $eventCode, $payload = array(), $user = null)
    {
        self::ensureSchema($db);

        $entity = (int) $entity;
        if ($entity <= 0) {
            $entity = !empty($GLOBALS['conf']->entity) ? (int) $GLOBALS['conf']->entity : 1;
        }

        $eventCode = strtolower(trim((string) $eventCode));
        if ($eventCode === '') {
            return array('sent' => 0, 'failed' => 0);
        }

        $payload = is_array($payload) ? $payload : array('value' => $payload);
        $envelope = array(
            'event' => $eventCode,
            'entity' => $entity,
            'timestamp' => dol_print_date(dol_now(), 'dayhourlog'),
            'data' => $payload,
        );
        $payloadJson = json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payloadJson === false) {
            $payloadJson = '{}';
        }

        $hooks = self::listWebhooks($db, $entity, true);
        $sent = 0;
        $failed = 0;

        foreach ($hooks as $hook) {
            if (!self::eventMatches((string) $hook->events_csv, $eventCode)) {
                continue;
            }

            $headers = array(
                'Content-Type' => 'application/json',
                'X-Takepos-Event' => $eventCode,
                'X-Takepos-Entity' => (string) $entity,
            );

            $extraHeaders = self::decodeHeaders((string) $hook->headers_json);
            foreach ($extraHeaders as $k => $v) {
                $k = trim((string) $k);
                if ($k === '') {
                    continue;
                }
                $headers[$k] = (string) $v;
            }

            if (!empty($hook->secret_key)) {
                $headers['X-Takepos-Signature'] = hash_hmac('sha256', $payloadJson, (string) $hook->secret_key);
            }

            try {
                $result = self::sendHttpRequest(
                    (string) $hook->target_url,
                    $payloadJson,
                    $headers,
                    (int) $hook->timeout_sec > 0 ? (int) $hook->timeout_sec : 8,
                    !empty($hook->verify_ssl)
                );

                $ok = ((int) $result['code'] >= 200 && (int) $result['code'] < 300);
                self::logDelivery($db, $entity, (int) $hook->rowid, $eventCode, $payloadJson, (int) $result['code'], (string) $result['body'], ($ok ? 1 : 0));

                $sql = "UPDATE " . self::tableWebhook() . " SET date_last_sent = " . self::nowSql($db)
                    . ", last_status = '" . ($ok ? 'sent' : 'failed') . "'"
                    . ", last_error = " . ($ok ? 'NULL' : "'" . $db->escape(substr((string) $result['body'], 0, 255)) . "'")
                    . " WHERE rowid = " . ((int) $hook->rowid)
                    . " AND entity = " . $entity;
                $db->query($sql);

                if ($ok) {
                    $sent++;
                    self::safeAudit($db, $user, 'webhook_event_sent', TakeposAudit::SEVERITY_INFO, array(
                        'event' => $eventCode,
                        'webhook_id' => (int) $hook->rowid,
                        'response_code' => (int) $result['code'],
                    ), self::trans('TakeposWebhookEventSent', 'Webhook event sent'), 'webhook', (int) $hook->rowid);
                } else {
                    $failed++;
                    self::safeAudit($db, $user, 'webhook_event_failed', TakeposAudit::SEVERITY_WARNING, array(
                        'event' => $eventCode,
                        'webhook_id' => (int) $hook->rowid,
                        'response_code' => (int) $result['code'],
                        'response' => substr((string) $result['body'], 0, 500),
                    ), self::trans('TakeposWebhookEventFailed', 'Webhook event failed'), 'webhook', (int) $hook->rowid);
                }
            } catch (Throwable $e) {
                $failed++;
                self::logDelivery($db, $entity, (int) $hook->rowid, $eventCode, $payloadJson, 0, $e->getMessage(), 0);

                $sql = "UPDATE " . self::tableWebhook() . " SET date_last_sent = " . self::nowSql($db)
                    . ", last_status = 'failed'"
                    . ", last_error = '" . $db->escape(substr((string) $e->getMessage(), 0, 255)) . "'"
                    . " WHERE rowid = " . ((int) $hook->rowid)
                    . " AND entity = " . $entity;
                $db->query($sql);

                self::safeAudit($db, $user, 'webhook_event_failed', TakeposAudit::SEVERITY_WARNING, array(
                    'event' => $eventCode,
                    'webhook_id' => (int) $hook->rowid,
                    'error' => $e->getMessage(),
                ), self::trans('TakeposWebhookEventFailed', 'Webhook event failed'), 'webhook', (int) $hook->rowid);
            }
        }

        return array('sent' => $sent, 'failed' => $failed);
    }
}
