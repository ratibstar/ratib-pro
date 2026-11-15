<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$cid = $hrCompanyId;
$kind = (string) ($_GET['kind'] ?? 'employees');
$allowed = ['employees', 'attendance', 'payroll', 'leave'];
if (!in_array($kind, $allowed, true)) {
    http_response_code(400);
    exit('Invalid export.');
}

$filename = 'rateb-hr-' . $kind . '-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$out = fopen('php://output', 'w');
if ($out === false) {
    exit;
}
fprintf($out, "\xEF\xBB\xBF");

if ($kind === 'employees') {
    fputcsv($out, ['full_name', 'employee_code', 'department', 'job_title', 'status', 'email', 'phone', 'hired_at', 'salary_sar']);
    $stmt = $pdo->prepare('SELECT e.*, d.name AS department_name FROM hr_employees e LEFT JOIN hr_departments d ON d.id = e.department_id AND d.company_user_id = e.company_user_id WHERE e.company_user_id = ? ORDER BY e.full_name');
    $stmt->execute([$cid]);
    foreach ($stmt->fetchAll() as $row) {
        fputcsv($out, [
            (string) $row['full_name'],
            (string) ($row['employee_code'] ?? ''),
            (string) ($row['department_name'] ?? ''),
            (string) ($row['job_title'] ?? ''),
            (string) $row['status'],
            (string) ($row['email'] ?? ''),
            (string) ($row['phone'] ?? ''),
            (string) ($row['hired_at'] ?? ''),
            isset($row['salary_halalas']) ? hr_money_sar((int) $row['salary_halalas']) : '',
        ]);
    }
} elseif ($kind === 'attendance') {
    $from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
    $to = trim((string) ($_GET['to'] ?? date('Y-m-d')));
    fputcsv($out, ['work_date', 'employee', 'hours_worked', 'note']);
    $stmt = $pdo->prepare('SELECT a.work_date, a.hours_worked, a.note, e.full_name FROM hr_attendance a INNER JOIN hr_employees e ON e.id = a.employee_id AND e.company_user_id = a.company_user_id WHERE a.company_user_id = ? AND a.work_date BETWEEN ? AND ? ORDER BY a.work_date, e.full_name');
    $stmt->execute([$cid, $from, $to]);
    foreach ($stmt->fetchAll() as $row) {
        fputcsv($out, [(string) $row['work_date'], (string) $row['full_name'], (string) $row['hours_worked'], (string) ($row['note'] ?? '')]);
    }
} elseif ($kind === 'payroll') {
    fputcsv($out, ['period', 'employee', 'gross_sar', 'deductions_sar', 'net_sar', 'status', 'paid_at']);
    $stmt = $pdo->prepare('SELECT p.*, e.full_name FROM hr_payroll_records p INNER JOIN hr_employees e ON e.id = p.employee_id AND e.company_user_id = p.company_user_id WHERE p.company_user_id = ? ORDER BY p.period_month DESC');
    $stmt->execute([$cid]);
    foreach ($stmt->fetchAll() as $row) {
        fputcsv($out, [
            (string) $row['period_month'],
            (string) $row['full_name'],
            hr_money_sar((int) $row['gross_halalas']),
            hr_money_sar((int) $row['deductions_halalas']),
            hr_money_sar((int) $row['net_halalas']),
            (string) $row['status'],
            (string) ($row['paid_at'] ?? ''),
        ]);
    }
} else {
    fputcsv($out, ['employee', 'type', 'start', 'end', 'status', 'reason']);
    $stmt = $pdo->prepare('SELECT l.*, e.full_name FROM hr_leave_requests l INNER JOIN hr_employees e ON e.id = l.employee_id AND e.company_user_id = l.company_user_id WHERE l.company_user_id = ? ORDER BY l.id DESC');
    $stmt->execute([$cid]);
    foreach ($stmt->fetchAll() as $row) {
        fputcsv($out, [(string) $row['full_name'], (string) $row['leave_type'], (string) $row['start_date'], (string) $row['end_date'], (string) $row['status'], (string) ($row['reason'] ?? '')]);
    }
}
fclose($out);
exit;