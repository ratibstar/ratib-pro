<?php
declare(strict_types=1);

/*
 * Real MySQL/MariaDB integration test for the credit reservation SQL in app/plans.php:
 * rateb_reservation_charge, rateb_reservation_settle, rateb_reservation_sweep, and the
 * rateb_usage_begin plan gate that opens each reservation.
 *
 * It never uses app/bootstrap.php, db(), or config/database.php credentials. It connects
 * only to the database named in RATEB_TEST_DB_NAME and refuses to run unless that
 * database is clearly a disposable test database.
 *
 * SETUP (once, as a MySQL admin, on a local or throwaway server — never production):
 *   CREATE DATABASE rateb_ai_credit_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
 *   CREATE USER 'rateb_credit_test'@'localhost' IDENTIFIED BY 'choose-a-local-password';
 *   GRANT ALL PRIVILEGES ON rateb_ai_credit_test.* TO 'rateb_credit_test'@'localhost';
 *   -- Only if binary logging is on and the trigger cases report NOT RUN:
 *   -- SET GLOBAL log_bin_trust_function_creators = 1;
 *
 * RUN (PowerShell):
 *   $env:RATEB_TEST_DB_HOST = '127.0.0.1'
 *   $env:RATEB_TEST_DB_PORT = '3306'
 *   $env:RATEB_TEST_DB_NAME = 'rateb_ai_credit_test'
 *   $env:RATEB_TEST_DB_USER = 'rateb_credit_test'
 *   $env:RATEB_TEST_DB_PASS = 'choose-a-local-password'
 *   $env:RATEB_TEST_DB_CONFIRM = 'rateb_ai_credit_test'
 *   php tests/integration/credit-reservations-mysql.php
 *
 * CLEANUP:
 *   php tests/integration/credit-reservations-mysql.php --cleanup
 *   -- then, as admin: DROP DATABASE rateb_ai_credit_test; DROP USER 'rateb_credit_test'@'localhost';
 *
 * Exit codes: 0 all cases passed, 1 a case failed, 2 refused or incomplete (nothing proven).
 */

require_once __DIR__ . '/../../app/plans.php';

const IT_MARKER = 'rateb_integration_marker';
const IT_TABLES = ['users', 'usage_events', 'plans', 'subscriptions', IT_MARKER];

function it_refuse(string $why): never
{
    fwrite(STDERR, 'REFUSED: ' . $why . PHP_EOL);
    exit(2);
}

/** @return array{host:string,port:int,name:string,user:string,pass:string} */
function it_settings(): array
{
    $name = (string) getenv('RATEB_TEST_DB_NAME');
    $host = (string) (getenv('RATEB_TEST_DB_HOST') ?: '127.0.0.1');
    if ($name === '') {
        it_refuse('RATEB_TEST_DB_NAME is not set.');
    }
    if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $name) !== 1 || preg_match('/(^|_)test(_|$)/i', $name) !== 1) {
        it_refuse('the database name must contain "test" as its own word, for example rateb_ai_credit_test.');
    }
    if ((string) getenv('RATEB_TEST_DB_CONFIRM') !== $name) {
        it_refuse('set RATEB_TEST_DB_CONFIRM to the exact test database name to confirm it may be emptied.');
    }
    $production = @include __DIR__ . '/../../config/database.php';
    if (is_array($production) && strcasecmp((string) ($production['database'] ?? ''), $name) === 0) {
        it_refuse('the test database name matches the production database name in config/database.php.');
    }
    if (!in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1'], true) && getenv('RATEB_TEST_DB_ALLOW_REMOTE') !== '1') {
        it_refuse('the test host is not local. Set RATEB_TEST_DB_ALLOW_REMOTE=1 only for a disposable remote test server.');
    }
    if (is_array($production) && strcasecmp((string) ($production['host'] ?? ''), $host) === 0 && !in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1'], true)) {
        it_refuse('the test host matches the production host in config/database.php.');
    }
    return [
        'host' => $host,
        'port' => (int) (getenv('RATEB_TEST_DB_PORT') ?: 3306),
        'name' => $name,
        'user' => (string) getenv('RATEB_TEST_DB_USER'),
        'pass' => (string) getenv('RATEB_TEST_DB_PASS'),
    ];
}

/** Same PDO options as app/bootstrap.php, so prepares are native and rowCount reports changed rows. */
function it_connect(array $s): PDO
{
    $pdo = new PDO(
        "mysql:host={$s['host']};port={$s['port']};dbname={$s['name']};charset=utf8mb4",
        $s['user'],
        $s['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== $s['name']) {
        it_refuse('connected to a different database than RATEB_TEST_DB_NAME.');
    }
    return $pdo;
}

function it_guard_tables(PDO $pdo, bool $requireMarker = false): void
{
    $tables = array_map('strval', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
    $unexpected = array_diff($tables, IT_TABLES);
    if ($unexpected !== []) {
        it_refuse('the test database contains tables this test did not create: ' . implode(', ', $unexpected) . '. Use an empty dedicated database.');
    }
    if (($tables !== [] || $requireMarker) && !in_array(IT_MARKER, $tables, true)) {
        it_refuse('users/usage_events exist without the integration marker table, so they may hold real data.');
    }
}

function it_drop(PDO $pdo): void
{
    foreach (['it_fail_charge', 'it_fail_refund'] as $trigger) {
        $pdo->exec('DROP TRIGGER IF EXISTS ' . $trigger);
    }
    foreach (IT_TABLES as $table) {
        $pdo->exec('DROP TABLE IF EXISTS ' . $table);
    }
}

function it_setup(PDO $pdo): void
{
    it_drop($pdo);
    $pdo->exec('CREATE TABLE ' . IT_MARKER . ' (created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO ' . IT_MARKER . ' () VALUES ()');
    $pdo->exec("CREATE TABLE users (
        id BIGINT UNSIGNED NOT NULL,
        credits_balance INT NOT NULL DEFAULT 50,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Mirrors app/schema.php: CREATE TABLE usage_events plus its event_type and credits_deducted columns.
    $pdo->exec("CREATE TABLE usage_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        campaign_id BIGINT UNSIGNED NULL,
        operation VARCHAR(32) NOT NULL,
        units INT UNSIGNED NOT NULL DEFAULT 1,
        seconds INT UNSIGNED NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'reserved',
        cost_sar DECIMAL(10,4) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_operation (user_id, operation, status, created_at),
        KEY campaign_id (campaign_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec('ALTER TABLE usage_events ADD COLUMN event_type VARCHAR(32) NULL');
    $pdo->exec('ALTER TABLE usage_events ADD COLUMN credits_deducted INT NOT NULL DEFAULT 0');
    // Mirrors app/schema.php: CREATE TABLE plans and subscriptions.
    $pdo->exec("CREATE TABLE plans (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        code VARCHAR(32) NOT NULL,
        name_ar VARCHAR(80) NOT NULL,
        name_en VARCHAR(80) NOT NULL,
        price_sar DECIMAL(10,2) NOT NULL DEFAULT 0,
        video_limit INT UNSIGNED NOT NULL DEFAULT 0,
        video_max_seconds INT UNSIGNED NOT NULL DEFAULT 0,
        image_limit INT UNSIGNED NOT NULL DEFAULT 0,
        watermark TINYINT(1) NOT NULL DEFAULT 1,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY code (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE subscriptions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        plan_id BIGINT UNSIGNED NOT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'active',
        period_start DATETIME NOT NULL,
        period_end DATETIME NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_status (user_id, status),
        KEY plan_id (plan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** Same statement as rateb_usage_begin(). */
function it_reserve(PDO $pdo, int $userId, ?int $campaignId, string $operation): int
{
    $pdo->prepare("INSERT INTO usage_events (user_id, campaign_id, operation, units, seconds, status) VALUES (?, ?, ?, 1, ?, 'reserved')")
        ->execute([$userId, $campaignId, $operation, null]);
    return (int) $pdo->lastInsertId();
}

function it_user(PDO $pdo, int $userId, int $balance): void
{
    $pdo->prepare('INSERT INTO users (id, credits_balance) VALUES (?, ?)')->execute([$userId, $balance]);
}

/** @return array{balance:int,status:string,deducted:int,charges:int,refunds:int} */
function it_state(PDO $pdo, int $userId, int $usageId, string $feature): array
{
    $balance = $pdo->prepare('SELECT credits_balance FROM users WHERE id = ?');
    $balance->execute([$userId]);
    $row = $pdo->prepare('SELECT status, credits_deducted FROM usage_events WHERE id = ?');
    $row->execute([$usageId]);
    $reservation = $row->fetch() ?: ['status' => 'missing', 'credits_deducted' => -1];
    $count = $pdo->prepare('SELECT SUM(operation = ?) AS charges, SUM(operation = ?) AS refunds FROM usage_events WHERE user_id = ?');
    $count->execute([$feature, $feature . '_refund', $userId]);
    $counts = $count->fetch() ?: [];
    return [
        'balance' => (int) $balance->fetchColumn(),
        'status' => (string) $reservation['status'],
        'deducted' => (int) $reservation['credits_deducted'],
        'charges' => (int) ($counts['charges'] ?? 0),
        'refunds' => (int) ($counts['refunds'] ?? 0),
    ];
}

$results = ['PASS' => 0, 'FAIL' => 0, 'NOT RUN' => 0];

function it_report(string $outcome, string $name, string $detail = ''): void
{
    global $results;
    $results[$outcome]++;
    echo $outcome . ' ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
}

/** @param array<string,int|string> $expected */
function it_expect(string $name, array $actual, array $expected): void
{
    $diff = [];
    foreach ($expected as $key => $value) {
        if ($actual[$key] !== $value) {
            $diff[] = $key . '=' . var_export($actual[$key], true) . ' (expected ' . var_export($value, true) . ')';
        }
    }
    it_report($diff === [] ? 'PASS' : 'FAIL', $name, implode(', ', $diff));
}

function it_spawn(string $action, int $usageId, int $userId): array
{
    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, __FILE__, '--worker', $action, (string) $usageId, (string) $userId],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('could not start worker');
    }
    return [$process, $pipes];
}

/** @return array<string,mixed> */
function it_collect(array $worker): array
{
    [$process, $pipes] = $worker;
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    $decoded = json_decode(trim((string) $out), true);
    return is_array($decoded) ? $decoded + ['exit' => $code] : ['error' => trim($err . ' ' . $out), 'exit' => $code];
}

function it_waiting_updates(PDO $observer, string $prefix): int
{
    $stmt = $observer->prepare('SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID <> CONNECTION_ID() AND DB = DATABASE() AND INFO LIKE ?');
    $stmt->execute([$prefix . '%']);
    return (int) $stmt->fetchColumn();
}

/**
 * Hold the reservation row lock, start two workers that block on the same row, confirm both
 * are waiting inside MySQL, then release the lock so they race for it.
 *
 * @return array{0:array<int,array<string,mixed>>,1:bool}
 */
function it_race(PDO $holder, PDO $observer, string $action, int $usageId, int $userId, string $waitPrefix, string $lockTable = 'usage_events', ?int $lockId = null): array
{
    $holder->beginTransaction();
    $holder->prepare('SELECT id FROM ' . $lockTable . ' WHERE id = ? FOR UPDATE')->execute([$lockId ?? $usageId]);
    $workers = [it_spawn($action, $usageId, $userId), it_spawn($action, $usageId, $userId)];
    $overlap = false;
    $deadline = microtime(true) + 15;
    while (microtime(true) < $deadline) {
        if (it_waiting_updates($observer, $waitPrefix) >= 2) {
            $overlap = true;
            break;
        }
        usleep(100000);
    }
    $holder->commit();
    return [[it_collect($workers[0]), it_collect($workers[1])], $overlap];
}

if (($argv[1] ?? '') === '--worker') {
    $settings = it_settings();
    try {
        $pdo = it_connect($settings);
        it_guard_tables($pdo, true);
        $pdo->exec('SET SESSION innodb_lock_wait_timeout = 30');
        $usageId = (int) ($argv[3] ?? 0);
        $userId = (int) ($argv[4] ?? 0);
        $result = match ($argv[2] ?? '') {
            'settle' => rateb_reservation_settle($pdo, $usageId, $userId, 'failed', 'ai_image'),
            'charge' => rateb_reservation_charge($pdo, $usageId, $userId, 'ai_image', 70),
            'begin' => rateb_usage_begin($pdo, $userId, 72, 'image'),
            default => throw new RuntimeException('unknown worker action'),
        };
        echo json_encode(['result' => $result]), PHP_EOL;
        exit(0);
    } catch (Throwable $error) {
        echo json_encode(['error' => get_class($error) . ': ' . $error->getMessage()]), PHP_EOL;
        exit(1);
    }
}

$settings = it_settings();
try {
    $pdo = it_connect($settings);
} catch (PDOException $error) {
    it_refuse('could not connect to the test database (' . $error->getCode() . '). Nothing was tested.');
}
it_guard_tables($pdo);
if (in_array('--cleanup', $argv, true)) {
    it_drop($pdo);
    echo 'CLEANED: removed integration tables and triggers from ' . $settings['name'] . PHP_EOL;
    exit(0);
}

$version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
echo 'SERVER ' . $version . ' DATABASE ' . $settings['name'] . ' EMULATE_PREPARES=' . var_export($pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES), true) . PHP_EOL;
it_setup($pdo);
$engine = $pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usage_events'")->fetchColumn();
it_report($engine === 'InnoDB' ? 'PASS' : 'FAIL', 'usage_events uses InnoDB', 'engine=' . var_export($engine, true));

// 1. Successful charge.
it_user($pdo, 101, 25);
$r1 = it_reserve($pdo, 101, 70, 'image');
$outcome = rateb_reservation_charge($pdo, $r1, 101, 'ai_image', 70);
it_expect('successful charge returns ok and debits once', it_state($pdo, 101, $r1, 'ai_image') + ['outcome' => $outcome], ['outcome' => 'ok', 'balance' => 15, 'status' => 'reserved', 'deducted' => 10, 'charges' => 1, 'refunds' => 0]);
$outcome = rateb_reservation_charge($pdo, $r1, 101, 'ai_image', 70);
it_expect('charging the same reservation again is refused', it_state($pdo, 101, $r1, 'ai_image') + ['outcome' => $outcome], ['outcome' => 'reservation', 'balance' => 15, 'status' => 'reserved', 'deducted' => 10, 'charges' => 1, 'refunds' => 0]);

// 2. Failed charge rollback: low balance.
it_user($pdo, 102, 5);
$r2 = it_reserve($pdo, 102, 70, 'image');
$outcome = rateb_reservation_charge($pdo, $r2, 102, 'ai_image', 70);
it_expect('a low-balance charge rolls back the reservation mark', it_state($pdo, 102, $r2, 'ai_image') + ['outcome' => $outcome, 'open' => var_export($pdo->inTransaction(), true)], ['outcome' => 'credits', 'open' => 'false', 'balance' => 5, 'status' => 'reserved', 'deducted' => 0, 'charges' => 0, 'refunds' => 0]);

// 2b and 4b need triggers to force a database error in the middle of the transaction.
$triggers = true;
try {
    $pdo->exec("CREATE TRIGGER it_fail_charge BEFORE INSERT ON usage_events FOR EACH ROW
        BEGIN IF NEW.operation = 'ai_image' AND NEW.campaign_id = 999 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'it forced charge failure'; END IF; END");
    $pdo->exec("CREATE TRIGGER it_fail_refund BEFORE UPDATE ON users FOR EACH ROW
        BEGIN IF NEW.id = 105 AND NEW.credits_balance > OLD.credits_balance AND (SELECT COUNT(*) FROM " . IT_MARKER . ") = 2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'it forced refund failure'; END IF; END");
} catch (PDOException $error) {
    $triggers = false;
    it_report('NOT RUN', 'mid-transaction failure cases need CREATE TRIGGER', $error->getMessage());
}
if ($triggers) {
    it_user($pdo, 103, 25);
    $r3 = it_reserve($pdo, 103, 999, 'image');
    $threw = '';
    try {
        rateb_reservation_charge($pdo, $r3, 103, 'ai_image', 999);
    } catch (PDOException $error) {
        $threw = 'threw';
    }
    it_expect('a database error after the debit rolls back the whole charge', it_state($pdo, 103, $r3, 'ai_image') + ['threw' => $threw, 'open' => var_export($pdo->inTransaction(), true)], ['threw' => 'threw', 'open' => 'false', 'balance' => 25, 'status' => 'reserved', 'deducted' => 0, 'charges' => 0, 'refunds' => 0]);
}

// 3. Successful refund, 4. duplicate refund.
it_user($pdo, 104, 25);
$r4 = it_reserve($pdo, 104, 70, 'image');
rateb_reservation_charge($pdo, $r4, 104, 'ai_image', 70);
$first = rateb_reservation_settle($pdo, $r4, 104, 'failed', 'ai_image');
it_expect('a failed reservation refunds its recorded charge', it_state($pdo, 104, $r4, 'ai_image') + ['refunded' => var_export($first, true)], ['refunded' => 'true', 'balance' => 25, 'status' => 'failed', 'deducted' => 10, 'charges' => 1, 'refunds' => 1]);
$second = rateb_reservation_settle($pdo, $r4, 104, 'failed', 'ai_image');
it_expect('a duplicate failure report refunds nothing', it_state($pdo, 104, $r4, 'ai_image') + ['refunded' => var_export($second, true)], ['refunded' => 'false', 'balance' => 25, 'status' => 'failed', 'deducted' => 10, 'charges' => 1, 'refunds' => 1]);
$late = rateb_reservation_settle($pdo, $r4, 104, 'completed', 'ai_image');
it_expect('a late completion cannot reopen or re-charge a refunded reservation', it_state($pdo, 104, $r4, 'ai_image') + ['refunded' => var_export($late, true)], ['refunded' => 'false', 'balance' => 25, 'status' => 'failed', 'deducted' => 10, 'charges' => 1, 'refunds' => 1]);
$wrongUser = it_reserve($pdo, 104, 70, 'image');
rateb_reservation_charge($pdo, $wrongUser, 104, 'ai_image', 70);
$other = rateb_reservation_settle($pdo, $wrongUser, 999, 'failed', 'ai_image');
it_expect('another user cannot settle the reservation', it_state($pdo, 104, $wrongUser, 'ai_image') + ['refunded' => var_export($other, true)], ['refunded' => 'false', 'balance' => 15, 'status' => 'reserved', 'deducted' => 10, 'charges' => 2, 'refunds' => 1]);

if ($triggers) {
    it_user($pdo, 105, 25);
    $r5 = it_reserve($pdo, 105, 70, 'image');
    rateb_reservation_charge($pdo, $r5, 105, 'ai_image', 70);
    $pdo->exec('INSERT INTO ' . IT_MARKER . ' () VALUES ()');
    $threw = '';
    try {
        rateb_reservation_settle($pdo, $r5, 105, 'failed', 'ai_image');
    } catch (PDOException $error) {
        $threw = 'threw';
    }
    it_expect('a database error during refund rolls back and keeps the reservation open', it_state($pdo, 105, $r5, 'ai_image') + ['threw' => $threw, 'open' => var_export($pdo->inTransaction(), true)], ['threw' => 'threw', 'open' => 'false', 'balance' => 15, 'status' => 'reserved', 'deducted' => 10, 'charges' => 1, 'refunds' => 0]);
    $pdo->exec('DELETE FROM ' . IT_MARKER . ' LIMIT 1');
    $retry = rateb_reservation_settle($pdo, $r5, 105, 'failed', 'ai_image');
    it_expect('retrying the refund after the error refunds exactly once', it_state($pdo, 105, $r5, 'ai_image') + ['refunded' => var_export($retry, true)], ['refunded' => 'true', 'balance' => 25, 'status' => 'failed', 'deducted' => 10, 'charges' => 1, 'refunds' => 1]);
}

// 5. Expired reservation sweep, using the real DATE_SUB(NOW(), INTERVAL ? SECOND) prepared statement.
it_user($pdo, 106, 40);
$maxAge = rateb_image_reservation_max_age();
$stale = it_reserve($pdo, 106, 70, 'image');
rateb_reservation_charge($pdo, $stale, 106, 'ai_image', 70);
$young = it_reserve($pdo, 106, 70, 'image');
rateb_reservation_charge($pdo, $young, 106, 'ai_image', 70);
$uncharged = it_reserve($pdo, 106, 70, 'image');
$voice = it_reserve($pdo, 106, 70, 'voice');
$pdo->prepare('UPDATE usage_events SET credits_deducted = 15 WHERE id = ?')->execute([$voice]);
$pdo->prepare('UPDATE users SET credits_balance = credits_balance - 15 WHERE id = ?')->execute([106]);
$age = $pdo->prepare('UPDATE usage_events SET created_at = DATE_SUB(NOW(), INTERVAL ? SECOND) WHERE id = ?');
foreach ([$stale, $uncharged, $voice] as $id) {
    $age->execute([$maxAge + 60, $id]);
}
$age->execute([$maxAge - 60, $young]);
$swept = rateb_reservation_sweep($pdo, 106, 'image', 'ai_image', $maxAge);
it_expect('the sweep refunds only the stale charged image reservation', it_state($pdo, 106, $stale, 'ai_image') + ['swept' => $swept], ['swept' => 1, 'balance' => 15, 'status' => 'failed', 'deducted' => 10, 'charges' => 2, 'refunds' => 1]);
$others = [];
foreach (['young' => $young, 'uncharged' => $uncharged, 'voice' => $voice] as $label => $id) {
    $others[$label] = it_state($pdo, 106, $id, 'ai_image')['status'];
}
it_expect('the sweep leaves running, uncharged, and other-feature reservations reserved', $others, ['young' => 'reserved', 'uncharged' => 'reserved', 'voice' => 'reserved']);
$again = rateb_reservation_sweep($pdo, 106, 'image', 'ai_image', $maxAge);
it_expect('a second sweep refunds nothing', it_state($pdo, 106, $stale, 'ai_image') + ['swept' => $again], ['swept' => 0, 'balance' => 15, 'status' => 'failed', 'deducted' => 10, 'charges' => 2, 'refunds' => 1]);

// 6. Two concurrent connections settle the same reservation.
$holder = it_connect($settings);
$observer = it_connect($settings);
it_user($pdo, 107, 25);
$r7 = it_reserve($pdo, 107, 70, 'image');
rateb_reservation_charge($pdo, $r7, 107, 'ai_image', 70);
[$settled, $overlap] = it_race($holder, $observer, 'settle', $r7, 107, 'UPDATE usage_events SET status');
$true = count(array_filter($settled, static fn (array $w): bool => ($w['result'] ?? null) === true));
$false = count(array_filter($settled, static fn (array $w): bool => ($w['result'] ?? null) === false));
$errors = array_values(array_filter(array_map(static fn (array $w): string => (string) ($w['error'] ?? ''), $settled)));
if (!$overlap) {
    it_report('NOT RUN', 'concurrent settle', 'could not confirm both connections were waiting on the row lock; results: ' . json_encode($settled));
} else {
    it_expect('two concurrent settles refund exactly once', it_state($pdo, 107, $r7, 'ai_image') + ['winners' => $true, 'losers' => $false, 'errors' => implode('; ', $errors)], ['winners' => 1, 'losers' => 1, 'errors' => '', 'balance' => 25, 'status' => 'failed', 'deducted' => 10, 'charges' => 1, 'refunds' => 1]);
}

it_user($pdo, 108, 25);
$r8 = it_reserve($pdo, 108, 70, 'image');
[$charged, $overlap] = it_race($holder, $observer, 'charge', $r8, 108, 'UPDATE usage_events SET credits_deducted');
$outcomes = array_map(static fn (array $w): string => (string) ($w['result'] ?? ('error: ' . ($w['error'] ?? ''))), $charged);
sort($outcomes);
if (!$overlap) {
    it_report('NOT RUN', 'concurrent charge', 'could not confirm both connections were waiting on the row lock; results: ' . json_encode($charged));
} else {
    it_expect('two concurrent charges of one reservation debit exactly once', it_state($pdo, 108, $r8, 'ai_image') + ['outcomes' => implode(',', $outcomes)], ['outcomes' => 'ok,reservation', 'balance' => 15, 'status' => 'reserved', 'deducted' => 10, 'charges' => 1, 'refunds' => 0]);
}

// 7. Voice reservations charge 15 credits and refund or sweep them with the voice max age.
it_user($pdo, 201, 40);
$v1 = it_reserve($pdo, 201, 71, 'voice');
$outcome = rateb_reservation_charge($pdo, $v1, 201, 'saudi_voice', 71);
it_expect('a voice reservation is charged 15 credits once', it_state($pdo, 201, $v1, 'saudi_voice') + ['outcome' => $outcome], ['outcome' => 'ok', 'balance' => 25, 'status' => 'reserved', 'deducted' => 15, 'charges' => 1, 'refunds' => 0]);
$outcome = rateb_reservation_charge($pdo, $v1, 201, 'saudi_voice', 71);
it_expect('a charged voice reservation cannot be charged again', it_state($pdo, 201, $v1, 'saudi_voice') + ['outcome' => $outcome], ['outcome' => 'reservation', 'balance' => 25, 'deducted' => 15, 'charges' => 1]);
$first = rateb_reservation_settle($pdo, $v1, 201, 'failed', 'saudi_voice');
$second = rateb_reservation_settle($pdo, $v1, 201, 'failed', 'saudi_voice');
it_expect('a failed voice reservation refunds 15 credits exactly once', it_state($pdo, 201, $v1, 'saudi_voice') + ['first' => var_export($first, true), 'second' => var_export($second, true)], ['first' => 'true', 'second' => 'false', 'balance' => 40, 'status' => 'failed', 'deducted' => 15, 'charges' => 1, 'refunds' => 1]);
$v2 = it_reserve($pdo, 201, 71, 'voice');
rateb_reservation_charge($pdo, $v2, 201, 'saudi_voice', 71);
$done = rateb_reservation_settle($pdo, $v2, 201, 'completed', 'saudi_voice');
$late = rateb_reservation_settle($pdo, $v2, 201, 'failed', 'saudi_voice');
it_expect('a completed voice reservation keeps its charge and ignores a late failure', it_state($pdo, 201, $v2, 'saudi_voice') + ['done' => var_export($done, true), 'late' => var_export($late, true)], ['done' => 'false', 'late' => 'false', 'balance' => 25, 'status' => 'completed', 'deducted' => 15, 'charges' => 2, 'refunds' => 1]);

it_user($pdo, 202, 10);
$v3 = it_reserve($pdo, 202, 71, 'voice');
$outcome = rateb_reservation_charge($pdo, $v3, 202, 'saudi_voice', 71);
it_expect('a voice charge above the balance is refused and changes nothing', it_state($pdo, 202, $v3, 'saudi_voice') + ['outcome' => $outcome], ['outcome' => 'credits', 'balance' => 10, 'status' => 'reserved', 'deducted' => 0, 'charges' => 0, 'refunds' => 0]);

it_user($pdo, 203, 60);
$voiceAge = rateb_voice_reservation_max_age();
$staleVoice = it_reserve($pdo, 203, 71, 'voice');
rateb_reservation_charge($pdo, $staleVoice, 203, 'saudi_voice', 71);
$youngVoice = it_reserve($pdo, 203, 71, 'voice');
rateb_reservation_charge($pdo, $youngVoice, 203, 'saudi_voice', 71);
$staleImage = it_reserve($pdo, 203, 71, 'image');
rateb_reservation_charge($pdo, $staleImage, 203, 'ai_image', 71);
$age->execute([$voiceAge + 60, $staleVoice]);
$age->execute([$voiceAge - 60, $youngVoice]);
$age->execute([rateb_image_reservation_max_age() + 60, $staleImage]);
$swept = rateb_reservation_sweep($pdo, 203, 'voice', 'saudi_voice', $voiceAge);
it_expect('the voice sweep refunds only the stale charged voice reservation', it_state($pdo, 203, $staleVoice, 'saudi_voice') + ['swept' => $swept], ['swept' => 1, 'balance' => 35, 'status' => 'failed', 'deducted' => 15, 'charges' => 2, 'refunds' => 1]);
it_expect('the voice sweep leaves a running voice and a stale image reservation alone', ['young' => it_state($pdo, 203, $youngVoice, 'saudi_voice')['status'], 'image' => it_state($pdo, 203, $staleImage, 'ai_image')['status']], ['young' => 'reserved', 'image' => 'reserved']);

// 8. The real rateb_usage_begin() plan gate, followed by charging.
$plan = $pdo->prepare('INSERT INTO plans (code, name_ar, name_en, price_sar, video_limit, video_max_seconds, image_limit, watermark, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
$plan->execute(['free', 'مجاني', 'Free', 0, 2, 10, 2, 1, 1]);
$plan->execute(['pro', 'احترافي', 'Pro', 99, 5, 30, 3, 0, 1]);
$proPlan = (int) $pdo->lastInsertId();
$plan->execute(['retired', 'متوقف', 'Retired', 49, 5, 30, 5, 0, 0]);
$retiredPlan = (int) $pdo->lastInsertId();
$subscribe = $pdo->prepare("INSERT INTO subscriptions (user_id, plan_id, status, period_start, period_end) VALUES (?, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH))");
$countRows = static function (PDO $pdo, int $userId, string $operation, ?string $status = null): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM usage_events WHERE user_id = ? AND operation = ?' . ($status !== null ? ' AND status = ?' : ''));
    $stmt->execute($status !== null ? [$userId, $operation, $status] : [$userId, $operation]);
    return (int) $stmt->fetchColumn();
};
$subscriptionPlans = static function (PDO $pdo, int $userId): string {
    $stmt = $pdo->prepare('SELECT p.code FROM subscriptions s INNER JOIN plans p ON p.id = s.plan_id WHERE s.user_id = ? ORDER BY s.id');
    $stmt->execute([$userId]);
    return implode(',', $stmt->fetchAll(PDO::FETCH_COLUMN));
};

it_user($pdo, 301, 25);
$gate = rateb_usage_begin($pdo, 301, 72, 'image');
it_expect('usage_begin subscribes a new user to the free plan and opens a reservation', ['ok' => var_export($gate['ok'], true), 'watermark' => var_export($gate['watermark'] ?? null, true), 'plans' => $subscriptionPlans($pdo, 301), 'open' => var_export($pdo->inTransaction(), true)] + it_state($pdo, 301, (int) $gate['id'], 'ai_image'), ['ok' => 'true', 'watermark' => 'true', 'plans' => 'free', 'open' => 'false', 'balance' => 25, 'status' => 'reserved', 'deducted' => 0, 'charges' => 0]);
$outcome = rateb_reservation_charge($pdo, (int) $gate['id'], 301, 'ai_image', 72);
rateb_reservation_settle($pdo, (int) $gate['id'], 301, 'completed', 'ai_image');
it_expect('the reservation from usage_begin is charged once and completes', it_state($pdo, 301, (int) $gate['id'], 'ai_image') + ['outcome' => $outcome], ['outcome' => 'ok', 'balance' => 15, 'status' => 'completed', 'deducted' => 10, 'charges' => 1, 'refunds' => 0]);
$gate2 = rateb_usage_begin($pdo, 301, 72, 'image');
rateb_reservation_charge($pdo, (int) $gate2['id'], 301, 'ai_image', 72);
it_expect('a second usage_begin reuses the free subscription', ['ok' => var_export($gate2['ok'], true), 'plans' => $subscriptionPlans($pdo, 301)] + it_state($pdo, 301, (int) $gate2['id'], 'ai_image'), ['ok' => 'true', 'plans' => 'free', 'balance' => 5, 'status' => 'reserved', 'deducted' => 10, 'charges' => 2]);
$denied = rateb_usage_begin($pdo, 301, 72, 'image');
it_expect('one completed and one running image reach the free limit of 2', ['ok' => var_export($denied['ok'], true), 'code' => $denied['code'], 'id' => $denied['id'], 'reservations' => $countRows($pdo, 301, 'image'), 'open' => var_export($pdo->inTransaction(), true)] + it_state($pdo, 301, (int) $gate2['id'], 'ai_image'), ['ok' => 'false', 'code' => 'plan_limit', 'id' => 0, 'reservations' => 2, 'open' => 'false', 'balance' => 5, 'charges' => 2]);
rateb_reservation_settle($pdo, (int) $gate2['id'], 301, 'failed', 'ai_image');
$gate3 = rateb_usage_begin($pdo, 301, 72, 'image');
it_expect('a refunded image frees its slot and the refund restores the balance', ['ok' => var_export($gate3['ok'], true)] + it_state($pdo, 301, (int) $gate2['id'], 'ai_image'), ['ok' => 'true', 'balance' => 15, 'status' => 'failed', 'charges' => 2, 'refunds' => 1]);
$age->execute([21 * 60, (int) $gate3['id']]);
$gate4 = rateb_usage_begin($pdo, 301, 72, 'image');
$full = rateb_usage_begin($pdo, 301, 72, 'image');
$pdo->prepare('UPDATE usage_events SET created_at = DATE_SUB(NOW(), INTERVAL 40 DAY) WHERE id = ?')->execute([(int) $gate['id']]);
$gate5 = rateb_usage_begin($pdo, 301, 72, 'image');
it_expect('reservations older than 20 minutes and last month\'s images do not count toward the limit', ['abandoned' => var_export($gate4['ok'], true), 'full' => $full['code'], 'last_month' => var_export($gate5['ok'], true), 'reservations' => $countRows($pdo, 301, 'image', 'reserved')], ['abandoned' => 'true', 'full' => 'plan_limit', 'last_month' => 'true', 'reservations' => 3]);

it_user($pdo, 302, 40);
$subscribe->execute([302, $proPlan]);
$voiceGates = [];
foreach (range(1, 4) as $unused) {
    $voiceGate = rateb_usage_begin($pdo, 302, 72, 'voice');
    $voiceGates[] = var_export($voiceGate['ok'], true);
    rateb_reservation_settle($pdo, (int) $voiceGate['id'], 302, 'completed', 'saudi_voice');
}
$proGate = rateb_usage_begin($pdo, 302, 72, 'voice');
$outcome = rateb_reservation_charge($pdo, (int) $proGate['id'], 302, 'saudi_voice', 72);
rateb_reservation_settle($pdo, (int) $proGate['id'], 302, 'completed', 'saudi_voice');
it_expect('voice has no plan count limit and its usage_begin reservation is charged 15 credits', ['gates' => implode(',', $voiceGates), 'watermark' => var_export($proGate['watermark'] ?? null, true), 'outcome' => $outcome, 'plans' => $subscriptionPlans($pdo, 302)] + it_state($pdo, 302, (int) $proGate['id'], 'saudi_voice'), ['gates' => 'true,true,true,true', 'watermark' => 'false', 'outcome' => 'ok', 'plans' => 'pro', 'balance' => 25, 'status' => 'completed', 'deducted' => 15, 'charges' => 1, 'refunds' => 0]);

it_user($pdo, 303, 40);
$subscribe->execute([303, $retiredPlan]);
$inactive = rateb_usage_begin($pdo, 303, 72, 'image');
it_expect('an inactive plan denies usage_begin without a reservation', ['ok' => var_export($inactive['ok'], true), 'code' => $inactive['code'], 'rows' => $countRows($pdo, 303, 'image'), 'plans' => $subscriptionPlans($pdo, 303), 'open' => var_export($pdo->inTransaction(), true)], ['ok' => 'false', 'code' => 'plan_inactive', 'rows' => 0, 'plans' => 'retired', 'open' => 'false']);
$unknown = rateb_usage_begin($pdo, 303, 72, 'not_a_feature');
it_expect('an unknown operation is denied', ['ok' => var_export($unknown['ok'], true), 'code' => $unknown['code']], ['ok' => 'false', 'code' => 'plan_inactive']);

it_user($pdo, 304, 10);
$poorGate = rateb_usage_begin($pdo, 304, 72, 'voice');
$outcome = rateb_reservation_charge($pdo, (int) $poorGate['id'], 304, 'saudi_voice', 72);
rateb_usage_finish($pdo, (int) $poorGate['id'], 'failed');
it_expect('a low-balance voice request is closed by usage_finish without any charge', it_state($pdo, 304, (int) $poorGate['id'], 'saudi_voice') + ['ok' => var_export($poorGate['ok'], true), 'outcome' => $outcome], ['ok' => 'true', 'outcome' => 'credits', 'balance' => 10, 'status' => 'failed', 'deducted' => 0, 'charges' => 0, 'refunds' => 0]);

// 9. Two connections race for the last image slot of a plan; the plan row lock admits one.
it_user($pdo, 305, 50);
$subscribe->execute([305, $proPlan]);
$slot = (int) $pdo->lastInsertId();
foreach (range(1, 2) as $unused) {
    $used = rateb_usage_begin($pdo, 305, 72, 'image');
    rateb_reservation_settle($pdo, (int) $used['id'], 305, 'completed', 'ai_image');
}
[$begun, $overlap] = it_race($holder, $observer, 'begin', 0, 305, 'SELECT s.id AS subscription_id', 'subscriptions', $slot);
$codes = array_map(static fn (array $w): string => isset($w['result']) ? ($w['result']['ok'] ? 'ok' : (string) $w['result']['code']) : 'error: ' . ($w['error'] ?? ''), $begun);
sort($codes);
if (!$overlap) {
    it_report('NOT RUN', 'concurrent usage_begin', 'could not confirm both connections were waiting on the plan lock; results: ' . json_encode($begun));
} else {
    it_expect('two concurrent usage_begin calls for the last slot admit exactly one', ['codes' => implode(',', $codes), 'reserved' => $countRows($pdo, 305, 'image', 'reserved'), 'completed' => $countRows($pdo, 305, 'image', 'completed')], ['codes' => 'ok,plan_limit', 'reserved' => 1, 'completed' => 2]);
}

$pdo->exec('DROP TRIGGER IF EXISTS it_fail_charge');
$pdo->exec('DROP TRIGGER IF EXISTS it_fail_refund');
echo sprintf('SUMMARY pass=%d fail=%d not_run=%d', $results['PASS'], $results['FAIL'], $results['NOT RUN']) . PHP_EOL;
echo 'Test tables remain in ' . $settings['name'] . ' for inspection; run with --cleanup to remove them.' . PHP_EOL;
if ($results['FAIL'] > 0) {
    echo "RESULT=FAIL\n";
    exit(1);
}
if ($results['NOT RUN'] > 0) {
    echo "RESULT=INCOMPLETE\n";
    exit(2);
}
echo "RESULT=PASS\n";
exit(0);
