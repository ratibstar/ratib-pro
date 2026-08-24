-- Support ticket conversation replies (staff + tenant).
CREATE TABLE IF NOT EXISTS rateb_support_ticket_replies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    company_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    is_staff TINYINT(1) NOT NULL DEFAULT 0,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_str_ticket (ticket_id),
    INDEX idx_str_company (company_id),
    INDEX idx_str_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
