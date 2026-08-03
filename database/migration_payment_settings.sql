-- =========================================================
-- DairyBox – Payment Settings Table
-- Run in TablePlus against Railway database
-- =========================================================

CREATE TABLE IF NOT EXISTS coop_payment_settings (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    method          VARCHAR(40) NOT NULL UNIQUE,
    display_name    VARCHAR(80) NOT NULL,
    account_name    VARCHAR(120),
    account_number  VARCHAR(80),
    instructions    TEXT,
    qr_image        VARCHAR(255),
    is_active       TINYINT(1) DEFAULT 1,
    updated_by      INT,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Default payment methods
INSERT IGNORE INTO coop_payment_settings (method, display_name, account_name, account_number, instructions, is_active) VALUES
('GCash',         'GCash',         NULL, NULL, 'Send payment to our GCash number and show screenshot.', 1),
('Maya',          'Maya (PayMaya)', NULL, NULL, 'Send payment to our Maya number and show screenshot.', 1),
('Bank Transfer', 'Bank Transfer',  NULL, NULL, 'Transfer to our bank account and show proof.', 1),
('Credit',        'Store Credit',   NULL, NULL, 'Payment will be settled later.', 1);
