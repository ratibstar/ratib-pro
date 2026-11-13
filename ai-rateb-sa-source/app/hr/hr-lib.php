<?php
declare(strict_types=1);

require_once __DIR__ . '/../../migrations/company_hr_schema.php';

/** Company tenant = registered customer account (users.id). */
function hr_company_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function hr_require_company(PDO $pdo): int
{
    require_login();
    $id = hr_company_user_id();
    if ($id < 1) {
        header('Location: /login?next=/hr/');
        exit;
    }
    ensure_company_hr_schema($pdo);
    return $id;
}

function hr_verify_employee_belongs(PDO $pdo, int $companyUserId, int $employeeId): bool
{
    if ($employeeId < 1) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT id FROM hr_employees WHERE id = ? AND company_user_id = ? LIMIT 1');
    $stmt->execute([$employeeId, $companyUserId]);
    return (bool) $stmt->fetchColumn();
}

function hr_verify_department_belongs(PDO $pdo, int $companyUserId, int $departmentId): bool
{
    if ($departmentId < 1) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT id FROM hr_departments WHERE id = ? AND company_user_id = ? LIMIT 1');
    $stmt->execute([$departmentId, $companyUserId]);
    return (bool) $stmt->fetchColumn();
}

function hr_money_sar(int $halalas): string
{
    return number_format($halalas / 100, 2, '.', '');
}
