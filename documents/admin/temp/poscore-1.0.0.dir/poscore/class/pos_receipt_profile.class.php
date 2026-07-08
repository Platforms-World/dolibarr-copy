<?php
require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';

class PosReceiptProfile extends CommonObject
{
    public $element = 'pos_receipt_profile';
    public $table_element = 'pos_receipt_profile';

    public $rowid;
    public $entity;
    public $code;
    public $label;
    public $is_default = 0;
    public $settings_json;
    public $status = 1;
    public $datec;
    public $fk_user_creat;
    public $fk_user_modif;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function create($user)
    {
        $this->entity = getEntity('pos_receipt_profile');
        $this->datec = dol_now();
        $this->fk_user_creat = $user->id;

        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "pos_receipt_profile(entity, code, label, is_default, settings_json, status, datec, fk_user_creat) VALUES (";
        $sql .= ((int) $this->entity) . ",";
        $sql .= "'" . $this->db->escape($this->code) . "',";
        $sql .= "'" . $this->db->escape($this->label) . "',";
        $sql .= ((int) $this->is_default) . ",";
        $sql .= ($this->settings_json !== null ? "'" . $this->db->escape($this->settings_json) . "'" : "NULL") . ",";
        $sql .= ((int) $this->status) . ",";
        $sql .= "'" . $this->db->idate($this->datec) . "',";
        $sql .= ((int) $this->fk_user_creat) . ")";
        if ($this->db->query($sql)) {
            $this->rowid = $this->db->last_insert_id(MAIN_DB_PREFIX . 'pos_receipt_profile');
            return $this->rowid;
        }
        $this->error = $this->db->lasterror();
        return -1;
    }
}
