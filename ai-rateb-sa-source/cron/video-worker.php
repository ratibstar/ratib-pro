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
 * Options: --check (report readiness, including a real ffmpeg render and a two-lock test,
 * and exit), --max-seconds=N (default 50).
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
    $readiness = rateb_video_worker_readiness($pdo, ['env' => $env]);
    foreach ($readiness['report'] as $key => $value) {
        echo 'WORKER_', strtoupper($key), '=', $value, "\n";
    }
    echo 'WORKER_READY=', $readiness['ready'] ? 'yes' : 'no', "\n";
    exit($readiness['ready'] ? 0 : 1);
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
