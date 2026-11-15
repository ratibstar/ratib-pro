<?php
declare(strict_types=1);

function ensure_company_hr_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS hr_departments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_user_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(120) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY company_user (company_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS hr_employees (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_user_id BIGINT UNSIGNED NOT NULL,
        department_id BIGINT UNSIGNED NULL,
        employee_code VARCHAR(32) NULL,
        full_name VARCHAR(160) NOT NULL,
        email VARCHAR(190) NULL,
        phone VARCHAR(40) NULL,
        job_title VARCHAR(120) NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'active',
        hired_at DATE NULL,
        terminated_at DATE NULL,
        salary_halalas INT UNSIGNED NULL,
        notes TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY company_status (company_user_id, status),
        KEY department_id (department_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS hr_attendance (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_user_id BIGINT UNSIGNED NOT NULL,
        employee_id BIGINT UNSIGNED NOT NULL,
        work_date DATE NOT NULL,
        hours_worked DECIMAL(5,2) NOT NULL DEFAULT 0,
        note VARCHAR(255) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_day (company_user_id, employee_id, work_date),
        KEY company_user (company_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS hr_leave_requests (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_user_id BIGINT UNSIGNED NOT NULL,
        employee_id BIGINT UNSIGNED NOT NULL,
        leave_type VARCHAR(32) NOT NULL DEFAULT 'annual',
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'pending',
        reason VARCHAR(500) NULL,
        decided_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY company_status (company_user_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS hr_documents (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_user_id BIGINT UNSIGNED NOT NULL,
        employee_id BIGINT UNSIGNED NOT NULL,
        title VARCHAR(160) NOT NULL,
        file_label VARCHAR(255) NOT NULL,
        storage_path VARCHAR(512) NULL,
        storage_note VARCHAR(500) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY company_employee (company_user_id, employee_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $col = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $col->execute(['hr_documents', 'storage_path']);
    if ((int) $col->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE hr_documents ADD COLUMN storage_path VARCHAR(512) NULL AFTER file_label');
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS hr_payroll_records (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_user_id BIGINT UNSIGNED NOT NULL,
        employee_id BIGINT UNSIGNED NOT NULL,
        period_month CHAR(7) NOT NULL,
        gross_halalas INT UNSIGNED NOT NULL DEFAULT 0,
        deductions_halalas INT UNSIGNED NOT NULL DEFAULT 0,
        net_halalas INT UNSIGNED NOT NULL DEFAULT 0,
        status VARCHAR(16) NOT NULL DEFAULT 'draft',
        paid_at DATETIME NULL,
        notes VARCHAR(500) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_period (company_user_id, employee_id, period_month)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
