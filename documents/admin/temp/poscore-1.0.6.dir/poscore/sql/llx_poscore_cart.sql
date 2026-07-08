CREATE TABLE llx_poscore_cart (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER NOT NULL DEFAULT 1,
    fk_user INTEGER NOT NULL,
    fk_product INTEGER NOT NULL,
    qty DOUBLE NOT NULL DEFAULT 1,
    price_ht DOUBLE NOT NULL DEFAULT 0,
    remise_percent DOUBLE NOT NULL DEFAULT 0,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_poscore_cart (entity, fk_user, fk_product),
    KEY idx_poscore_cart_user (entity, fk_user),
    KEY idx_poscore_cart_product (entity, fk_product)
) ENGINE=innodb;
