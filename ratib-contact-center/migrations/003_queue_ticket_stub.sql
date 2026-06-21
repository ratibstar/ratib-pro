-- Minimal queue + ticket tables for IVR route_call actions
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rcc_queues (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    code VARCHAR(40) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    UNIQUE KEY uk_rcc_queue (tenant_id, code),
    INDEX idx_rcc_queue_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    ticket_no VARCHAR(40) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    description TEXT NULL,
    call_id BIGINT UNSIGNED NULL,
    channel VARCHAR(40) NOT NULL DEFAULT 'phone',
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    status ENUM('open','pending','in_progress','resolved','closed','cancelled') NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_rcc_ticket_no (tenant_id, ticket_no),
    INDEX idx_rcc_ticket_tenant (tenant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
