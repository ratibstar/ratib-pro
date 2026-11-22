<?php
declare(strict_types=1);

require_once __DIR__ . '/library.php';

require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$roleStmt = db()->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
$roleStmt->execute([(int) $_SESSION['user_id']]);
if ((string) ($roleStmt->fetchColumn() ?: '') !== 'owner') {
    http_response_code(403);
    exit('Forbidden');
}

header('Location: /admin/users.php');
exit;
