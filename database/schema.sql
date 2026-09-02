-- IOMS (Inventory & Order Management System) MySQL 8 Schema
-- Charset utf8mb4, InnoDB, explicit FKs and indexes on filtered columns.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150)        NOT NULL,
    email           VARCHAR(190)        NOT NULL,
    password_hash   VARCHAR(255)        NOT NULL,
    role            ENUM('Admin','Sales','WarehouseStaff') NOT NULL DEFAULT 'Sales',
    is_active       TINYINT(1)          NOT NULL DEFAULT 1,
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role),
    KEY idx_users_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- warehouses
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS warehouses (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    location    VARCHAR(255) NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    KEY idx_warehouses_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- categories
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    description TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- products
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku             VARCHAR(64)  NOT NULL,
    name            VARCHAR(190) NOT NULL,
    category_id     BIGINT UNSIGNED NULL,
    unit            VARCHAR(32)  NOT NULL DEFAULT 'pcs',
    purchase_price  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    selling_price   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    reorder_point   INT NOT NULL DEFAULT 0,
    image_path      VARCHAR(255) NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_products_sku (sku),
    KEY idx_products_category_id (category_id),
    KEY idx_products_is_active (is_active),
    CONSTRAINT chk_products_reorder_point CHECK (reorder_point >= 0),
    CONSTRAINT fk_products_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- product_stocks
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS product_stocks (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id    BIGINT UNSIGNED NOT NULL,
    warehouse_id  BIGINT UNSIGNED NOT NULL,
    quantity      INT NOT NULL DEFAULT 0,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_product_warehouse (product_id, warehouse_id),
    KEY idx_stocks_warehouse_id (warehouse_id),
    KEY idx_stocks_quantity (quantity),
    CONSTRAINT chk_stocks_quantity CHECK (quantity >= 0),
    CONSTRAINT fk_stocks_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_stocks_warehouse
        FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- suppliers
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS suppliers (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(190) NOT NULL,
    contact     VARCHAR(190) NULL,
    address     VARCHAR(255) NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    KEY idx_suppliers_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- customers
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(190) NOT NULL,
    contact     VARCHAR(190) NULL,
    address     VARCHAR(255) NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    KEY idx_customers_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- purchase_orders
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS purchase_orders (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_number      VARCHAR(64) NOT NULL,
    supplier_id    BIGINT UNSIGNED NOT NULL,
    warehouse_id   BIGINT UNSIGNED NOT NULL,
    status         ENUM('Draft','Ordered','PartiallyReceived','Received','Cancelled') NOT NULL DEFAULT 'Draft',
    order_date     DATE NOT NULL,
    created_by     BIGINT UNSIGNED NOT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_po_number (po_number),
    KEY idx_po_status (status),
    KEY idx_po_supplier_id (supplier_id),
    KEY idx_po_warehouse_id (warehouse_id),
    KEY idx_po_created_by (created_by),
    CONSTRAINT fk_po_supplier
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_po_warehouse
        FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_po_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- purchase_order_items
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS purchase_order_items (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id   BIGINT UNSIGNED NOT NULL,
    product_id          BIGINT UNSIGNED NOT NULL,
    qty_ordered         INT NOT NULL,
    qty_received        INT NOT NULL DEFAULT 0,
    purchase_price      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    KEY idx_poi_po_id (purchase_order_id),
    KEY idx_poi_product_id (product_id),
    CONSTRAINT chk_poi_qty_ordered CHECK (qty_ordered >= 0),
    CONSTRAINT chk_poi_qty_received CHECK (qty_received >= 0),
    CONSTRAINT fk_poi_po
        FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_poi_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- sales_orders
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sales_orders (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    so_number      VARCHAR(64) NOT NULL,
    customer_id    BIGINT UNSIGNED NOT NULL,
    warehouse_id   BIGINT UNSIGNED NOT NULL,
    created_by     BIGINT UNSIGNED NOT NULL,
    approved_by    BIGINT UNSIGNED NULL,
    status         ENUM('Draft','PendingApproval','Approved','Fulfilled','Cancelled') NOT NULL DEFAULT 'Draft',
    order_date     DATE NOT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_so_number (so_number),
    KEY idx_so_status (status),
    KEY idx_so_customer_id (customer_id),
    KEY idx_so_warehouse_id (warehouse_id),
    KEY idx_so_created_by (created_by),
    CONSTRAINT fk_so_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_so_warehouse
        FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_so_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_so_approved_by
        FOREIGN KEY (approved_by) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- sales_order_items
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sales_order_items (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sales_order_id   BIGINT UNSIGNED NOT NULL,
    product_id       BIGINT UNSIGNED NOT NULL,
    qty              INT NOT NULL,
    selling_price    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    KEY idx_soi_so_id (sales_order_id),
    KEY idx_soi_product_id (product_id),
    CONSTRAINT chk_soi_qty CHECK (qty >= 0),
    CONSTRAINT fk_soi_so
        FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_soi_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- stock_ledger
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stock_ledger (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id      BIGINT UNSIGNED NOT NULL,
    warehouse_id    BIGINT UNSIGNED NOT NULL,
    movement_type   ENUM('Receipt','Issue','Adjustment') NOT NULL,
    quantity        INT NOT NULL,
    reference_type  VARCHAR(64) NULL,
    reference_id    BIGINT UNSIGNED NULL,
    performed_by    BIGINT UNSIGNED NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ledger_product_id (product_id),
    KEY idx_ledger_warehouse_id (warehouse_id),
    KEY idx_ledger_movement_type (movement_type),
    KEY idx_ledger_reference (reference_type, reference_id),
    KEY idx_ledger_created_at (created_at),
    CONSTRAINT fk_ledger_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ledger_warehouse
        FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ledger_performed_by
        FOREIGN KEY (performed_by) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
