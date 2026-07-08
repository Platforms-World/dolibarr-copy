/**
 * ChWhatsAppButton Module JavaScript
 */

// Function to send custom WhatsApp message
function chwhatsappSendCustom(phone) {
    var message = document.getElementById("chwhatsapp-custom-message").value;
    if (message.trim() === "") {
        alert("Por favor, ingrese un mensaje");
        return;
    }
    var url = "https://web.whatsapp.com/send?phone=" + phone + "&text=" + encodeURIComponent(message);
    window.open(url, "_blank");
}

// Initialize modal functionality when document is ready
$(document).ready(function() {
    console.log("ChWhatsAppButton: JavaScript loaded");
    
    // Close modal when clicking on X
    $(".chwhatsapp-close").click(function() {
        $("#chwhatsapp-modal").hide();
    });
    
    // Close modal when clicking outside of it
    $(window).click(function(event) {
        if (event.target.id === "chwhatsapp-modal") {
            $("#chwhatsapp-modal").hide();
        }
    });
    
    // Confirm before opening WhatsApp
    $("a[href*='web.whatsapp.com']").click(function(e) {
        var confirmed = confirm("IMPORTANTE: Necesitas tener WhatsApp Web o WhatsApp Desktop (Windows/Mac) instalado y activo para enviar mensajes. ¿Deseas continuar?");
        if (!confirmed) {
            e.preventDefault();
        }
    });
    
    // INJECT WHATSAPP BUTTON ON CARD PAGES
    setTimeout(function() {
        // Check if we're on a card page
        var isThirdpartyCard = window.location.href.indexOf('/societe/card.php') > -1;
        var isInvoiceCard = window.location.href.indexOf('/compta/facture/card.php') > -1;
        var isPropalCard = window.location.href.indexOf('/comm/propal/card.php') > -1;
        var isProjectCard = window.location.href.indexOf('/projet/card.php') > -1;
        
        if (isThirdpartyCard || isInvoiceCard || isPropalCard || isProjectCard) {
            console.log("ChWhatsAppButton: On card page, injecting button");
            
            // Find the actions bar
            var actionsBar = $('.tabsAction');
            
            if (actionsBar.length > 0) {
                console.log("ChWhatsAppButton: Found .tabsAction, adding button");
                
                // Create WhatsApp button
                var whatsappBtn = $('<a class="butAction" href="#" id="chwhatsapp-test-btn">');
                // Try Font Awesome icon, fallback to emoji
                var btnText = (typeof chwhatsappLang !== 'undefined') ? chwhatsappLang.WhatsApp : 'WhatsApp';
                whatsappBtn.html('<i class="fab fa-whatsapp" style="font-size:1.2em; color:white !important;"></i> ' + btnText);
                whatsappBtn.css({
                    'background-color': '#25D366 !important',
                    'color': 'white !important',
                    'border-color': '#25D366 !important'
                });
                
                // Check if icon loaded, otherwise use emoji
                setTimeout(function() {
                    var icon = whatsappBtn.find('i');
                    if (icon.width() === 0 || icon.height() === 0) {
                        // Icon didn't load, use emoji instead
                        whatsappBtn.html('<span style="font-size:1.2em;">📱</span> WhatsApp');
                    }
                }, 100);
                
                // Find the EMAIL button and insert WhatsApp button after it
                // Try different variations of the email button text
                var emailBtn = actionsBar.find('a').filter(function() {
                    var text = $(this).text().toUpperCase();
                    return text.indexOf('E-MAIL') > -1 || 
                           text.indexOf('EMAIL') > -1 || 
                           text.indexOf('ENVIAR') > -1 ||
                           $(this).attr('href') && $(this).attr('href').indexOf('action=presend') > -1;
                });
                
                if (emailBtn.length > 0) {
                    console.log("ChWhatsAppButton: Found EMAIL button, inserting WhatsApp after it");
                    console.log("ChWhatsAppButton: Email button text:", emailBtn.first().text());
                    emailBtn.first().after(whatsappBtn);
                } else {
                    // If no email button found, try to insert before the first button
                    var firstBtn = actionsBar.find('a.butAction').first();
                    if (firstBtn.length > 0) {
                        console.log("ChWhatsAppButton: No EMAIL button found, inserting before first button");
                        firstBtn.before(whatsappBtn);
                    } else {
                        console.log("ChWhatsAppButton: No buttons found, appending to end");
                        actionsBar.append(whatsappBtn);
                    }
                }
                
                // Handle click
                whatsappBtn.click(function(e) {
                    e.preventDefault();
                    console.log("ChWhatsAppButton: Button clicked");
                    
                    // Determine entity type and ID
                    var entityType = '';
                    var entityId = 0;
                    
                    if (isThirdpartyCard) {
                        entityType = 'thirdparty';
                        entityId = getUrlParameter('id') || getUrlParameter('socid');
                    } else if (isInvoiceCard) {
                        entityType = 'invoice';
                        entityId = getUrlParameter('id') || getUrlParameter('facid');
                    } else if (isPropalCard) {
                        entityType = 'propal';
                        entityId = getUrlParameter('id');
                    } else if (isProjectCard) {
                        entityType = 'project';
                        entityId = getUrlParameter('id');
                    }
                    
                    if (!entityId) {
                        var errorMsg = (typeof chwhatsappLang !== 'undefined') ? chwhatsappLang.CouldNotDetermineEntityId : 'Could not determine entity ID';
                        alert(errorMsg);
                        return;
                    }
                    
                    console.log("ChWhatsAppButton: Loading templates for", entityType, entityId);
                    
                    // Show loading
                    var loadingText = (typeof chwhatsappLang !== 'undefined') ? chwhatsappLang.Loading : 'Loading';
                    whatsappBtn.html('<i class="fa fa-spinner fa-spin"></i> ' + loadingText + '...');
                    
                    // Load templates via AJAX
                    $.ajax({
                        url: '/custom/chwhatsappbutton/ajax/get_templates.php',
                        type: 'GET',
                        data: {
                            entity_type: entityType,
                            entity_id: entityId
                        },
                        dataType: 'json',
                        success: function(response) {
                            var btnText = (typeof chwhatsappLang !== 'undefined') ? chwhatsappLang.WhatsApp : 'WhatsApp';
                            whatsappBtn.html('<span class="fa fa-whatsapp"></span> ' + btnText);
                            
                            if (response.success) {
                                showWhatsAppModal(response.phone, response.templates, response.thirdparty_name);
                            } else {
                                alert('Error: ' + response.error);
                            }
                        },
                        error: function(xhr, status, error) {
                            var btnText = (typeof chwhatsappLang !== 'undefined') ? chwhatsappLang.WhatsApp : 'WhatsApp';
                            whatsappBtn.html('<span class="fa fa-whatsapp"></span> ' + btnText);
                            var errorMsg = (typeof chwhatsappLang !== 'undefined') ? chwhatsappLang.ErrorLoadingTemplates : 'Error loading templates';
                            alert(errorMsg + ': ' + error);
                            console.error('AJAX error:', xhr.responseText);
                        }
                    });
                });
                
                console.log("ChWhatsAppButton: Button added successfully!");
            } else {
                console.warn("ChWhatsAppButton: .tabsAction not found on page");
                console.log("ChWhatsAppButton: Available classes:", $('[class*="tab"]').map(function() { return this.className; }).get());
            }
        } else {
            console.log("ChWhatsAppButton: Not on a card page");
        }
    }, 1000); // Wait 1 second for page to fully load
});

// Helper function to get URL parameters
function getUrlParameter(name) {
    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
    var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    var results = regex.exec(location.search);
    return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
}

// Global variable to store templates
var chwhatsappCurrentTemplates = [];

// Function to show WhatsApp modal with templates
function showWhatsAppModal(phone, templates, thirdpartyName) {
    // Store templates globally
    chwhatsappCurrentTemplates = templates;
    
    // Remove existing modal if any
    $('#chwhatsapp-dynamic-modal').remove();
    
    // Get translations
    var lang = (typeof chwhatsappLang !== 'undefined') ? chwhatsappLang : {};
    var sendToText = lang.SendToWhatsApp || 'Send WhatsApp to';
    var phoneText = lang.Phone || 'Phone';
    var selectTemplateText = lang.SelectTemplate || 'Select a template';
    var sendThisMessageText = lang.SendThisMessage || 'Send this message';
    var orWriteCustomText = lang.OrWriteCustomMessage || 'Or write a custom message';
    var sendCustomText = lang.SendCustomMessage || 'Send custom message';
    
    // Create modal HTML
    var modalHtml = '<div id="chwhatsapp-dynamic-modal" class="chwhatsapp-modal" style="display:block;">';
    modalHtml += '  <div class="chwhatsapp-modal-content">';
    modalHtml += '    <span class="chwhatsapp-close">&times;</span>';
    modalHtml += '    <h2><i class="fa fa-whatsapp"></i> ' + sendToText + ' ' + thirdpartyName + '</h2>';
    modalHtml += '    <p>' + phoneText + ': <strong>' + phone + '</strong></p>';
    
    if (templates.length > 0) {
        modalHtml += '    <h3>' + selectTemplateText + ':</h3>';
        modalHtml += '    <div class="chwhatsapp-templates">';
        
        templates.forEach(function(tpl, index) {
            modalHtml += '      <div class="chwhatsapp-template-item">';
            modalHtml += '        <h4>' + tpl.label + '</h4>';
            if (tpl.description) {
                modalHtml += '        <p class="chwhatsapp-template-desc">' + tpl.description + '</p>';
            }
            modalHtml += '        <div class="chwhatsapp-template-preview">' + tpl.message.replace(/\n/g, '<br>') + '</div>';
            modalHtml += '        <button class="butAction chwhatsapp-send-template" data-phone="' + phone + '" data-template-index="' + index + '">' + sendThisMessageText + '</button>';
            modalHtml += '      </div>';
        });
        
        modalHtml += '    </div>';
    }
    
    modalHtml += '    <h3>' + orWriteCustomText + ':</h3>';
    modalHtml += '    <textarea id="chwhatsapp-custom-msg" rows="5" style="width:100%; padding:10px;"></textarea>';
    modalHtml += '    <br><br>';
    modalHtml += '    <button class="butAction" onclick="sendWhatsAppCustomMessage(\'' + phone + '\')">' + sendCustomText + '</button>';
    modalHtml += '  </div>';
    modalHtml += '</div>';
    
    // Append to body
    $('body').append(modalHtml);
    
    // Setup close handlers
    $('#chwhatsapp-dynamic-modal .chwhatsapp-close').click(function() {
        $('#chwhatsapp-dynamic-modal').remove();
    });
    
    $(window).click(function(event) {
        if (event.target.id === 'chwhatsapp-dynamic-modal') {
            $('#chwhatsapp-dynamic-modal').remove();
        }
    });
    
    // Setup template send buttons
    $('.chwhatsapp-send-template').click(function() {
        var phone = $(this).data('phone');
        var templateIndex = $(this).data('template-index');
        var template = chwhatsappCurrentTemplates[templateIndex];
        
        if (template && template.message) {
            sendWhatsAppMessage(phone, template.message);
        } else {
            alert('Error: No se pudo cargar el mensaje de la plantilla');
        }
    });
}

// Function to send WhatsApp message
function sendWhatsAppMessage(phone, message) {
    var lang = (typeof chwhatsappLang !== 'undefined') ? chwhatsappLang : {};
    var confirmText = lang.OpenWhatsAppConfirm || 'Open WhatsApp Web to send this message?';
    
    if (confirm(confirmText)) {
        var url = 'https://web.whatsapp.com/send?phone=' + phone + '&text=' + encodeURIComponent(message);
        window.open(url, '_blank');
        $('#chwhatsapp-dynamic-modal').remove();
    }
}

// Function to send custom WhatsApp message
function sendWhatsAppCustomMessage(phone) {
    var message = $('#chwhatsapp-custom-msg').val();
    if (message.trim() === '') {
        var lang = (typeof chwhatsappLang !== 'undefined') ? chwhatsappLang : {};
        var alertText = lang.PleaseWriteMessage || 'Please write a message';
        alert(alertText);
        return;
    }
    sendWhatsAppMessage(phone, message);
}
