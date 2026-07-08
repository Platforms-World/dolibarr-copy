-- Table for WhatsApp message templates
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
