<?php
declare(strict_types=1);

/**
 * CLI: php tests/hr-tenant-smoke.php
 * Requires MySQL schema from config/database.php (or env).
 */
$root = dirname(__DIR__);
require $root . '/migrations/company_hr_schema.php';

$config = require $root . '/config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}",
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
ensure_company_hr_schema($pdo);

$fail = 0;
$assert = static function (bool $ok, string $label) use (&$fail): void {
    if ($ok) {
        echo "OK  $label\n";
    } else {
        echo "FAIL $label\n";
        $fail++;
    }
};

$pdo->exec("DELETE FROM hr_attendance WHERE company_user_id IN (909001, 909002)");
$pdo->exec("DELETE FROM hr_employees WHERE company_user_id IN (909001, 909002)");
$pdo->exec("DELETE FROM hr_departments WHERE company_user_id IN (909001, 909002)");

$pdo->prepare('INSERT INTO hr_departments (company_user_id, name) VALUES (909001, ?)')->execute(['Eng A']);
$pdo->prepare('INSERT INTO hr_departments (company_user_id, name) VALUES (909002, ?)')->execute(['Eng B']);
$pdo->prepare('INSERT INTO hr_employees (company_user_id, full_name, status) VALUES (909001, ?, ?)')->execute(['Alice', 'active']);
$pdo->prepare('INSERT INTO hr_employees (company_user_id, full_name, status) VALUES (909002, ?, ?)')->execute(['Bob', 'active']);

$stmt = $pdo->prepare('SELECT COUNT(*) FROM hr_employees WHERE company_user_id = ?');
$stmt->execute([909001]);
$assert((int) $stmt->fetchColumn() === 1, 'tenant A sees one employee');

$stmt = $pdo->prepare('SELECT full_name FROM hr_employees WHERE company_user_id = ?');
$stmt->execute([909002]);
$names = $stmt->fetchAll(PDO::FETCH_COLUMN);
$assert($names === ['Bob'], 'tenant B isolation');

$cross = $pdo->prepare('SELECT id FROM hr_employees WHERE company_user_id = ? AND full_name = ?');
$cross->execute([909001, 'Bob']);
$assert($cross->fetchColumn() === false, 'A cannot query B employee by filter');

$pdo->exec("DELETE FROM hr_attendance WHERE company_user_id IN (909001, 909002)");
$pdo->exec("DELETE FROM hr_employees WHERE company_user_id IN (909001, 909002)");
$pdo->exec("DELETE FROM hr_departments WHERE company_user_id IN (909001, 909002)");

exit($fail > 0 ? 1 : 0);
