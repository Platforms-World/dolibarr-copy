CREATE TABLE llx_pos_settings (
  rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
  entity INTEGER NOT NULL DEFAULT 1,
  code VARCHAR(128) NOT NULL,
  value TEXT DEFAULT NULL,
  description VARCHAR(255) DEFAULT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_settings_entity_code (entity, code),
  KEY idx_pos_settings_entity (entity)
) ENGINE=innodb;
