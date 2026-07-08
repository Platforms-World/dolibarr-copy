-- UTF-8 hardening for Arabic-safe category/product/customer text handling.
-- Safe for MySQL/MariaDB: checks table existence before ALTER.
-- Run on the target Dolibarr database (same DB used by TakePOS).

SET @db_name = DATABASE();
SET @target_collation = 'utf8mb4_unicode_ci';

-- Ensure DB session charset is UTF-8 for this migration run.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;


-- Core catalog tables.
SELECT IF(EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = @db_name AND table_name = 'llx_categorie'),
    CONCAT('ALTER TABLE llx_categorie CONVERT TO CHARACTER SET utf8mb4 COLLATE ', @target_collation),
    'SELECT 1') INTO @sql;
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT IF(EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = @db_name AND table_name = 'llx_categorie_lang'),
    CONCAT('ALTER TABLE llx_categorie_lang CONVERT TO CHARACTER SET utf8mb4 COLLATE ', @target_collation),
    'SELECT 1') INTO @sql;
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT IF(EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = @db_name AND table_name = 'llx_product'),
    CONCAT('ALTER TABLE llx_product CONVERT TO CHARACTER SET utf8mb4 COLLATE ', @target_collation),
    'SELECT 1') INTO @sql;
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT IF(EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = @db_name AND table_name = 'llx_product_lang'),
    CONCAT('ALTER TABLE llx_product_lang CONVERT TO CHARACTER SET utf8mb4 COLLATE ', @target_collation),
    'SELECT 1') INTO @sql;
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Customer/supplier names are in llx_societe.
SELECT IF(EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = @db_name AND table_name = 'llx_societe'),
    CONCAT('ALTER TABLE llx_societe CONVERT TO CHARACTER SET utf8mb4 COLLATE ', @target_collation),
    'SELECT 1') INTO @sql;
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Optional custom TakePOS tables (if present) for consistent Arabic handling.
SELECT IF(EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = @db_name AND table_name = 'llx_takepos_store'),
    CONCAT('ALTER TABLE llx_takepos_store CONVERT TO CHARACTER SET utf8mb4 COLLATE ', @target_collation),
    'SELECT 1') INTO @sql;
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT IF(EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = @db_name AND table_name = 'llx_takepos_terminal'),
    CONCAT('ALTER TABLE llx_takepos_terminal CONVERT TO CHARACTER SET utf8mb4 COLLATE ', @target_collation),
    'SELECT 1') INTO @sql;
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT IF(EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = @db_name AND table_name = 'llx_takepos_refund_reason'),
    CONCAT('ALTER TABLE llx_takepos_refund_reason CONVERT TO CHARACTER SET utf8mb4 COLLATE ', @target_collation),
    'SELECT 1') INTO @sql;
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Quick verification snapshot.
SELECT t.table_name, t.table_collation, c.character_set_name
FROM information_schema.tables t
LEFT JOIN information_schema.collation_character_set_applicability c ON c.collation_name = t.table_collation
WHERE t.table_schema = @db_name
  AND t.table_name IN (
    'llx_categorie','llx_categorie_lang','llx_product','llx_product_lang','llx_societe',
    'llx_takepos_store','llx_takepos_terminal','llx_takepos_refund_reason'
  )
ORDER BY t.table_name;