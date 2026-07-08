<?php

class PosRefService
{
    public static function nextRef($db, $table, $prefix, $entity)
    {
        $prefix = trim((string) $prefix);
        if ($prefix === '') {
            $prefix = 'POS';
        }

        $sql = "SELECT MAX(CAST(SUBSTRING(ref, " . (strlen($prefix) + 2) . ") AS UNSIGNED)) AS maxnum";
        $sql .= " FROM " . MAIN_DB_PREFIX . $db->escape($table);
        $sql .= " WHERE entity = " . ((int) $entity);
        $sql .= " AND ref LIKE '" . $db->escape($prefix) . "-%'";

        $resql = $db->query($sql);
        $num = 0;
        if ($resql) {
            $obj = $db->fetch_object($resql);
            $num = (int) ($obj->maxnum ?? 0);
        }

        return $prefix . '-' . sprintf('%05d', $num + 1);
    }
}
