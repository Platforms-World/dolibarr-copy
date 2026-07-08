<?php
/**
 * WhatsApp Button Injector
 * This file is included automatically by Dolibarr when the module is active
 */

if (!defined('NOREQUIRESOC')) {
    define('NOREQUIRESOC', '1');
}
if (!defined('NOCSRFCHECK')) {
    define('NOCSRFCHECK', '1');
}
if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOLOGIN')) {
    define('NOLOGIN', '1');
}

// Only execute if module is enabled
if (empty($conf->chwhatsappbutton->enabled)) {
    return;
}

// Add JavaScript to inject WhatsApp button
?>
<script type="text/javascript">
$(document).ready(function() {
    console.log("ChWhatsAppButton: Script loaded");
    
    // Wait for page to be fully loaded
    setTimeout(function() {
        // Check if we're on a card page
        var isThirdpartyCard = window.location.href.indexOf('/societe/card.php') > -1;
        var isInvoiceCard = window.location.href.indexOf('/compta/facture/card.php') > -1;
        var isPropalCard = window.location.href.indexOf('/comm/propal/card.php') > -1;
        var isProjectCard = window.location.href.indexOf('/projet/card.php') > -1;
        
        if (isThirdpartyCard || isInvoiceCard || isPropalCard || isProjectCard) {
            console.log("ChWhatsAppButton: On card page, adding button");
            
            // Find the actions bar
            var actionsBar = $('.tabsAction');
            
            if (actionsBar.length > 0) {
                // Create WhatsApp button
                var whatsappBtn = $('<a class="butAction" href="#" id="chwhatsapp-btn" style="background-color: #25D366; color: white;">');
                whatsappBtn.html('<span class="fa fa-whatsapp"></span> WhatsApp');
                
                // Add button to actions bar
                actionsBar.append(whatsappBtn);
                
                // Handle click
                whatsappBtn.click(function(e) {
                    e.preventDefault();
                    alert('Botón de WhatsApp funcionando! Ahora necesitamos obtener el teléfono del tercero.');
                    console.log("ChWhatsAppButton: Button clicked");
                });
                
                console.log("ChWhatsAppButton: Button added successfully");
            } else {
                console.warn("ChWhatsAppButton: .tabsAction not found");
            }
        }
    }, 500);
});
</script>
<?php
