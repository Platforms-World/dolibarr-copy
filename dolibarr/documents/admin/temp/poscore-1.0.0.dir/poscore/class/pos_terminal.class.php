<?php
require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';

class PosTerminal extends CommonObject
{
    public $element = 'pos_terminal';
    public $table_element = 'pos_terminal';

    public $rowid;
    public $entity;
    public $ref;
    public $label;
    public $warehouse_id;
    public $receipt_profile_id;
    public $status = 1;
    public $is_default = 0;
    public $note_public;
    public $note_private;
    public $datec;
    public $tms;
    public $fk_user_creat;
    public $fk_user_modif;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function fetch($id)
    {
        $sql = "SELECT * FROM " . MAIN_DB_PREFIX . "pos_terminal WHERE rowid=" . ((int) $id) . " AND entity=" . ((int) getEntity('pos_terminal'));
        $resql = $this->db->query($sql);
        if (!$resql) return -1;
        if ($obj = $this->db->fetch_object($resql)) {
            foreach (get_object_vars($obj) as $k => $v) {
                $this->$k = $v;
            }
            return 1;
        }
        return 0;
    }

    public function create($user)
    {
        $this->entity = getEntity('pos_terminal');
        $this->datec = dol_now();
        $this->fk_user_creat = $user->id;

        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "pos_terminal (entity, ref, label, warehouse_id, receipt_profile_id, status, is_default, note_public, note_private, datec, fk_user_creat) VALUES (";
        $sql .= ((int) $this->entity) . ",";
        $sql .= "'" . $this->db->escape($this->ref) . "',";
        $sql .= "'" . $this->db->escape($this->label) . "',";
        $sql .= ($this->warehouse_id ? ((int) $this->warehouse_id) : "NULL") . ",";
        $sql .= ($this->receipt_profile_id ? ((int) $this->receipt_profile_id) : "NULL") . ",";
        $sql .= ((int) $this->status) . ",";
        $sql .= ((int) $this->is_default) . ",";
        $sql .= ($this->note_public !== null ? "'" . $this->db->escape($this->note_public) . "'" : "NULL") . ",";
        $sql .= ($this->note_private !== null ? "'" . $this->db->escape($this->note_private) . "'" : "NULL") . ",";
        $sql .= "'" . $this->db->idate($this->datec) . "',";
        $sql .= ((int) $this->fk_user_creat) . ")";

        if ($this->db->query($sql)) {
            $this->rowid = $this->db->last_insert_id(MAIN_DB_PREFIX . 'pos_terminal');
            return $this->rowid;
        }
        $this->error = $this->db->lasterror();
        return -1;
    }

    public function update($user)
    {
        $sql = "UPDATE " . MAIN_DB_PREFIX . "pos_terminal SET ";
        $sql .= "label='" . $this->db->escape($this->label) . "',";
        $sql .= "warehouse_id=" . ($this->warehouse_id ? ((int) $this->warehouse_id) : "NULL") . ",";
        $sql .= "receipt_profile_id=" . ($this->receipt_profile_id ? ((int) $this->receipt_profile_id) : "NULL") . ",";
        $sql .= "status=" . ((int) $this->status) . ",";
        $sql .= "is_default=" . ((int) $this->is_default) . ",";
        $sql .= "note_public=" . ($this->note_public !== null ? "'" . $this->db->escape($this->note_public) . "'" : "NULL") . ",";
        $sql .= "note_private=" . ($this->note_private !== null ? "'" . $this->db->escape($this->note_private) . "'" : "NULL") . ",";
        $sql .= "fk_user_modif=" . ((int) $user->id);
        $sql .= " WHERE rowid=" . ((int) $this->rowid) . " AND entity=" . ((int) getEntity('pos_terminal'));

        if ($this->db->query($sql)) {
            return 1;
        }
        $this->error = $this->db->lasterror();
        return -1;
    }

    public function delete($user)
    {
        $sql = "DELETE FROM " . MAIN_DB_PREFIX . "pos_terminal WHERE rowid=" . ((int) $this->rowid) . " AND entity=" . ((int) getEntity('pos_terminal'));
        if ($this->db->query($sql)) {
            return 1;
        }
        $this->error = $this->db->lasterror();
        return -1;
    }
}
