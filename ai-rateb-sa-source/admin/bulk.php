<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin');
    exit;
}

verify_csrf();
$screen = (string) ($_POST['screen'] ?? '');
$action = (string) ($_POST['bulk_action'] ?? '');
$reason = trim((string) ($_POST['bulk_reason'] ?? ($_POST['reason'] ?? 'bulk admin')));
$ids = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];
$return = (string) ($_POST['return'] ?? '/admin');
if (!str_starts_with($return, '/admin')) {
    $return = '/admin';
}

$result = admin_bulk_apply($pdo, $adminActor, $screen, $action, $ids, $reason);
$sep = str_contains($return, '?') ? '&' : '?';
header('Location: ' . $return . $sep . 'bulk=' . rawurlencode($result['code']) . '&n=' . (int) $result['count']);
exit;