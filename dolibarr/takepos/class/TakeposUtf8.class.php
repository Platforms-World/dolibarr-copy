<?php
/**
 * UTF-8 helpers for TakePOS text input/search and DB connection handling.
 *
 * This class is intentionally defensive:
 * - Never throws fatal errors if charset APIs are unavailable.
 * - Keeps normalization deterministic for duplicate checks and search terms.
 */
class TakeposUtf8
{
    private static $connectionBootstrapped = false;

    private static function syslogMessage($message, $level = LOG_WARNING)
    {
        if (function_exists('dol_syslog')) {
            dol_syslog('[TakePOS][UTF8] ' . (string) $message, $level);
        }
    }

    /**
     * Best-effort connection charset bootstrap.
     *
     * @param DoliDB $db Database handle
     * @return void
     */
    public static function bootstrapConnection($db)
    {
        if (self::$connectionBootstrapped || !is_object($db)) {
            return;
        }
        self::$connectionBootstrapped = true;

        try {
            if (method_exists($db, 'setConnectionCharset')) {
                $db->setConnectionCharset('utf8mb4');
            }
        } catch (Throwable $e) {
            self::syslogMessage('setConnectionCharset(utf8mb4) failed: ' . $e->getMessage());
        }

        // Fallback SQL path for MySQL/MariaDB variants.
        $queries = array(
            "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            "SET CHARACTER SET utf8mb4",
            "SET collation_connection = 'utf8mb4_unicode_ci'",
        );

        $ok = false;
        foreach ($queries as $sql) {
            $res = @$db->query($sql);
            if ($res) {
                $ok = true;
            }
        }

        if (!$ok) {
            // Last-resort fallback for very old engines still using utf8 alias.
            @$db->query("SET NAMES utf8");
        }
    }

    /**
     * Normalize Unicode text input safely.
     *
     * @param mixed $value Raw value
     * @param int $maxLength Max chars (0 = unlimited)
     * @param bool $collapseWhitespace Collapse whitespace runs to a single space
     * @return string
     */
    public static function normalizeText($value, $maxLength = 0, $collapseWhitespace = true)
    {
        $raw = ($value === null) ? '' : (string) $value;

        // Remove UTF-8 BOM if present in value.
        if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
            $raw = substr($raw, 3);
        }

        // Replace NBSP and zero-width markers that often break duplicate checks.
        $raw = str_replace("\xC2\xA0", ' ', $raw);
        $raw = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', $raw);
        if ($raw === null) {
            $raw = '';
        }

        if ($collapseWhitespace) {
            $collapsed = preg_replace('/\s+/u', ' ', $raw);
            if ($collapsed !== null) {
                $raw = $collapsed;
            }
        }

        // Unicode-aware trim.
        $trimmed = preg_replace('/^\s+|\s+$/u', '', $raw);
        if ($trimmed !== null) {
            $raw = $trimmed;
        } else {
            $raw = trim($raw);
        }

        // NFC normalization keeps combining characters consistent for compares.
        if (class_exists('Normalizer')) {
            try {
                $n = Normalizer::normalize($raw, Normalizer::FORM_C);
                if (is_string($n)) {
                    $raw = $n;
                }
            } catch (Throwable $e) {
                // Keep original value if normalizer extension fails.
            }
        }

        $maxLength = (int) $maxLength;
        if ($maxLength > 0) {
            if (function_exists('mb_substr')) {
                $raw = mb_substr($raw, 0, $maxLength, 'UTF-8');
            } else {
                $raw = substr($raw, 0, $maxLength);
            }
        }

        return $raw;
    }

    /**
     * Normalized text key for duplicate checks.
     *
     * @param mixed $value Raw value
     * @param int $maxLength Max chars
     * @return string
     */
    public static function normalizeCompareKey($value, $maxLength = 255)
    {
        $txt = self::normalizeText($value, $maxLength, true);
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($txt, 'UTF-8');
        }

        return strtolower($txt);
    }

    /**
     * Search term normalizer.
     *
     * @param mixed $value Raw term
     * @param int $maxLength Max chars
     * @return string
     */
    public static function normalizeSearchTerm($value, $maxLength = 190)
    {
        return self::normalizeText($value, $maxLength, true);
    }

    /**
     * Table charset/collation audit helper.
     *
     * @param DoliDB $db Database
     * @param string[] $tables Full table names
     * @return array
     */
    public static function auditTableCharsets($db, $tables)
    {
        $result = array();
        if (!is_array($tables)) {
            return $result;
        }

        foreach ($tables as $table) {
            $table = trim((string) $table);
            if ($table === '') {
                continue;
            }

            $sql = "SELECT t.table_name, t.table_collation, c.character_set_name"
                . " FROM information_schema.tables t"
                . " LEFT JOIN information_schema.collation_character_set_applicability c ON c.collation_name = t.table_collation"
                . " WHERE t.table_schema = DATABASE() AND t.table_name = '" . $db->escape($table) . "'"
                . " LIMIT 1";
            $resql = $db->query($sql);
            if ($resql && ($obj = $db->fetch_object($resql))) {
                $result[$table] = array(
                    'exists' => true,
                    'collation' => (string) $obj->table_collation,
                    'charset' => (string) $obj->character_set_name,
                );
            } else {
                $result[$table] = array(
                    'exists' => false,
                    'collation' => '',
                    'charset' => '',
                );
            }
        }

        return $result;
    }

    /**
     * Convert tables to utf8mb4 using compatibility-safe inspection.
     *
     * @param DoliDB $db Database
     * @param string[] $tables Full table names
     * @param string $collation Target collation
     * @return array
     */
    public static function convertTablesToUtf8mb4($db, $tables, $collation = 'utf8mb4_unicode_ci')
    {
        $report = array();
        $audit = self::auditTableCharsets($db, $tables);

        foreach ($audit as $table => $meta) {
            $entry = array(
                'table' => $table,
                'exists' => !empty($meta['exists']),
                'before_charset' => (string) ($meta['charset'] ?? ''),
                'before_collation' => (string) ($meta['collation'] ?? ''),
                'changed' => false,
                'error' => '',
            );

            if (empty($meta['exists'])) {
                $report[] = $entry;
                continue;
            }

            if ((string) $meta['charset'] === 'utf8mb4') {
                $report[] = $entry;
                continue;
            }

            $safeCollation = preg_replace('/[^a-z0-9_]/i', '', $collation);
            if ($safeCollation === null || $safeCollation === '') {
                $safeCollation = 'utf8mb4_unicode_ci';
            }

            $sql = "ALTER TABLE " . $table . " CONVERT TO CHARACTER SET utf8mb4 COLLATE " . $safeCollation;
            $res = $db->query($sql);
            if ($res) {
                $entry['changed'] = true;
            } else {
                $entry['error'] = (string) $db->lasterror();
                self::syslogMessage('Failed converting ' . $table . ' to utf8mb4: ' . $entry['error'], LOG_ERR);
            }

            $report[] = $entry;
        }

        return $report;
    }
}