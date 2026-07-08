-- Multi-barcode support for TakePOS search
CREATE TABLE IF NOT EXISTS llx_takepos_product_barcode (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  fk_product INT NOT NULL,
  barcode VARCHAR(190) NOT NULL,
  entity INT NOT NULL DEFAULT 1,
  date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_takepos_product_barcode_entity (entity, barcode),
  KEY idx_takepos_product_barcode_product (fk_product),
  CONSTRAINT fk_takepos_product_barcode_product
    FOREIGN KEY (fk_product) REFERENCES llx_product(rowid)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
