<?php
/**
 * JavaScript translations for ChWhatsAppButton module
 */

// Load Dolibarr environment
if (!defined('NOREQUIREUSER')) define('NOREQUIREUSER', '1');
if (!defined('NOREQUIREDB')) define('NOREQUIREDB', '1');
if (!defined('NOREQUIRESOC')) define('NOREQUIRESOC', '1');
if (!defined('NOREQUIRETRAN')) define('NOREQUIRETRAN', '1');
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', 1);
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1);
if (!defined('NOLOGIN')) define('NOLOGIN', 1);
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', 1);
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', 1);
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');

$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
    $res = @include "../../../../main.inc.php";
}

// Load translation files
$langs->loadLangs(array("chwhatsappbutton@chwhatsappbutton"));

// Set content type
header('Content-Type: application/javascript');
header('Cache-Control: max-age=3600, public');

// Generate JavaScript object with translations
?>
// ChWhatsAppButton translations
var chwhatsappLang = {
    SendToWhatsApp: "<?php echo $langs->transnoentities('SendToWhatsApp'); ?>",
    Phone: "<?php echo $langs->transnoentities('Phone'); ?>",
    SelectTemplate: "<?php echo $langs->transnoentities('SelectTemplate'); ?>",
    OrWriteCustomMessage: "<?php echo $langs->transnoentities('OrWriteCustomMessage'); ?>",
    SendThisMessage: "<?php echo $langs->transnoentities('SendThisMessage'); ?>",
    SendCustomMessage: "<?php echo $langs->transnoentities('SendCustomMessage'); ?>",
    OpenWhatsAppConfirm: "<?php echo $langs->transnoentities('OpenWhatsAppConfirm'); ?>",
    Loading: "<?php echo $langs->transnoentities('Loading'); ?>",
    WhatsApp: "<?php echo $langs->transnoentities('WhatsApp'); ?>",
    CouldNotDetermineEntityId: "<?php echo $langs->transnoentities('CouldNotDetermineEntityId'); ?>",
    ErrorLoadingTemplates: "<?php echo $langs->transnoentities('ErrorLoadingTemplates'); ?>",
    TemplateNotFound: "<?php echo $langs->transnoentities('TemplateNotFound'); ?>",
    PleaseWriteMessage: "<?php echo $langs->transnoentities('PleaseWriteMessage'); ?>"
};
