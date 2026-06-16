-- RATEB ERP — Human Resources tables (idempotent)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_hr_departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(40) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hr_dept_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_employees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    employee_code VARCHAR(40) NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    national_id VARCHAR(40) NULL,
    department_id INT UNSIGNED NULL,
    job_title VARCHAR(120) NULL,
    hire_date DATE NULL,
    salary_base DECIMAL(12,2) NOT NULL DEFAULT 0,
    user_id INT UNSIGNED NULL,
    status ENUM('active','inactive','terminated') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_employee_code (company_id, employee_code),
    INDEX idx_employee_company (company_id),
    INDEX idx_employee_dept (department_id),
    INDEX idx_employee_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_attendance_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    check_in TIME NULL,
    check_out TIME NULL,
    status ENUM('present','absent','late','leave','holiday') NOT NULL DEFAULT 'present',
    notes VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_attendance_day (company_id, employee_id, attendance_date),
    INDEX idx_attendance_company (company_id),
    INDEX idx_attendance_date (attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_leave_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    paid TINYINT(1) NOT NULL DEFAULT 1,
    days_per_year DECIMAL(5,1) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_leave_type_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_leave_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    leave_type_id INT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days DECIMAL(5,1) NOT NULL DEFAULT 1,
    reason TEXT NULL,
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_leave_req_company (company_id),
    INDEX idx_leave_req_employee (employee_id),
    INDEX idx_leave_req_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_payroll_periods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    period_year SMALLINT UNSIGNED NOT NULL,
    period_month TINYINT UNSIGNED NOT NULL,
    status ENUM('draft','approved','posted') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_payroll_period (company_id, period_year, period_month),
    INDEX idx_payroll_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_payroll_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    period_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    allowances DECIMAL(12,2) NOT NULL DEFAULT 0,
    deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes VARCHAR(500) NULL,
    UNIQUE KEY uk_payroll_line (period_id, employee_id),
    INDEX idx_payroll_line_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
