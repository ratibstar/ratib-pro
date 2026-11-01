<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/video/engine.php';

const RATEB_TEST_DB = 'rateb_ai_worker_concurrency';

function mariadb_report(string $result, string $reason): void
{
    echo 'MARIADB=' . $result . PHP_EOL;
    echo $reason . PHP_EOL;
    exit($result === 'FAIL' ? 1 : 0);
}

function mariadb_settings(): ?array
{
    $host = getenv('RATEB_TEST_MARIADB_HOST');
    $user = getenv('RATEB_TEST_MARIADB_USER');
    $password = getenv('RATEB_TEST_MARIADB_PASSWORD');
    $port = getenv('RATEB_TEST_MARIADB_PORT');
    if (!is_string($host) || $host === '' || !is_string($user) || $user === '' || !is_string($password)) {
        return null;
    }
    return [
        'host' => $host,
        'port' => is_string($port) && ctype_digit($port) ? (int) $port : 3306,
        'user' => $user,
        'password' => $password,
    ];
}

function mariadb_pdo(array $settings, bool $withDatabase): PDO
{
    $dsn = 'mysql:host=' . $settings['host'] . ';port=' . $settings['port'] . ';charset=utf8mb4';
    if ($withDatabase) {
        $dsn .= ';dbname=' . RATEB_TEST_DB;
    }
    $pdo = new PDO($dsn, $settings['user'], $settings['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    if ($withDatabase && (string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== RATEB_TEST_DB) {
        throw new RuntimeException('connected to the wrong database');
    }
    return $pdo;
}

function mariadb_worker(array $settings): void
{
    $jobId = (int) getenv('RATEB_TEST_JOB_ID');
    $pdo = mariadb_pdo($settings, true);
    $pdo->exec('UPDATE test_gate SET ready = ready + 1 WHERE id = 1');
    $deadline = microtime(true) + 8;
    $ready = 0;
    do {
        $ready = (int) $pdo->query('SELECT ready FROM test_gate WHERE id = 1')->fetchColumn();
        if ($ready >= 3) {
            break;
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    if ($ready < 3) {
        fwrite(STDERR, "barrier timeout\n");
        exit(1);
    }
    rateb_video_fail($pdo, [
        'id' => $jobId,
        'campaign_id' => 3,
        'user_id' => 7,
        'usage_id' => 0,
        'status' => 'processing',
        'output_media_id' => null,
        'approval_status' => 'draft',
        'lock_token' => null,
    ], 'provider');
    exit(0);
}

if (RATEB_TEST_DB === 'admin_rateb_ai') {
    mariadb_report('FAIL', 'the test database name must stay separate from production');
}

$settings = mariadb_settings();
if ($settings === null) {
    mariadb_report('NOT RUN', 'MariaDB test settings are not configured');
}
if (getenv('RATEB_TEST_WORKER_MODE') === 'fail') {
    mariadb_worker($settings);
}

try {
    $server = mariadb_pdo($settings, false);
} catch (Throwable $error) {
    mariadb_report('NOT RUN', 'MariaDB is not reachable');
}
$version = (string) $server->query('SELECT VERSION()')->fetchColumn();
if (!str_starts_with($version, '10.11')) {
    mariadb_report('NOT RUN', 'MariaDB 10.11 is not the server version');
}
$server->exec('CREATE DATABASE IF NOT EXISTS `' . RATEB_TEST_DB . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$server = null;

try {
    $setup = mariadb_pdo($settings, true);
} catch (Throwable $error) {
    mariadb_report('NOT RUN', 'the separate test database could not be opened');
}
$setup->exec('DROP TABLE IF EXISTS video_jobs, usage_events, users, test_gate');
$setup->exec('CREATE TABLE users (id INT PRIMARY KEY, credits_balance INT NOT NULL) ENGINE=InnoDB');
$setup->exec("CREATE TABLE usage_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    campaign_id INT NULL,
    operation VARCHAR(64) NOT NULL,
    units INT NOT NULL DEFAULT 1,
    seconds INT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'reserved',
    event_type VARCHAR(64) NULL,
    credits_deducted INT NOT NULL DEFAULT 0,
    idempotency_key VARCHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idempotency_key (idempotency_key)
) ENGINE=InnoDB");
$setup->exec("CREATE TABLE video_jobs (
    id INT PRIMARY KEY,
    campaign_id INT NOT NULL,
    user_id INT NOT NULL,
    status VARCHAR(16) NOT NULL,
    output_media_id INT NULL,
    usage_id INT NOT NULL DEFAULT 0,
    error_code VARCHAR(32) NOT NULL DEFAULT '',
    storyboard MEDIUMTEXT NULL,
    approval_status VARCHAR(16) NOT NULL DEFAULT 'draft',
    lock_token CHAR(32) NULL,
    locked_until DATETIME NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB");
$setup->exec('CREATE TABLE test_gate (id INT PRIMARY KEY, ready TINYINT NOT NULL) ENGINE=InnoDB');
$setup->exec('INSERT INTO test_gate (id, ready) VALUES (1, 0)');

$runPair = static function (int $firstJob, int $secondJob) use ($settings): array {
    $specs = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $base = getenv() ?: [];
    $base['RATEB_TEST_MARIADB_HOST'] = $settings['host'];
    $base['RATEB_TEST_MARIADB_PORT'] = (string) $settings['port'];
    $base['RATEB_TEST_MARIADB_USER'] = $settings['user'];
    $base['RATEB_TEST_MARIADB_PASSWORD'] = $settings['password'];
    $base['RATEB_TEST_WORKER_MODE'] = 'fail';
    $start = static function (int $jobId) use ($specs, $base): array {
        $env = $base;
        $env['RATEB_TEST_JOB_ID'] = (string) $jobId;
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__], $specs, $pipes, null, $env);
        if (!is_resource($process)) {
            throw new RuntimeException('proc_open failed');
        }
        fclose($pipes[0]);
        return [$process, $pipes];
    };
    $processes = [$start($firstJob), $start($secondJob)];
    $gate = mariadb_pdo($settings, true);
    $seen = 0;
    $deadline = microtime(true) + 8;
    while ($seen < 2 && microtime(true) < $deadline) {
        $seen = (int) $gate->query('SELECT ready FROM test_gate WHERE id = 1')->fetchColumn();
        usleep(20000);
    }
    if ($seen < 2) {
        foreach ($processes as [$process]) {
            proc_terminate($process);
        }
        return ['FAIL', 'the two connections did not reach the barrier'];
    }
    $gate->exec('UPDATE test_gate SET ready = 3 WHERE id = 1');
    $gate = null;
    $output = '';
    foreach ($processes as [$process, $pipes]) {
        $output .= stream_get_contents($pipes[1]);
        $output .= stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            return ['FAIL', trim($output) !== '' ? trim($output) : 'a refund connection failed'];
        }
    }
    return ['OK', ''];
};

$assertOnce = static function (PDO $pdo, int $expectedBalance): ?string {
    $balance = (int) $pdo->query('SELECT credits_balance FROM users WHERE id = 7')->fetchColumn();
    $count = (int) $pdo->query("SELECT COUNT(*) FROM usage_events WHERE idempotency_key = 'video_storyboard_charge:5:refund'")->fetchColumn();
    $rows = (int) $pdo->query("SELECT COUNT(*) FROM usage_events WHERE operation = 'video_storyboard_refund'")->fetchColumn();
    if ($balance !== $expectedBalance || $count !== 1 || $rows !== 1) {
        return 'balance=' . $balance . ' expected=' . $expectedBalance . ' key_count=' . $count . ' refund_rows=' . $rows;
    }
    return null;
};

$prepare = static function (PDO $pdo, int $balance, array $jobs, bool $alreadyRefunded): void {
    $pdo->exec('DELETE FROM video_jobs');
    $pdo->exec('DELETE FROM usage_events');
    $pdo->exec('DELETE FROM users');
    $pdo->exec('UPDATE test_gate SET ready = 0 WHERE id = 1');
    $pdo->prepare('INSERT INTO users (id, credits_balance) VALUES (7, ?)')->execute([$balance]);
    $pdo->exec("INSERT INTO usage_events (id, user_id, campaign_id, operation, status, event_type, credits_deducted) VALUES (5, 7, 3, 'video_storyboard', 'completed', 'video_storyboard', 20)");
    if ($alreadyRefunded) {
        $pdo->exec("INSERT INTO usage_events (user_id, campaign_id, operation, status, event_type, credits_deducted, idempotency_key) VALUES (7, 3, 'video_storyboard_refund', 'completed', 'video_storyboard_refund', 0, 'video_storyboard_charge:5:refund')");
    }
    $insert = $pdo->prepare("INSERT INTO video_jobs (id, campaign_id, user_id, status, usage_id, updated_at) VALUES (?, 3, 7, 'processing', 0, '2026-10-28 00:00:00')");
    foreach ($jobs as $jobId) {
        $insert->execute([$jobId]);
    }
};

$prepare($setup, 100, [21, 22], false);
$setup = null;
$concurrent = $runPair(21, 22);
if ($concurrent[0] !== 'OK') {
    mariadb_report('FAIL', $concurrent[1]);
}
$check = mariadb_pdo($settings, true);
$problem = $assertOnce($check, 120);
if ($problem !== null) {
    mariadb_report('FAIL', 'concurrent failure refund was not exactly once: ' . $problem);
}
$prepare($check, 120, [31, 32], true);
$check = null;
$retry = $runPair(31, 32);
if ($retry[0] !== 'OK') {
    mariadb_report('FAIL', $retry[1]);
}
$check = mariadb_pdo($settings, true);
$problem = $assertOnce($check, 120);
if ($problem !== null) {
    mariadb_report('FAIL', 'concurrent retry credited the balance again: ' . $problem);
}
$check->exec('DROP TABLE IF EXISTS video_jobs, usage_events, users, test_gate');
mariadb_report('PASS', 'one refund won both the concurrent failure and the concurrent retry');
