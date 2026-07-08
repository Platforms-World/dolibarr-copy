<?php
require_once __DIR__ . '/TakeposMigration.class.php';

if (defined('DOL_DOCUMENT_ROOT')) {
    require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
}

class TakeposProductImageService
{
    public static function placeholderUrl()
    {
        return DOL_URL_ROOT . '/public/theme/common/nophoto.png';
    }

    public static function buildProductImageUrl($productId)
    {
        return DOL_URL_ROOT . '/takepos/product_image.php?id=' . ((int) $productId);
    }

    public static function hasProductImage($db, $productId)
    {
        static $cache = array();

        $productId = (int) $productId;
        if ($productId <= 0) {
            return false;
        }
        if (array_key_exists($productId, $cache)) {
            return (bool) $cache[$productId];
        }

        $hasImage = false;
        try {
            $hasImage = (self::resolveProductImage($db, $productId) !== null);
        } catch (Exception $e) {
            $hasImage = false;
        }

        $cache[$productId] = $hasImage;
        return $hasImage;
    }

    /**
     * Output a product image.
     *
     * IMPORTANT: this NEVER redirects to viewimage.php. A redirect forces a
     * second HTTP request, and that second request hits Dolibarr's own login
     * check with no session cookie / no token support - which is exactly what
     * caused the "works in browser, 404/login in API" bug. Every resolution
     * strategy below reads the file from disk and streams the bytes directly
     * in THIS request, so it behaves identically for browser and API/token
     * callers.
     *
     * @param DoliDB $db
     * @param int    $productId
     * @return bool
     */
    public static function outputProductImage($db, $productId)
    {
        $resolved = self::resolveProductImage($db, $productId);
        if ($resolved) {
            return self::outputFile($resolved['path'], $resolved['mtime']);
        }

        return self::outputPlaceholder();
    }

    /**
     * Resolve the product image to an actual file path on disk (no URLs,
     * no redirects). Tries several known Dolibarr storage layouts.
     *
     * @param DoliDB $db
     * @param int    $productId
     * @return array|null  ['path' => string, 'mtime' => int] or null
     */
    public static function resolveProductImage($db, $productId)
    {
        $productId = (int) $productId;
        if ($productId <= 0 || !class_exists('Product')) {
            return null;
        }

        $product = new Product($db);
        if ($product->fetch($productId) <= 0 || empty($product->id)) {
            return null;
        }

        $entity = self::resolveProductEntity($product);
        $baseDir = self::resolveProductBaseDir($entity);
        if ($baseDir === '') {
            return null;
        }

        $realBaseDir = realpath($baseDir);
        if ($realBaseDir === false || !is_dir($realBaseDir)) {
            return null;
        }

        // Strategy 1: strict candidate directories with realpath containment
        // check (original logic).
        foreach (self::candidateDirectories($product, $realBaseDir) as $candidateDir) {
            $resolved = self::resolvePhotoFromDirectory($product, $realBaseDir, $candidateDir);
            if ($resolved) {
                return $resolved;
            }
        }

        // Strategy 1b: direct "{baseDir}/{ref}/{ref}.{ext}" check - this is
        // the simplest, most common TakePOS/Dolibarr layout (folder named
        // after the product ref, containing a file named after the ref).
        // This bypasses liste_photos() entirely in case it's the one
        // failing to detect an otherwise perfectly valid file.
        $ref = self::sanitizeProductRef(isset($product->ref) ? $product->ref : '');
        if ($ref !== '') {
            $refDir = rtrim($baseDir, '/\\') . '/' . $ref . '/';
            $allowedExt = array('jpg', 'jpeg', 'png', 'webp', 'gif');
            foreach ($allowedExt as $ext) {
                $candidatePath = $refDir . $ref . '.' . $ext;
                if (is_file($candidatePath) && is_readable($candidatePath)) {
                    return array(
                        'path' => $candidatePath,
                        'mtime' => @filemtime($candidatePath) ?: time(),
                    );
                }
            }
            // Also try: any image file directly inside {baseDir}/{ref}/
            if (is_dir($refDir)) {
                $files = @scandir($refDir);
                if (is_array($files)) {
                    foreach ($files as $file) {
                        if ($file === '.' || $file === '..') {
                            continue;
                        }
                        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        if (!in_array($extension, $allowedExt, true)) {
                            continue;
                        }
                        $candidatePath = $refDir . $file;
                        if (is_file($candidatePath) && is_readable($candidatePath)) {
                            return array(
                                'path' => $candidatePath,
                                'mtime' => @filemtime($candidatePath) ?: time(),
                            );
                        }
                    }
                }
            }
        }

        // Strategy 2: same directory Dolibarr's own show_photos()/liste_photos()
        // use for viewimage.php, but resolved to a real file path instead of
        // a URL - this covers cases where Strategy 1's stricter realpath
        // containment check rejects a valid path (symlinks, trailing slash
        // differences, etc).
        $pdir = get_exdir($product->id, 2, 0, 0, $product, 'product') . $product->id . '/photos/';
        $dir = rtrim($baseDir, '/\\') . '/' . $pdir;
        if (is_dir($dir) && method_exists($product, 'liste_photos')) {
            $photos = $product->liste_photos($dir);
            if (!empty($photos) && is_array($photos)) {
                $allowed = array('jpg', 'jpeg', 'png', 'webp', 'gif');
                foreach ($photos as $photo) {
                    $filename = '';
                    if (!empty($photo['photo_vignette'])) {
                        $filename = (string) $photo['photo_vignette'];
                    } elseif (!empty($photo['photo'])) {
                        $filename = (string) $photo['photo'];
                    }
                    $filename = basename($filename);
                    if ($filename === '') {
                        continue;
                    }
                    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    if (!in_array($extension, $allowed, true)) {
                        continue;
                    }

                    $fullPath = $dir . $filename;
                    if (is_file($fullPath) && is_readable($fullPath)) {
                        return array(
                            'path' => $fullPath,
                            'mtime' => @filemtime($fullPath) ?: time(),
                        );
                    }
                }
            }
        }

        return null;
    }

    public static function outputPlaceholder()
    {
        $placeholders = array(
            DOL_DOCUMENT_ROOT . '/public/theme/common/nophoto.png',
            DOL_DOCUMENT_ROOT . '/theme/common/nophoto.png',
            DOL_DOCUMENT_ROOT . '/theme/eldy/img/object_product.png',
            DOL_DOCUMENT_ROOT . '/theme/eldy/img/object_generic.png',
        );

        foreach ($placeholders as $placeholder) {
            if (is_file($placeholder) && is_readable($placeholder)) {
                return self::outputFile($placeholder, @filemtime($placeholder) ?: time());
            }
        }

        if (!headers_sent()) {
            header('Content-Type: image/png');
            header('Cache-Control: public, max-age=3600');
        }
        echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
        return true;
    }

    private static function outputFile($path, $mtime)
    {
        $mime = self::mimeType($path);
        $etag = '"' . md5($path . '|' . (string) $mtime . '|' . (string) @filesize($path)) . '"';
        $lastModified = gmdate('D, d M Y H:i:s', (int) $mtime) . ' GMT';

        if (!headers_sent()) {
            header('Content-Type: ' . $mime);
            header('Cache-Control: public, max-age=3600');
            header('ETag: ' . $etag);
            header('Last-Modified: ' . $lastModified);
        }

        $ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim((string) $_SERVER['HTTP_IF_NONE_MATCH']) : '';
        $ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? trim((string) $_SERVER['HTTP_IF_MODIFIED_SINCE']) : '';
        if (($ifNoneMatch !== '' && $ifNoneMatch === $etag) || ($ifModifiedSince !== '' && strtotime($ifModifiedSince) >= (int) $mtime)) {
            if (!headers_sent()) {
                http_response_code(304);
            }
            return true;
        }

        $size = @filesize($path);
        if (!headers_sent() && $size !== false) {
            header('Content-Length: ' . ((int) $size));
        }

        $fp = @fopen($path, 'rb');
        if (!$fp) {
            return self::outputPlaceholder();
        }

        while (!feof($fp)) {
            echo fread($fp, 8192);
        }
        fclose($fp);
        return true;
    }

    private static function mimeType($path)
    {
        $extension = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
        $map = array(
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        );

        return isset($map[$extension]) ? $map[$extension] : 'application/octet-stream';
    }

    private static function candidateDirectories($product, $baseDir)
    {
        $baseDir = rtrim((string) $baseDir, '/\\');
        $ref = self::sanitizeProductRef(isset($product->ref) ? $product->ref : '');
        $dirs = array();

        $dirs[] = $baseDir . '/' . ltrim(get_exdir($product->id, 2, 0, 0, $product, 'product') . $product->id . '/photos', '/\\');

        $newBase = ltrim(get_exdir(0, 0, 0, 0, $product, 'product'), '/\\');
        if ($ref !== '') {
            $dirs[] = $baseDir . '/' . ltrim($newBase . $ref, '/\\');
            $dirs[] = $baseDir . '/' . ltrim($newBase . $ref . '/photos', '/\\');
            $dirs[] = $baseDir . '/' . $ref;
            $dirs[] = $baseDir . '/' . $ref . '/photos';
        }

        $normalized = array();
        foreach ($dirs as $dir) {
            $dir = rtrim(str_replace('\\', '/', (string) $dir), '/');
            if ($dir === '') {
                continue;
            }
            $normalized[$dir] = $dir;
        }

        return array_values($normalized);
    }

    private static function resolvePhotoFromDirectory($product, $realBaseDir, $candidateDir)
    {
        $realDir = realpath($candidateDir);
        if ($realDir === false || !is_dir($realDir) || !self::isPathInside($realDir, $realBaseDir)) {
            return null;
        }

        $photos = method_exists($product, 'liste_photos') ? $product->liste_photos($realDir, 1) : array();
        if (empty($photos) || !is_array($photos)) {
            return null;
        }

        $allowed = array('jpg', 'jpeg', 'png', 'webp', 'gif');
        foreach ($photos as $photo) {
            foreach (array('photo_vignette', 'photo') as $field) {
                if (empty($photo[$field])) {
                    continue;
                }

                $filename = basename((string) $photo[$field]);
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if ($filename === '' || !in_array($extension, $allowed, true)) {
                    continue;
                }

                $realPath = realpath($realDir . DIRECTORY_SEPARATOR . $filename);
                if ($realPath === false || !self::isPathInside($realPath, $realBaseDir) || !is_file($realPath) || !is_readable($realPath)) {
                    continue;
                }

                return array(
                    'path' => $realPath,
                    'mtime' => @filemtime($realPath) ?: time(),
                );
            }
        }

        return null;
    }

    private static function resolveProductEntity($product)
    {
        global $conf;
        if (!empty($product->entity)) {
            return (int) $product->entity;
        }
        return !empty($conf->entity) ? (int) $conf->entity : 1;
    }

    private static function resolveProductBaseDir($entity)
    {
        global $conf;
        $entity = (int) $entity;
        if (!empty($conf->product->multidir_output[$entity])) {
            return (string) $conf->product->multidir_output[$entity];
        }
        if (!empty($conf->product->dir_output)) {
            return (string) $conf->product->dir_output;
        }
        return '';
    }

    private static function sanitizeProductRef($ref)
    {
        $ref = (string) $ref;
        if ($ref === '') {
            return '';
        }

        if (function_exists('dol_sanitizeFileName')) {
            return dol_sanitizeFileName($ref);
        }

        return preg_replace('/[^A-Za-z0-9._-]+/', '_', $ref);
    }

    private static function isPathInside($path, $baseDir)
    {
        $normalizedPath = self::normalizePath($path);
        $normalizedBaseDir = self::normalizePath($baseDir);

        return ($normalizedPath === $normalizedBaseDir || strpos($normalizedPath, $normalizedBaseDir . '/') === 0);
    }

    private static function normalizePath($path)
    {
        return strtolower(str_replace('\\', '/', rtrim((string) $path, '/\\')));
    }
}