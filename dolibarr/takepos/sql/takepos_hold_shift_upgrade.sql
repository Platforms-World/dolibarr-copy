-- TakePOS Hold/Suspend Sale shift-link upgrade
-- Adds fk_shift support to prevent held sales from leaking across shifts.
-- Safe to run on MySQL/MariaDB environments used by Dolibarr 22+.

SET @db_name = DATABASE();

SET @has_fk_shift = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'llx_takepos_held_sale'
      AND COLUMN_NAME = 'fk_shift'
);
SET @sql = IF(
    @has_fk_shift = 0,
    'ALTER TABLE llx_takepos_held_sale ADD COLUMN fk_shift INT(11) NOT NULL DEFAULT 0 AFTER fk_user',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_shift_index = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'llx_takepos_held_sale'
      AND INDEX_NAME = 'idx_takepos_held_shift'
);
SET @sql = IF(
    @has_shift_index = 0,
    'ALTER TABLE llx_takepos_held_sale ADD INDEX idx_takepos_held_shift (entity, fk_shift, status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
