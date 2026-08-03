-- =========================================================
-- DairyBox Production & Herd Health System
-- Database Schema
-- =========================================================
-- NOTE: Run this against your existing database.
-- CREATE DATABASE and USE are intentionally omitted
-- so this works on Railway, Render, Aiven, etc.
-- ---------------------------------------------------------

-- ---------------------------------------------------------
-- Users (role-based)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(60)  NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    full_name   VARCHAR(120) NOT NULL,
    role        ENUM('farm_manager','farm_caretaker','dairy_cooperative','veterinarian') NOT NULL,
    email       VARCHAR(120),
    phone       VARCHAR(30),
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------
-- Buffalo / Livestock Records
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS buffaloes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    tag_number      VARCHAR(40)  NOT NULL UNIQUE,
    qr_code         VARCHAR(100) UNIQUE,
    name            VARCHAR(80),
    breed           VARCHAR(80),
    sex             ENUM('Female','Male') DEFAULT 'Female',
    date_of_birth   DATE,
    weight_kg       DECIMAL(6,2),
    color           VARCHAR(50),
    acquisition_date DATE,
    acquisition_type ENUM('Born on Farm','Purchased','Donated') DEFAULT 'Born on Farm',
    status          ENUM('Active','Sold','Dead','Transferred') DEFAULT 'Active',
    health_status   ENUM('Healthy','Sick','Under Treatment','Recovered') DEFAULT 'Healthy',
    notes           TEXT,
    photo           VARCHAR(200),
    created_by      INT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------
-- Milk Production Records
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS milk_production (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    buffalo_id      INT NOT NULL,
    record_date     DATE NOT NULL,
    session         ENUM('Morning','Afternoon','Evening') DEFAULT 'Morning',
    quantity_liters DECIMAL(7,2) NOT NULL,
    quality_notes   TEXT,
    recorded_by     INT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (buffalo_id)  REFERENCES buffaloes(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------
-- Health Records
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS health_records (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    buffalo_id      INT NOT NULL,
    record_date     DATE NOT NULL,
    condition_type  ENUM('Illness','Injury','Routine Check','Disease Alert','Other') DEFAULT 'Routine Check',
    diagnosis       VARCHAR(200),
    symptoms        TEXT,
    treatment       TEXT,
    medicine_used   VARCHAR(200),
    dosage          VARCHAR(100),
    vet_name        VARCHAR(120),
    followup_date   DATE,
    status          ENUM('Active','Resolved','Monitoring') DEFAULT 'Active',
    notes           TEXT,
    recorded_by     INT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (buffalo_id)  REFERENCES buffaloes(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------
-- Vaccination Records
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS vaccinations (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    buffalo_id      INT NOT NULL,
    vaccine_name    VARCHAR(120) NOT NULL,
    vaccine_type    VARCHAR(80),
    administered_date DATE NOT NULL,
    next_due_date   DATE,
    administered_by VARCHAR(120),
    batch_number    VARCHAR(60),
    dose            VARCHAR(60),
    notes           TEXT,
    status          ENUM('Done','Scheduled','Overdue') DEFAULT 'Done',
    recorded_by     INT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (buffalo_id)  REFERENCES buffaloes(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------
-- Breeding Records
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS breeding_records (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    buffalo_id      INT NOT NULL,
    breeding_date   DATE NOT NULL,
    method          ENUM('Natural','Artificial Insemination') DEFAULT 'Natural',
    sire_id         INT,
    sire_name       VARCHAR(100),
    expected_calving DATE,
    pregnancy_status ENUM('Not Confirmed','Confirmed','Failed','Delivered') DEFAULT 'Not Confirmed',
    pregnancy_check_date DATE,
    notes           TEXT,
    recorded_by     INT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (buffalo_id)  REFERENCES buffaloes(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------
-- Calving Records
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS calving_records (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    mother_id       INT NOT NULL,
    breeding_id     INT,
    calving_date    DATE NOT NULL,
    calf_tag        VARCHAR(40),
    calf_sex        ENUM('Female','Male','Unknown') DEFAULT 'Unknown',
    calf_weight_kg  DECIMAL(5,2),
    delivery_type   ENUM('Normal','Assisted','Cesarean') DEFAULT 'Normal',
    calf_health     ENUM('Healthy','Weak','Stillborn') DEFAULT 'Healthy',
    notes           TEXT,
    recorded_by     INT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mother_id)  REFERENCES buffaloes(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------
-- Medicine / Supply Inventory
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS inventory (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    item_name       VARCHAR(120) NOT NULL,
    category        ENUM('Medicine','Vaccine','Supply','Equipment','Feed','Other') DEFAULT 'Medicine',
    unit            VARCHAR(30),
    quantity        DECIMAL(10,2) DEFAULT 0,
    reorder_level   DECIMAL(10,2) DEFAULT 10,
    expiry_date     DATE,
    supplier        VARCHAR(120),
    unit_cost       DECIMAL(10,2),
    notes           TEXT,
    updated_by      INT,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------
-- Notifications / Alerts
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    type            ENUM('vaccination','breeding','calving','health','production','system') NOT NULL,
    title           VARCHAR(200) NOT NULL,
    message         TEXT NOT NULL,
    buffalo_id      INT,
    target_role     VARCHAR(50),
    is_read         TINYINT(1) DEFAULT 0,
    priority        ENUM('low','medium','high','urgent') DEFAULT 'medium',
    due_date        DATE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (buffalo_id) REFERENCES buffaloes(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- Activity / Audit Log
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT,
    action      VARCHAR(100),
    module      VARCHAR(60),
    details     TEXT,
    ip_address  VARCHAR(45),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- =========================================================
-- Default Users
-- Password for ALL accounts: password
-- Hash: password_hash('password', PASSWORD_DEFAULT)
-- =========================================================
INSERT INTO users (username, password, full_name, role, email) VALUES
('manager1',    '$2y$10$H3VWZlbDr5KGzSi.REF6v.EnIqrO7KgbrMmKX8TEBHZRgNW.RJqvG', 'Juan dela Cruz',     'farm_manager',      'manager@dairybox.ph'),
('caretaker1',  '$2y$10$H3VWZlbDr5KGzSi.REF6v.EnIqrO7KgbrMmKX8TEBHZRgNW.RJqvG', 'Maria Santos',       'farm_caretaker',    'caretaker@dairybox.ph'),
('coop1',       '$2y$10$H3VWZlbDr5KGzSi.REF6v.EnIqrO7KgbrMmKX8TEBHZRgNW.RJqvG', 'Surallah Dairy Coop','dairy_cooperative', 'coop@dairybox.ph'),
('vet1',        '$2y$10$H3VWZlbDr5KGzSi.REF6v.EnIqrO7KgbrMmKX8TEBHZRgNW.RJqvG', 'Dr. Jose Reyes',     'veterinarian',      'vet@dairybox.ph');

-- Sample Buffaloes
INSERT INTO buffaloes (tag_number, qr_code, name, breed, sex, date_of_birth, weight_kg, color, health_status, status, created_by) VALUES
('BUF-001', 'QR-BUF-001', 'Bella',  'Murrah',     'Female', '2019-03-15', 480.00, 'Black',    'Healthy',       'Active', 1),
('BUF-002', 'QR-BUF-002', 'Rosa',   'Murrah',     'Female', '2020-06-20', 510.00, 'Black',    'Healthy',       'Active', 1),
('BUF-003', 'QR-BUF-003', 'Luna',   'Nili-Ravi',  'Female', '2018-09-10', 550.00, 'Gray',     'Healthy',       'Active', 1),
('BUF-004', 'QR-BUF-004', 'Star',   'Murrah',     'Female', '2021-01-05', 420.00, 'Black',    'Under Treatment','Active', 1),
('BUF-005', 'QR-BUF-005', 'Daisy',  'Surti',      'Female', '2020-11-18', 395.00, 'Brown',    'Healthy',       'Active', 1),
('BUF-006', 'QR-BUF-006', 'Lola',   'Nili-Ravi',  'Female', '2017-07-22', 580.00, 'Gray',     'Healthy',       'Active', 1),
('BUF-007', 'QR-BUF-007', 'Rex',    'Murrah',     'Male',   '2019-04-12', 620.00, 'Black',    'Healthy',       'Active', 1),
('BUF-008', 'QR-BUF-008', 'Coco',   'Murrah',     'Female', '2022-02-28', 360.00, 'Black',    'Healthy',       'Active', 1);

-- Sample Milk Production
INSERT INTO milk_production (buffalo_id, record_date, session, quantity_liters, recorded_by) VALUES
(1,'2026-07-20','Morning',6.50,2),(1,'2026-07-20','Evening',5.80,2),
(2,'2026-07-20','Morning',7.20,2),(2,'2026-07-20','Evening',6.50,2),
(3,'2026-07-20','Morning',8.10,2),(3,'2026-07-20','Evening',7.40,2),
(5,'2026-07-20','Morning',5.90,2),(5,'2026-07-20','Evening',5.20,2),
(6,'2026-07-20','Morning',9.00,2),(6,'2026-07-20','Evening',8.30,2),
(1,'2026-07-21','Morning',6.70,2),(1,'2026-07-21','Evening',5.90,2),
(2,'2026-07-21','Morning',7.40,2),(2,'2026-07-21','Evening',6.70,2),
(3,'2026-07-21','Morning',8.30,2),(3,'2026-07-21','Evening',7.60,2),
(5,'2026-07-21','Morning',6.10,2),(5,'2026-07-21','Evening',5.40,2),
(6,'2026-07-21','Morning',9.20,2),(6,'2026-07-21','Evening',8.50,2),
(1,'2026-07-22','Morning',6.60,2),(1,'2026-07-22','Evening',5.85,2),
(2,'2026-07-22','Morning',7.30,2),(2,'2026-07-22','Evening',6.60,2),
(3,'2026-07-22','Morning',8.20,2),(3,'2026-07-22','Evening',7.50,2),
(5,'2026-07-22','Morning',6.00,2),(5,'2026-07-22','Evening',5.30,2),
(6,'2026-07-22','Morning',9.10,2),(6,'2026-07-22','Evening',8.40,2);

-- Sample Vaccinations
INSERT INTO vaccinations (buffalo_id, vaccine_name, vaccine_type, administered_date, next_due_date, administered_by, status, recorded_by) VALUES
(1,'FMD Vaccine','Foot and Mouth Disease','2026-01-15','2026-07-15','Dr. Reyes','Overdue',1),
(2,'FMD Vaccine','Foot and Mouth Disease','2026-02-10','2026-08-10','Dr. Reyes','Scheduled',1),
(3,'Hemorrhagic Septicemia','Bacterial','2026-03-05','2026-09-05','Dr. Reyes','Scheduled',1),
(4,'Brucellosis','Bacterial','2026-05-20','2027-05-20','Dr. Reyes','Done',1),
(5,'FMD Vaccine','Foot and Mouth Disease','2026-04-12','2026-10-12','Dr. Reyes','Scheduled',1);

-- Sample Breeding Records
INSERT INTO breeding_records (buffalo_id, breeding_date, method, sire_name, expected_calving, pregnancy_status, recorded_by) VALUES
(1,'2026-03-10','Natural','Rex','2026-12-10','Confirmed',1),
(2,'2026-04-15','Artificial Insemination',NULL,'2027-01-15','Confirmed',1),
(5,'2026-05-20','Natural','Rex','2027-02-20','Not Confirmed',1);

-- Sample Notifications
INSERT INTO notifications (type, title, message, buffalo_id, target_role, priority, due_date) VALUES
('vaccination','FMD Vaccination Due – BUF-001','Bella (BUF-001) is overdue for FMD Vaccine. Schedule immediately.',1,'farm_manager','urgent','2026-07-15'),
('vaccination','Upcoming Vaccination – BUF-002','Rosa (BUF-002) FMD Vaccine due on Aug 10.',2,'farm_manager','high','2026-08-10'),
('calving','Expected Calving – Bella','Bella (BUF-001) expected calving on Dec 10, 2026.',1,'veterinarian','medium','2026-12-10'),
('health','Health Alert – BUF-004','Star (BUF-004) is currently under treatment. Follow up needed.',4,'veterinarian','high','2026-07-28'),
('breeding','Pregnancy Check Due – BUF-005','Daisy (BUF-005) breeding on May 20 – pregnancy check due.',5,'farm_manager','medium','2026-07-20');

-- Sample Inventory
INSERT INTO inventory (item_name, category, unit, quantity, reorder_level, expiry_date, unit_cost, updated_by) VALUES
('FMD Vaccine',             'Vaccine',   'dose',   50,  20, '2027-01-01', 85.00,  1),
('Hemorrhagic Sep. Vaccine','Vaccine',   'dose',   30,  15, '2026-12-01', 90.00,  1),
('Ivermectin',              'Medicine',  'bottle', 15,  10, '2027-06-01', 250.00, 1),
('Penicillin',              'Medicine',  'vial',   25,  10, '2027-03-01', 120.00, 1),
('Milk Recording Sheets',   'Supply',    'pack',    8,   5, NULL,          45.00,  1),
('Syringes (5ml)',          'Supply',    'box',    20,  10, NULL,          80.00,  1),
('Ear Tags',                'Supply',    'piece', 100,  50, NULL,           5.00,  1);

-- =========================================================
-- Dairy Cooperative POS & Sales Module (added)
-- =========================================================

-- Products (items sold by the cooperative)
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

-- Cooperative Inventory (stock movements)
CREATE TABLE IF NOT EXISTS coop_inventory (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    product_id      INT NOT NULL,
    movement_type   ENUM('Stock In','Stock Out','Adjustment','Sale','Return') DEFAULT 'Stock In',
    quantity        DECIMAL(10,2) NOT NULL,
    reference_id    INT DEFAULT NULL,
    notes           TEXT,
    recorded_by     INT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id)  REFERENCES coop_products(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id)         ON DELETE SET NULL
);

-- Sales Transactions (POS header)
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

-- Sales Items (POS line items)
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

-- Sample products
INSERT INTO coop_products (product_code, name, category, unit, selling_price, cost_price, stock_qty, reorder_level, created_by) VALUES
('PRD-001', 'Fresh Carabao Milk',      'Milk',       'liter',    65.00,  40.00, 200.00, 50.00, 1),
('PRD-002', 'Pasteurized Milk 1L',     'Milk',       'bottle',   80.00,  52.00, 150.00, 30.00, 1),
('PRD-003', 'Kesong Puti (250g)',       'Cheese',     'pack',    120.00,  75.00,  60.00, 15.00, 1),
('PRD-004', 'Carabao Butter (200g)',    'Butter',     'pack',    150.00,  95.00,  40.00, 10.00, 1),
('PRD-005', 'Carabao Yogurt (500ml)',   'Yogurt',     'bottle',  110.00,  70.00,  50.00, 12.00, 1),
('PRD-006', 'Milk Ice Cream (1L)',      'Ice Cream',  'tub',     180.00, 110.00,  30.00,  8.00, 1),
('PRD-007', 'Pasteurized Milk 500ml',  'Milk',       'bottle',   45.00,  28.00, 100.00, 25.00, 1);
