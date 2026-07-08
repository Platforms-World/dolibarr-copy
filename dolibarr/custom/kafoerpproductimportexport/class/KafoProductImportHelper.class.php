<?php
/* Copyright (C) 2026 */

/**
 * Helper methods for kafo product import/export.
 */
class KafoProductImportHelper
{
    /**
     * Build filesystem path safely.
     *
     * @param string ...$parts Path parts
     * @return string
     */
    public static function buildPath(...$parts)
    {
        $cleanParts = array();
        foreach ($parts as $part) {
            if ($part === null) {
                continue;
            }

            $part = (string) $part;
            if ($part === '') {
                continue;
            }

            $part = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $part);
            $cleanParts[] = trim($part, DIRECTORY_SEPARATOR);
        }

        if (empty($cleanParts)) {
            return '';
        }

        $firstPart = (string) reset($parts);
        $firstPart = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $firstPart);
        $prefix = '';

        if (preg_match('/^[A-Za-z]:$/', rtrim($firstPart, DIRECTORY_SEPARATOR))) {
            $prefix = rtrim($firstPart, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            array_shift($cleanParts);
        } elseif (strpos($firstPart, DIRECTORY_SEPARATOR) === 0) {
            $prefix = DIRECTORY_SEPARATOR;
        }

        return $prefix . implode(DIRECTORY_SEPARATOR, $cleanParts);
    }

    /**
     * Ensure directory exists.
     *
     * @param string $path Directory path
     * @return bool
     */
    public static function ensureDirectory($path)
    {
        if (is_dir($path)) {
            return true;
        }

        return dol_mkdir($path) >= 0;
    }

    /**
     * Recursive delete helper.
     *
     * @param string $path Path
     * @return void
     */
    public static function cleanupDirectory($path)
    {
        if (!empty($path) && is_dir($path)) {
            dol_delete_dir_recursive($path);
        }
    }

    /**
     * Find products.csv inside extraction directory.
     *
     * @param string $rootDir Root directory
     * @return string
     */
    public static function findProductsCsv($rootDir)
    {
        $rootFile = self::buildPath($rootDir, 'products.csv');
        if (is_file($rootFile)) {
            return $rootFile;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            /** @var SplFileInfo $fileInfo */
            if ($fileInfo->isFile() && $fileInfo->getFilename() === 'products.csv') {
                return $fileInfo->getPathname();
            }
        }

        return '';
    }

    /**
     * Find images directory in extraction content.
     *
     * @param string $rootDir Root directory
     * @return string
     */
    public static function findImagesDirectory($rootDir)
    {
        $direct = self::buildPath($rootDir, 'images');
        if (is_dir($direct)) {
            return $direct;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $fileInfo) {
            /** @var SplFileInfo $fileInfo */
            if ($fileInfo->isDir() && $fileInfo->getFilename() === 'images') {
                return $fileInfo->getPathname();
            }
        }

        return '';
    }

    /**
     * Parse numeric value in a CSV-safe way.
     *
     * @param string $value Numeric input
     * @return float
     */
    public static function parseDecimal($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0.0;
        }

        $normalized = str_replace(',', '.', $value);
        return (float) price2num($normalized);
    }

    /**
     * Find category ID by ref_ext, then by label.
     *
     * @param DoliDB $db Database
     * @param int    $entity Entity id
     * @param string $search Search value
     * @return int
     */
    public static function findCategoryId($db, $entity, $search)
    {
        $search = trim((string) $search);
        if ($search === '') {
            return 0;
        }

        // إزالة الأصفار من اليسار للتوحيد مع ref_ext في DB
        if (is_numeric($search)) {
            $search = (string)(int)$search;
        }

        $escaped = $db->escape($search);

        $sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . 'categorie';
        $sql .= ' WHERE type = 0';
        $sql .= ' AND entity IN (0, ' . ((int) $entity) . ')';
        $sql .= " AND ref_ext = '" . $escaped . "'";
        $sql .= ' ORDER BY rowid ASC LIMIT 1';

        $resql = $db->query($sql);
        if ($resql && ($obj = $db->fetch_object($resql))) {
            return (int) $obj->rowid;
        }

        $sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . 'categorie';
        $sql .= ' WHERE type = 0';
        $sql .= ' AND entity IN (0, ' . ((int) $entity) . ')';
        $sql .= " AND label = '" . $escaped . "'";
        $sql .= ' ORDER BY rowid ASC LIMIT 1';

        $resql = $db->query($sql);
        if ($resql && ($obj = $db->fetch_object($resql))) {
            return (int) $obj->rowid;
        }

        return 0;
    }

    /**
     * Find warehouse ID by ref, then label, then lieu.
     *
     * @param DoliDB $db Database
     * @param int    $entity Entity id
     * @param string $search Search value
     * @return int
     */
    public static function findWarehouseId($db, $entity, $search)
    {
        $search = trim((string) $search);
        if ($search === '') {
            return 0;
        }

        $escaped = $db->escape($search);
        $fields = array('ref', 'label', 'lieu');

        foreach ($fields as $field) {
            $sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . 'entrepot';
            $sql .= ' WHERE entity IN (0, ' . ((int) $entity) . ')';
            $sql .= " AND " . $field . " = '" . $escaped . "'";
            $sql .= ' ORDER BY rowid ASC';

            $resql = $db->query($sql);
            if ($resql && ($obj = $db->fetch_object($resql))) {
                return (int) $obj->rowid;
            }
        }

        return 0;
    }

    /**
     * Check if product ref exists.
     *
     * @param DoliDB $db Database
     * @param int    $entity Entity id
     * @param string $ref Product ref
     * @return bool
     */
    public static function productRefExists($db, $entity, $ref)
    {
        $ref = trim((string) $ref);
        if ($ref === '') {
            return false;
        }

        $sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . 'product';
        $sql .= ' WHERE entity IN (0, ' . ((int) $entity) . ')';
        $sql .= " AND ref = '" . $db->escape($ref) . "'";
        $sql .= ' LIMIT 1';

        $resql = $db->query($sql);
        return ($resql && ($db->num_rows($resql) > 0));
    }

    /**
     * Check if barcode exists already.
     *
     * @param DoliDB $db Database
     * @param int    $entity Entity id
     * @param string $barcode Barcode value
     * @return bool
     */
    public static function barcodeExists($db, $entity, $barcode)
    {
        $barcode = trim((string) $barcode);
        if ($barcode === '') {
            return false;
        }

        $sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . 'product';
        $sql .= ' WHERE entity IN (0, ' . ((int) $entity) . ')';
        $sql .= " AND barcode = '" . $db->escape($barcode) . "'";
        $sql .= ' LIMIT 1';

        $resql = $db->query($sql);
        return ($resql && ($db->num_rows($resql) > 0));
    }

    /**
     * Find image file for one row.
     *
     * @param string $imagesDir Images directory
     * @param string $imageName Image value from CSV
     * @param string $productRef Product ref
     * @return string
     */
    public static function findImageFile($imagesDir, $imageName, $productRef)
    {
        if (empty($imagesDir) || !is_dir($imagesDir)) {
            return '';
        }

        $candidates = array();
        $imageName = trim((string) $imageName);
        $productRef = trim((string) $productRef);

        if ($imageName !== '') {
            $candidates[] = dol_sanitizeFileName(basename($imageName));
        } else {
            $candidates[] = dol_sanitizeFileName($productRef . '.jpg');
            $candidates[] = dol_sanitizeFileName($productRef . '.jpeg');
            $candidates[] = dol_sanitizeFileName($productRef . '.png');
        }

        foreach ($candidates as $name) {
            if ($name === '') {
                continue;
            }

            $fullPath = self::buildPath($imagesDir, $name);
            if (is_file($fullPath)) {
                return $fullPath;
            }

            $recursive = self::findFileByNameRecursive($imagesDir, $name);
            if ($recursive !== '') {
                return $recursive;
            }
        }

        if ($imageName !== '') {
            // Extra fallback: try product ref if explicit image name not found.
            $fallbackExt = array('.jpg', '.jpeg', '.png');
            foreach ($fallbackExt as $ext) {
                $fallbackName = dol_sanitizeFileName($productRef . $ext);
                $fullPath = self::buildPath($imagesDir, $fallbackName);
                if (is_file($fullPath)) {
                    return $fullPath;
                }
            }
        }

        return '';
    }

    /**
     * Find file by exact basename recursively.
     *
     * @param string $rootDir Root
     * @param string $filename File name
     * @return string
     */
    public static function findFileByNameRecursive($rootDir, $filename)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            /** @var SplFileInfo $fileInfo */
            if ($fileInfo->isFile() && $fileInfo->getFilename() === $filename) {
                return $fileInfo->getPathname();
            }
        }

        return '';
    }

    /**
     * Normalize CSV row to expected columns.
     *
     * @param array<int, string> $header Header list
     * @param array<int, string> $row Data row
     * @return array<string, string>
     */
    public static function rowToAssoc(array $header, array $row)
    {
        $normalizedRow = array_values($row);
        $headerCount = count($header);
        $rowCount = count($normalizedRow);

        if ($rowCount < $headerCount) {
            $normalizedRow = array_pad($normalizedRow, $headerCount, '');
        } elseif ($rowCount > $headerCount) {
            $normalizedRow = array_slice($normalizedRow, 0, $headerCount);
        }

        $assoc = array_combine($header, $normalizedRow);
        return is_array($assoc) ? $assoc : array();
    }
}