-- =========================================================
-- DairyBox – Dairy Cooperative POS & Sales Module
-- Migration: Run this against dairybox_db
-- =========================================================

USE dairybox_db;

-- ---------------------------------------------------------
-- Products (items sold by the cooperative)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS coop_products (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    product_code    VARCHAR(40)  NOT NULL UNIQUE,
    name            VARCHAR(120) NOT NULL,
    category        ENUM('Milk','Cheese','Butter','Yogurt','Ice Cream','By-Product','Other') DEFAULT 'Milk',
    description     TEXT,
    unit            VARCHAR(30)  NOT NULL DEFAULT 'liter',
    selling_price   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    cost_price      DECIMAL(10,2) DEFAULT 0.00,
    stock_qty       DECIMAL(10,2) DEFAULT 0.00,
    reorder_level   DECIMAL(10,2) DEFAULT 10.00,
    is_active       TINYINT(1)   DEFAULT 1,
    created_by      INT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------
-- Cooperative Inventory (stock movements)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS coop_inventory (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    product_id      INT NOT NULL,
    movement_type   ENUM('Stock In','Stock Out','Adjustment','Sale','Return') DEFAULT 'Stock In',
    quantity        DECIMAL(10,2) NOT NULL,
    reference_id    INT DEFAULT NULL,    -- links to sale_id if type=Sale
    notes           TEXT,
    recorded_by     INT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id)  REFERENCES coop_products(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id)         ON DELETE SET NULL
);

-- ---------------------------------------------------------
-- Sales Transactions (POS header)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS coop_sales (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number  VARCHAR(40)  NOT NULL UNIQUE,
    sale_date       DATE         NOT NULL,
    customer_name   VARCHAR(120) DEFAULT 'Walk-in Customer',
    customer_phone  VARCHAR(30),
    subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(12,2) DEFAULT 0.00,
    tax_amount      DECIMAL(12,2) DEFAULT 0.00,
    total_amount    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount_tendered DECIMAL(12,2) DEFAULT 0.00,
    change_amount   DECIMAL(12,2) DEFAULT 0.00,
    payment_method  ENUM('Cash','GCash','Maya','Bank Transfer','Credit') DEFAULT 'Cash',
    status          ENUM('Completed','Voided','Refunded') DEFAULT 'Completed',
    notes           TEXT,
    created_by      INT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------
-- Sales Items (POS line items)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS coop_sale_items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    sale_id         INT NOT NULL,
    product_id      INT NOT NULL,
    quantity        DECIMAL(10,2) NOT NULL,
    unit_price      DECIMAL(10,2) NOT NULL,
    discount        DECIMAL(10,2) DEFAULT 0.00,
    line_total      DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (sale_id)    REFERENCES coop_sales(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES coop_products(id) ON DELETE RESTRICT
);

-- ---------------------------------------------------------
-- Sample products
-- ---------------------------------------------------------
INSERT INTO coop_products (product_code, name, category, unit, selling_price, cost_price, stock_qty, reorder_level, created_by) VALUES
('PRD-001', 'Fresh Carabao Milk',      'Milk',       'liter',    65.00,  40.00, 200.00, 50.00, 1),
('PRD-002', 'Pasteurized Milk 1L',     'Milk',       'bottle',   80.00,  52.00, 150.00, 30.00, 1),
('PRD-003', 'Kesong Puti (250g)',       'Cheese',     'pack',    120.00,  75.00,  60.00, 15.00, 1),
('PRD-004', 'Carabao Butter (200g)',    'Butter',     'pack',    150.00,  95.00,  40.00, 10.00, 1),
('PRD-005', 'Carabao Yogurt (500ml)',   'Yogurt',     'bottle',  110.00,  70.00,  50.00, 12.00, 1),
('PRD-006', 'Milk Ice Cream (1L)',      'Ice Cream',  'tub',     180.00, 110.00,  30.00,  8.00, 1),
('PRD-007', 'Pasteurized Milk 500ml',  'Milk',       'bottle',   45.00,  28.00, 100.00, 25.00, 1);

-- Change category from ENUM to VARCHAR to allow free-text input
ALTER TABLE coop_products MODIFY COLUMN category VARCHAR(80) DEFAULT 'Milk';

-- Backfill null or empty category values
UPDATE coop_products SET category = 'Uncategorized' WHERE category IS NULL OR category = '';
