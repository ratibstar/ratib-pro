<?php
declare(strict_types=1);

/**
 * Local MariaDB/MySQL only. Refuses to run unless 127.0.0.1 accepts a connection.
 * Never uses production credentials.
 */
$host = getenv('RATEB_REFUND_TEST_HOST') ?: '127.0.0.1';
$port = getenv('RATEB_REFUND_TEST_PORT') ?: '3306';
$user = getenv('RATEB_REFUND_TEST_USER') ?: 'root';
$pass = getenv('RATEB_REFUND_TEST_PASSWORD') ?: '';
$dsn = "mysql:host={$host};port={$port};charset=utf8mb4";

if (($argv[1] ?? '') === 'child') {
    putenv('RATEB_WELCOME_HMAC_SECRET=test-secret');
    $_COOKIE['rateb_device'] = $argv[5];
    $pdo = new PDO($dsn . ';dbname=' . $argv[2], $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    require_once __DIR__ . '/../app/welcome.php';
    while (!is_file($argv[3])) {
        usleep(20000);
    }
    try {
        rateb_welcome_apply($pdo, (int) $argv[6], 'race@example.com', true);
        file_put_contents($argv[4], 'ok');
    } catch (Throwable $error) {
        file_put_contents($argv[4], 'err');
    }
    exit(0);
}

try {
    $admin = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]);
} catch (Throwable $error) {
    fwrite(STDERR, "MariaDB test NOT RUN: no local server at {$host}:{$port}\n");
    exit(2);
}

putenv('RATEB_WELCOME_HMAC_SECRET=test-secret');
require_once __DIR__ . '/../app/welcome.php';

$name = 'rateb_welcome_' . getmypid();
$admin->exec("CREATE DATABASE `{$name}`");
$admin->exec("USE `{$name}`");
$admin->exec('CREATE TABLE users (id INT PRIMARY KEY, email VARCHAR(190) NOT NULL, credits_balance INT NOT NULL) ENGINE=InnoDB');
$admin->exec("CREATE TABLE usage_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    campaign_id INT NULL,
    operation VARCHAR(64) NOT NULL,
    units INT NOT NULL DEFAULT 1,
    status VARCHAR(16) NOT NULL,
    event_type VARCHAR(64) NULL,
    credits_deducted INT NOT NULL DEFAULT 0,
    idempotency_key VARCHAR(64) NULL,
    UNIQUE KEY idempotency_key (idempotency_key)
) ENGINE=InnoDB");
$admin->exec("CREATE TABLE welcome_grants (
    user_id INT NOT NULL,
    email_hash CHAR(64) NOT NULL,
    device_hash CHAR(64) NULL,
    status VARCHAR(16) NOT NULL,
    credits INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY email_hash (email_hash)
) ENGINE=InnoDB");
$admin->exec("CREATE TABLE signup_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email_hash CHAR(64) NOT NULL,
    device_hash CHAR(64) NULL,
    email_verified TINYINT NOT NULL DEFAULT 0,
    decision VARCHAR(16) NOT NULL,
    detail VARCHAR(255) NOT NULL DEFAULT '',
    admin_user_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

function fail(PDO $admin, string $name, string $message): void
{
    $admin->exec("DROP DATABASE `{$name}`");
    fwrite(STDERR, $message . "\n");
    exit(1);
}

$device = bin2hex(random_bytes(32));
$_COOKIE['rateb_device'] = $device;
$admin->exec("INSERT INTO users (id, email, credits_balance) VALUES (1, 'one@example.com', 0), (2, 'two@example.com', 0), (3, 'three@example.com', 0)");
rateb_welcome_apply($admin, 1, 'one@example.com', true);
rateb_welcome_apply($admin, 2, 'two@example.com', true);
rateb_welcome_apply($admin, 3, 'three@example.com', true);
$balances = $admin->query('SELECT id, credits_balance FROM users ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
if ((int) $balances[1] !== 50 || (int) $balances[2] !== 50 || (int) $balances[3] !== 0) {
    fail($admin, $name, 'device thresholds failed: ' . json_encode($balances));
}
$third = $admin->query("SELECT status FROM welcome_grants WHERE user_id = 3")->fetchColumn();
if ($third !== 'review') {
    fail($admin, $name, 'third device grant was ' . (string) $third);
}

$signupDevice = bin2hex(random_bytes(32));
$_COOKIE['rateb_device'] = $signupDevice;
$hash = hash('sha256', $signupDevice);
$admin->exec("INSERT INTO users (id, email, credits_balance) VALUES (4, 'four@example.com', 0)");
$admin->prepare('INSERT INTO signup_audit (user_id, email_hash, device_hash, decision, detail) VALUES (90, ?, ?, \'grant\', \'seed\'), (91, ?, ?, \'grant\', \'seed\')')->execute([$hash, $hash, $hash, $hash]);
rateb_welcome_apply($admin, 4, 'four@example.com', true);
if ((int) $admin->query('SELECT credits_balance FROM users WHERE id = 4')->fetchColumn() !== 0) {
    fail($admin, $name, 'third signup in an hour received credits');
}
if ($admin->query("SELECT status FROM welcome_grants WHERE user_id = 4")->fetchColumn() !== 'review') {
    fail($admin, $name, 'third signup was not reviewed');
}

unset($_COOKIE['rateb_device']);
$admin->exec("INSERT INTO users (id, email, credits_balance) VALUES (5, 'five@example.com', 0)");
rateb_welcome_apply($admin, 5, 'five@example.com', false);
if ((int) $admin->query('SELECT credits_balance FROM users WHERE id = 5')->fetchColumn() !== 0 || $admin->query("SELECT status FROM welcome_grants WHERE user_id = 5")->fetchColumn() !== 'review') {
    fail($admin, $name, 'missing cookie was granted');
}

$_COOKIE['rateb_device'] = 'not-a-token';
$admin->exec("INSERT INTO users (id, email, credits_balance) VALUES (6, 'six@example.com', 0)");
rateb_welcome_apply($admin, 6, 'six@example.com', false);
if ((int) $admin->query('SELECT credits_balance FROM users WHERE id = 6')->fetchColumn() !== 0 || $admin->query("SELECT status FROM welcome_grants WHERE user_id = 6")->fetchColumn() !== 'review') {
    fail($admin, $name, 'invalid cookie was granted');
}

$_COOKIE['rateb_device'] = bin2hex(random_bytes(32));
$admin->exec("INSERT INTO users (id, email, credits_balance) VALUES (7, 'one@example.com', 0)");
rateb_welcome_apply($admin, 7, 'one@example.com', true);
$dup = $admin->query("SELECT decision, detail FROM signup_audit WHERE user_id = 7 ORDER BY id DESC LIMIT 1")->fetch();
if ((int) $admin->query('SELECT credits_balance FROM users WHERE id = 7')->fetchColumn() !== 0 || !$dup || $dup['decision'] !== 'duplicate') {
    fail($admin, $name, 'duplicate email was not audited at zero credits');
}
if ((int) $admin->query('SELECT COUNT(*) FROM welcome_grants WHERE email_hash = ' . $admin->quote(rateb_welcome_email_hash('one@example.com')))->fetchColumn() !== 1) {
    fail($admin, $name, 'duplicate email created another grant');
}
if (rateb_welcome_release($admin, 7, 99)) {
    fail($admin, $name, 'admin released a duplicate email');
}

$legacyEmail = 'legacy@example.com';
$admin->exec("INSERT INTO users (id, email, credits_balance) VALUES (8, 'legacy-new@example.com', 0)");
$admin->prepare('INSERT INTO welcome_grants (user_id, email_hash, status, credits) VALUES (80, ?, \'granted\', 50)')->execute([rateb_welcome_legacy_email_hash($legacyEmail)]);
rateb_welcome_apply($admin, 8, $legacyEmail, true);
if ((int) $admin->query('SELECT credits_balance FROM users WHERE id = 8')->fetchColumn() !== 0 || $admin->query("SELECT decision FROM signup_audit WHERE user_id = 8")->fetchColumn() !== 'duplicate') {
    fail($admin, $name, 'legacy hash did not keep the email granted');
}

$admin->exec("INSERT INTO users (id, email, credits_balance) VALUES (9, 'recover@example.com', 0)");
$admin->prepare("INSERT INTO signup_audit (user_id, email_hash, decision, detail) VALUES (9, ?, 'failure', 'grant_failed')")->execute([rateb_welcome_email_hash('recover@example.com')]);
$_COOKIE['rateb_device'] = bin2hex(random_bytes(32));
rateb_welcome_recover($admin, 9, 'recover@example.com', true);
rateb_welcome_recover($admin, 9, 'recover@example.com', true);
if ((int) $admin->query('SELECT credits_balance FROM users WHERE id = 9')->fetchColumn() !== 50 || (int) $admin->query("SELECT COUNT(*) FROM usage_events WHERE idempotency_key = 'welcome:9'")->fetchColumn() !== 1) {
    fail($admin, $name, 'recovery granted the wrong number of credits');
}

$admin->exec("INSERT INTO users (id, email, credits_balance) VALUES (10, 'held@example.com', 0)");
$_COOKIE['rateb_device'] = bin2hex(random_bytes(32));
rateb_welcome_apply($admin, 10, 'held@example.com', false);
$admin->prepare("UPDATE welcome_grants SET status = 'review', credits = 0 WHERE user_id = 10")->execute();
$admin->prepare('UPDATE users SET credits_balance = 0 WHERE id = 10')->execute();
$admin->exec("DELETE FROM usage_events WHERE idempotency_key = 'welcome:10'");
if (!rateb_welcome_release($admin, 10, 42) || rateb_welcome_release($admin, 10, 42)) {
    fail($admin, $name, 'admin release was not one-time');
}
$release = $admin->query("SELECT admin_user_id, detail FROM signup_audit WHERE user_id = 10 AND detail = 'manual_release'")->fetch();
if ((int) $admin->query('SELECT credits_balance FROM users WHERE id = 10')->fetchColumn() !== 50 || !$release || (int) $release['admin_user_id'] !== 42) {
    fail($admin, $name, 'admin release did not record the owner');
}

$admin->exec("INSERT INTO users (id, email, credits_balance) VALUES (11, 'race@example.com', 0), (12, 'race@example.com', 0)");
$gate = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $name . '.gate';
$outA = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $name . '.a';
$outB = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $name . '.b';
@unlink($gate);
@unlink($outA);
@unlink($outB);
$raceCookie = bin2hex(random_bytes(32));
$procs = [];
foreach ([[$outA, '11'], [$outB, '12']] as [$out, $id]) {
    $pipes = [];
    $proc = proc_open([PHP_BINARY, __FILE__, 'child', $name, $gate, $out, $raceCookie, $id], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $procs[] = [$proc, $pipes];
}
usleep(300000);
file_put_contents($gate, 'go');
foreach ($procs as [$proc, $pipes]) {
    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }
    if (is_resource($proc)) {
        proc_close($proc);
    }
}
$raceBalance = (int) $admin->query('SELECT COALESCE(SUM(credits_balance), 0) FROM users WHERE id IN (11, 12)')->fetchColumn();
$raceGrants = (int) $admin->query("SELECT COUNT(*) FROM welcome_grants WHERE email_hash = " . $admin->quote(rateb_welcome_email_hash('race@example.com')) . " AND status = 'granted'")->fetchColumn();
if ($raceBalance !== 50 || $raceGrants !== 1) {
    fail($admin, $name, "concurrent grant balance={$raceBalance} grants={$raceGrants}");
}

$admin->exec("DROP DATABASE `{$name}`");
echo "welcome mariadb ok\n";
