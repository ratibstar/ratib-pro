<?php
declare(strict_types=1);

/*
 * Real MySQL/MariaDB integration test for the cron video worker: job creation, named
 * locks, worker passes, crash recovery, reaping, and the credit ledger. ffmpeg renders
 * real clips; the video provider is a local fake, so nothing is sent over the network.
 *
 * It never uses app/bootstrap.php or config/database.php credentials. It connects only to
 * the database named in RATEB_TEST_DB_NAME and refuses to run unless that database is a
 * disposable test database that is empty or was created by this test.
 *
 * RUN (bash):
 *   RATEB_TEST_DB_HOST=127.0.0.1 RATEB_TEST_DB_NAME=rateb_ai_video_test \
 *   RATEB_TEST_DB_CONFIRM=rateb_ai_video_test RATEB_TEST_DB_USER=... RATEB_TEST_DB_PASS=... \
 *   php tests/integration/video-worker-mysql.php
 * CLEANUP: php tests/integration/video-worker-mysql.php --cleanup
 *
 * Exit codes: 0 all cases passed, 1 a case failed, 2 refused or incomplete (nothing proven).
 */

const VW_MARKER = 'rateb_video_worker_marker';
const VW_COST = 20;

function vw_refuse(string $why): never
{
    fwrite(STDERR, 'REFUSED: ' . $why . PHP_EOL);
    exit(2);
}

/** @return array{host:string,port:int,name:string,user:string,pass:string} */
function vw_settings(): array
{
    $name = (string) getenv('RATEB_TEST_DB_NAME');
    $host = (string) (getenv('RATEB_TEST_DB_HOST') ?: '127.0.0.1');
    if ($name === '' || preg_match('/^[A-Za-z0-9_]{1,64}$/', $name) !== 1 || preg_match('/(^|_)test(_|$)/i', $name) !== 1) {
        vw_refuse('RATEB_TEST_DB_NAME must name a database with "test" as its own word.');
    }
    if ((string) getenv('RATEB_TEST_DB_CONFIRM') !== $name) {
        vw_refuse('set RATEB_TEST_DB_CONFIRM to the exact test database name to confirm it may be emptied.');
    }
    $production = @include __DIR__ . '/../../config/database.php';
    if (is_array($production) && strcasecmp((string) ($production['database'] ?? ''), $name) === 0) {
        vw_refuse('the test database name matches the production database name.');
    }
    if (!in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1'], true) && getenv('RATEB_TEST_DB_ALLOW_REMOTE') !== '1') {
        vw_refuse('the test host is not local.');
    }
    return [
        'host' => $host,
        'port' => (int) (getenv('RATEB_TEST_DB_PORT') ?: 3306),
        'name' => $name,
        'user' => (string) getenv('RATEB_TEST_DB_USER'),
        'pass' => (string) getenv('RATEB_TEST_DB_PASS'),
    ];
}

function vw_connect(array $s): PDO
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
        vw_refuse('connected to a different database than RATEB_TEST_DB_NAME.');
    }
    return $pdo;
}

function vw_guard(PDO $pdo): void
{
    $tables = array_map('strval', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
    if ($tables !== [] && !in_array(VW_MARKER, $tables, true)) {
        vw_refuse('the test database has tables that this test did not create. Use an empty dedicated database.');
    }
}

function vw_drop(PDO $pdo): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', (string) $table) . '`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

$settings = vw_settings();
$mediaRoot = (string) (getenv('VW_MEDIA_ROOT') ?: '');
if ($mediaRoot === '') {
    $mediaRoot = sys_get_temp_dir() . '/rateb-vw-media-' . bin2hex(random_bytes(4));
    putenv('VW_MEDIA_ROOT=' . $mediaRoot);
}
if (!is_dir($mediaRoot)) {
    mkdir($mediaRoot, 0700, true);
}
define('MEDIA_ROOT', $mediaRoot);
putenv('RUNWAY_API_KEY=vw-test-provider-is-faked');
putenv('RATEB_VIDEO_PROVIDER');

try {
    $GLOBALS['vw_pdo'] = vw_connect($settings);
} catch (PDOException $error) {
    vw_refuse('could not connect to the test database (' . $error->getCode() . '). Nothing was tested.');
}

function db(): PDO
{
    return $GLOBALS['vw_pdo'];
}

require_once __DIR__ . '/../../app/schema.php';
require_once __DIR__ . '/../../app/media/library.php';
require_once __DIR__ . '/../../app/video/worker.php';

/** Local stand-in for Runway. Every image request is appended to VW_CALLS. */
final class VwProvider implements RatebVideoProvider
{
    public function __construct(private string $mode)
    {
    }

    public function name(): string
    {
        return 'runway';
    }

    public function configured(): bool
    {
        return true;
    }

    public function generateFromImage(string $imageDataUri, string $prompt, int $seconds, string $ratio): array
    {
        $calls = (string) getenv('VW_CALLS');
        if ($calls !== '') {
            file_put_contents($calls, $this->mode . "\n", FILE_APPEND | LOCK_EX);
        }
        if (!str_starts_with($imageDataUri, 'data:image/jpeg;base64,')) {
            throw new RuntimeException('rejected_image');
        }
        return match ($this->mode) {
            'local' => throw new RuntimeException('rejected_credit'),
            'slow-local' => (static function (): never {
                usleep(2500000);
                throw new RuntimeException('rejected_credit');
            })(),
            'crash-second' => $this->crashSecond(),
            'reject' => throw new RuntimeException('rejected'),
            'remote' => ['task_id' => 'vw-task-' . $seconds],
            'badtask' => ['task_id' => '../../etc/passwd'],
            default => throw new LogicException('unknown provider mode'),
        };
    }

    private function crashSecond(): array
    {
        $calls = file((string) getenv('VW_CALLS'), FILE_IGNORE_NEW_LINES) ?: [];
        $mine = count(array_filter($calls, static fn (string $line): bool => $line === 'crash-second'));
        if ($mine >= 2) {
            throw new Error('simulated worker crash');
        }
        throw new RuntimeException('rejected_credit');
    }

    public function generateFromText(string $prompt, int $seconds, string $ratio): array
    {
        throw new LogicException('not used');
    }

    public function generateFromVideo(string $videoUri, string $prompt, int $seconds, string $ratio): array
    {
        throw new LogicException('not used');
    }

    public function getTaskStatus(string $taskId): array
    {
        return ['status' => 'completed', 'url' => 'https://provider.invalid/' . $taskId . '.mp4', 'error' => ''];
    }

    public function downloadResult(string $url): string
    {
        return (string) file_get_contents((string) getenv('VW_CLIP'));
    }
}

/** @return array<int,array<string,mixed>> */
function vw_inputs(PDO $pdo, int $campaignId): array
{
    $images = $pdo->prepare("SELECT * FROM campaign_media WHERE campaign_id = ? AND kind = 'image' ORDER BY id");
    $images->execute([$campaignId]);
    return $images->fetchAll();
}

function vw_campaign_row(PDO $pdo, int $campaignId): array
{
    $stmt = $pdo->prepare('SELECT * FROM campaigns WHERE id = ?');
    $stmt->execute([$campaignId]);
    return $stmt->fetch() ?: [];
}

/** @return array{ok:bool,code:string,job_id:int} */
function vw_start(PDO $pdo, int $campaignId, int $seconds = 5): array
{
    $campaign = vw_campaign_row($pdo, $campaignId);
    $strategy = ['output_type' => 'strategy', 'approval_status' => 'approved', 'content' => '{}'];
    $copy = [['output_type' => 'short_ad', 'approval_status' => 'approved', 'content' => 'Fresh dates delivered today.']];
    return rateb_video_start($pdo, $campaign, $strategy, $copy, vw_inputs($pdo, $campaignId), $seconds);
}

if (($argv[1] ?? '') === '--worker') {
    try {
        $pdo = db();
        $pdo->exec('SET SESSION innodb_lock_wait_timeout = 30');
        $id = (int) ($argv[3] ?? 0);
        $result = match ($argv[2] ?? '') {
            'run' => rateb_video_worker_run($pdo, ['provider' => new VwProvider((string) ($argv[4] ?? 'local')), 'seconds' => 50]),
            'start' => vw_start($pdo, $id),
            'reap' => (static function () use ($pdo, $id): string {
                $job = $pdo->prepare('SELECT * FROM video_jobs WHERE id = ?');
                $job->execute([$id]);
                return (string) rateb_video_reap($pdo, $job->fetch())['status'];
            })(),
            'fail' => (static function () use ($pdo, $id): bool {
                $job = $pdo->prepare('SELECT * FROM video_jobs WHERE id = ?');
                $job->execute([$id]);
                return rateb_video_fail($pdo, $job->fetch(), 'timeout');
            })(),
            default => throw new RuntimeException('unknown worker action'),
        };
        echo json_encode(['result' => $result]), PHP_EOL;
        exit(0);
    } catch (Throwable $error) {
        echo json_encode(['error' => get_class($error) . ': ' . $error->getMessage()]), PHP_EOL;
        exit(1);
    }
}

$pdo = db();
vw_guard($pdo);
if (in_array('--cleanup', $argv, true)) {
    vw_drop($pdo);
    echo 'CLEANED: removed video worker test tables from ' . $settings['name'] . PHP_EOL;
    exit(0);
}

$results = ['PASS' => 0, 'FAIL' => 0, 'NOT RUN' => 0];
function vw_report(string $outcome, string $name, string $detail = ''): void
{
    global $results;
    $results[$outcome]++;
    echo $outcome . ' ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
}

/** @param array<string,mixed> $expected */
function vw_expect(string $name, array $actual, array $expected): void
{
    $diff = [];
    foreach ($expected as $key => $value) {
        if (($actual[$key] ?? null) !== $value) {
            $diff[] = $key . '=' . var_export($actual[$key] ?? null, true) . ' (expected ' . var_export($value, true) . ')';
        }
    }
    vw_report($diff === [] ? 'PASS' : 'FAIL', $name, implode(', ', $diff));
}

echo 'SERVER ' . (string) $pdo->query('SELECT VERSION()')->fetchColumn() . ' DATABASE ' . $settings['name'] . PHP_EOL;
vw_drop($pdo);
$pdo->exec('CREATE TABLE ' . VW_MARKER . ' (created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
$pdo->exec("CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL DEFAULT '',
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("CREATE TABLE campaigns (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    product_name VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT NULL,
    price VARCHAR(64) NULL,
    target_customer VARCHAR(255) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("CREATE TABLE campaign_media (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    campaign_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    kind VARCHAR(16) NOT NULL,
    source VARCHAR(16) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(64) NOT NULL,
    mime VARCHAR(64) NOT NULL,
    bytes INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY campaign_user (campaign_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec('ALTER TABLE campaigns AUTO_INCREMENT = 500');
ensure_campaign_schema($pdo);

$ffmpeg = rateb_video_ffmpeg_path();
$hasFfmpeg = function_exists('proc_open') && rateb_video_run_ffmpeg(escapeshellarg($ffmpeg) . ' -nostdin -hide_banner -version', 30);
$hasGd = function_exists('imagecreatefromstring');
$clip = $mediaRoot . '/provider-clip.mp4';
if ($hasFfmpeg) {
    rateb_video_run_ffmpeg(escapeshellarg($ffmpeg) . ' -nostdin -loglevel error -y -f lavfi -i color=c=0x0f766e:s=720x1280:d=5 -r 25 -c:v libx264 -pix_fmt yuv420p ' . escapeshellarg($clip), 120);
}
putenv('VW_CLIP=' . $clip);
$calls = $mediaRoot . '/provider-calls.txt';
putenv('VW_CALLS=' . $calls);
echo 'FFMPEG ' . ($hasFfmpeg ? 'yes' : 'no') . ' GD ' . ($hasGd ? 'yes' : 'no') . PHP_EOL;

$initial = [];
$planIds = [];
foreach ($pdo->query('SELECT id, code FROM plans') as $row) {
    $planIds[(string) $row['code']] = (int) $row['id'];
}

function vw_user(PDO $pdo, array &$initial, array $planIds, int $balance, string $plan): int
{
    $pdo->prepare('INSERT INTO users (name, email, credits_balance) VALUES (?, ?, ?)')->execute(['VW', 'vw-' . bin2hex(random_bytes(5)) . '@example.invalid', $balance]);
    $id = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO subscriptions (user_id, plan_id, status, period_start, period_end) VALUES (?, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH))")->execute([$id, $planIds[$plan]]);
    $initial[$id] = $balance;
    return $id;
}

function vw_campaign(PDO $pdo, int $userId): int
{
    $pdo->prepare("INSERT INTO campaigns (user_id, title, product_name, description, target_customer) VALUES (?, 'Dates', 'Premium dates', 'Fresh Saudi dates.', 'Families')")->execute([$userId]);
    $campaignId = (int) $pdo->lastInsertId();
    $image = imagecreatetruecolor(360, 640);
    imagefill($image, 0, 0, imagecolorallocate($image, 15, 118, 110));
    imagefilledrectangle($image, 80, 200, 280, 440, imagecolorallocate($image, 240, 200, 90));
    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();
    imagedestroy($image);
    media_store_bytes(['id' => $campaignId, 'user_id' => $userId], 'image', 'upload', 'product.png', 'image/png', $png);
    return $campaignId;
}

function vw_job(PDO $pdo, int $jobId): array
{
    $stmt = $pdo->prepare('SELECT * FROM video_jobs WHERE id = ?');
    $stmt->execute([$jobId]);
    return $stmt->fetch() ?: ['status' => 'missing', 'error_code' => '', 'output_media_id' => null, 'usage_id' => 0, 'storyboard' => '[]'];
}

/** @return array<string,int|string> */
function vw_ledger(PDO $pdo, int $userId, int $jobId = 0): array
{
    $balance = $pdo->prepare('SELECT credits_balance FROM users WHERE id = ?');
    $balance->execute([$userId]);
    $count = $pdo->prepare("SELECT SUM(operation = 'video_storyboard') AS charges, SUM(operation = 'video_storyboard_refund') AS refunds, SUM(operation = 'video') AS reservations FROM usage_events WHERE user_id = ?");
    $count->execute([$userId]);
    $counts = $count->fetch() ?: [];
    $out = [
        'balance' => (int) $balance->fetchColumn(),
        'charges' => (int) ($counts['charges'] ?? 0),
        'refunds' => (int) ($counts['refunds'] ?? 0),
        'reservations' => (int) ($counts['reservations'] ?? 0),
    ];
    if ($jobId > 0) {
        $job = vw_job($pdo, $jobId);
        $usage = $pdo->prepare('SELECT status FROM usage_events WHERE id = ?');
        $usage->execute([(int) $job['usage_id']]);
        $media = $pdo->prepare("SELECT COUNT(*) FROM campaign_media WHERE campaign_id = ? AND kind = 'video'");
        $media->execute([(int) ($job['campaign_id'] ?? 0)]);
        $out += [
            'job' => (string) $job['status'],
            'error' => (string) $job['error_code'],
            'usage' => (string) ($usage->fetchColumn() ?: 'missing'),
            'videos' => (int) $media->fetchColumn(),
        ];
    }
    return $out;
}

function vw_calls(string $calls): int
{
    return is_file($calls) ? count(file($calls, FILE_IGNORE_NEW_LINES) ?: []) : 0;
}

function vw_spawn(array $args): array
{
    $process = proc_open(array_merge([PHP_BINARY, __FILE__, '--worker'], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('could not start worker');
    }
    return [$process, $pipes];
}

function vw_collect(array $worker): array
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

function vw_waiting(PDO $observer, string $prefix): int
{
    $stmt = $observer->prepare('SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID <> CONNECTION_ID() AND DB = DATABASE() AND INFO LIKE ?');
    $stmt->execute([$prefix . '%']);
    return (int) $stmt->fetchColumn();
}

function vw_wait_for(PDO $observer, string $prefix, int $count): bool
{
    $deadline = microtime(true) + 15;
    while (microtime(true) < $deadline) {
        if (vw_waiting($observer, $prefix) >= $count) {
            return true;
        }
        usleep(100000);
    }
    return false;
}

function vw_video_ok(PDO $pdo, int $jobId): bool
{
    $stmt = $pdo->prepare('SELECT m.* FROM video_jobs j INNER JOIN campaign_media m ON m.id = j.output_media_id WHERE j.id = ?');
    $stmt->execute([$jobId]);
    $row = $stmt->fetch();
    $path = $row ? media_resolve_path($row) : null;
    return $path !== null && substr((string) file_get_contents($path, false, null, 0, 16), 4, 4) === 'ftyp';
}

$holder = vw_connect($settings);
$observer = vw_connect($settings);
$local = new VwProvider('local');
$render = $hasFfmpeg && $hasGd;

// 1. Creating a job is idempotent and charges once.
$u1 = vw_user($pdo, $initial, $planIds, 50, 'starter');
$c1 = vw_campaign($pdo, $u1);
$first = vw_start($pdo, $c1);
$again = vw_start($pdo, $c1);
vw_expect('starting a video twice returns the same queued job and charges once', vw_ledger($pdo, $u1, $first['job_id']) + ['same' => var_export($first['ok'] && $again['ok'] && $first['job_id'] === $again['job_id'], true)], ['same' => 'true', 'balance' => 30, 'charges' => 1, 'refunds' => 0, 'reservations' => 1, 'job' => 'queued', 'usage' => 'reserved']);

// 2. Two concurrent requests for the same campaign create one job and one charge.
$u2 = vw_user($pdo, $initial, $planIds, 50, 'starter');
$c2 = vw_campaign($pdo, $u2);
rateb_video_lock($holder, 'video-start:' . $c2, 0);
$starts = [vw_spawn(['start', (string) $c2]), vw_spawn(['start', (string) $c2])];
$overlap = vw_wait_for($observer, 'SELECT GET_LOCK', 2);
rateb_video_unlock($holder, 'video-start:' . $c2);
$started = [vw_collect($starts[0]), vw_collect($starts[1])];
$jobIds = array_values(array_unique(array_map(static fn (array $w): int => (int) ($w['result']['job_id'] ?? 0), $started)));
$jobRows = (int) $pdo->query('SELECT COUNT(*) FROM video_jobs WHERE campaign_id = ' . $c2)->fetchColumn();
if (!$overlap) {
    vw_report('NOT RUN', 'concurrent video starts', 'both requests were not seen waiting on the campaign lock: ' . json_encode($started));
} else {
    vw_expect('two concurrent starts for one campaign create one job and charge once', vw_ledger($pdo, $u2) + ['job_ids' => count($jobIds), 'job_rows' => $jobRows, 'nonzero' => var_export(($jobIds[0] ?? 0) > 0, true)], ['job_ids' => 1, 'job_rows' => 1, 'nonzero' => 'true', 'balance' => 30, 'charges' => 1, 'refunds' => 0, 'reservations' => 1]);
}

// 3. Not enough credits: no job, no charge, reservation closed.
$u3 = vw_user($pdo, $initial, $planIds, 10, 'starter');
$c3 = vw_campaign($pdo, $u3);
$poor = vw_start($pdo, $c3);
$closed = (int) $pdo->query("SELECT COUNT(*) FROM usage_events WHERE user_id = {$u3} AND operation = 'video' AND status = 'failed'")->fetchColumn();
vw_expect('a start without enough credits creates no job and charges nothing', vw_ledger($pdo, $u3) + ['code' => $poor['code'], 'closed' => $closed, 'jobs' => (int) $pdo->query('SELECT COUNT(*) FROM video_jobs WHERE campaign_id = ' . $c3)->fetchColumn()], ['code' => 'credits', 'closed' => 1, 'jobs' => 0, 'balance' => 10, 'charges' => 0, 'refunds' => 0]);

if (!$render) {
    vw_report('NOT RUN', 'worker rendering cases', 'ffmpeg and GD are required');
} else {
    // 4. The worker renders a queued job once; a rerun changes nothing.
    @unlink($calls);
    $stats = rateb_video_worker_run($pdo, ['provider' => $local]);
    vw_expect('the worker renders a queued job with ffmpeg and completes it', vw_ledger($pdo, $u1, $first['job_id']) + ['valid_mp4' => var_export(vw_video_ok($pdo, $first['job_id']), true), 'locked' => var_export($stats['locked'], true)], ['locked' => 'true', 'valid_mp4' => 'true', 'job' => 'completed', 'error' => '', 'usage' => 'completed', 'videos' => 1, 'balance' => 30, 'charges' => 1, 'refunds' => 0]);
    vw_expect('the same pass also completes the job created by the concurrent starts', vw_ledger($pdo, $u2, $jobIds[0] ?? 0), ['job' => 'completed', 'usage' => 'completed', 'videos' => 1, 'balance' => 30, 'charges' => 1, 'refunds' => 0]);
    $callsBefore = vw_calls($calls);
    $rerun = rateb_video_worker_run($pdo, ['provider' => $local]);
    vw_expect('a second pass after completion adds no video, charge, or refund', vw_ledger($pdo, $u1, $first['job_id']) + ['jobs' => $rerun['jobs'], 'new_calls' => vw_calls($calls) - $callsBefore], ['jobs' => 0, 'new_calls' => 0, 'job' => 'completed', 'videos' => 1, 'balance' => 30, 'charges' => 1, 'refunds' => 0]);

    // 5. Two worker processes at once: one pass runs, the other exits.
    $u5 = vw_user($pdo, $initial, $planIds, 50, 'starter');
    $c5 = vw_campaign($pdo, $u5);
    $j5 = vw_start($pdo, $c5)['job_id'];
    @unlink($calls);
    $runs = [vw_spawn(['run', '0', 'slow-local'])];
    usleep(300000);
    $runs[] = vw_spawn(['run', '0', 'slow-local']);
    $ran = [vw_collect($runs[0]), vw_collect($runs[1])];
    $lockedRuns = count(array_filter($ran, static fn (array $w): bool => ($w['result']['locked'] ?? null) === true));
    $skipped = count(array_filter($ran, static fn (array $w): bool => ($w['result']['locked'] ?? null) === false));
    vw_expect('two concurrent workers: exactly one runs and the job renders once', vw_ledger($pdo, $u5, $j5) + ['ran' => $lockedRuns, 'skipped' => $skipped, 'provider_calls' => vw_calls($calls)], ['ran' => 1, 'skipped' => 1, 'provider_calls' => 1, 'job' => 'completed', 'videos' => 1, 'balance' => 30, 'charges' => 1, 'refunds' => 0]);

    // 6. A job held by another process is skipped, then processed once released.
    $u6 = vw_user($pdo, $initial, $planIds, 50, 'starter');
    $c6 = vw_campaign($pdo, $u6);
    $j6 = vw_start($pdo, $c6)['job_id'];
    @unlink($calls);
    rateb_video_lock($holder, 'video-job:' . $j6, 0);
    $busy = rateb_video_worker_job($pdo, $j6, $local);
    $held = vw_ledger($pdo, $u6, $j6) + ['outcome' => $busy, 'provider_calls' => vw_calls($calls)];
    rateb_video_unlock($holder, 'video-job:' . $j6);
    vw_expect('a job locked by another process is not touched', $held, ['outcome' => 'busy', 'provider_calls' => 0, 'job' => 'queued', 'usage' => 'reserved', 'videos' => 0, 'balance' => 30]);
    $released = rateb_video_worker_job($pdo, $j6, $local);
    vw_expect('the job is processed once its lock is released', vw_ledger($pdo, $u6, $j6) + ['outcome' => $released], ['outcome' => 'completed', 'job' => 'completed', 'videos' => 1, 'balance' => 30, 'charges' => 1, 'refunds' => 0]);

    // 7. A crash between scenes resumes without re-rendering finished scenes or duplicating the video.
    $u7 = vw_user($pdo, $initial, $planIds, 50, 'growth');
    $c7 = vw_campaign($pdo, $u7);
    $j7 = vw_start($pdo, $c7, 15)['job_id'];
    @unlink($calls);
    $crashed = rateb_video_worker_run($pdo, ['provider' => new VwProvider('crash-second')]);
    $midway = vw_job($pdo, $j7);
    $scenes = json_decode((string) $midway['storyboard'], true);
    vw_expect('a crash after the first scene leaves the job processing with that scene saved', vw_ledger($pdo, $u7, $j7) + ['errors' => $crashed['errors'], 'scenes' => count($scenes), 'first' => (string) ($scenes[0]['status'] ?? ''), 'second' => (string) ($scenes[1]['status'] ?? '')], ['errors' => 1, 'scenes' => 2, 'first' => 'ready', 'second' => 'queued', 'job' => 'processing', 'usage' => 'reserved', 'videos' => 0, 'balance' => 30, 'refunds' => 0]);
    $before = vw_calls($calls);
    rateb_video_worker_run($pdo, ['provider' => $local]);
    vw_expect('the next pass renders only the missing scene and completes once', vw_ledger($pdo, $u7, $j7) + ['new_calls' => vw_calls($calls) - $before, 'valid_mp4' => var_export(vw_video_ok($pdo, $j7), true)], ['new_calls' => 1, 'valid_mp4' => 'true', 'job' => 'completed', 'usage' => 'completed', 'videos' => 1, 'balance' => 30, 'charges' => 1, 'refunds' => 0]);

    // 8. The provider path: create a task, then download and compose on the next pass.
    $u8 = vw_user($pdo, $initial, $planIds, 50, 'starter');
    $c8 = vw_campaign($pdo, $u8);
    $j8 = vw_start($pdo, $c8)['job_id'];
    $remote = new VwProvider('remote');
    rateb_video_worker_job($pdo, $j8, $remote);
    $task = (string) (json_decode((string) vw_job($pdo, $j8)['storyboard'], true)[0]['task_id'] ?? '');
    rateb_video_worker_job($pdo, $j8, $remote);
    vw_expect('a provider task is saved, then downloaded and completed on the next pass', vw_ledger($pdo, $u8, $j8) + ['task' => $task, 'valid_mp4' => var_export(vw_video_ok($pdo, $j8), true)], ['task' => 'vw-task-5', 'valid_mp4' => 'true', 'job' => 'completed', 'videos' => 1, 'balance' => 30, 'charges' => 1, 'refunds' => 0]);

    // 9. Free plan videos carry the RATEB AI watermark pass.
    $u9 = vw_user($pdo, $initial, $planIds, 50, 'free');
    $c9 = vw_campaign($pdo, $u9);
    $j9 = vw_start($pdo, $c9)['job_id'];
    rateb_video_worker_job($pdo, $j9, $local);
    vw_expect('a watermarked free-plan video renders and completes', vw_ledger($pdo, $u9, $j9) + ['watermark' => (int) vw_job($pdo, $j9)['watermark']], ['watermark' => 1, 'job' => 'completed', 'error' => '', 'videos' => 1, 'balance' => 30, 'refunds' => 0]);
}

// 10. A provider failure fails the job and refunds exactly once.
$u10 = vw_user($pdo, $initial, $planIds, 50, 'starter');
$c10 = vw_campaign($pdo, $u10);
$j10 = vw_start($pdo, $c10)['job_id'];
$outcome = rateb_video_worker_job($pdo, $j10, new VwProvider('reject'));
vw_expect('a provider rejection fails the job and refunds the charge', vw_ledger($pdo, $u10, $j10) + ['outcome' => $outcome], ['outcome' => 'failed', 'job' => 'failed', 'error' => 'rejected', 'usage' => 'failed', 'videos' => 0, 'balance' => 50, 'charges' => 1, 'refunds' => 1]);
$repeat = rateb_video_fail($pdo, vw_job($pdo, $j10), 'provider');
$reconciled = rateb_video_reconcile($pdo);
vw_expect('failing it again or reconciling refunds nothing more', vw_ledger($pdo, $u10, $j10) + ['repeat' => var_export($repeat, true), 'error_kept' => vw_job($pdo, $j10)['error_code']], ['repeat' => 'false', 'error_kept' => 'rejected', 'balance' => 50, 'charges' => 1, 'refunds' => 1]);
$late = rateb_video_complete($pdo, vw_job($pdo, $j10), vw_campaign_row($pdo, $c10), 'not-a-video');
vw_expect('a late completion cannot revive a failed job', vw_ledger($pdo, $u10, $j10) + ['late' => var_export($late, true)], ['late' => 'NULL', 'job' => 'failed', 'videos' => 0, 'balance' => 50, 'refunds' => 1]);

// 11. Bad provider task ids and tampered storyboards are refused and refunded.
$u11 = vw_user($pdo, $initial, $planIds, 50, 'starter');
$c11 = vw_campaign($pdo, $u11);
$j11 = vw_start($pdo, $c11)['job_id'];
rateb_video_worker_job($pdo, $j11, new VwProvider('badtask'));
vw_expect('a provider task id with path characters fails the job and refunds', vw_ledger($pdo, $u11, $j11), ['job' => 'failed', 'error' => 'provider', 'balance' => 50, 'refunds' => 1]);
$u12 = vw_user($pdo, $initial, $planIds, 50, 'starter');
$c12 = vw_campaign($pdo, $u12);
$j12 = vw_start($pdo, $c12)['job_id'];
$pdo->prepare('UPDATE video_jobs SET storyboard = ? WHERE id = ?')->execute([json_encode([['image_id' => '1 OR 1=1', 'duration' => 5, 'prompt' => 'x', 'task_id' => '', 'status' => 'queued']]), $j12]);
rateb_video_worker_job($pdo, $j12, $local);
vw_expect('a tampered storyboard fails as invalid and refunds', vw_ledger($pdo, $u12, $j12), ['job' => 'failed', 'error' => 'invalid', 'balance' => 50, 'refunds' => 1]);
$u13 = vw_user($pdo, $initial, $planIds, 50, 'starter');
$c13 = vw_campaign($pdo, $u13);
$j13 = vw_start($pdo, $c13)['job_id'];
$pdo->prepare('DELETE FROM campaigns WHERE id = ?')->execute([$c13]);
rateb_video_worker_job($pdo, $j13, $local);
vw_expect('a job whose campaign no longer belongs to its user fails and refunds', vw_ledger($pdo, $u13, $j13), ['job' => 'failed', 'error' => 'invalid', 'balance' => 50, 'refunds' => 1]);

// 12. Stalled jobs: concurrent reapers refund once; a held job is never reaped.
$age = $pdo->prepare('UPDATE video_jobs SET updated_at = DATE_SUB(NOW(), INTERVAL ? SECOND) WHERE id = ?');
$u14 = vw_user($pdo, $initial, $planIds, 50, 'starter');
$c14 = vw_campaign($pdo, $u14);
$j14 = vw_start($pdo, $c14)['job_id'];
$age->execute([rateb_video_stale_seconds() - 60, $j14]);
$young = rateb_video_reap($pdo, vw_job($pdo, $j14));
vw_expect('a job inside the time limit is not reaped', vw_ledger($pdo, $u14, $j14) + ['status' => (string) $young['status']], ['status' => 'queued', 'balance' => 30, 'refunds' => 0]);
$age->execute([rateb_video_stale_seconds() + 60, $j14]);
rateb_video_lock($holder, 'video-job:' . $j14, 0);
$heldReap = rateb_video_reap($pdo, vw_job($pdo, $j14));
rateb_video_unlock($holder, 'video-job:' . $j14);
vw_expect('a stalled job held by a worker is not reaped', vw_ledger($pdo, $u14, $j14) + ['status' => (string) $heldReap['status']], ['status' => 'queued', 'balance' => 30, 'refunds' => 0]);
$reapers = [vw_spawn(['reap', (string) $j14]), vw_spawn(['reap', (string) $j14])];
$reaped = [vw_collect($reapers[0]), vw_collect($reapers[1])];
$reapErrors = implode('; ', array_filter(array_map(static fn (array $w): string => (string) ($w['error'] ?? ''), $reaped)));
vw_expect('two concurrent reapers fail a stalled job and refund exactly once', vw_ledger($pdo, $u14, $j14) + ['errors' => $reapErrors], ['errors' => '', 'job' => 'failed', 'error' => 'timeout', 'usage' => 'failed', 'balance' => 50, 'charges' => 1, 'refunds' => 1]);

$u15 = vw_user($pdo, $initial, $planIds, 50, 'starter');
$c15 = vw_campaign($pdo, $u15);
$j15 = vw_start($pdo, $c15)['job_id'];
$holder->beginTransaction();
$holder->prepare('SELECT id FROM video_jobs WHERE id = ? FOR UPDATE')->execute([$j15]);
$failers = [vw_spawn(['fail', (string) $j15]), vw_spawn(['fail', (string) $j15])];
$overlap = vw_wait_for($observer, "UPDATE video_jobs SET status = 'failed'", 2);
$holder->commit();
$failed = [vw_collect($failers[0]), vw_collect($failers[1])];
$winners = count(array_filter($failed, static fn (array $w): bool => ($w['result'] ?? null) === true));
if (!$overlap) {
    vw_report('NOT RUN', 'concurrent job failure', 'both connections were not seen waiting on the job row: ' . json_encode($failed));
} else {
    vw_expect('two connections failing the same job at once refund exactly once', vw_ledger($pdo, $u15, $j15) + ['winners' => $winners], ['winners' => 1, 'job' => 'failed', 'balance' => 50, 'charges' => 1, 'refunds' => 1]);
}

// 13. Crash recovery for the ledger: interrupted settles and orphan reservations.
$u16 = vw_user($pdo, $initial, $planIds, 50, 'starter');
$c16 = vw_campaign($pdo, $u16);
$j16 = vw_start($pdo, $c16)['job_id'];
$pdo->prepare("UPDATE video_jobs SET status = 'completed' WHERE id = ?")->execute([$j16]);
$u17 = vw_user($pdo, $initial, $planIds, 50, 'starter');
$c17 = vw_campaign($pdo, $u17);
$j17 = vw_start($pdo, $c17)['job_id'];
$pdo->prepare("UPDATE video_jobs SET status = 'failed', error_code = 'provider' WHERE id = ?")->execute([$j17]);
$u18 = vw_user($pdo, $initial, $planIds, 50, 'starter');
$orphanOld = rateb_usage_begin($pdo, $u18, null, 'video', 5)['id'];
rateb_reservation_charge($pdo, $orphanOld, $u18, 'video_storyboard', null);
$orphanYoung = rateb_usage_begin($pdo, $u18, null, 'video', 5)['id'];
rateb_reservation_charge($pdo, $orphanYoung, $u18, 'video_storyboard', null);
$pdo->prepare('UPDATE usage_events SET created_at = DATE_SUB(NOW(), INTERVAL ? SECOND) WHERE id = ?')->execute([rateb_video_stale_seconds() + 60, $orphanOld]);
$fixed = rateb_video_reconcile($pdo);
$youngStatus = (string) $pdo->query('SELECT status FROM usage_events WHERE id = ' . (int) $orphanYoung)->fetchColumn();
vw_expect('a crash after completion is settled as completed without a refund', vw_ledger($pdo, $u16, $j16), ['job' => 'completed', 'usage' => 'completed', 'balance' => 30, 'charges' => 1, 'refunds' => 0]);
vw_expect('a crash after failure is settled with exactly one refund', vw_ledger($pdo, $u17, $j17), ['job' => 'failed', 'usage' => 'failed', 'balance' => 50, 'charges' => 1, 'refunds' => 1]);
vw_expect('an old charged reservation without a job is refunded; a recent one is left alone', vw_ledger($pdo, $u18) + ['orphans' => $fixed['orphans'], 'young' => $youngStatus], ['orphans' => 1, 'young' => 'reserved', 'balance' => 30, 'charges' => 2, 'refunds' => 1]);
$fixedAgain = rateb_video_reconcile($pdo);
vw_expect('a second reconcile changes nothing', ['settled' => $fixedAgain['settled'], 'orphans' => $fixedAgain['orphans']] + vw_ledger($pdo, $u17), ['settled' => 0, 'orphans' => 0, 'balance' => 50, 'refunds' => 1]);
rateb_reservation_settle($pdo, $orphanYoung, $u18, 'failed', 'video_storyboard');

// 14. Jobs created before reservations recorded their charge (credits_deducted = 0 on the
// reservation, the charge in a separate earlier row). A refund needs a proven charge.
/**
 * Build a job the way the old start flow did: charge row (optional), reservation, job.
 * @return array{0:int,1:int} job id, usage id
 */
function vw_legacy_job(PDO $pdo, int $userId, int $campaignId, bool $charge = true, ?int $usageId = null): array
{
    if ($charge) {
        rateb_check_and_deduct_credits($userId, 'video_storyboard', $campaignId);
    }
    $usageId ??= (int) rateb_usage_begin($pdo, $userId, $campaignId, 'video', 5)['id'];
    $scenes = json_encode([['scene' => 1, 'image_id' => (int) vw_inputs($pdo, $campaignId)[0]['id'], 'duration' => 5, 'prompt' => 'push-in', 'task_id' => '', 'status' => 'queued', 'url' => '']]);
    $pdo->prepare("INSERT INTO video_jobs (campaign_id, user_id, status, provider, storyboard, usage_id, watermark, approval_status) VALUES (?, ?, 'queued', 'runway', ?, ?, 0, 'draft')")->execute([$campaignId, $userId, $scenes, $usageId]);
    return [(int) $pdo->lastInsertId(), $usageId];
}
$reject = new VwProvider('reject');

$u19 = vw_user($pdo, $initial, $planIds, 50, 'starter');
$c19 = vw_campaign($pdo, $u19);
[$j19, $usage19] = vw_legacy_job($pdo, $u19, $c19);
rateb_video_worker_job($pdo, $j19, $reject);
vw_expect('legacy, valid charge: the failed job refunds its earlier charge once', vw_ledger($pdo, $u19, $j19), ['job' => 'failed', 'usage' => 'failed', 'balance' => 50, 'charges' => 1, 'refunds' => 1]);
$repeatFail = rateb_video_fail($pdo, vw_job($pdo, $j19), 'provider');
$repeatSettle = rateb_video_settle($pdo, vw_job($pdo, $j19), 'failed');
rateb_video_reconcile($pdo);
rateb_video_reconcile($pdo);
$recorded = (int) $pdo->query('SELECT credits_deducted FROM usage_events WHERE id = ' . $usage19)->fetchColumn();
vw_expect('legacy, valid charge: failing, settling, and reconciling again refund nothing more', vw_ledger($pdo, $u19, $j19) + ['fail' => var_export($repeatFail, true), 'settle' => var_export($repeatSettle, true), 'recorded' => $recorded], ['fail' => 'false', 'settle' => 'false', 'recorded' => VW_COST, 'balance' => 50, 'charges' => 1, 'refunds' => 1]);

$u20 = vw_user($pdo, $initial, $planIds, 50, 'starter');
$c20 = vw_campaign($pdo, $u20);
[$j20] = vw_legacy_job($pdo, $u20, $c20, false);
rateb_video_worker_job($pdo, $j20, $reject);
rateb_video_reconcile($pdo);
vw_expect('legacy, zero credits deducted and no charge row: closed without a refund', vw_ledger($pdo, $u20, $j20), ['job' => 'failed', 'usage' => 'failed', 'balance' => 50, 'charges' => 0, 'refunds' => 0]);

$u21 = vw_user($pdo, $initial, $planIds, 50, 'starter');
$c21 = vw_campaign($pdo, $u21);
[$j21a] = vw_legacy_job($pdo, $u21, $c21, true, 0);
rateb_video_worker_job($pdo, $j21a, $reject);
$c21b = vw_campaign($pdo, $u21);
[$j21b] = vw_legacy_job($pdo, $u21, $c21b, true, 987654321);
rateb_video_worker_job($pdo, $j21b, $reject);
rateb_video_reconcile($pdo);
vw_expect('legacy, missing reservation (none, or an id that does not exist): no refund', vw_ledger($pdo, $u21) + ['a' => vw_job($pdo, $j21a)['status'], 'b' => vw_job($pdo, $j21b)['status']], ['a' => 'failed', 'b' => 'failed', 'balance' => 10, 'charges' => 2, 'refunds' => 0]);

$u22 = vw_user($pdo, $initial, $planIds, 50, 'starter');
$c22 = vw_campaign($pdo, $u22);
rateb_check_and_deduct_credits($u22, 'video_storyboard', $c22);
rateb_refund_credits($u22, 'video_storyboard', $c22);
[$j22] = vw_legacy_job($pdo, $u22, $c22, false);
rateb_video_worker_job($pdo, $j22, $reject);
vw_expect('legacy, charge already refunded before the reservation: no second refund', vw_ledger($pdo, $u22, $j22), ['job' => 'failed', 'usage' => 'failed', 'balance' => 50, 'charges' => 1, 'refunds' => 1]);

$u23 = vw_user($pdo, $initial, $planIds, 50, 'starter');
$c23 = vw_campaign($pdo, $u23);
[$j23a] = vw_legacy_job($pdo, $u23, $c23);
[$j23b] = vw_legacy_job($pdo, $u23, $c23, false);
rateb_video_worker_job($pdo, $j23b, $reject);
$afterSecond = vw_ledger($pdo, $u23);
rateb_video_worker_job($pdo, $j23a, $reject);
vw_expect('legacy, one charge and two reservations: only the reservation paired with it is refunded', vw_ledger($pdo, $u23) + ['refunds_after_unpaired' => $afterSecond['refunds']], ['refunds_after_unpaired' => 0, 'balance' => 50, 'charges' => 1, 'refunds' => 1]);

$u24 = vw_user($pdo, $initial, $planIds, 50, 'starter');
$c24 = vw_campaign($pdo, $u24);
rateb_check_and_deduct_credits($u24, 'video_storyboard', $c24);
$pdo->prepare("UPDATE usage_events SET created_at = DATE_SUB(NOW(), INTERVAL ? SECOND) WHERE user_id = ? AND operation = 'video_storyboard'")->execute([rateb_video_legacy_charge_window() + 100, $u24]);
[$j24] = vw_legacy_job($pdo, $u24, $c24, false);
rateb_video_worker_job($pdo, $j24, $reject);
vw_expect('legacy, charge older than the pairing window: no refund', vw_ledger($pdo, $u24, $j24), ['job' => 'failed', 'usage' => 'failed', 'balance' => 30, 'charges' => 1, 'refunds' => 0]);

$u25 = vw_user($pdo, $initial, $planIds, 50, 'starter');
$c25 = vw_campaign($pdo, $u25);
$c25b = vw_campaign($pdo, $u25);
rateb_check_and_deduct_credits($u25, 'video_storyboard', $c25);
[$j25] = vw_legacy_job($pdo, $u25, $c25b, false);
rateb_video_worker_job($pdo, $j25, $reject);
vw_expect("legacy, charge for another campaign: no refund", vw_ledger($pdo, $u25, $j25), ['job' => 'failed', 'balance' => 30, 'charges' => 1, 'refunds' => 0]);

$u26 = vw_user($pdo, $initial, $planIds, 50, 'starter');
$c26 = vw_campaign($pdo, $u26);
[$j26] = vw_legacy_job($pdo, $u26, $c26);
$c26b = vw_campaign($pdo, $u26);
[$j26b] = vw_legacy_job($pdo, $u26, $c26b, false);
$c26c = vw_campaign($pdo, $u26);
[$j26c] = vw_legacy_job($pdo, $u26, $c26c);
$pdo->prepare("UPDATE video_jobs SET status = 'failed', error_code = 'provider' WHERE id IN (?, ?)")->execute([$j26, $j26b]);
$pdo->prepare("UPDATE video_jobs SET status = 'completed' WHERE id = ?")->execute([$j26c]);
$recon = rateb_video_reconcile($pdo);
$reconAgain = rateb_video_reconcile($pdo);
vw_expect('legacy, interrupted jobs: reconcile refunds the proven failure once, closes the unproven one, and settles the completed one without a refund', vw_ledger($pdo, $u26) + [
    'proven' => vw_ledger($pdo, $u26, $j26)['usage'], 'unproven' => vw_ledger($pdo, $u26, $j26b)['usage'], 'completed' => vw_ledger($pdo, $u26, $j26c)['usage'], 'again' => $reconAgain['settled'],
], ['proven' => 'failed', 'unproven' => 'failed', 'completed' => 'completed', 'again' => 0, 'balance' => 30, 'charges' => 2, 'refunds' => 1]);

$u27 = vw_user($pdo, $initial, $planIds, 50, 'starter');
$c27 = vw_campaign($pdo, $u27);
[$j27] = vw_legacy_job($pdo, $u27, $c27);
$holder->beginTransaction();
$holder->prepare('SELECT id FROM video_jobs WHERE id = ? FOR UPDATE')->execute([$j27]);
$legacyFailers = [vw_spawn(['fail', (string) $j27]), vw_spawn(['fail', (string) $j27])];
$overlap = vw_wait_for($observer, "UPDATE video_jobs SET status = 'failed'", 2);
$holder->commit();
$legacyFailed = [vw_collect($legacyFailers[0]), vw_collect($legacyFailers[1])];
rateb_video_reconcile($pdo);
if (!$overlap) {
    vw_report('NOT RUN', 'concurrent legacy job failure', 'both connections were not seen waiting on the job row: ' . json_encode($legacyFailed));
} else {
    vw_expect('legacy, two connections failing the same job at once refund exactly once', vw_ledger($pdo, $u27, $j27) + ['winners' => count(array_filter($legacyFailed, static fn (array $w): bool => ($w['result'] ?? null) === true))], ['winners' => 1, 'job' => 'failed', 'usage' => 'failed', 'balance' => 50, 'charges' => 1, 'refunds' => 1]);
}

// 15. Readiness: two named locks per connection, the real ffmpeg render, and failures.
$lockKeys = [];
$safe = rateb_video_check_locks($pdo, static function (PDO $pdo, string $a, string $b) use (&$lockKeys): void {
    $lockKeys = [$a, $b];
});
$free = $pdo->prepare("SELECT IS_FREE_LOCK(CONCAT(LEFT(DATABASE(), 24), ':', ?))");
$released = 0;
foreach ($lockKeys as $key) {
    $free->execute([$key]);
    $released += (int) $free->fetchColumn();
}
vw_expect('this server keeps two named locks on one connection, and the check releases both', ['ok' => var_export($safe['ok'], true), 'released' => $released], ['ok' => 'true', 'released' => 2]);
$lostFirst = rateb_video_check_locks($pdo, static function (PDO $pdo, string $a): void {
    rateb_video_unlock($pdo, $a);
});
vw_expect('a server that drops the first lock when a second is taken fails the lock check', ['ok' => var_export($lostFirst['ok'], true), 'names_first' => var_export(str_contains($lostFirst['detail'], 'first'), true)], ['ok' => 'false', 'names_first' => 'true']);
$stolenKey = '';
$stolen = rateb_video_check_locks($pdo, static function (PDO $pdo, string $a, string $b) use ($holder, &$stolenKey): void {
    rateb_video_unlock($pdo, $b);
    rateb_video_lock($holder, $b, 0);
    $stolenKey = $b;
});
vw_expect('a lock that ends up owned by another connection fails the lock check', ['ok' => var_export($stolen['ok'], true), 'names_second' => var_export(str_contains($stolen['detail'], 'second'), true), 'stolen' => var_export($stolenKey !== '', true)], ['ok' => 'false', 'names_second' => 'true', 'stolen' => 'true']);
rateb_video_unlock($holder, $stolenKey);
$unsafe = rateb_video_worker_readiness($pdo, ['env' => 'loaded', 'lock_hook' => static function (PDO $pdo, string $a): void {
    rateb_video_unlock($pdo, $a);
}]);
vw_expect('readiness is refused when named locks are unsafe', ['ready' => var_export($unsafe['ready'], true), 'locks' => substr($unsafe['report']['named_locks'], 0, 6)], ['ready' => 'false', 'locks' => 'FAILED']);
if (!$hasFfmpeg || !$hasGd) {
    vw_report('NOT RUN', 'readiness with a real ffmpeg render', 'ffmpeg and GD are required');
} else {
    $ready = rateb_video_worker_readiness($pdo, ['env' => 'loaded']);
    vw_expect('readiness passes with a real render, working locks, and storage', ['ready' => var_export($ready['ready'], true)] + $ready['report'], ['ready' => 'true', 'named_locks' => 'ok', 'ffmpeg_render' => 'ok', 'media_root' => 'ok', 'proc_open' => 'ok']);
}
$noFfmpeg = rateb_video_worker_readiness($pdo, ['env' => 'loaded', 'ffmpeg' => $mediaRoot . '/no-such-ffmpeg']);
vw_expect('readiness is refused when ffmpeg cannot render', ['ready' => var_export($noFfmpeg['ready'], true), 'render' => substr($noFfmpeg['report']['ffmpeg_render'], 0, 6), 'ffmpeg' => substr($noFfmpeg['report']['ffmpeg'], 0, 6)], ['ready' => 'false', 'render' => 'FAILED', 'ffmpeg' => 'FAILED']);
$bareFfmpeg = rateb_video_worker_readiness($pdo, ['env' => 'loaded', 'ffmpeg' => 'ffmpeg']);
vw_expect('readiness is refused when ffmpeg is a bare command name instead of an absolute path', ['ready' => var_export($bareFfmpeg['ready'], true), 'ffmpeg' => substr($bareFfmpeg['report']['ffmpeg'], 0, 6)], ['ready' => 'false', 'ffmpeg' => 'FAILED']);

// 16. Ledger invariant for every test user.
$broken = [];
foreach ($initial as $userId => $start) {
    $l = vw_ledger($pdo, (int) $userId);
    if ($l['balance'] !== $start - VW_COST * $l['charges'] + VW_COST * $l['refunds'] || $l['refunds'] > $l['charges'] || $l['balance'] < 0) {
        $broken[] = $userId . ':' . json_encode($l);
    }
}
vw_expect('every balance equals its start minus charges plus refunds, and no user is refunded more than charged', ['broken' => implode(' ', $broken)], ['broken' => '']);

foreach (glob($mediaRoot . '/*/clips/*') ?: [] as $file) {
    @unlink($file);
}
echo sprintf('SUMMARY pass=%d fail=%d not_run=%d', $results['PASS'], $results['FAIL'], $results['NOT RUN']) . PHP_EOL;
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
