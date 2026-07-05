-- RATEB ERP — POS core tables + permissions (Phase 2)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_pos_terminals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NULL,
    code VARCHAR(30) NOT NULL,
    name VARCHAR(150) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    device_meta JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pos_terminal_code (company_id, code),
    INDEX idx_pos_terminal_branch (company_id, branch_id),
    INDEX idx_pos_terminal_wh (warehouse_id),
    CONSTRAINT fk_pos_terminal_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pos_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NOT NULL,
    terminal_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    shift_id INT UNSIGNED NULL,
    status ENUM('active','ended') NOT NULL DEFAULT 'active',
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    INDEX idx_pos_session_terminal (terminal_id, status),
    INDEX idx_pos_session_branch (company_id, branch_id),
    CONSTRAINT fk_pos_session_terminal FOREIGN KEY (terminal_id) REFERENCES rateb_pos_terminals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pos_shifts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NOT NULL,
    terminal_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    shift_no VARCHAR(30) NOT NULL,
    status ENUM('open','closed') NOT NULL DEFAULT 'open',
    opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at DATETIME NULL,
    opening_float DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    closing_float DECIMAL(14,2) NULL,
    expected_cash DECIMAL(14,2) NULL,
    variance DECIMAL(14,2) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pos_shift_no (company_id, shift_no),
    INDEX idx_pos_shift_branch (company_id, branch_id, status),
    INDEX idx_pos_shift_terminal (terminal_id, status),
    CONSTRAINT fk_pos_shift_terminal FOREIGN KEY (terminal_id) REFERENCES rateb_pos_terminals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pos_cash_drawers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NOT NULL,
    terminal_id INT UNSIGNED NOT NULL,
    shift_id INT UNSIGNED NULL,
    status ENUM('closed','open') NOT NULL DEFAULT 'closed',
    expected_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    counted_balance DECIMAL(14,2) NULL,
    variance DECIMAL(14,2) NULL,
    opened_at DATETIME NULL,
    closed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pos_drawer_branch (company_id, branch_id, status),
    INDEX idx_pos_drawer_terminal (terminal_id),
    INDEX idx_pos_drawer_shift (shift_id),
    CONSTRAINT fk_pos_drawer_terminal FOREIGN KEY (terminal_id) REFERENCES rateb_pos_terminals(id) ON DELETE CASCADE,
    CONSTRAINT fk_pos_drawer_shift FOREIGN KEY (shift_id) REFERENCES rateb_pos_shifts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pos_cash_drawer_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NOT NULL,
    drawer_id INT UNSIGNED NOT NULL,
    shift_id INT UNSIGNED NULL,
    event_type ENUM('open','close','pay_in','pay_out','no_sale') NOT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pos_drawer_event_drawer (drawer_id, created_at),
    CONSTRAINT fk_pos_drawer_event_drawer FOREIGN KEY (drawer_id) REFERENCES rateb_pos_cash_drawers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pos_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    settings_json JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pos_settings_scope (company_id, branch_id),
    CONSTRAINT fk_pos_settings_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('View POS', 'View POS', 'pos.view', 'pos', 'View POS dashboard and lists', 'View POS dashboard and lists'),
('POS Register', 'POS Register', 'pos.register', 'pos', 'Access POS register screen', 'Access POS register screen'),
('Manage POS', 'Manage POS', 'pos.manage', 'pos', 'Manage POS operations', 'Manage POS operations'),
('Manage POS Terminals', 'Manage POS Terminals', 'pos.terminal.manage', 'pos', 'Create and edit POS terminals', 'Create and edit POS terminals'),
('Open POS Shift', 'Open POS Shift', 'pos.shift.open', 'pos', 'Open a cash shift', 'Open a cash shift'),
('Close POS Shift', 'Close POS Shift', 'pos.shift.close', 'pos', 'Close a cash shift', 'Close a cash shift'),
('Manage Cash Drawer', 'Manage Cash Drawer', 'pos.cash_drawer.manage', 'pos', 'Cash drawer events and reconciliation', 'Cash drawer events and reconciliation'),
('View POS Orders', 'View POS Orders', 'pos.orders.view', 'pos', 'View POS order history', 'View POS order history'),
('Manage POS Settings', 'Manage POS Settings', 'pos.settings.manage', 'pos', 'Configure POS settings', 'Configure POS settings'),
('Manage POS Sync', 'Manage POS Sync', 'pos.sync.manage', 'pos', 'Offline sync administration', 'Offline sync administration'),
('View POS Reports', 'View POS Reports', 'pos.reports.view', 'pos', 'View X reports and shift summaries', 'View X reports and shift summaries'),
('POS Z Report', 'POS Z Report', 'pos.reports.z', 'pos', 'Run Z report and close day', 'Run Z report and close day'),
('Manage POS Discounts', 'Manage POS Discounts', 'pos.discount.manage', 'pos', 'Apply POS discounts', 'Apply POS discounts'),
('Manage POS Returns', 'Manage POS Returns', 'pos.returns.manage', 'pos', 'Process returns and refunds', 'Process returns and refunds'),
('Manage POS Loyalty', 'Manage POS Loyalty', 'pos.loyalty.manage', 'pos', 'Loyalty program administration', 'Loyalty program administration'),
('Manage Gift Cards', 'Manage Gift Cards', 'pos.gift_card.manage', 'pos', 'Issue and redeem gift cards', 'Issue and redeem gift cards'),
('Manage POS Coupons', 'Manage POS Coupons', 'pos.coupon.manage', 'pos', 'Coupon rules administration', 'Coupon rules administration')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
INNER JOIN rateb_permissions p ON p.slug LIKE 'pos.%'
WHERE r.slug IN ('super-admin', 'company-full-access');
