<?php
header('Content-Type: application/json; charset=utf-8');
$f = __DIR__ . '/_context.php';
$src = is_readable($f) ? file_get_contents($f) : '';
echo json_encode(array(
    'mtime'       => file_exists($f) ? date('c', filemtime($f)) : null,
    'size_bytes'  => file_exists($f) ? filesize($f) : null,
    'has_fk_soc'  => strpos($src, 'fk_soc') !== false,
    'has_step1'   => strpos($src, 'Step 1') !== false,
    'has_old_socid' => strpos($src, 'f.socid,') !== false,
    'has_old_subquery' => strpos($src, 'SELECT COUNT(d.rowid) FROM') !== false,
    'first_300'   => substr($src, 0, 300),
), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
