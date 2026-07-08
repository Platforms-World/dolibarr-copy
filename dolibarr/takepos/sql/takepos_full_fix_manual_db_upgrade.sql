-- ==========================================================
-- TakePOS Full Fix - Manual DB Upgrade
-- تاريخ الإنشاء: 2026-04-24
--
-- ملاحظات مهمة قبل التشغيل:
-- 1) خذ نسخة احتياطية من قاعدة البيانات قبل التنفيذ.
-- 2) هذا الملف يستخدم بادئة Dolibarr الافتراضية llx_. إذا كانت البادئة مختلفة، استبدل llx_ بالبادئة الصحيحة قبل التشغيل.
-- 3) هذا الملف يفترض أن رقم الكيان entity = 1. غيّر القيمة التالية إذا كانت شركتك على entity مختلف.
-- 4) سعر الدولار الافتراضي هنا: 1 JOD = 1.410000 USD. عدّله حسب سعر الصرف المطلوب قبل التشغيل.
-- ==========================================================

SET @takepos_entity = 1;
SET @takepos_usd_rate = '1.410000';
SET @db_name = DATABASE();

-- ==========================================================
-- إعدادات النظام: العملة الأساسية JOD وتفعيل دفع USD والشفت
-- ==========================================================
DELETE FROM llx_const
WHERE entity = @takepos_entity
  AND name IN (
    'MAIN_MONNAIE',
    'TAKEPOS_EXTRA_PAYMENT_CURRENCIES',
    'TAKEPOS_USD_RATE',
    'TAKEPOS_REQUIRE_OPEN_SHIFT_FOR_PAYMENTS',
    'TAKEPOS_REQUIRE_SHIFT_FOR_CASH_MOVEMENTS'
  );

INSERT INTO llx_const (entity, name, value, type, visible, note) VALUES
(@takepos_entity, 'MAIN_MONNAIE', 'JOD', 'chaine', 1, 'Set main currency to Jordanian Dinar for TakePOS'),
(@takepos_entity, 'TAKEPOS_EXTRA_PAYMENT_CURRENCIES', 'USD', 'chaine', 1, 'Extra payment currencies shown in TakePOS payment screen'),
(@takepos_entity, 'TAKEPOS_USD_RATE', @takepos_usd_rate, 'chaine', 1, 'Exchange rate: 1 JOD equals this USD amount'),
(@takepos_entity, 'TAKEPOS_REQUIRE_OPEN_SHIFT_FOR_PAYMENTS', '1', 'yesno', 1, 'Require an open shift before payment'),
(@takepos_entity, 'TAKEPOS_REQUIRE_SHIFT_FOR_CASH_MOVEMENTS', '1', 'yesno', 1, 'Require an open shift for cash movements');

-- ==========================================================
-- جدول الباركودات المتعددة للمنتجات
-- ==========================================================
CREATE TABLE IF NOT EXISTS llx_takepos_product_barcode (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  fk_product INT NOT NULL,
  barcode VARCHAR(190) NOT NULL,
  entity INT NOT NULL DEFAULT 1,
  date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_takepos_product_barcode_entity (entity, barcode),
  KEY idx_takepos_product_barcode_product (fk_product)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================================
-- جداول الشفت والكاش والحفظ المؤقت
-- ==========================================================
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

CREATE TABLE IF NOT EXISTS llx_takepos_held_sale (
  rowid INT NOT NULL AUTO_INCREMENT,
  entity INT NOT NULL DEFAULT 1,
  fk_invoice INT NOT NULL,
  fk_terminal INT NOT NULL DEFAULT 0,
  fk_user INT NOT NULL DEFAULT 0,
  fk_shift INT NOT NULL DEFAULT 0,
  hold_label VARCHAR(128) DEFAULT '',
  date_hold DATETIME NOT NULL,
  date_update DATETIME NOT NULL,
  status SMALLINT NOT NULL DEFAULT 1 COMMENT '1=held, 0=resumed/cancelled',
  PRIMARY KEY (rowid),
  INDEX idx_takepos_held_invoice (fk_invoice),
  INDEX idx_takepos_held_terminal (entity, fk_terminal, status),
  INDEX idx_takepos_held_shift (entity, fk_shift, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إضافة أعمدة/فهارس ناقصة في جدول الفواتير المعلقة إن كان موجوداً مسبقاً بنسخة أقدم
SET @has_held_fk_shift = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'llx_takepos_held_sale' AND COLUMN_NAME = 'fk_shift'
);
SET @sql = IF(@has_held_fk_shift = 0,
  'ALTER TABLE llx_takepos_held_sale ADD COLUMN fk_shift INT NOT NULL DEFAULT 0 AFTER fk_user',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_held_date_update = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'llx_takepos_held_sale' AND COLUMN_NAME = 'date_update'
);
SET @sql = IF(@has_held_date_update = 0,
  'ALTER TABLE llx_takepos_held_sale ADD COLUMN date_update DATETIME NULL AFTER date_hold',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_held_shift_index = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'llx_takepos_held_sale' AND INDEX_NAME = 'idx_takepos_held_shift'
);
SET @sql = IF(@has_held_shift_index = 0,
  'ALTER TABLE llx_takepos_held_sale ADD INDEX idx_takepos_held_shift (entity, fk_shift, status)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ==========================================================
-- ربط الفواتير بالشفت بدل الاعتماد على الجهاز فقط
-- ==========================================================
CREATE TABLE IF NOT EXISTS llx_takepos_invoice_shift (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  fk_invoice INT NOT NULL,
  fk_shift INT NOT NULL,
  fk_terminal INT NULL,
  fk_cashier_user INT NULL,
  terminal_code VARCHAR(64) NULL,
  date_creation DATETIME NOT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_takepos_invoice_shift_invoice (entity, fk_invoice),
  KEY idx_takepos_invoice_shift_shift (entity, fk_shift),
  KEY idx_takepos_invoice_shift_terminal (entity, fk_terminal),
  KEY idx_takepos_invoice_shift_cashier (entity, fk_cashier_user)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @has_facture_shift = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'llx_facture' AND COLUMN_NAME = 'fk_takepos_shift'
);
SET @sql = IF(@has_facture_shift = 0,
  'ALTER TABLE llx_facture ADD COLUMN fk_takepos_shift INT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_facture_shift_index = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'llx_facture' AND INDEX_NAME = 'idx_facture_takepos_shift'
);
SET @sql = IF(@has_facture_shift_index = 0,
  'ALTER TABLE llx_facture ADD INDEX idx_facture_takepos_shift (fk_takepos_shift)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ==========================================================
-- حفظ معلومات الدفع بعملة أجنبية وسعر الصرف
-- ==========================================================
CREATE TABLE IF NOT EXISTS llx_takepos_payment_currency (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  fk_invoice INT NOT NULL,
  fk_paiement INT NOT NULL,
  payment_code VARCHAR(32) NULL,
  base_currency VARCHAR(8) NOT NULL,
  payment_currency VARCHAR(8) NOT NULL,
  payment_rate DECIMAL(24,8) NOT NULL DEFAULT 1,
  amount_base DECIMAL(24,8) NOT NULL DEFAULT 0,
  amount_foreign DECIMAL(24,8) NOT NULL DEFAULT 0,
  excess_base DECIMAL(24,8) NOT NULL DEFAULT 0,
  excess_foreign DECIMAL(24,8) NOT NULL DEFAULT 0,
  fk_user_author INT NULL,
  date_creation DATETIME NOT NULL,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_takepos_payment_currency_invoice (entity, fk_invoice),
  KEY idx_takepos_payment_currency_payment (entity, fk_paiement),
  KEY idx_takepos_payment_currency_code (entity, payment_currency)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================================
-- جدول الشيكات المستخدم في التقارير والتنبيهات
-- ==========================================================
CREATE TABLE IF NOT EXISTS llx_takepos_cheque (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL DEFAULT 1,
  ref VARCHAR(32) NOT NULL,
  cheque_number VARCHAR(64) NOT NULL,
  fk_supplier INT NOT NULL DEFAULT 0,
  fk_purchase INT NOT NULL DEFAULT 0,
  bank_name VARCHAR(128) NOT NULL DEFAULT '',
  amount DOUBLE(24,8) NOT NULL DEFAULT 0,
  cheque_date DATE DEFAULT NULL,
  collection_date DATE DEFAULT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'pending',
  note_private TEXT DEFAULT NULL,
  fk_user_author INT NOT NULL DEFAULT 0,
  fk_user_modif INT NOT NULL DEFAULT 0,
  datec DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  tms TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_takepos_cheque_entity_ref (entity, ref),
  KEY idx_takepos_cheque_entity_status (entity, status),
  KEY idx_takepos_cheque_entity_supplier (entity, fk_supplier),
  KEY idx_takepos_cheque_entity_purchase (entity, fk_purchase),
  KEY idx_takepos_cheque_entity_cheque_date (entity, cheque_date),
  KEY idx_takepos_cheque_entity_collection_date (entity, collection_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================================
-- نسب ضريبة أساسية شائعة. يمكن إضافة أي نسبة أخرى من شاشة TakePOS > نسب الضرائب.
-- إذا كانت النسب موجودة مسبقاً لن يتم تكرارها.
-- ==========================================================
INSERT INTO llx_c_tva (entity, taux)
SELECT @takepos_entity, 0.000
WHERE NOT EXISTS (SELECT 1 FROM llx_c_tva WHERE entity IN (0, @takepos_entity) AND taux = 0.000);

INSERT INTO llx_c_tva (entity, taux)
SELECT @takepos_entity, 4.000
WHERE NOT EXISTS (SELECT 1 FROM llx_c_tva WHERE entity IN (0, @takepos_entity) AND taux = 4.000);

INSERT INTO llx_c_tva (entity, taux)
SELECT @takepos_entity, 8.000
WHERE NOT EXISTS (SELECT 1 FROM llx_c_tva WHERE entity IN (0, @takepos_entity) AND taux = 8.000);

INSERT INTO llx_c_tva (entity, taux)
SELECT @takepos_entity, 16.000
WHERE NOT EXISTS (SELECT 1 FROM llx_c_tva WHERE entity IN (0, @takepos_entity) AND taux = 16.000);

-- نهاية ملف الترقية
