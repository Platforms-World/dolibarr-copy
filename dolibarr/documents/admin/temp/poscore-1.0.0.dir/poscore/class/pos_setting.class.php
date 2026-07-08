<?php
class PosSetting
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getAllByEntity($entity)
    {
        $rows = array();
        $sql = "SELECT rowid, code, value, description FROM " . MAIN_DB_PREFIX . "pos_settings WHERE entity=" . ((int) $entity) . " ORDER BY code";
        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $rows[$obj->code] = array(
                    'rowid' => $obj->rowid,
                    'value' => $obj->value,
                    'description' => $obj->description,
                );
            }
        }
        return $rows;
    }

    public function upsert($entity, $code, $value, $description = null)
    {
        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "pos_settings(entity, code, value, description) VALUES (";
        $sql .= ((int) $entity) . ",";
        $sql .= "'" . $this->db->escape($code) . "',";
        $sql .= ($value !== null ? "'" . $this->db->escape($value) . "'" : "NULL") . ",";
        $sql .= ($description !== null ? "'" . $this->db->escape($description) . "'" : "NULL") . ")";
        $sql .= " ON DUPLICATE KEY UPDATE value=VALUES(value), description=VALUES(description)";
        return $this->db->query($sql);
    }
}
