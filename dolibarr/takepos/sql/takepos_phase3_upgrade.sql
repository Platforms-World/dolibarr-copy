-- ======================================================
-- TakePOS Phase 3 Upgrade Script
-- Offline/Sync + Loyalty/CRM + Device Layer + API/Webhooks
-- Compatibility-safe SQL for MySQL / MariaDB
-- ======================================================

-- ---------------------------------------------------------------------
-- Offline sync queue
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_takepos_sync_queue (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  action_type VARCHAR(64) NOT NULL,
  payload_json LONGTEXT NULL,
  local_ref VARCHAR(128) NULL,
  idempotency_key VARCHAR(128) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  retry_count INT NOT NULL DEFAULT 0,
  last_error VARCHAR(255) NULL,
  fk_user INT NULL,
  fk_store INT NULL,
  fk_terminal INT NULL,
  conflict_note TEXT NULL,
  date_creation DATETIME NOT NULL,
  date_last_attempt DATETIME NULL,
  date_synced DATETIME NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_takepos_sync_idem (entity, idempotency_key),
  KEY idx_takepos_sync_status (entity, status),
  KEY idx_takepos_sync_action (entity, action_type),
  KEY idx_takepos_sync_user (entity, fk_user),
  KEY idx_takepos_sync_created (entity, date_creation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Offline sync queue event log
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_takepos_sync_log (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  fk_queue INT NOT NULL,
  event_code VARCHAR(64) NOT NULL,
  message VARCHAR(255) NULL,
  context_json LONGTEXT NULL,
  fk_user INT NULL,
  datec DATETIME NOT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_takepos_sync_log_queue (entity, fk_queue),
  KEY idx_takepos_sync_log_event (entity, event_code),
  KEY idx_takepos_sync_log_date (entity, datec)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Loyalty account per customer
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_takepos_loyalty_account (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  fk_soc INT NOT NULL,
  points_balance INT NOT NULL DEFAULT 0,
  total_earned INT NOT NULL DEFAULT 0,
  total_redeemed INT NOT NULL DEFAULT 0,
  tier_code VARCHAR(32) NULL,
  last_purchase_date DATETIME NULL,
  purchase_count INT NOT NULL DEFAULT 0,
  notes TEXT NULL,
  date_creation DATETIME NOT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_takepos_loyalty_account (entity, fk_soc),
  KEY idx_takepos_loyalty_points (entity, points_balance),
  KEY idx_takepos_loyalty_tier (entity, tier_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Loyalty transactions journal
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_takepos_loyalty_txn (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  fk_account INT NOT NULL,
  fk_soc INT NOT NULL,
  txn_type VARCHAR(16) NOT NULL,
  points_delta INT NOT NULL DEFAULT 0,
  amount_base DECIMAL(24,8) NULL,
  source_type VARCHAR(32) NULL,
  source_id INT NULL,
  note VARCHAR(255) NULL,
  fk_user INT NULL,
  date_creation DATETIME NOT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_takepos_loyalty_txn_account (entity, fk_account, date_creation),
  KEY idx_takepos_loyalty_txn_soc (entity, fk_soc, date_creation),
  KEY idx_takepos_loyalty_txn_type (entity, txn_type, date_creation),
  KEY idx_takepos_loyalty_txn_source (entity, source_type, source_id),
  UNIQUE KEY uk_takepos_loyalty_txn_uniq (entity, txn_type, source_type, source_id, fk_soc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Generic hardware device profiles
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_takepos_device_profile (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  device_code VARCHAR(48) NOT NULL,
  label VARCHAR(128) NOT NULL,
  device_type VARCHAR(32) NOT NULL,
  settings_json LONGTEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  date_creation DATETIME NOT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_takepos_device_code (entity, device_code),
  KEY idx_takepos_device_type (entity, device_type, active),
  KEY idx_takepos_device_active (entity, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Terminal to device binding registry
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_takepos_terminal_device (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  fk_terminal INT NOT NULL,
  fk_device_profile INT NOT NULL,
  binding_type VARCHAR(32) NOT NULL,
  priority INT NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  date_creation DATETIME NOT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_takepos_terminal_binding (entity, fk_terminal, binding_type, priority),
  KEY idx_takepos_terminal_binding_profile (entity, fk_device_profile, active),
  KEY idx_takepos_terminal_binding_terminal (entity, fk_terminal, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Receipt printer profiles
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_takepos_printer_profile (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  profile_code VARCHAR(48) NOT NULL,
  label VARCHAR(128) NOT NULL,
  driver_type VARCHAR(32) NOT NULL DEFAULT 'raw',
  target_uri VARCHAR(255) NULL,
  copies INT NOT NULL DEFAULT 1,
  settings_json LONGTEXT NULL,
  fk_device_profile INT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  date_creation DATETIME NOT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_takepos_printer_code (entity, profile_code),
  KEY idx_takepos_printer_active (entity, active),
  KEY idx_takepos_printer_device (entity, fk_device_profile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- API token registry
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_takepos_api_token (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  token_label VARCHAR(128) NOT NULL,
  token_hash VARCHAR(128) NOT NULL,
  scope_csv VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  fk_created_by INT NULL,
  date_creation DATETIME NOT NULL,
  date_last_use DATETIME NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_takepos_api_hash (entity, token_hash),
  KEY idx_takepos_api_active (entity, active),
  KEY idx_takepos_api_last_use (entity, date_last_use)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Webhook endpoint registry
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_takepos_webhook (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  webhook_code VARCHAR(48) NOT NULL,
  label VARCHAR(128) NOT NULL,
  target_url VARCHAR(255) NOT NULL,
  secret_key VARCHAR(255) NULL,
  events_csv TEXT NOT NULL,
  headers_json LONGTEXT NULL,
  verify_ssl TINYINT(1) NOT NULL DEFAULT 1,
  timeout_sec INT NOT NULL DEFAULT 8,
  active TINYINT(1) NOT NULL DEFAULT 1,
  fk_created_by INT NULL,
  date_creation DATETIME NOT NULL,
  date_last_sent DATETIME NULL,
  last_status VARCHAR(24) NULL,
  last_error VARCHAR(255) NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_takepos_webhook_code (entity, webhook_code),
  KEY idx_takepos_webhook_active (entity, active),
  KEY idx_takepos_webhook_last (entity, date_last_sent)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Webhook delivery attempts log
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_takepos_webhook_log (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  fk_webhook INT NOT NULL,
  event_code VARCHAR(64) NOT NULL,
  payload_json LONGTEXT NULL,
  response_code INT NULL,
  response_body LONGTEXT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  date_creation DATETIME NOT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_takepos_webhook_log_hook (entity, fk_webhook, date_creation),
  KEY idx_takepos_webhook_log_event (entity, event_code, date_creation),
  KEY idx_takepos_webhook_log_success (entity, success, date_creation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
