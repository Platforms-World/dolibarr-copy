-- ======================================================
-- TakePOS Phase 2A Upgrade Script (Shift/Cash/Store/Terminal)
-- Compatibility-safe SQL for MySQL / MariaDB
-- ======================================================

-- Shift lifecycle table
CREATE TABLE IF NOT EXISTS llx_takepos_shift (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  fk_store INT NULL,
  fk_terminal INT NOT NULL,
  fk_cashier_user INT NOT NULL,
  shift_ref VARCHAR(64) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'open',
  opening_float DECIMAL(24,8) NOT NULL DEFAULT 0,
  expected_cash DECIMAL(24,8) NOT NULL DEFAULT 0,
  counted_cash DECIMAL(24,8) NULL,
  cash_difference DECIMAL(24,8) NULL,
  total_cash_sales DECIMAL(24,8) NOT NULL DEFAULT 0,
  total_card_sales DECIMAL(24,8) NOT NULL DEFAULT 0,
  total_other_sales DECIMAL(24,8) NOT NULL DEFAULT 0,
  total_paid_in DECIMAL(24,8) NOT NULL DEFAULT 0,
  total_paid_out DECIMAL(24,8) NOT NULL DEFAULT 0,
  total_safe_drop DECIMAL(24,8) NOT NULL DEFAULT 0,
  date_open DATETIME NOT NULL,
  date_close DATETIME NULL,
  fk_opened_by INT NOT NULL,
  fk_closed_by INT NULL,
  fk_approved_by INT NULL,
  notes TEXT NULL,
  date_creation DATETIME NOT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_takepos_shift_ref (entity, shift_ref),
  KEY idx_takepos_shift_entity_status (entity, status),
  KEY idx_takepos_shift_entity_terminal_status (entity, fk_terminal, status),
  KEY idx_takepos_shift_entity_cashier_status (entity, fk_cashier_user, status),
  KEY idx_takepos_shift_entity_store (entity, fk_store),
  KEY idx_takepos_shift_open_close (date_open, date_close)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cash movement ledger bound to shift
CREATE TABLE IF NOT EXISTS llx_takepos_cash_movement (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  fk_shift INT NOT NULL,
  movement_type VARCHAR(32) NOT NULL,
  amount DECIMAL(24,8) NOT NULL,
  reason_code VARCHAR(64) NULL,
  reason_text VARCHAR(255) NULL,
  note TEXT NULL,
  fk_created_by INT NOT NULL,
  fk_approved_by INT NULL,
  date_creation DATETIME NOT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_takepos_cash_entity_shift (entity, fk_shift),
  KEY idx_takepos_cash_type_date (entity, movement_type, date_creation),
  KEY idx_takepos_cash_created_by (entity, fk_created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Store/branch registry
CREATE TABLE IF NOT EXISTS llx_takepos_store (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  code VARCHAR(32) NOT NULL,
  label VARCHAR(128) NOT NULL,
  description TEXT NULL,
  warehouse_id INT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  date_creation DATETIME NOT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_takepos_store_entity_code (entity, code),
  KEY idx_takepos_store_entity_active (entity, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Terminal registry/mapping to store
CREATE TABLE IF NOT EXISTS llx_takepos_terminal (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  terminal_code VARCHAR(32) NOT NULL,
  label VARCHAR(128) NOT NULL,
  fk_store INT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_seen DATETIME NULL,
  metadata_json LONGTEXT NULL,
  date_creation DATETIME NOT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_takepos_terminal_entity_code (entity, terminal_code),
  KEY idx_takepos_terminal_store (entity, fk_store, active),
  KEY idx_takepos_terminal_active (entity, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User -> allowed stores mapping
CREATE TABLE IF NOT EXISTS llx_takepos_user_store (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  fk_user INT NOT NULL,
  fk_store INT NOT NULL,
  role_in_store VARCHAR(32) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  date_creation DATETIME NOT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_takepos_user_store (entity, fk_user, fk_store),
  KEY idx_takepos_user_store_store (entity, fk_store, active),
  KEY idx_takepos_user_store_user (entity, fk_user, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
