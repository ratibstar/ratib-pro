<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/video/engine.php';

$failures = 0;
function check(bool $ok, string $name): void
{
    global $failures;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, credits_balance INTEGER NOT NULL)');
$pdo->exec("CREATE TABLE usage_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    campaign_id INTEGER NULL,
    operation TEXT NOT NULL,
    units INTEGER NOT NULL DEFAULT 1,
    seconds INTEGER NULL,
    status TEXT NOT NULL DEFAULT 'reserved',
    event_type TEXT NULL,
    credits_deducted INTEGER NOT NULL DEFAULT 0,
    idempotency_key TEXT NULL UNIQUE,
    created_at TEXT NOT NULL DEFAULT '2026-10-28 00:00:00'
)");
$pdo->exec("CREATE TABLE video_jobs (
    id INTEGER PRIMARY KEY,
    campaign_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    status TEXT NOT NULL,
    output_media_id INTEGER NULL,
    usage_id INTEGER NOT NULL DEFAULT 0,
    error_code TEXT NOT NULL DEFAULT '',
    storyboard TEXT NOT NULL DEFAULT '[]',
    approval_status TEXT NOT NULL DEFAULT 'draft',
    lock_token TEXT NULL,
    locked_until TEXT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    updated_at TEXT NOT NULL
)");
$pdo->exec("CREATE TABLE campaign_media (
    id INTEGER PRIMARY KEY,
    campaign_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL DEFAULT 0,
    kind TEXT NOT NULL DEFAULT 'video',
    source TEXT NOT NULL DEFAULT 'ai',
    original_name TEXT NOT NULL DEFAULT '',
    stored_name TEXT NOT NULL DEFAULT ''
)");
$pdo->exec('INSERT INTO users (id, credits_balance) VALUES (7, 100)');
$pdo->exec('INSERT INTO users (id, credits_balance) VALUES (8, 50)');
$pdo->exec("INSERT INTO usage_events (id, user_id, campaign_id, operation, status) VALUES (4, 7, 3, 'video', 'reserved')");
$pdo->exec("INSERT INTO usage_events (id, user_id, campaign_id, operation, status, event_type, credits_deducted) VALUES (5, 7, 3, 'video_storyboard', 'completed', 'video_storyboard', 20)");
$pdo->exec("INSERT INTO video_jobs (id, campaign_id, user_id, status, usage_id, updated_at) VALUES (9, 3, 7, 'processing', 4, '2026-10-28 00:00:00')");

$job = ['id' => 9, 'campaign_id' => 3, 'user_id' => 7, 'usage_id' => 4, 'status' => 'processing', 'output_media_id' => null, 'approval_status' => 'draft', 'lock_token' => null];
rateb_video_fail($pdo, $job, 'provider');
rateb_video_fail($pdo, $job, 'provider');
$balance = (int) $pdo->query('SELECT credits_balance FROM users WHERE id = 7')->fetchColumn();
$refunds = (int) $pdo->query("SELECT COUNT(*) FROM usage_events WHERE idempotency_key = 'video_storyboard_charge:5:refund'")->fetchColumn();
$status = (string) $pdo->query('SELECT status FROM video_jobs WHERE id = 9')->fetchColumn();
$reservation = (string) $pdo->query('SELECT status FROM usage_events WHERE id = 4')->fetchColumn();
$other = (int) $pdo->query('SELECT credits_balance FROM users WHERE id = 8')->fetchColumn();
check($balance === 120 && $refunds === 1 && $status === 'failed' && $reservation === 'failed' && $other === 50, 'a repeated failure refunds the storyboard credit once');

$pdo->exec("UPDATE video_jobs SET status = 'processing', error_code = '' WHERE id = 9");
$pdo->exec('UPDATE users SET credits_balance = 100 WHERE id = 7');
rateb_video_fail($pdo, $job, 'provider');
$balance = (int) $pdo->query('SELECT credits_balance FROM users WHERE id = 7')->fetchColumn();
$status = (string) $pdo->query('SELECT status FROM video_jobs WHERE id = 9')->fetchColumn();
$refunds = (int) $pdo->query("SELECT COUNT(*) FROM usage_events WHERE idempotency_key = 'video_storyboard_charge:5:refund'")->fetchColumn();
$other = (int) $pdo->query('SELECT credits_balance FROM users WHERE id = 8')->fetchColumn();
check($balance === 100 && $status === 'failed' && $refunds === 1 && $other === 50, 'a retry after the refund row exists fails the job without crediting again');
$pdo->exec("INSERT INTO video_jobs (id, campaign_id, user_id, status, usage_id, updated_at) VALUES (11, 3, 7, 'processing', 0, '2026-10-28 00:00:00')");
$uncharged = ['id' => 11, 'campaign_id' => 4, 'user_id' => 7, 'usage_id' => 0, 'status' => 'processing', 'output_media_id' => null, 'approval_status' => 'draft', 'lock_token' => null];
$beforeUncharged = (int) $pdo->query('SELECT credits_balance FROM users WHERE id = 7')->fetchColumn();
rateb_video_fail($pdo, $uncharged, 'invalid');
check((int) $pdo->query('SELECT credits_balance FROM users WHERE id = 7')->fetchColumn() === $beforeUncharged && (int) $pdo->query('SELECT credits_balance FROM users WHERE id = 8')->fetchColumn() === 50, 'a failure without a storyboard charge does not grant credits');

$now = time();
$live = ['id' => 2, 'status' => 'processing', 'output_media_id' => null, 'approval_status' => 'draft', 'lock_token' => 'aaaa', 'locked_until' => date('Y-m-d H:i:s', $now + 600), 'updated_at' => date('Y-m-d H:i:s', $now - 5000)];
$expired = $live;
$expired['locked_until'] = date('Y-m-d H:i:s', $now - 60);
$done = ['id' => 22, 'status' => 'completed', 'output_media_id' => 105, 'approval_status' => 'approved', 'lock_token' => null, 'locked_until' => null, 'updated_at' => date('Y-m-d H:i:s', $now - 5000)];
check(rateb_video_owns($live, $now) === false, 'a live lock is not recovered by a stale worker');
check(rateb_video_owns($expired, $now) === true, 'an expired lock can be recovered');
check(rateb_video_owns($done, $now) === false, 'a completed job with media is not recovered');

$pdo->exec("INSERT INTO video_jobs (id, campaign_id, user_id, status, output_media_id, approval_status, updated_at) VALUES (22, 3, 7, 'completed', 105, 'approved', '2026-10-28 00:00:00')");
$before = (int) $pdo->query('SELECT credits_balance FROM users WHERE id = 7')->fetchColumn();
rateb_video_fail($pdo, ['id' => 22, 'campaign_id' => 3, 'user_id' => 7, 'usage_id' => 0, 'output_media_id' => 105, 'approval_status' => 'approved', 'lock_token' => null], 'timeout');
$after = (int) $pdo->query('SELECT credits_balance FROM users WHERE id = 7')->fetchColumn();
$kept = (string) $pdo->query('SELECT status FROM video_jobs WHERE id = 22')->fetchColumn();
check($before === $after && $kept === 'completed', 'stale recovery does not fail or refund a completed job');

check(rateb_video_claim($pdo, 22, 'bbbb', $now) === false, 'a completed job cannot be claimed');
$pdo->exec("INSERT INTO video_jobs (id, campaign_id, user_id, status, updated_at) VALUES (30, 3, 7, 'queued', '2026-10-28 00:00:00')");
check(rateb_video_claim($pdo, 30, 'cccc', $now) === true, 'the first worker claims a queued job');
check(rateb_video_claim($pdo, 30, 'dddd', $now) === false, 'a second worker cannot take a live claim');
$owned = ['id' => 30, 'campaign_id' => 3, 'user_id' => 7, 'usage_id' => 0, 'status' => 'processing', 'lock_token' => 'cccc'];
$pdo->exec('INSERT INTO campaign_media (id, campaign_id) VALUES (8, 3)');
check(rateb_video_complete($pdo, $owned, 8) === true, 'the owning worker completes only after the media row exists');
check((int) $pdo->query('SELECT output_media_id FROM video_jobs WHERE id = 30')->fetchColumn() === 8, 'the completed job points at the stored media');
$pdo->exec("INSERT INTO video_jobs (id, campaign_id, user_id, status, lock_token, updated_at) VALUES (31, 3, 7, 'processing', 'eeee', '2026-10-28 00:00:00')");
$pdo->exec('INSERT INTO campaign_media (id, campaign_id) VALUES (9, 3)');
$stranger = ['id' => 31, 'campaign_id' => 3, 'user_id' => 7, 'usage_id' => 0, 'lock_token' => 'ffff'];
$lost = ['id' => 31, 'user_id' => 7, 'lock_token' => ''];
check(rateb_video_save_storyboard($pdo, $lost, [['scene' => 1]]) === false, 'a storyboard write without the winning token is rejected');
$pdo->exec("UPDATE video_jobs SET lock_token = 'eeee' WHERE id = 31");
check(rateb_video_save_storyboard($pdo, ['id' => 31, 'user_id' => 7, 'lock_token' => 'eeee'], [['scene' => 1]]) === true, 'the owning token can update the storyboard');
check(rateb_video_save_storyboard($pdo, ['id' => 31, 'user_id' => 7, 'lock_token' => 'ffff'], [['scene' => 2]]) === false, 'a replaced token cannot update the storyboard');

$charge = rateb_credit_charge($pdo, 7, 'video_storyboard', 3);
check($charge > 0, 'a storyboard charge is recorded before a job exists');
check(rateb_video_refund_prejob($pdo, 7, 3, $charge) === true, 'the pre-job refund is applied once');
check(rateb_video_refund_prejob($pdo, 7, 3, $charge) === false, 'retrying the pre-job refund does not credit again');
$prejobBalance = (int) $pdo->query('SELECT credits_balance FROM users WHERE id = 7')->fetchColumn();
check($prejobBalance === $after, 'a crashed retry of the same pre-job refund leaves the balance unchanged');

$pdo->exec("INSERT INTO campaign_media (id, campaign_id, user_id, kind, source, original_name, stored_name) VALUES (40, 3, 7, 'video', 'ai', 'video-job-40.mp4', 'abcdef0123456789abcdef0123456789.mp4')");
check(rateb_video_find_output($pdo, 3, 7, 40) === 40, 'a retry reuses the media row saved before completion');

$pdo->exec("UPDATE video_jobs SET locked_until = '2099-01-01 00:00:00' WHERE id = 31");
putenv('RUNWAY_API_KEY');
putenv('RATEB_VIDEO_PROVIDER');
$pdo->exec("INSERT INTO video_jobs (id, campaign_id, user_id, status, storyboard, usage_id, updated_at) VALUES (50, 3, 7, 'queued', '[{\"scene\":1}]', 0, '2026-10-28 00:00:00')");
$worker = rateb_video_worker_run($pdo, $now);
$workerStatus = (string) $pdo->query('SELECT status FROM video_jobs WHERE id = 50')->fetchColumn();
check($worker['ran'] === true && $workerStatus === 'failed', 'the CLI worker runs the job and records a failure');
$workerAgain = rateb_video_worker_run($pdo, $now);
check($workerAgain['ran'] === false, 'a failed job is not run again');

check(rateb_video_complete($pdo, $stranger, 9) === false, 'a stale worker cannot attach its file to another owner');
check($pdo->query('SELECT output_media_id FROM video_jobs WHERE id = 31')->fetchColumn() === null, 'the other owner keeps an empty output');
check($pdo->query('SELECT id FROM campaign_media WHERE id = 9')->fetchColumn() === false, 'the rejected file row is removed');

$pdo->exec("INSERT INTO video_jobs (id, campaign_id, user_id, status, attempts, lock_token, locked_until, updated_at) VALUES (60, 3, 7, 'processing', 3, 'expired', '2000-01-01 00:00:00', '2026-10-28 00:00:00')");
$exhausted = rateb_video_worker_run($pdo, $now);
$exhaustedStatus = (string) $pdo->query('SELECT status FROM video_jobs WHERE id = 60')->fetchColumn();
check($exhausted['ran'] === true && $exhaustedStatus === 'failed', 'the third attempt is the last and the job fails');
$exhaustedAgain = rateb_video_worker_run($pdo, $now);
check($exhaustedAgain['ran'] === false, 'a permanently failed job is not selected again');
$pdo->exec("INSERT INTO video_jobs (id, campaign_id, user_id, status, attempts, updated_at) VALUES (61, 3, 7, 'queued', 3, '2026-10-28 00:00:00')");
check(rateb_video_claim($pdo, 61, 'zzzz', $now) === false, 'an expired job at the attempt limit cannot be claimed again');

$savedPassword = getenv('RATEB_AI_DB_PASSWORD');
$savedPasswordFile = getenv('RATEB_AI_DB_PASSWORD_FILE');
putenv('RATEB_AI_DB_PASSWORD');
$passwordFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rateb-db-password-' . getmypid();
file_put_contents($passwordFile, "stored-secret\n");
chmod($passwordFile, 0600);
putenv('RATEB_AI_DB_PASSWORD_FILE=' . $passwordFile);
$loaded = require __DIR__ . '/../config/database.php';
check(rateb_db_password_perms_private(0600), 'a private credential mode is accepted');
check(!rateb_db_password_perms_private(0644), 'a group-readable credential mode is rejected');
check(!rateb_db_password_perms_private(0666), 'a world-readable credential mode is rejected');
$privateFile = rateb_db_password_file_allowed($passwordFile);
check($privateFile ? ($loaded['password'] ?? '') === 'stored-secret' : ($loaded['password'] ?? '') === '', $privateFile ? 'the CLI password is read from a private credential file' : 'a credential file is ignored when its mode is not private');
putenv('RATEB_AI_DB_PASSWORD=from-env');
$envFirst = require __DIR__ . '/../config/database.php';
check(($envFirst['password'] ?? '') === 'from-env', 'the environment password takes priority over the credential file');
chmod($passwordFile, 0644);
putenv('RATEB_AI_DB_PASSWORD');
if (!rateb_db_password_file_allowed($passwordFile)) {
    $rejected = require __DIR__ . '/../config/database.php';
    check(($rejected['password'] ?? '') === '', 'a readable credential file without private mode is not loaded');
}
check(!str_contains((string) file_get_contents(__DIR__ . '/../config/database.php'), 'error_log'), 'credential loading does not log the password');
unlink($passwordFile);
putenv('RATEB_AI_DB_PASSWORD_FILE');
putenv('RATEB_AI_DB_PASSWORD');
if (is_string($savedPassword) && $savedPassword !== '') {
    putenv('RATEB_AI_DB_PASSWORD=' . $savedPassword);
}
if (is_string($savedPasswordFile) && $savedPasswordFile !== '') {
    putenv('RATEB_AI_DB_PASSWORD_FILE=' . $savedPasswordFile);
}
check(!str_contains((string) file_get_contents(__DIR__ . '/../app/video/worker.php'), 'bootstrap.php'), 'worker startup does not run schema changes');

$worker = realpath(__DIR__ . '/../app/video/worker.php');
$requireScript = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rateb-worker-require-' . getmypid() . '.php';
file_put_contents($requireScript, "<?php\ndeclare(strict_types=1);\nputenv('RATEB_AI_DB_PASSWORD');\nputenv('RATEB_AI_DB_PASSWORD_FILE');\nrequire " . var_export($worker, true) . ";\necho \"required-no-run\\n\";\n");
$cleanEnv = getenv() ?: [];
unset($cleanEnv['RATEB_AI_DB_PASSWORD'], $cleanEnv['RATEB_AI_DB_PASSWORD_FILE']);
$runPhp = static function (string $script) use ($cleanEnv): array {
    $pipes = [];
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open([PHP_BINARY, $script], $descriptors, $pipes, null, $cleanEnv);
    if (is_resource($process)) {
        fclose($pipes[0]);
    }
    if (!is_resource($process)) {
        return [1, '', 'proc_open failed'];
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), is_string($stdout) ? $stdout : '', is_string($stderr) ? $stderr : ''];
};
[$requireCode, $requireOut, $requireErr] = $runPhp($requireScript);
check($requireCode === 0 && $requireOut === "required-no-run\n" && $requireErr === '', 'requiring the worker does not run the loop');
[$directCode, $directOut, $directErr] = $runPhp($worker);
check($directCode === 1 && $directOut === '' && str_contains($directErr, 'database credentials are not available'), 'a direct CLI run stops with a credential error before processing');
unlink($requireScript);

$workerSource = (string) file_get_contents($worker);
$engineSource = (string) file_get_contents(__DIR__ . '/../app/video/engine.php');
$databaseSource = (string) file_get_contents(__DIR__ . '/../config/database.php');
$workerCode = preg_replace('#/\*.*?\*/#s', '', $workerSource) ?? $workerSource;
preg_match_all('/\b(rateb_[a-z0-9_]+)\s*\(/', $workerCode, $workerCalls);
$missing = [];
foreach (array_unique($workerCalls[1]) as $name) {
    $declaration = 'function ' . $name;
    if (!str_contains($workerSource, $declaration) && !str_contains($engineSource, $declaration)) {
        $missing[] = $name;
    }
}
check($missing === [], 'the worker calls only functions defined by the worker or the engine');
check(!str_contains($workerSource, 'function rateb_video_worker_run'), 'the worker does not carry a second run loop');
check(preg_match('/function\s+rateb_video_worker_run\(\s*PDO\s+\$pdo\s*,\s*int\s+\$now\s*\)/', $engineSource) === 1, 'the engine loop signature matches the direct worker call');
check(str_contains($workerSource, 'rateb_video_worker_run(rateb_video_worker_pdo(), time())'), 'the direct worker calls the engine loop');
check(str_contains($engineSource, 'function rateb_video_worker_reconcile'), 'the engine settles reservations before processing');
$pdo->exec("INSERT INTO video_jobs (id, campaign_id, user_id, status, storyboard, updated_at) VALUES (110, 9, 8, 'queued', '[]', '2026-10-28 00:00:00')");
$pdo->exec("INSERT INTO video_jobs (id, campaign_id, user_id, status, storyboard, updated_at) VALUES (111, 9, 7, 'queued', '[]', '2026-10-28 00:00:00')");
$savedRunway = getenv('RUNWAY_API_KEY');
putenv('RUNWAY_API_KEY=present-but-blocked');
rateb_video_test_job_id(111);
$isolated = rateb_video_worker_run($pdo, $now);
rateb_video_test_job_id(0);
putenv('RUNWAY_API_KEY');
if (is_string($savedRunway) && $savedRunway !== '') {
    putenv('RUNWAY_API_KEY=' . $savedRunway);
}
$blocked = false;
rateb_video_test_job_id(111);
try {
    (new RunwayVideoProvider())->generateFromText('test', 5, '720:1280');
} catch (RuntimeException $error) {
    $blocked = $error->getMessage() === 'not_configured';
}
rateb_video_test_job_id(0);
check($isolated['id'] === 111 && (string) $pdo->query('SELECT status FROM video_jobs WHERE id = 110')->fetchColumn() === 'queued' && (int) $pdo->query('SELECT credits_balance FROM users WHERE id = 8')->fetchColumn() === 50 && $blocked, 'test mode handles one job and does not call Runway or change another balance');
$watermarkBlocked = false;
if (!rateb_video_ffmpeg_has_filter('drawtext')) {
    try {
        rateb_video_watermark_filter();
    } catch (RuntimeException $error) {
        $watermarkBlocked = $error->getMessage() === 'watermark';
    }
} else {
    $watermarkBlocked = str_contains(rateb_video_watermark_filter(), 'drawtext');
}
check($watermarkBlocked, 'a watermark is not claimed when drawtext is unavailable');
$pdo->exec("INSERT INTO video_jobs (id, campaign_id, user_id, status, usage_id, output_media_id, updated_at) VALUES (70, 3, 7, 'failed', 80, NULL, '2026-10-28 00:00:00')");
$pdo->exec("INSERT INTO usage_events (id, user_id, campaign_id, operation, status, credits_deducted, created_at) VALUES (80, 7, 3, 'video', 'reserved', 15, '2026-10-28 00:00:00')");
$pdo->exec('UPDATE users SET credits_balance = 100 WHERE id = 7');
$firstSettle = rateb_video_worker_reconcile($pdo, $now);
$secondSettle = rateb_video_worker_reconcile($pdo, $now);
$settledBalance = (int) $pdo->query('SELECT credits_balance FROM users WHERE id = 7')->fetchColumn();
$settledStatus = (string) $pdo->query('SELECT status FROM usage_events WHERE id = 80')->fetchColumn();
check($firstSettle['settled'] === 1 && $secondSettle['settled'] === 0 && $settledBalance === 115 && $settledStatus === 'failed', 'a reserved failed job is settled once');
$pdo->exec("INSERT INTO usage_events (id, user_id, campaign_id, operation, status, credits_deducted, created_at) VALUES (90, 7, 3, 'video', 'reserved', 10, '2026-10-28 00:00:00')");
$beforeOrphan = (int) $pdo->query('SELECT credits_balance FROM users WHERE id = 7')->fetchColumn();
$firstOrphan = rateb_video_worker_reconcile($pdo, $now);
$secondOrphan = rateb_video_worker_reconcile($pdo, $now);
$afterOrphan = (int) $pdo->query('SELECT credits_balance FROM users WHERE id = 7')->fetchColumn();
check($firstOrphan['orphans'] === 1 && $secondOrphan['orphans'] === 0 && $afterOrphan === $beforeOrphan + 10, 'an orphaned video reservation is refunded once');
check(str_contains($databaseSource, 'RATEB_AI_DB_PASSWORD') && str_contains($databaseSource, 'RATEB_AI_DB_PASSWORD_FILE'), 'database credentials keep the environment value ahead of the file');
foreach (['lock_token', 'locked_until', 'attempts', 'idempotency_key'] as $column) {
    check(str_contains($engineSource, $column), 'the engine uses the migrated column ' . $column);
}

exit($failures === 0 ? 0 : 1);
