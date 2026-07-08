<?php
/**
 * Class for WhatsApp Templates
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class ChWhatsAppTemplate extends CommonObject
{
    /**
     * @var string ID to identify managed object
     */
    public $element = 'chwhatsapptemplate';

    /**
     * @var string Name of table without prefix where object is stored
     */
    public $table_element = 'chwhatsapp_templates';

    /**
     * @var int ID
     */
    public $id;

    public $ref;
    public $label;
    public $description;
    public $message_text;
    public $entity_type; // thirdparty, project, propal, invoice
    public $is_active;
    public $is_default;
    public $position;
    public $fk_user_author;
    public $fk_user_modif;
    public $datec;
    public $tms;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Create object into database
     *
     * @param User $user User that creates
     * @param int $notrigger 0=launch triggers after, 1=disable triggers
     * @return int <0 if KO, Id of created object if OK
     */
    public function create($user, $notrigger = 0)
    {
        global $conf;

        $error = 0;

        // Clean parameters
        $this->ref = trim($this->ref);
        $this->label = trim($this->label);
        $this->message_text = trim($this->message_text);
        $this->entity_type = trim($this->entity_type);

        // Check parameters
        if (empty($this->ref)) {
            $this->error = 'ErrorFieldRequired';
            return -1;
        }

        // Insert request
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."chwhatsapp_templates(";
        $sql .= "ref,";
        $sql .= "label,";
        $sql .= "description,";
        $sql .= "message_text,";
        $sql .= "entity_type,";
        $sql .= "is_active,";
        $sql .= "is_default,";
        $sql .= "position,";
        $sql .= "fk_user_author,";
        $sql .= "datec";
        $sql .= ") VALUES (";
        $sql .= "'".$this->db->escape($this->ref)."',";
        $sql .= "'".$this->db->escape($this->label)."',";
        $sql .= "'".$this->db->escape($this->description)."',";
        $sql .= "'".$this->db->escape($this->message_text)."',";
        $sql .= "'".$this->db->escape($this->entity_type)."',";
        $sql .= ($this->is_active ? 1 : 0).",";
        $sql .= ($this->is_default ? 1 : 0).",";
        $sql .= (int)$this->position.",";
        $sql .= (int)$user->id.",";
        $sql .= "'".$this->db->idate(dol_now())."'";
        $sql .= ")";

        $this->db->begin();

        dol_syslog(get_class($this)."::create", LOG_DEBUG);
        $resql = $this->db->query($sql);
        if (!$resql) {
            $error++;
            $this->errors[] = "Error ".$this->db->lasterror();
        }

        if (!$error) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."chwhatsapp_templates");
        }

        // Commit or rollback
        if ($error) {
            foreach ($this->errors as $errmsg) {
                dol_syslog(get_class($this)."::create ".$errmsg, LOG_ERR);
                $this->error .= ($this->error ? ', '.$errmsg : $errmsg);
            }
            $this->db->rollback();
            return -1 * $error;
        } else {
            $this->db->commit();
            return $this->id;
        }
    }

    /**
     * Load object in memory from the database
     *
     * @param int $id Id object
     * @param string $ref Ref
     * @return int <0 if KO, 0 if not found, >0 if OK
     */
    public function fetch($id, $ref = null)
    {
        $sql = "SELECT";
        $sql .= " t.rowid,";
        $sql .= " t.ref,";
        $sql .= " t.label,";
        $sql .= " t.description,";
        $sql .= " t.message_text,";
        $sql .= " t.entity_type,";
        $sql .= " t.is_active,";
        $sql .= " t.is_default,";
        $sql .= " t.position,";
        $sql .= " t.fk_user_author,";
        $sql .= " t.fk_user_modif,";
        $sql .= " t.datec,";
        $sql .= " t.tms";
        $sql .= " FROM ".MAIN_DB_PREFIX."chwhatsapp_templates as t";
        if ($ref) {
            $sql .= " WHERE t.ref = '".$this->db->escape($ref)."'";
        } else {
            $sql .= " WHERE t.rowid = ".(int)$id;
        }

        dol_syslog(get_class($this)."::fetch", LOG_DEBUG);
        $resql = $this->db->query($sql);
        if ($resql) {
            if ($this->db->num_rows($resql)) {
                $obj = $this->db->fetch_object($resql);

                $this->id = $obj->rowid;
                $this->ref = $obj->ref;
                $this->label = $obj->label;
                $this->description = $obj->description;
                $this->message_text = $obj->message_text;
                $this->entity_type = $obj->entity_type;
                $this->is_active = $obj->is_active;
                $this->is_default = $obj->is_default;
                $this->position = $obj->position;
                $this->fk_user_author = $obj->fk_user_author;
                $this->fk_user_modif = $obj->fk_user_modif;
                $this->datec = $this->db->jdate($obj->datec);
                $this->tms = $this->db->jdate($obj->tms);
            }
            $this->db->free($resql);

            return 1;
        } else {
            $this->error = "Error ".$this->db->lasterror();
            return -1;
        }
    }

    /**
     * Update object into database
     *
     * @param User $user User that modifies
     * @param int $notrigger 0=launch triggers after, 1=disable triggers
     * @return int <0 if KO, >0 if OK
     */
    public function update($user, $notrigger = 0)
    {
        $error = 0;

        // Clean parameters
        $this->ref = trim($this->ref);
        $this->label = trim($this->label);
        $this->message_text = trim($this->message_text);
        $this->entity_type = trim($this->entity_type);

        // Update request
        $sql = "UPDATE ".MAIN_DB_PREFIX."chwhatsapp_templates SET";
        $sql .= " ref='".$this->db->escape($this->ref)."',";
        $sql .= " label='".$this->db->escape($this->label)."',";
        $sql .= " description='".$this->db->escape($this->description)."',";
        $sql .= " message_text='".$this->db->escape($this->message_text)."',";
        $sql .= " entity_type='".$this->db->escape($this->entity_type)."',";
        $sql .= " is_active=".(int)$this->is_active.",";
        $sql .= " is_default=".(int)$this->is_default.",";
        $sql .= " position=".(int)$this->position.",";
        $sql .= " fk_user_modif=".(int)$user->id;
        $sql .= " WHERE rowid=".(int)$this->id;

        $this->db->begin();

        dol_syslog(get_class($this)."::update", LOG_DEBUG);
        $resql = $this->db->query($sql);
        if (!$resql) {
            $error++;
            $this->errors[] = "Error ".$this->db->lasterror();
        }

        // Commit or rollback
        if ($error) {
            foreach ($this->errors as $errmsg) {
                dol_syslog(get_class($this)."::update ".$errmsg, LOG_ERR);
                $this->error .= ($this->error ? ', '.$errmsg : $errmsg);
            }
            $this->db->rollback();
            return -1 * $error;
        } else {
            $this->db->commit();
            return 1;
        }
    }

    /**
     * Delete object in database
     *
     * @param User $user User that deletes
     * @param int $notrigger 0=launch triggers after, 1=disable triggers
     * @return int <0 if KO, >0 if OK
     */
    public function delete($user, $notrigger = 0)
    {
        $error = 0;

        $this->db->begin();

        $sql = "DELETE FROM ".MAIN_DB_PREFIX."chwhatsapp_templates";
        $sql .= " WHERE rowid=".(int)$this->id;

        dol_syslog(get_class($this)."::delete", LOG_DEBUG);
        $resql = $this->db->query($sql);
        if (!$resql) {
            $error++;
            $this->errors[] = "Error ".$this->db->lasterror();
        }

        // Commit or rollback
        if ($error) {
            foreach ($this->errors as $errmsg) {
                dol_syslog(get_class($this)."::delete ".$errmsg, LOG_ERR);
                $this->error .= ($this->error ? ', '.$errmsg : $errmsg);
            }
            $this->db->rollback();
            return -1 * $error;
        } else {
            $this->db->commit();
            return 1;
        }
    }

    /**
     * Get list of templates by entity type
     *
     * @param string $entity_type Entity type (thirdparty, project, propal, invoice)
     * @param bool $active_only Only active templates
     * @return array Array of templates
     */
    public function fetchAllByType($entity_type, $active_only = true)
    {
        $templates = array();

        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."chwhatsapp_templates";
        $sql .= " WHERE entity_type = '".$this->db->escape($entity_type)."'";
        if ($active_only) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY position ASC, label ASC";

        dol_syslog(get_class($this)."::fetchAllByType", LOG_DEBUG);
        $resql = $this->db->query($sql);
        if ($resql) {
            $num = $this->db->num_rows($resql);
            $i = 0;
            while ($i < $num) {
                $obj = $this->db->fetch_object($resql);
                $template = new ChWhatsAppTemplate($this->db);
                $template->fetch($obj->rowid);
                $templates[] = $template;
                $i++;
            }
            $this->db->free($resql);
        }

        return $templates;
    }

    /**
     * Replace substitution variables in message text
     *
     * @param object $object Object (thirdparty, project, propal, invoice)
     * @param string $entity_type Entity type
     * @return string Message with substitutions
     */
    public function replaceSubstitutions($object, $entity_type)
    {
        global $conf, $langs;

        $message = $this->message_text;

        // Common substitutions
        if (isset($object->thirdparty) && is_object($object->thirdparty)) {
            $thirdparty = $object->thirdparty;
        } elseif ($entity_type == 'thirdparty') {
            $thirdparty = $object;
        } else {
            $thirdparty = null;
        }

        if ($thirdparty) {
            $message = str_replace('__THIRDPARTY_NAME__', $thirdparty->name, $message);
            $message = str_replace('__THIRDPARTY_CODE__', $thirdparty->code_client, $message);
        }

        // Entity-specific substitutions
        switch ($entity_type) {
            case 'project':
                $message = str_replace('__PROJECT_REF__', $object->ref, $message);
                $message = str_replace('__PROJECT_TITLE__', $object->title, $message);
                break;

            case 'propal':
                $message = str_replace('__PROPAL_REF__', $object->ref, $message);
                $message = str_replace('__PROPAL_TOTAL_HT__', price($object->total_ht), $message);
                $message = str_replace('__PROPAL_TOTAL_TTC__', price($object->total_ttc), $message);
                break;

            case 'invoice':
                $message = str_replace('__INVOICE_REF__', $object->ref, $message);
                $message = str_replace('__INVOICE_TOTAL_HT__', price($object->total_ht), $message);
                $message = str_replace('__INVOICE_TOTAL_TTC__', price($object->total_ttc), $message);
                break;
        }

        return $message;
    }
}
