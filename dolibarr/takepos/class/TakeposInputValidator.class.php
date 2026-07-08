<?php
/**
 * Strict numeric validation helpers for TakePOS workflows.
 */
class TakeposInputValidator
{
    public static function normalizeDecimalString($value)
    {
        if ($value === null) {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        // Accept decimal comma as decimal separator only when dot is not present.
        if (strpos($raw, ',') !== false && strpos($raw, '.') === false) {
            $raw = str_replace(',', '.', $raw);
        }

        return $raw;
    }

    public static function parseDecimal($value, &$parsed, $allowNegative = false, $maxScale = 8)
    {
        $parsed = null;
        $normalized = self::normalizeDecimalString($value);
        if ($normalized === '') {
            return false;
        }

        $maxScale = (int) $maxScale;
        if ($maxScale < 0) {
            $maxScale = 8;
        }

        $regex = '/^-?\d+(?:\.\d{1,' . $maxScale . '})?$/';
        if (!preg_match($regex, $normalized)) {
            return false;
        }

        $numeric = (float) $normalized;
        if (!$allowNegative && $numeric < 0) {
            return false;
        }

        $parsed = $numeric;
        return true;
    }

    public static function parsePositiveDecimal($value, &$parsed, $allowZero = true, $maxScale = 8)
    {
        if (!self::parseDecimal($value, $parsed, false, $maxScale)) {
            return false;
        }

        if (!$allowZero && (float) $parsed <= 0) {
            return false;
        }

        return true;
    }

    public static function parseInteger($value, &$parsed, $allowNegative = false)
    {
        $parsed = null;
        if ($value === null) {
            return false;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return false;
        }

        if (!preg_match('/^-?\d+$/', $raw)) {
            return false;
        }

        $num = (int) $raw;
        if (!$allowNegative && $num < 0) {
            return false;
        }

        $parsed = $num;
        return true;
    }

    public static function parsePositiveInteger($value, &$parsed, $allowZero = true)
    {
        if (!self::parseInteger($value, $parsed, false)) {
            return false;
        }

        if (!$allowZero && (int) $parsed <= 0) {
            return false;
        }

        return true;
    }

    /**
     * Normalize UTF-8 text input for labels/names/search fields.
     *
     * @param mixed $value Raw value
     * @param int $maxLength Max chars (0 = unlimited)
     * @param bool $collapseWhitespace Collapse whitespace runs
     * @return string
     */
    public static function normalizeUtf8Text($value, $maxLength = 0, $collapseWhitespace = true)
    {
        if (class_exists('TakeposUtf8')) {
            return TakeposUtf8::normalizeText($value, (int) $maxLength, (bool) $collapseWhitespace);
        }

        $txt = trim((string) $value);
        $maxLength = (int) $maxLength;
        if ($maxLength > 0 && function_exists('mb_substr')) {
            $txt = mb_substr($txt, 0, $maxLength, 'UTF-8');
        }
        return $txt;
    }

    /**
     * Stable normalized key for duplicate checks.
     *
     * @param mixed $value Raw value
     * @param int $maxLength Max chars
     * @return string
     */
    public static function normalizeCompareText($value, $maxLength = 255)
    {
        if (class_exists('TakeposUtf8')) {
            return TakeposUtf8::normalizeCompareKey($value, (int) $maxLength);
        }

        $txt = self::normalizeUtf8Text($value, $maxLength, true);
        return function_exists('mb_strtolower') ? mb_strtolower($txt, 'UTF-8') : strtolower($txt);
    }
}