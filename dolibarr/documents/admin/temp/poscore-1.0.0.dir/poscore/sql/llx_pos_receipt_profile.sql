CREATE TABLE llx_pos_receipt_profile (
  rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
  entity INTEGER NOT NULL DEFAULT 1,
  code VARCHAR(64) NOT NULL,
  label VARCHAR(255) NOT NULL,
  is_default SMALLINT NOT NULL DEFAULT 0,
  settings_json LONGTEXT DEFAULT NULL,
  status SMALLINT NOT NULL DEFAULT 1,
  datec DATETIME NOT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_user_creat INTEGER DEFAULT NULL,
  fk_user_modif INTEGER DEFAULT NULL,
  UNIQUE KEY uk_pos_receipt_profile_entity_code (entity, code),
  KEY idx_pos_receipt_profile_entity (entity),
  KEY idx_pos_receipt_profile_status (status)
) ENGINE=innodb;
