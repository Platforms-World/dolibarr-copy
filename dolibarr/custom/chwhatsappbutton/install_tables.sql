-- Install ChWhatsAppButton tables
USE dolibarr;

-- Create table
CREATE TABLE IF NOT EXISTS llx_chwhatsapp_templates (
    rowid int(11) NOT NULL AUTO_INCREMENT,
    ref varchar(128) NOT NULL,
    label varchar(255) NOT NULL,
    description text,
    message_text longtext NOT NULL,
    entity_type varchar(50) NOT NULL COMMENT 'thirdparty, project, propal, invoice',
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
) ENGINE=innodb DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default templates
INSERT IGNORE INTO llx_chwhatsapp_templates (ref, label, description, message_text, entity_type, is_active, is_default, position, fk_user_author, datec) VALUES
('THIRDPARTY_DEFAULT', 'Mensaje general a tercero', 'Plantilla por defecto para terceros', 'Hola __THIRDPARTY_NAME__,\n\nEsperamos que todo esté bien.\n\nSaludos cordiales.', 'thirdparty', 1, 1, 10, 1, NOW()),
('PROJECT_UPDATE', 'Actualización de proyecto', 'Notificar avances en proyecto', 'Hola __THIRDPARTY_NAME__,\n\nTe escribo sobre el proyecto: __PROJECT_REF__ - __PROJECT_TITLE__\n\n[Escribe tu mensaje aquí]\n\nSaludos.', 'project', 1, 1, 20, 1, NOW()),
('PROPAL_SEND', 'Envío de presupuesto', 'Notificar envío de presupuesto', 'Hola __THIRDPARTY_NAME__,\n\nTe hemos enviado el presupuesto __PROPAL_REF__ por un importe de __PROPAL_TOTAL_TTC__.\n\nQuedamos a tu disposición para cualquier consulta.\n\nSaludos.', 'propal', 1, 1, 30, 1, NOW()),
('INVOICE_SEND', 'Envío de factura', 'Notificar envío de factura', 'Hola __THIRDPARTY_NAME__,\n\nTe informamos que la factura __INVOICE_REF__ por un importe de __INVOICE_TOTAL_TTC__ está disponible.\n\nGracias por tu confianza.\n\nSaludos.', 'invoice', 1, 1, 40, 1, NOW()),
('PAYMENT_REMINDER', 'Recordatorio de pago', 'Recordar pago pendiente', 'Hola __THIRDPARTY_NAME__,\n\nTe recordamos que la factura __INVOICE_REF__ por __INVOICE_TOTAL_TTC__ está pendiente de pago.\n\nSi ya has realizado el pago, por favor ignora este mensaje.\n\nGracias.', 'invoice', 1, 0, 50, 1, NOW()),
('PROPAL_FOLLOWUP', 'Seguimiento de presupuesto', 'Hacer seguimiento de presupuesto enviado', 'Hola __THIRDPARTY_NAME__,\n\n¿Has tenido oportunidad de revisar el presupuesto __PROPAL_REF__ que te enviamos?\n\nEstamos disponibles para cualquier duda o aclaración.\n\nSaludos.', 'propal', 1, 0, 60, 1, NOW());

-- Verify installation
SELECT 'Templates installed:' as status, COUNT(*) as count FROM llx_chwhatsapp_templates;
