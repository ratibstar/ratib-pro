<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || isset($_SERVER['REQUEST_METHOD']) || isset($_SERVER['GATEWAY_INTERFACE'])) {
    if (!headers_sent()) {
        http_response_code(404);
    }
    exit;
}

/*
 * Video background worker for ai.rateb.sa. Run by cron once a minute:
 *   php cron/video-worker.php --log=/home/<account>/.rateb-ai/video-worker.log
 * Options: --check (report readiness and exit), --max-seconds=N (default 50).
 * A video-worker.pause file next to the log, younger than 15 minutes, skips the pass; the
 * deploy job uses it while files are being replaced.
 */

ini_set('display_errors', '0');
ini_set('log_errors', '0');
@set_time_limit(0);

$root = dirname(__DIR__);
$options = getopt('', ['check', 'log:', 'max-seconds:']) ?: [];
$check = array_key_exists('check', $options);
$maxSeconds = max(5, min(55, (int) ($options['max-seconds'] ?? 50)));

$logFile = '';
if (isset($options['log'])) {
    $candidate = (string) $options['log'];
    $dir = realpath(dirname($candidate));
    $rootReal = realpath($root);
    if ($dir === false || !is_dir($dir) || !is_writable($dir) || !str_ends_with($candidate, '.log')
        || ($rootReal !== false && str_starts_with($dir . '/', rtrim($rootReal, '/') . '/'))) {
        fwrite(STDERR, "video-worker: refusing log path\n");
        exit(1);
    }
    $logFile = $dir . '/' . basename($candidate);
    $pause = $dir . '/video-worker.pause';
    if (!$check && is_file($pause) && (int) @filemtime($pause) > time() - 900) {
        exit(0);
    }
    if (is_file($logFile) && (int) @filesize($logFile) > 5 * 1024 * 1024) {
        @rename($logFile, $logFile . '.1');
    }
}

$log = static function (string $line) use ($logFile): void {
    $entry = gmdate('Y-m-d\TH:i:s\Z') . ' video-worker ' . $line . "\n";
    if ($logFile !== '') {
        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
        @chmod($logFile, 0600);
    } else {
        fwrite(STDERR, $entry);
    }
};

require_once $root . '/app/video/worker.php';

register_shutdown_function(static function () use ($log): void {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $log('fatal=' . rateb_video_worker_redact((string) $error['message']) . ' line=' . (int) $error['line']);
    }
});

$env = rateb_video_worker_load_env($root);
if ($env === 'refused') {
    $log('error=environment file declared in .user.ini failed validation');
    exit(1);
}

require_once $root . '/app/media/library.php';
require_once $root . '/app/db.php';

try {
    $pdo = db();
} catch (Throwable $error) {
    $log('error=database connection failed class=' . get_class($error) . ' code=' . rateb_video_worker_redact((string) $error->getCode()));
    exit(1);
}

if ($check) {
    $report = ['php' => PHP_VERSION . ' ' . PHP_SAPI, 'env' => $env, 'db' => 'ok'];
    $report['db_server'] = rateb_video_worker_redact((string) $pdo->query('SELECT VERSION()')->fetchColumn());
    $first = rateb_video_lock($pdo, 'video-check-a', 0);
    $second = rateb_video_lock($pdo, 'video-check-b', 0);
    $report['named_locks'] = ($first && $second) ? 'ok' : 'FAILED';
    rateb_video_unlock($pdo, 'video-check-a');
    rateb_video_unlock($pdo, 'video-check-b');
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    $report['proc_open'] = function_exists('proc_open') && !in_array('proc_open', $disabled, true) ? 'ok' : 'FAILED';
    $report['curl'] = extension_loaded('curl') ? 'ok' : 'FAILED';
    $report['gd'] = function_exists('imagecreatefromstring') ? 'ok' : 'FAILED';
    $ffmpeg = rateb_video_ffmpeg_path();
    $report['ffmpeg'] = $ffmpeg;
    $report['ffmpeg_runs'] = $report['proc_open'] === 'ok' && rateb_video_run_ffmpeg(escapeshellarg($ffmpeg) . ' -nostdin -hide_banner -version', 30) ? 'ok' : 'FAILED';
    $report['media_root'] = is_dir(MEDIA_ROOT) && is_writable(MEDIA_ROOT) ? 'ok' : 'FAILED';
    $provider = rateb_video_provider();
    $report['provider'] = $provider->name() . ($provider->configured() ? ' configured' : ' NOT configured');
    $report['active_jobs'] = (string) (int) $pdo->query("SELECT COUNT(*) FROM video_jobs WHERE status IN ('queued', 'processing')")->fetchColumn();
    foreach ($report as $key => $value) {
        echo 'WORKER_', strtoupper($key), '=', $value, "\n";
    }
    $ready = !in_array('FAILED', $report, true);
    echo 'WORKER_READY=', $ready ? 'yes' : 'no', "\n";
    exit($ready ? 0 : 1);
}

$stats = rateb_video_worker_run($pdo, ['seconds' => $maxSeconds, 'log' => $log]);
if (!$stats['locked']) {
    exit(0);
}
if ($stats['jobs'] > 0 || $stats['errors'] > 0 || $stats['settled'] > 0 || $stats['orphans'] > 0) {
    $summary = [];
    foreach ($stats as $key => $value) {
        if ($key !== 'locked') {
            $summary[] = $key . '=' . (int) $value;
        }
    }
    $log('pass ' . implode(' ', $summary));
}
exit($stats['errors'] > 0 ? 1 : 0);
