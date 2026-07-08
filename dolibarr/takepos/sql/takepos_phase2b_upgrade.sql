-- ======================================================
-- TakePOS Phase 2B Upgrade Script (Refunds / Exchanges / Analytics)
-- Compatibility-safe SQL for MySQL / MariaDB
-- ======================================================

-- Refund header table
CREATE TABLE IF NOT EXISTS llx_takepos_refund (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  fk_original_invoice INT NULL,
  fk_refund_invoice INT NULL,
  fk_store INT NULL,
  fk_terminal INT NULL,
  fk_cashier_user INT NOT NULL,
  refund_ref VARCHAR(64) NOT NULL,
  refund_type VARCHAR(24) NOT NULL,
  total_amount DECIMAL(24,8) NOT NULL DEFAULT 0,
  payment_method VARCHAR(32) NULL,
  reason_code VARCHAR(64) NULL,
  note TEXT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'completed',
  fk_approved_by INT NULL,
  date_creation DATETIME NOT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_takepos_refund_ref (entity, refund_ref),
  KEY idx_takepos_refund_entity_date (entity, date_creation),
  KEY idx_takepos_refund_original (entity, fk_original_invoice),
  KEY idx_takepos_refund_store (entity, fk_store),
  KEY idx_takepos_refund_terminal (entity, fk_terminal),
  KEY idx_takepos_refund_cashier (entity, fk_cashier_user),
  KEY idx_takepos_refund_status (entity, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Refund lines table to track partial/quantity refunds and duplicate prevention
CREATE TABLE IF NOT EXISTS llx_takepos_refund_line (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  fk_refund INT NOT NULL,
  fk_original_line INT NULL,
  fk_product INT NULL,
  qty_refunded DECIMAL(24,8) NOT NULL DEFAULT 0,
  unit_price DECIMAL(24,8) NOT NULL DEFAULT 0,
  line_total DECIMAL(24,8) NOT NULL DEFAULT 0,
  restock_flag TINYINT(1) NOT NULL DEFAULT 0,
  date_creation DATETIME NOT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_takepos_refund_line_refund (entity, fk_refund),
  KEY idx_takepos_refund_line_original (entity, fk_original_line),
  KEY idx_takepos_refund_line_product (entity, fk_product)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Exchange table (return + replacement sale link)
CREATE TABLE IF NOT EXISTS llx_takepos_exchange (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  fk_original_invoice INT NOT NULL,
  fk_refund INT NULL,
  fk_new_invoice INT NULL,
  exchange_ref VARCHAR(64) NOT NULL,
  return_total DECIMAL(24,8) NOT NULL DEFAULT 0,
  new_sale_total DECIMAL(24,8) NOT NULL DEFAULT 0,
  net_difference DECIMAL(24,8) NOT NULL DEFAULT 0,
  status VARCHAR(24) NOT NULL DEFAULT 'completed',
  fk_approved_by INT NULL,
  date_creation DATETIME NOT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_takepos_exchange_ref (entity, exchange_ref),
  KEY idx_takepos_exchange_original (entity, fk_original_invoice),
  KEY idx_takepos_exchange_refund (entity, fk_refund),
  KEY idx_takepos_exchange_new_invoice (entity, fk_new_invoice),
  KEY idx_takepos_exchange_status (entity, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Refund reason registry (extensible)
CREATE TABLE IF NOT EXISTS llx_takepos_refund_reason (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  code VARCHAR(64) NOT NULL,
  label VARCHAR(128) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  date_creation DATETIME NOT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_takepos_refund_reason (entity, code),
  KEY idx_takepos_refund_reason_active (entity, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO llx_takepos_refund_reason(entity, code, label, active, date_creation)
SELECT 1, 'damaged', 'Damaged', 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM llx_takepos_refund_reason WHERE entity=1 AND code='damaged');
INSERT INTO llx_takepos_refund_reason(entity, code, label, active, date_creation)
SELECT 1, 'wrong_item', 'Wrong Item', 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM llx_takepos_refund_reason WHERE entity=1 AND code='wrong_item');
INSERT INTO llx_takepos_refund_reason(entity, code, label, active, date_creation)
SELECT 1, 'customer_changed_mind', 'Customer Changed Mind', 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM llx_takepos_refund_reason WHERE entity=1 AND code='customer_changed_mind');
INSERT INTO llx_takepos_refund_reason(entity, code, label, active, date_creation)
SELECT 1, 'pricing_error', 'Pricing Error', 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM llx_takepos_refund_reason WHERE entity=1 AND code='pricing_error');
INSERT INTO llx_takepos_refund_reason(entity, code, label, active, date_creation)
SELECT 1, 'duplicate_charge', 'Duplicate Charge', 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM llx_takepos_refund_reason WHERE entity=1 AND code='duplicate_charge');
INSERT INTO llx_takepos_refund_reason(entity, code, label, active, date_creation)
SELECT 1, 'expired_item', 'Expired Item', 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM llx_takepos_refund_reason WHERE entity=1 AND code='expired_item');
INSERT INTO llx_takepos_refund_reason(entity, code, label, active, date_creation)
SELECT 1, 'other', 'Other', 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM llx_takepos_refund_reason WHERE entity=1 AND code='other');
