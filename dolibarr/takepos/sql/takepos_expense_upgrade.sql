-- ======================================================
-- TakePOS POS Expenses Upgrade Script
-- Creates the operational expense tables used by /takepos/expenses.php
-- Runtime schema helpers still inspect and add missing columns/indexes safely.
-- ======================================================

CREATE TABLE IF NOT EXISTS llx_takepos_expense_category (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  label VARCHAR(128) NOT NULL,
  accountancy_code VARCHAR(64) NULL,
  vat_default DECIMAL(8,4) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  pos_visible TINYINT(1) NOT NULL DEFAULT 1,
  datec DATETIME NOT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_takepos_expense_category_label (entity, label),
  KEY idx_takepos_expense_category_visible (entity, active, pos_visible)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS llx_takepos_expense (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  ref VARCHAR(64) NOT NULL,
  date_expense DATETIME NOT NULL,
  fk_user INT NOT NULL,
  fk_terminal INT NULL,
  fk_store INT NULL,
  fk_shift INT NULL,
  fk_category INT NOT NULL,
  description VARCHAR(255) NOT NULL,
  amount_ht DECIMAL(24,8) NOT NULL DEFAULT 0,
  amount_tva DECIMAL(24,8) NOT NULL DEFAULT 0,
  amount_ttc DECIMAL(24,8) NOT NULL DEFAULT 0,
  vat_rate DECIMAL(8,4) NOT NULL DEFAULT 0,
  payment_source VARCHAR(32) NOT NULL DEFAULT 'cash_register',
  note_private TEXT NULL,
  external_ref VARCHAR(128) NULL,
  status SMALLINT NOT NULL DEFAULT 1,
  accountancy_code VARCHAR(64) NULL,
  fk_bank_account INT NULL,
  fk_payment_various INT NULL,
  fk_bank_line INT NULL,
  fk_cash_movement INT NULL,
  fk_posted_user INT NULL,
  date_posted DATETIME NULL,
  import_key VARCHAR(64) NULL,
  datec DATETIME NOT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_takepos_expense_ref (entity, ref),
  KEY idx_takepos_expense_entity_date (entity, date_expense),
  KEY idx_takepos_expense_terminal (entity, fk_terminal),
  KEY idx_takepos_expense_store (entity, fk_store),
  KEY idx_takepos_expense_shift (entity, fk_shift),
  KEY idx_takepos_expense_user (entity, fk_user),
  KEY idx_takepos_expense_status (entity, status),
  KEY idx_takepos_expense_category (entity, fk_category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO llx_takepos_expense_category (entity, label, accountancy_code, vat_default, active, pos_visible, datec)
SELECT 1, 'Cleaning Expense', '611000', 0, 1, 1, NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM llx_takepos_expense_category
  WHERE entity = 1 AND label = 'Cleaning Expense'
);

INSERT INTO llx_takepos_expense_category (entity, label, accountancy_code, vat_default, active, pos_visible, datec)
SELECT 1, 'Maintenance Expense', '615000', 0, 1, 1, NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM llx_takepos_expense_category
  WHERE entity = 1 AND label = 'Maintenance Expense'
);

INSERT INTO llx_takepos_expense_category (entity, label, accountancy_code, vat_default, active, pos_visible, datec)
SELECT 1, 'Office Supplies', '606300', 0, 1, 1, NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM llx_takepos_expense_category
  WHERE entity = 1 AND label = 'Office Supplies'
);

INSERT INTO llx_takepos_expense_category (entity, label, accountancy_code, vat_default, active, pos_visible, datec)
SELECT 1, 'Fuel / Transport', '625100', 0, 1, 1, NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM llx_takepos_expense_category
  WHERE entity = 1 AND label = 'Fuel / Transport'
);

INSERT INTO llx_takepos_expense_category (entity, label, accountancy_code, vat_default, active, pos_visible, datec)
SELECT 1, 'Petty Cash Expense', '658000', 0, 1, 1, NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM llx_takepos_expense_category
  WHERE entity = 1 AND label = 'Petty Cash Expense'
);

INSERT INTO llx_takepos_expense_category (entity, label, accountancy_code, vat_default, active, pos_visible, datec)
SELECT 1, 'Store Misc Expense', '658000', 0, 1, 1, NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM llx_takepos_expense_category
  WHERE entity = 1 AND label = 'Store Misc Expense'
);
