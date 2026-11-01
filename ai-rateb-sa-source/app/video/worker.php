<?php
declare(strict_types=1);

/**
 * CLI only. PHP-FPM requests exit before any work.
 *
 * Credentials come from RATEB_AI_DB_PASSWORD or from the file named by
 * RATEB_AI_DB_PASSWORD_FILE. That file must be mode 0600 and owned by the
 * worker user. Do not put the password in the cron command, process list,
 * or log. This script prints only a status word.
 *
 * The process user must be able to write the campaign media directory and
 * execute the FFmpeg binary selected by rateb_video_ffmpeg(). It must not be
 * the PHP-FPM pool and must not be given write access to other sites.
 * Creating that user, the password file, and the cron entry is an operator
 * step. This file does not change PHP settings or install cron.
 *
 * A job is claimed with a lock. After three attempts it is marked failed
 * once and is not selected again.
 *
 * Requiring this file defines nothing and processes nothing. The loop runs
 * only when this path is the CLI script.
 */
$script = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
$self = realpath(__FILE__);
$direct = $script !== false && $self !== false && $script === $self;
if (!$direct) {
    return;
}
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/engine.php';

$requestedJob = getenv('RATEB_VIDEO_TEST_JOB');
rateb_video_test_job_id(is_string($requestedJob) && preg_match('/^[1-9][0-9]*$/', trim($requestedJob)) === 1 ? (int) trim($requestedJob) : 0);

try {
    $result = rateb_video_worker_run(rateb_video_worker_pdo(), time());
} catch (RuntimeException $error) {
    if ($error->getMessage() === 'database_unavailable') {
        fwrite(STDERR, "database credentials are not available\n");
        exit(1);
    }
    throw $error;
}
echo (string) ($result['status'] ?? 'empty'), PHP_EOL;
