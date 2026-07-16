<?php
declare(strict_types=1);
$_SERVER['HTTP_HOST'] = 'rateb.sa';
$_SERVER['DOCUMENT_ROOT'] = '/home/admin/domains/rateb.sa/public_html';
$_SERVER['HTTPS'] = 'on';
$_SERVER['REQUEST_URI'] = '/rateb-erp/public/admin';
$_GET['company_id'] = '22';
define('RATEB_ROOT', '/home/admin/domains/rateb.sa/public_html/rateb-erp');
require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
require_once RATEB_ROOT . '/config/database.php';

$mode = $argv[1] ?? 'mint';
$targetEmail = trim((string) ($argv[2] ?? 'admin@rateb.sa'));
$pdo = \Rateb\App\Core\Database::connection();
$email = $targetEmail !== '' ? $targetEmail : 'admin@rateb.sa';

$cols = $pdo->query('SHOW COLUMNS FROM rateb_users')->fetchAll(PDO::FETCH_COLUMN);
$pwCol = in_array('password_hash', $cols, true) ? 'password_hash' : (in_array('password', $cols, true) ? 'password' : null);
if ($pwCol === null) {
    echo json_encode(['ok' => false, 'error' => 'no_password_column', 'cols' => $cols]);
    exit(1);
}

$st = $pdo->prepare('SELECT * FROM rateb_users WHERE email = ? LIMIT 1');
$st->execute([$email]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row && $email === 'admin@rateb.sa') {
    $row = $pdo->query('SELECT * FROM rateb_users WHERE id=26')->fetch(PDO::FETCH_ASSOC);
}
if (!$row) {
    echo json_encode(['ok' => false, 'error' => 'user_missing', 'email' => $email]);
    exit(1);
}

if ($mode === 'unlock') {
    $pdo->prepare('UPDATE rateb_users SET failed_attempts = 0, locked_until = NULL WHERE id = ?')
        ->execute([(int) $row['id']]);
    echo json_encode([
        'ok' => true,
        'mode' => 'unlock',
        'id' => (int) $row['id'],
        'email' => $row['email'] ?? $email,
    ], JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($mode === 'unlockall') {
    $n = $pdo->exec('UPDATE rateb_users SET failed_attempts = 0, locked_until = NULL WHERE locked_until IS NOT NULL OR failed_attempts > 0');
    echo json_encode(['ok' => true, 'mode' => 'unlockall', 'rows' => (int) $n], JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($mode === 'settemp') {
    $bak = '/tmp/rateb_admin_pw_backup.json';
    file_put_contents($bak, json_encode([
        'id' => (int) $row['id'],
        'pwCol' => $pwCol,
        'password' => $row[$pwCol] ?? '',
        'password_legacy' => $row['password'] ?? null,
        'password_hash_legacy' => $row['password_hash'] ?? null,
    ], JSON_UNESCAPED_SLASHES));
    $temp = 'RatebBench!' . substr(bin2hex(random_bytes(4)), 0, 8);
    $userModel = new \Rateb\App\Models\User();
    $patch = [];
    $userModel->applyPassword($patch, $temp);
    if ($patch === []) {
        $patch[$pwCol] = password_hash($temp, PASSWORD_DEFAULT);
    }
    $sets = [];
    $params = ['id' => (int) $row['id']];
    foreach ($patch as $col => $hash) {
        $sets[] = "`{$col}` = :{$col}";
        $params[$col] = $hash;
    }
    $pdo->prepare('UPDATE rateb_users SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
    $pdo->prepare('UPDATE rateb_users SET failed_attempts = 0, locked_until = NULL WHERE id = ?')
        ->execute([(int) $row['id']]);
    echo json_encode([
        'ok' => true,
        'mode' => 'settemp',
        'email' => $row['email'] ?? $email,
        'temp' => $temp,
    ], JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($mode === 'restore') {
    $bak = '/tmp/rateb_admin_pw_backup.json';
    if (!is_file($bak)) {
        echo json_encode(['ok' => false, 'error' => 'no_backup']);
        exit(1);
    }
    $j = json_decode((string) file_get_contents($bak), true);
    $col = $j['pwCol'] ?? $pwCol;
    $u = $pdo->prepare("UPDATE rateb_users SET `{$col}` = ? WHERE id = ?");
    $u->execute([$j['password'], (int) $j['id']]);
    @unlink($bak);
    echo json_encode(['ok' => true, 'mode' => 'restore']);
    exit(0);
}

\Rateb\App\Core\Auth::loginUser($row);
if (function_exists('rateb_adopt_ops_company_id')) {
    rateb_adopt_ops_company_id(22);
}

$posBio = false;
if ($mode === 'mintpos') {
    $bio = new \Rateb\App\Services\BiometricAuthService();
    $bio->markPosVerified((int) $row['id']);
    $posBio = $bio->isPosVerified((int) $row['id']);
}

echo json_encode([
    'ok' => true,
    'mode' => $mode === 'mintpos' ? 'mintpos' : 'mint',
    'session_name' => session_name(),
    'session_id' => session_id(),
    'cookie' => session_name(),
    'user_id' => (int) $row['id'],
    'email' => $row['email'] ?? null,
    'company_id' => 22,
    'pos_biometric_verified' => $posBio,
], JSON_UNESCAPED_SLASHES);
