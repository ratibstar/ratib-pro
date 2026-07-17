<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
putenv('RATEB_HYBRID_SYNC_SINK=mirror');
$_ENV['RATEB_HYBRID_SYNC_SINK'] = 'mirror';

require_once $root . '/rateb-erp/app/Core/HybridSyncConfig.php';
require_once $root . '/rateb-erp/app/Core/HybridSyncSink.php';

use Rateb\App\Core\HybridSyncSink;

$passed = 0;
$failed = 0;
$assert = static function (string $name, bool $condition) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    $condition ? $passed++ : $failed++;
};

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE rateb_orders (id INTEGER PRIMARY KEY, status TEXT, total REAL)');
$pdo->exec('CREATE TABLE rateb_users (id INTEGER PRIMARY KEY, role_id INTEGER)');

$sink = new HybridSyncSink();
$method = new ReflectionMethod($sink, 'compileMappedMutation');
$method->setAccessible(true);

$valid = $method->invoke(
    $sink,
    $pdo,
    'UPDATE rateb_orders SET status = :status WHERE id = :id',
    'rateb_orders',
    'UPDATE'
);
$crossTable = $method->invoke(
    $sink,
    $pdo,
    'UPDATE rateb_users SET role_id = :role WHERE id = :id',
    'rateb_orders',
    'UPDATE'
);
$spoofedEntity = $method->invoke(
    $sink,
    $pdo,
    "UPDATE rateb_users SET role_id = :role WHERE id = :id AND 'rateb_orders' = :entity",
    'rateb_orders',
    'UPDATE'
);

$assert('HybridSyncSink compiles mapped entity mutation', is_string($valid) && str_contains($valid, '"rateb_orders"'));
$assert('HybridSyncSink rejects cross-table mutation', $crossTable === null);
$assert('HybridSyncSink rejects entity-token spoofing', $spoofedEntity === null);
if (is_string($valid)) {
    $pdo->exec("INSERT INTO rateb_orders (id, status, total) VALUES (1, 'draft', 10)");
    $statement = $pdo->prepare($valid);
    $statement->execute(['status' => 'posted', 'id' => 1]);
}
$assert(
    'HybridSyncSink compiled mutation executes with bound values',
    (string) $pdo->query('SELECT status FROM rateb_orders WHERE id = 1')->fetchColumn() === 'posted'
);

$sinkSource = (string) file_get_contents($root . '/rateb-erp/app/Core/HybridSyncSink.php');
$assert(
    'HybridSyncSink never prepares caller SQL',
    !str_contains($sinkSource, 'prepare($applySql)')
        && str_contains($sinkSource, 'prepare($mutation)')
        && str_contains($sinkSource, 'mappedTableForEntity')
);

$hr = (string) file_get_contents($root . '/api/hr/documents.php');
$permissions = (string) file_get_contents($root . '/api/core/module-permissions.php');
$assert(
    'HR bulk update has dedicated write permission and CSRF',
    str_contains($hr, "'bulk-update' => 'bulk-update'")
        && str_contains($permissions, "'bulk-update' => 'edit_employee'")
        && str_contains($hr, 'requireApiMutationSecurity();')
);
$assert(
    'HR bulk delete has dedicated write permission and CSRF',
    str_contains($hr, "'bulk-delete' => 'bulk-delete'")
        && str_contains($permissions, "'bulk-delete' => 'delete_employee'")
        && str_contains($hr, 'requireApiMutationSecurity();')
);

$notifications = (string) file_get_contents($root . '/api/notifications/notifications-api.php');
$assert(
    'Role broadcast has dedicated permission and CSRF',
    str_contains($notifications, "\$action === 'send_role_broadcast'")
        && str_contains($notifications, "enforceApiPermission('notifications', 'broadcast')")
        && str_contains($permissions, "'broadcast' => 'broadcast_notifications'")
        && str_contains($notifications, 'requireApiMutationSecurity();')
);

echo PHP_EOL . $passed . '/' . ($passed + $failed) . ' passed' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
