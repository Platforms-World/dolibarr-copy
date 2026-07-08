-- TakePOS Stabilization & Production Hardening upgrade
-- Safe for MySQL / MariaDB deployments commonly used with Dolibarr on cPanel.

SET @db_name = DATABASE();

-- ---------------------------------------------------------------------
-- API token expiration
-- ---------------------------------------------------------------------
SET @has_api_token_expiration = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'llx_takepos_api_token'
      AND COLUMN_NAME = 'date_expiration'
);
SET @sql = IF(
    @has_api_token_expiration = 0,
    'ALTER TABLE llx_takepos_api_token ADD COLUMN date_expiration DATETIME NULL AFTER date_last_use',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_api_token_expiration_index = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'llx_takepos_api_token'
      AND INDEX_NAME = 'idx_takepos_api_expiration'
);
SET @sql = IF(
    @has_api_token_expiration_index = 0,
    'ALTER TABLE llx_takepos_api_token ADD INDEX idx_takepos_api_expiration (entity, date_expiration)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- API idempotency registry
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_takepos_api_idempotency (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  idempotency_key VARCHAR(190) NOT NULL,
  endpoint VARCHAR(80) NOT NULL,
  invoice_id INT NOT NULL DEFAULT 0,
  amount DECIMAL(24,8) NOT NULL DEFAULT 0,
  response_json LONGTEXT NULL,
  http_code INT NOT NULL DEFAULT 200,
  fk_user INT NULL,
  datec DATETIME NOT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_takepos_api_idem (entity, endpoint, idempotency_key),
  KEY idx_takepos_api_idem_invoice (entity, endpoint, invoice_id),
  KEY idx_takepos_api_idem_user (entity, fk_user, datec)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- API login rate-limit storage
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_takepos_api_login_attempt (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  login_value VARCHAR(190) NOT NULL,
  ip_address VARCHAR(64) NOT NULL,
  attempt_count INT NOT NULL DEFAULT 0,
  window_start DATETIME NOT NULL,
  locked_until DATETIME NULL,
  date_last_attempt DATETIME NOT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_takepos_api_login_attempt (entity, login_value, ip_address),
  KEY idx_takepos_api_login_lock (entity, locked_until),
  KEY idx_takepos_api_login_last (entity, date_last_attempt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
