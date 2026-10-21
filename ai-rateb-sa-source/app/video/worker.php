<?php
declare(strict_types=1);

require_once __DIR__ . '/engine.php';

function rateb_video_worker_redact(string $text): string
{
    $text = preg_replace('/(password|passwd|pwd|token|secret|key|authorization|bearer)(["\'\s:=]+)[^\s,;"\']+/i', '$1$2[redacted]', $text) ?? '';
    $text = preg_replace('/[A-Za-z0-9_\-+\/=]{32,}/', '[redacted]', $text) ?? '';
    $text = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $text) ?? '';
    return mb_substr(trim($text), 0, 300);
}

/**
 * Load the environment file that PHP-FPM prepends through the site's .user.ini, so the CLI
 * worker sees the same DB and provider settings without copying any secret.
 *
 * @return string loaded | none | refused
 */
function rateb_video_worker_load_env(string $docroot): string
{
    $ini = $docroot . '/.user.ini';
    if (!is_file($ini)) {
        return 'none';
    }
    $settings = @parse_ini_file($ini, false, INI_SCANNER_RAW);
    $file = trim((string) (is_array($settings) ? ($settings['auto_prepend_file'] ?? '') : ''), " \t\"'");
    if ($file === '') {
        return 'none';
    }
    $real = realpath($file);
    if ($real === false || !is_file($real) || !is_readable($real) || strtolower(pathinfo($real, PATHINFO_EXTENSION)) !== 'php') {
        return 'refused';
    }
    $perms = @fileperms($real);
    if ($perms === false || ($perms & 0022) !== 0) {
        return 'refused';
    }
    $owner = @fileowner($real);
    $self = function_exists('posix_geteuid') ? posix_geteuid() : getmyuid();
    if ($owner === false || $owner !== $self) {
        return 'refused';
    }
    $docrootReal = realpath($docroot);
    if ($docrootReal !== false && str_starts_with($real, rtrim($docrootReal, '/') . '/')) {
        return 'refused';
    }
    ob_start();
    try {
        require_once $real;
    } finally {
        ob_end_clean();
    }
    return 'loaded';
}

/**
 * Readiness report for `cron/video-worker.php --check`. A value that starts with FAILED
 * blocks the cron entry from being installed.
 *
 * @param array{env?:string,ffmpeg?:string,tmp?:string,lock_hook?:callable} $options
 * @return array{report:array<string,string>,ready:bool}
 */
function rateb_video_worker_readiness(PDO $pdo, array $options = []): array
{
    $report = ['php' => PHP_VERSION . ' ' . PHP_SAPI, 'env' => (string) ($options['env'] ?? 'none'), 'db' => 'ok'];
    $report['db_server'] = rateb_video_worker_redact((string) $pdo->query('SELECT VERSION()')->fetchColumn());
    $locks = rateb_video_check_locks($pdo, $options['lock_hook'] ?? null);
    $report['named_locks'] = $locks['ok'] ? 'ok' : 'FAILED ' . $locks['detail'];
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    $report['proc_open'] = function_exists('proc_open') && !in_array('proc_open', $disabled, true) ? 'ok' : 'FAILED';
    $report['curl'] = extension_loaded('curl') ? 'ok' : 'FAILED';
    $report['gd'] = function_exists('imagecreatefromstring') ? 'ok' : 'FAILED';
    $ffmpeg = (string) ($options['ffmpeg'] ?? rateb_video_ffmpeg_path());
    $report['ffmpeg'] = $ffmpeg;
    $render = rateb_video_check_ffmpeg($ffmpeg, $options['tmp'] ?? null);
    $report['ffmpeg_render'] = $render['ok'] ? 'ok' : 'FAILED ' . rateb_video_worker_redact($render['detail']);
    $report['media_root'] = is_dir(MEDIA_ROOT) && is_writable(MEDIA_ROOT) ? 'ok' : 'FAILED';
    $provider = rateb_video_provider();
    $report['provider'] = $provider->name() . ($provider->configured() ? ' configured' : ' NOT configured');
    $report['active_jobs'] = (string) (int) $pdo->query("SELECT COUNT(*) FROM video_jobs WHERE status IN ('queued', 'processing')")->fetchColumn();
    $ready = true;
    foreach ($report as $value) {
        if (str_starts_with($value, 'FAILED')) {
            $ready = false;
        }
    }
    return ['report' => $report, 'ready' => $ready];
}

/**
 * Finish ledger work that a crash interrupted between a job's final state and its
 * reservation, and refund charged video reservations that never got a job.
 *
 * @return array{settled:int,orphans:int}
 */
function rateb_video_reconcile(PDO $pdo): array
{
    $done = ['settled' => 0, 'orphans' => 0];
    $open = $pdo->query("SELECT j.id, j.user_id, j.campaign_id, j.status, j.usage_id FROM video_jobs j
        INNER JOIN usage_events u ON u.id = j.usage_id AND u.user_id = j.user_id
        WHERE j.status IN ('completed', 'failed') AND u.status = 'reserved' AND u.operation = 'video'
        ORDER BY j.id LIMIT 50")->fetchAll();
    foreach ($open as $job) {
        rateb_video_settle($pdo, $job, (string) $job['status']);
        $done['settled']++;
    }
    $orphans = $pdo->prepare("SELECT u.id, u.user_id FROM usage_events u
        WHERE u.operation = 'video' AND u.status = 'reserved' AND u.credits_deducted > 0
          AND u.created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)
          AND NOT EXISTS (SELECT 1 FROM video_jobs j WHERE j.usage_id = u.id)
        ORDER BY u.id LIMIT 50");
    $orphans->execute([rateb_video_stale_seconds()]);
    foreach ($orphans->fetchAll() as $row) {
        if (rateb_reservation_settle($pdo, (int) $row['id'], (int) $row['user_id'], 'failed', 'video_storyboard')) {
            $done['orphans']++;
        }
    }
    return $done;
}

/** @return string the job status after this pass, or busy | missing */
function rateb_video_worker_job(PDO $pdo, int $jobId, ?RatebVideoProvider $provider = null): string
{
    $key = 'video-job:' . $jobId;
    if (!rateb_video_lock($pdo, $key, 0)) {
        return 'busy';
    }
    try {
        $stmt = $pdo->prepare('SELECT * FROM video_jobs WHERE id = ?');
        $stmt->execute([$jobId]);
        $job = $stmt->fetch();
        if (!$job) {
            return 'missing';
        }
        if (!rateb_video_active((string) $job['status'])) {
            return (string) $job['status'];
        }
        $campaign = $pdo->prepare('SELECT * FROM campaigns WHERE id = ? AND user_id = ?');
        $campaign->execute([(int) $job['campaign_id'], (int) $job['user_id']]);
        $campaignRow = $campaign->fetch();
        if (!$campaignRow) {
            rateb_video_fail($pdo, $job, 'invalid');
            return 'failed';
        }
        $job = rateb_video_reap_locked($pdo, $job);
        if (!rateb_video_active((string) $job['status'])) {
            return (string) $job['status'];
        }
        return (string) rateb_video_advance($pdo, $job, $campaignRow, $provider)['status'];
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        rateb_video_unlock($pdo, $key);
    }
}

/**
 * One cron pass. Only one pass runs at a time; each active job is advanced at most once
 * and is skipped when another process holds it.
 *
 * @param array{seconds?:int,limit?:int,provider?:RatebVideoProvider,log?:callable} $options
 * @return array<string,int|bool>
 */
function rateb_video_worker_run(PDO $pdo, array $options = []): array
{
    $deadline = microtime(true) + max(1, (int) ($options['seconds'] ?? 50));
    $limit = max(1, min(100, (int) ($options['limit'] ?? 25)));
    $provider = $options['provider'] ?? null;
    $log = $options['log'] ?? static function (string $line): void {
    };
    $stats = ['locked' => false, 'jobs' => 0, 'completed' => 0, 'failed' => 0, 'processing' => 0, 'busy' => 0, 'errors' => 0, 'settled' => 0, 'orphans' => 0];
    if (!rateb_video_lock($pdo, 'video-worker', 0)) {
        return $stats;
    }
    $stats['locked'] = true;
    try {
        $reconciled = rateb_video_reconcile($pdo);
        $stats['settled'] = $reconciled['settled'];
        $stats['orphans'] = $reconciled['orphans'];
        $ids = $pdo->query("SELECT id FROM video_jobs WHERE status IN ('queued', 'processing') ORDER BY id LIMIT " . $limit)->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            if (microtime(true) >= $deadline) {
                break;
            }
            $stats['jobs']++;
            try {
                $status = rateb_video_worker_job($pdo, (int) $id, $provider);
            } catch (Throwable $error) {
                $stats['errors']++;
                $log('job=' . (int) $id . ' error=' . get_class($error) . ' code=' . rateb_video_worker_redact((string) $error->getCode()) . ' message=' . rateb_video_worker_redact($error->getMessage()));
                continue;
            }
            if (isset($stats[$status]) && is_int($stats[$status])) {
                $stats[$status]++;
            }
            if ($status === 'completed' || $status === 'failed') {
                $log('job=' . (int) $id . ' status=' . $status);
            }
        }
    } finally {
        rateb_video_unlock($pdo, 'video-worker');
    }
    return $stats;
}
