<?php
/**
 * Test script to manually create tables for ChWhatsAppButton
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../main.inc.php")) {
    $res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

echo "<h1>ChWhatsAppButton - Test de Activación</h1>";

// Create table
echo "<h2>1. Creando tabla...</h2>";
$sql = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."chwhatsapp_templates (
    rowid int(11) NOT NULL AUTO_INCREMENT,
    ref varchar(128) NOT NULL,
    label varchar(255) NOT NULL,
    description text,
    message_text longtext NOT NULL,
    entity_type varchar(50) NOT NULL,
    is_active tinyint(1) DEFAULT 1,
    is_default tinyint(1) DEFAULT 0,
    position int(11) DEFAULT 0,
    fk_user_author int(11) NOT NULL,
    fk_user_modif int(11),
    datec datetime NOT NULL,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (rowid),
    UNIQUE KEY uk_chwhatsapp_templates_ref (ref),
    KEY idx_chwhatsapp_entity_type (entity_type),
    KEY idx_chwhatsapp_active (is_active)
) ENGINE=innodb DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$result = $db->query($sql);
if ($result) {
    echo "<p style='color: green;'>✓ Tabla creada correctamente</p>";
} else {
    echo "<p style='color: red;'>✗ Error al crear tabla: ".$db->lasterror()."</p>";
}

// Insert templates
echo "<h2>2. Insertando plantillas (desde archivos de idioma)...</h2>";

// Load language file
$langs->load("chwhatsappbutton@chwhatsappbutton");

// Define templates structure
$templates_structure = array(
    array('THIRDPARTY_DEFAULT', 'thirdparty', 1, 10),
    array('PROJECT_UPDATE', 'project', 1, 20),
    array('PROPAL_SEND', 'propal', 1, 30),
    array('INVOICE_SEND', 'invoice', 1, 40),
    array('PAYMENT_REMINDER', 'invoice', 0, 50),
    array('PROPAL_FOLLOWUP', 'propal', 0, 60)
);

$count = 0;
foreach ($templates_structure as $tpl_struct) {
    $ref = $tpl_struct[0];
    $entity_type = $tpl_struct[1];
    $is_default = $tpl_struct[2];
    $position = $tpl_struct[3];
    
    // Get translations from lang file
    $label = $langs->trans('Template_'.$ref.'_Label');
    $description = $langs->trans('Template_'.$ref.'_Desc');
    $message = $langs->trans('Template_'.$ref.'_Message');
    
    // Skip if translation not found
    if ($label == 'Template_'.$ref.'_Label') {
        echo "<p style='color: red;'>✗ Traducción no encontrada para: ".$ref."</p>";
        continue;
    }
    
    $sql = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."chwhatsapp_templates ";
    $sql .= "(ref, label, description, message_text, entity_type, is_active, is_default, position, fk_user_author, datec) ";
    $sql .= "VALUES (";
    $sql .= "'".$db->escape($ref)."', ";
    $sql .= "'".$db->escape($label)."', ";
    $sql .= "'".$db->escape($description)."', ";
    $sql .= "'".$db->escape($message)."', ";
    $sql .= "'".$db->escape($entity_type)."', ";
    $sql .= "1, ";
    $sql .= (int)$is_default.", ";
    $sql .= (int)$position.", ";
    $sql .= "1, ";
    $sql .= "NOW()";
    $sql .= ")";
    
    $result = $db->query($sql);
    if ($result) {
        $count++;
        echo "<p style='color: green;'>✓ Plantilla insertada: ".$ref." (".$label.")</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Plantilla ya existe o error: ".$ref."</p>";
    }
}

echo "<p><strong>Total plantillas insertadas: $count</strong></p>";

// Verify
echo "<h2>3. Verificando plantillas...</h2>";
$sql = "SELECT ref, label, entity_type, is_active FROM ".MAIN_DB_PREFIX."chwhatsapp_templates ORDER BY position";
$result = $db->query($sql);

if ($result) {
    $num = $db->num_rows($result);
    echo "<p>Se encontraron <strong>$num plantillas</strong>:</p>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Ref</th><th>Label</th><th>Entity Type</th><th>Active</th></tr>";
    
    while ($obj = $db->fetch_object($result)) {
        $active = $obj->is_active ? '✓' : '✗';
        echo "<tr>";
        echo "<td>".$obj->ref."</td>";
        echo "<td>".$obj->label."</td>";
        echo "<td>".$obj->entity_type."</td>";
        echo "<td>".$active."</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>Error al consultar plantillas</p>";
}

echo "<hr>";
echo "<h2>4. Siguiente paso</h2>";
echo "<p>Ahora ve a <strong>Inicio → Configuración → Módulos</strong> y activa el módulo <strong>WhatsApp Button</strong></p>";
echo "<p>Luego ve a <strong>Herramientas → WhatsApp</strong> para gestionar las plantillas</p>";
