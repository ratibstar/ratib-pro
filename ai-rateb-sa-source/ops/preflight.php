<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || isset($_SERVER['REQUEST_METHOD']) || isset($_SERVER['GATEWAY_INTERFACE'])) {
    if (!headers_sent()) {
        http_response_code(404);
    }
    exit;
}

/*
 * Pre-deployment check for ai.rateb.sa. The deploy job copies this file and
 * app/video/runtime.php into a private folder and runs it over SSH, under the same bare
 * environment cron gives the worker, before any application file is replaced:
 *   env -i HOME=... PATH=/usr/bin:/bin php preflight.php --cron-env --target=DIR
 *     --media-root=DIR --bootstrap-sha256=HASH[,HASH] [--runtime=FILE] [--ffmpeg=PATH]
 * It prints PREFLIGHT_* lines, never secret values, and exits 1 when the deploy must stop.
 */

ini_set('display_errors', '0');
ini_set('log_errors', '0');
@set_time_limit(0);

$options = getopt('', ['target:', 'bootstrap-sha256:', 'runtime:', 'ffmpeg:', 'media-root:', 'cron-env']) ?: [];
$failures = 0;
$inheritedPassword = (string) getenv('RATEB_AI_DB_PASSWORD') !== '';

function pf(string $key, string $value, bool $ok = true): bool
{
    global $failures;
    echo 'PREFLIGHT_', $key, '=', preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value), "\n";
    if (!$ok) {
        $failures++;
    }
    return $ok;
}

function pf_mode(int $perms): string
{
    return sprintf('%04o', $perms & 07777);
}

$uid = function_exists('posix_geteuid') ? posix_geteuid() : null;
pf('POSIX', $uid !== null ? 'ok' : 'FAILED the posix extension is required to check file ownership', $uid !== null);
$owned = static fn (string $path): bool => $uid !== null && @fileowner($path) === $uid;

$home = realpath((string) getenv('HOME'));
$homeOk = $home !== false && is_dir($home) && $owned($home);
pf('HOME', $homeOk ? 'ok' : 'FAILED HOME is not a directory owned by this account', $homeOk);

$given = (string) ($options['target'] ?? '');
$target = $given !== '' ? realpath($given) : false;
$allowed = $home === false ? [] : [$home . '/domains/ai.rateb.sa', $home . '/domains/ai.rateb.sa/public_html', $home . '/domains/ai.rateb.sa/private_html'];
$targetOk = false;
if ($target === false || $target !== $given) {
    pf('TARGET', 'FAILED the target is not an existing real path', false);
} elseif (!in_array($target, $allowed, true)) {
    pf('TARGET', "FAILED the target is not ai.rateb.sa inside this account's home directory", false);
} elseif (!$owned($target)) {
    pf('TARGET', 'FAILED the target is not owned by the deploying account', false);
} elseif (!is_file($target . '/app/plans.php') || !is_writable($target) || !is_dir($target . '/app') || !is_writable($target . '/app')) {
    pf('TARGET', 'FAILED the target is not a writable ai.rateb.sa application folder', false);
} else {
    $targetOk = pf('TARGET', 'ok');
}

$hashes = array_values(array_filter(explode(',', strtolower((string) ($options['bootstrap-sha256'] ?? ''))), static fn (string $h): bool => preg_match('/^[0-9a-f]{64}$/', $h) === 1));
if (!$targetOk) {
    pf('BOOTSTRAP', 'FAILED not checked', false);
} elseif ($hashes === []) {
    pf('BOOTSTRAP', 'FAILED no expected sha256 was given', false);
} else {
    $boot = $target . '/app/bootstrap.php';
    $actual = is_file($boot) ? (string) hash_file('sha256', $boot) : '';
    if ($actual === '') {
        pf('BOOTSTRAP', 'FAILED app/bootstrap.php is missing', false);
    } elseif (!in_array($actual, $hashes, true)) {
        pf('BOOTSTRAP', 'FAILED unexpected sha256=' . $actual . '; reconcile app/bootstrap.php on the server before deploying', false);
    } elseif (!$owned($boot) || !is_writable($boot)) {
        pf('BOOTSTRAP', 'FAILED app/bootstrap.php is not owned by or writable for the deploying account', false);
    } else {
        pf('BOOTSTRAP', 'ok sha256=' . $actual);
    }
}

$prependLoaded = false;
if (!$targetOk) {
    pf('PREPEND', 'FAILED not checked', false);
} else {
    $ini = @parse_ini_file($target . '/.user.ini', false, INI_SCANNER_RAW);
    $declared = trim((string) (is_array($ini) ? ($ini['auto_prepend_file'] ?? '') : ''), " \t\"'");
    $file = $declared !== '' ? realpath($declared) : false;
    $perms = $file !== false ? @fileperms($file) : false;
    if ($declared === '') {
        pf('PREPEND', 'FAILED .user.ini declares no auto_prepend_file', false);
    } elseif ($file === false || !is_file($file) || !is_readable($file)) {
        pf('PREPEND', 'FAILED the declared prepend file is not a readable file', false);
    } elseif (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php') {
        pf('PREPEND', 'FAILED the prepend file is not a .php file', false);
    } elseif (str_starts_with($file, $target . '/')) {
        pf('PREPEND', 'FAILED the prepend file is inside the web root', false);
    } elseif ($home === false || !str_starts_with($file, $home . '/')) {
        pf('PREPEND', "FAILED the prepend file is outside this account's home directory", false);
    } elseif (!$owned($file)) {
        pf('PREPEND', 'FAILED the prepend file is not owned by the deploying account', false);
    } elseif ($perms === false || ($perms & 0022) !== 0) {
        pf('PREPEND', 'FAILED the prepend file is group- or world-writable mode=' . ($perms === false ? 'unknown' : pf_mode($perms)), false);
    } else {
        pf('PREPEND', 'ok mode=' . pf_mode($perms));
        pf('PREPEND_WORLD_READABLE', ($perms & 0004) !== 0 ? 'yes (warning: other local accounts can read it)' : 'no');
        ob_start();
        try {
            require_once $file;
            $prependLoaded = true;
        } catch (Throwable $error) {
            pf('PREPEND_LOAD', 'FAILED ' . get_class($error), false);
        } finally {
            $printed = strlen((string) ob_get_clean());
        }
        if ($prependLoaded) {
            pf('PREPEND_OUTPUT_BYTES', (string) $printed);
        }
    }
}
if (array_key_exists('cron-env', $options)) {
    $session = getenv('SSH_CONNECTION') !== false || getenv('SSH_CLIENT') !== false;
    if ($session) {
        pf('CRON_ENV', 'FAILED the check runs with the SSH session environment, not the bare one cron uses', false);
    } elseif ($inheritedPassword) {
        pf('CRON_ENV', 'FAILED the database password came from the session environment; cron only gets it from the prepend file', false);
    } else {
        pf('CRON_ENV', 'ok PATH=' . (string) getenv('PATH'));
    }
}
$dbPassword = (string) getenv('RATEB_AI_DB_PASSWORD');
pf('ENV_RATEB_AI_DB_PASSWORD', $dbPassword !== '' ? 'SET' : 'FAILED NOT_SET', $dbPassword !== '');

$mediaGiven = (string) ($options['media-root'] ?? '');
$media = $mediaGiven !== '' ? realpath($mediaGiven) : false;
if ($mediaGiven === '' || $mediaGiven[0] !== '/') {
    pf('MEDIA_ROOT', 'FAILED no absolute media root was given', false);
} elseif ($media === false || !is_dir($media)) {
    pf('MEDIA_ROOT', 'FAILED the media root does not exist', false);
} elseif ($media !== $mediaGiven) {
    pf('MEDIA_ROOT', 'FAILED the media root is reached through a symbolic link', false);
} elseif ($home === false || !str_starts_with($media, $home . '/')) {
    pf('MEDIA_ROOT', "FAILED the media root is outside this account's home directory", false);
} elseif ($target !== false && str_starts_with($media . '/', $target . '/')) {
    pf('MEDIA_ROOT', 'FAILED the media root is inside the web root', false);
} elseif (!$owned($media)) {
    pf('MEDIA_ROOT', 'FAILED the media root is not owned by the deploying account', false);
} else {
    $probe = $media . '/.rateb-preflight-' . bin2hex(random_bytes(6));
    $wrote = @file_put_contents($probe, 'x') === 1;
    $removed = $wrote && @unlink($probe);
    pf('MEDIA_ROOT', $wrote && $removed ? 'ok' : 'FAILED the deploying account cannot create and remove a file in the media root', $wrote && $removed);
}
pf('ENV_RUNWAY_API_KEY', (string) getenv('RUNWAY_API_KEY') !== '' ? 'SET' : 'NOT_SET');

$disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
$procOpen = function_exists('proc_open') && !in_array('proc_open', $disabled, true);
pf('PROC_OPEN', $procOpen ? 'ok' : 'FAILED', $procOpen);
foreach (['pdo_mysql', 'curl', 'gd'] as $extension) {
    pf('EXT_' . strtoupper($extension), extension_loaded($extension) ? 'ok' : 'FAILED', extension_loaded($extension));
}
$crontab = '';
foreach (array_merge(explode(PATH_SEPARATOR, (string) getenv('PATH')), ['/usr/bin', '/bin']) as $dir) {
    if ($dir !== '' && is_file($dir . '/crontab') && is_executable($dir . '/crontab')) {
        $crontab = $dir . '/crontab';
        break;
    }
}
pf('CRONTAB', $crontab !== '' ? 'ok' : 'FAILED crontab command not found', $crontab !== '');

$runtime = (string) ($options['runtime'] ?? __DIR__ . '/../app/video/runtime.php');
$runtimeOk = is_file($runtime);
if ($runtimeOk) {
    require_once $runtime;
}
pf('RUNTIME', $runtimeOk ? 'ok' : 'FAILED runtime.php was not provided', $runtimeOk);

if (!$targetOk || !$runtimeOk || $dbPassword === '' || !extension_loaded('pdo_mysql')) {
    pf('DB', 'FAILED not checked', false);
} else {
    try {
        $c = (static fn (string $file) => require $file)($target . '/config/database.php');
        $pdo = new PDO(
            "mysql:host={$c['host']};dbname={$c['database']};charset={$c['charset']}",
            $c['username'],
            $c['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 10]
        );
        pf('DB', 'ok');
        pf('DB_SERVER', (string) preg_replace('/[^A-Za-z0-9._+\- ]/', '', (string) $pdo->query('SELECT VERSION()')->fetchColumn()));
        $locks = rateb_video_check_locks($pdo);
        pf('NAMED_LOCKS', $locks['ok'] ? 'ok' : 'FAILED ' . $locks['detail'], $locks['ok']);
        $pdo = null;
    } catch (Throwable $error) {
        pf('DB', 'FAILED ' . get_class($error) . ' code=' . preg_replace('/[^A-Za-z0-9]/', '', (string) $error->getCode()), false);
    }
}

if (!$runtimeOk) {
    pf('FFMPEG', 'FAILED not checked', false);
    pf('FFMPEG_RENDER', 'FAILED not checked', false);
} else {
    $ffmpeg = (string) ($options['ffmpeg'] ?? rateb_video_ffmpeg_path());
    $ffmpegOk = rateb_video_ffmpeg_usable($ffmpeg);
    pf('FFMPEG', $ffmpegOk ? $ffmpeg : 'FAILED no executable ffmpeg at an absolute path (looked in ' . implode(', ', rateb_video_ffmpeg_candidates()) . ')', $ffmpegOk);
    if (!$ffmpegOk || !$procOpen) {
        pf('FFMPEG_RENDER', 'FAILED not checked', false);
    } else {
        $render = rateb_video_check_ffmpeg($ffmpeg);
        pf('FFMPEG_RENDER', $render['ok'] ? 'ok' : 'FAILED ' . $render['detail'], $render['ok']);
    }
}

echo 'PREFLIGHT_OK=', $failures === 0 ? 'yes' : 'no', "\n";
exit($failures === 0 ? 0 : 1);
