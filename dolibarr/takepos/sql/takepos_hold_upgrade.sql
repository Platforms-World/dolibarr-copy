-- TakePOS Hold/Suspend Sale Feature
-- Migration: takepos_hold_upgrade.sql
-- Adds table to track held (suspended) sales per terminal
-- Safe to run multiple times (uses IF NOT EXISTS)

CREATE TABLE IF NOT EXISTS llx_takepos_held_sale (
    rowid         INT(11)      NOT NULL AUTO_INCREMENT,
    entity        INT(11)      NOT NULL DEFAULT 1,
    fk_invoice    INT(11)      NOT NULL,
    fk_terminal   INT(11)      NOT NULL DEFAULT 0,
    fk_user       INT(11)      NOT NULL DEFAULT 0,
    fk_shift      INT(11)      NOT NULL DEFAULT 0,
    hold_label    VARCHAR(128) DEFAULT '',
    date_hold     DATETIME     NOT NULL,
    date_update   DATETIME     NOT NULL,
    status        SMALLINT     NOT NULL DEFAULT 1 COMMENT '1=held, 0=resumed/cancelled',
    PRIMARY KEY (rowid),
    INDEX idx_takepos_held_invoice (fk_invoice),
    INDEX idx_takepos_held_terminal (entity, fk_terminal, status),
    INDEX idx_takepos_held_shift (entity, fk_shift, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
