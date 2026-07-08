-- ======================================================
-- TakePOS Phase 1 Critical Stabilization Upgrade Script
-- Compatibility-safe SQL for MySQL / MariaDB
-- ======================================================

-- NOTE:
-- This script intentionally avoids ALTER ... ADD COLUMN IF NOT EXISTS because
-- many shared-host MariaDB/MySQL versions handle that inconsistently.
-- Runtime class migrations (TakeposAudit / services) perform schema checks and
-- add missing columns/indexes safely when needed.

CREATE TABLE IF NOT EXISTS llx_takepos_audit (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  fk_user INT NULL,
  login VARCHAR(128) NULL,
  terminal INT NULL,
  event_code VARCHAR(80) NOT NULL,
  severity VARCHAR(16) NOT NULL DEFAULT 'info',
  object_type VARCHAR(64) NULL,
  object_id INT NULL,
  amount_ttc DECIMAL(24,8) NULL,
  description TEXT NULL,
  ip_address VARCHAR(64) NULL,
  request_uri VARCHAR(255) NULL,
  user_agent VARCHAR(255) NULL,
  extra_json LONGTEXT NULL,
  datec DATETIME NOT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_takepos_audit_entity_date (entity, datec),
  KEY idx_takepos_audit_user_date (fk_user, datec),
  KEY idx_takepos_audit_event_date (event_code, datec),
  KEY idx_takepos_audit_object (object_type, object_id),
  KEY idx_takepos_audit_severity_date (severity, datec)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS llx_takepos_override_session (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  session_token VARCHAR(64) NOT NULL,
  action_code VARCHAR(64) NOT NULL,
  fk_invoice INT NOT NULL,
  fk_line INT NULL,
  fk_cashier INT NOT NULL,
  fk_manager INT NOT NULL,
  requested_number DECIMAL(24,8) NULL,
  date_approved DATETIME NOT NULL,
  date_expires DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  date_used DATETIME NULL,
  used_reason VARCHAR(64) NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_takepos_override_token (session_token),
  KEY idx_takepos_override_lookup (entity, action_code, fk_invoice, fk_cashier, used, date_expires),
  KEY idx_takepos_override_manager (fk_manager, date_approved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS llx_takepos_user_limits (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  fk_user INT NOT NULL,
  role_code VARCHAR(32) NOT NULL DEFAULT 'cashier',
  max_discount_percent DECIMAL(24,8) NOT NULL DEFAULT 0,
  max_discount_amount DECIMAL(24,8) NOT NULL DEFAULT 0,
  max_price_override_delta DECIMAL(24,8) NOT NULL DEFAULT 0,
  datec DATETIME NOT NULL,
  tms DATETIME NOT NULL,
  UNIQUE KEY uk_takepos_user_limits_entity_user (entity, fk_user),
  KEY idx_takepos_user_limits_role (entity, role_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
