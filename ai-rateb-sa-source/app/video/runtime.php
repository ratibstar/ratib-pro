<?php
declare(strict_types=1);

/*
 * Process and lock primitives for the video worker. This file has no dependencies, so the
 * deploy preflight can load it on its own before any application file is replaced.
 */

function rateb_video_ffmpeg_path(): string
{
    return is_file('/home/admin/bin/ffmpeg') ? '/home/admin/bin/ffmpeg' : 'ffmpeg';
}

/**
 * Run one ffmpeg command with a hard time limit. Output is drained continuously so a
 * chatty encoder cannot block on a full pipe.
 */
function rateb_video_run_ffmpeg(string $cmd, int $timeoutSeconds): bool
{
    if (!function_exists('proc_open')) {
        throw new RuntimeException('storage');
    }
    $process = proc_open('exec ' . $cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('storage');
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $deadline = microtime(true) + max(1, $timeoutSeconds);
    $exit = -1;
    while (true) {
        $status = proc_get_status($process);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        if (!$status['running']) {
            $exit = (int) $status['exitcode'];
            break;
        }
        if (microtime(true) > $deadline) {
            proc_terminate($process, 9);
            break;
        }
        usleep(100000);
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    return $exit === 0;
}

function rateb_video_zoompan_filter(int $index, int $frames): string
{
    $zoom = $index % 2 === 0 ? "min(zoom+0.0009,1.15)" : "if(eq(on,0),1.15,max(zoom-0.0009,1))";
    return 'scale=1440:2560,zoompan=z=' . "'" . $zoom . "'" . ':d=' . $frames
        . ":x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':s=720x1280:fps=25,format=yuv420p";
}

function rateb_video_clip_command(string $ffmpeg, string $still, int $index, int $frames, string $clip): string
{
    return escapeshellarg($ffmpeg) . ' -nostdin -loglevel error -y -i ' . escapeshellarg($still)
        . ' -vf ' . escapeshellarg(rateb_video_zoompan_filter($index, $frames))
        . ' -frames:v ' . $frames . ' -c:v libx264 -preset veryfast -crf 23 -an ' . escapeshellarg($clip);
}

/** @param array<int,string> $clips */
function rateb_video_concat_list(array $clips): string
{
    $lines = '';
    foreach ($clips as $clip) {
        $lines .= 'file ' . escapeshellarg($clip) . "\n";
    }
    return $lines;
}

/** $audio is an audio file to lay under the video, or '' for a silent video. */
function rateb_video_compose_command(string $ffmpeg, string $list, string $audio, bool $watermark, string $out): string
{
    $filter = 'scale=720:1280:force_original_aspect_ratio=decrease,pad=720:1280:(ow-iw)/2:(oh-ih)/2,format=yuv420p';
    if ($watermark) {
        $filter .= ",drawtext=text='RATEB AI':fontsize=28:fontcolor=white:box=1:boxcolor=black@0.45:x=w-tw-24:y=h-th-24";
    }
    $cmd = escapeshellarg($ffmpeg) . ' -nostdin -loglevel error -y -f concat -safe 0 -i ' . escapeshellarg($list);
    if ($audio !== '') {
        $cmd .= ' -i ' . escapeshellarg($audio);
        $cmd .= ' -filter_complex ' . escapeshellarg('[0:v]' . $filter . '[v]');
        $cmd .= ' -map ' . escapeshellarg('[v]') . ' -map 1:a -shortest';
    } else {
        $cmd .= ' -vf ' . escapeshellarg($filter) . ' -an';
    }
    return $cmd . ' -c:v libx264 -preset veryfast -crf 23 -c:a aac -movflags +faststart ' . escapeshellarg($out);
}

/**
 * Named MySQL lock, scoped to the current database. It belongs to the connection, so a
 * crashed worker releases it automatically.
 */
function rateb_video_lock(PDO $pdo, string $key, int $waitSeconds): bool
{
    $stmt = $pdo->prepare("SELECT GET_LOCK(CONCAT(LEFT(DATABASE(), 24), ':', ?), ?)");
    $stmt->execute([$key, max(0, $waitSeconds)]);
    return (int) $stmt->fetchColumn() === 1;
}

function rateb_video_unlock(PDO $pdo, string $key): void
{
    $stmt = $pdo->prepare("SELECT RELEASE_LOCK(CONCAT(LEFT(DATABASE(), 24), ':', ?))");
    $stmt->execute([$key]);
    $stmt->fetchColumn();
}

/**
 * The worker holds its pass lock and a job lock on one connection at once. Servers older
 * than MySQL 5.7 / MariaDB 10.0.2 silently release the first named lock when a second is
 * taken, which would let two workers advance the same job; this proves both stay owned.
 *
 * @param callable(PDO,string,string):void|null $afterAcquire runs between taking and verifying the locks
 * @return array{ok:bool,detail:string}
 */
function rateb_video_check_locks(PDO $pdo, ?callable $afterAcquire = null): array
{
    $suffix = bin2hex(random_bytes(6));
    $keys = ['first' => 'readiness-a-' . $suffix, 'second' => 'readiness-b-' . $suffix];
    $held = [];
    try {
        foreach ($keys as $label => $key) {
            if (!rateb_video_lock($pdo, $key, 0)) {
                return ['ok' => false, 'detail' => 'the ' . $label . ' named lock could not be taken'];
            }
            $held[] = $key;
        }
        if ($afterAcquire !== null) {
            $afterAcquire($pdo, $keys['first'], $keys['second']);
        }
        $owned = $pdo->prepare("SELECT IS_USED_LOCK(CONCAT(LEFT(DATABASE(), 24), ':', ?)) = CONNECTION_ID()");
        foreach ($keys as $label => $key) {
            $owned->execute([$key]);
            $value = $owned->fetchColumn();
            $owned->closeCursor();
            if ($value === null || $value === false || (int) $value !== 1) {
                return ['ok' => false, 'detail' => 'the ' . $label . ' named lock is no longer held by this connection after taking two; the server does not keep multiple named locks'];
            }
        }
        return ['ok' => true, 'detail' => 'ok'];
    } catch (Throwable $error) {
        return ['ok' => false, 'detail' => 'named lock query failed (' . get_class($error) . ' ' . preg_replace('/[^A-Za-z0-9]/', '', (string) $error->getCode()) . ')'];
    } finally {
        foreach ($held as $key) {
            try {
                rateb_video_unlock($pdo, $key);
            } catch (Throwable) {
            }
        }
    }
}

/**
 * Render a two-second video with every ffmpeg feature the worker uses: zoompan clips from a
 * still, the concat demuxer, the drawtext watermark, libx264 video, and AAC audio. Nothing is
 * installed or changed, and the temporary folder is always removed.
 *
 * @return array{ok:bool,detail:string,cleaned:bool}
 */
function rateb_video_check_ffmpeg(string $ffmpeg, ?string $tmpBase = null): array
{
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    if (!function_exists('proc_open') || in_array('proc_open', $disabled, true)) {
        return ['ok' => false, 'detail' => 'proc_open is disabled for this PHP', 'cleaned' => true];
    }
    $dir = rtrim($tmpBase ?? sys_get_temp_dir(), '/') . '/rateb-video-check-' . bin2hex(random_bytes(6));
    if (!@mkdir($dir, 0700)) {
        return ['ok' => false, 'detail' => 'could not create a temporary folder', 'cleaned' => true];
    }
    try {
        $detail = rateb_video_check_ffmpeg_in($ffmpeg, $dir);
    } catch (Throwable $error) {
        $detail = 'check stopped (' . get_class($error) . ')';
    } finally {
        foreach (glob($dir . '/{,.}*', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($dir);
        clearstatcache();
        $cleaned = !file_exists($dir);
    }
    if ($detail === '' && !$cleaned) {
        $detail = 'temporary files could not be removed';
    }
    return ['ok' => $detail === '', 'detail' => $detail === '' ? 'ok' : $detail, 'cleaned' => $cleaned];
}

/** @return string '' when the render passed, otherwise what failed */
function rateb_video_check_ffmpeg_in(string $ffmpeg, string $dir): string
{
    $bin = escapeshellarg($ffmpeg);
    $tail = static function (string $file) use ($dir): string {
        $text = is_file($file) ? (string) file_get_contents($file, false, null, max(0, (int) filesize($file) - 600)) : '';
        $text = str_replace($dir, '<tmp>', $text);
        $text = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[\x00-\x1F\x7F]/', ' ', $text)));
        return $text === '' ? 'no error output' : substr($text, -200);
    };
    $lists = [];
    foreach (['encoders', 'filters', 'demuxers'] as $kind) {
        $file = $dir . '/' . $kind . '.txt';
        if (!rateb_video_run_ffmpeg($bin . ' -hide_banner -' . $kind . ' > ' . escapeshellarg($file) . ' 2>&1', 30)) {
            return 'ffmpeg did not run at ' . $ffmpeg . ': ' . $tail($file);
        }
        $lists[$kind] = (string) file_get_contents($file);
    }
    $required = ['encoders' => ['libx264', 'aac'], 'filters' => ['zoompan', 'drawtext', 'scale', 'pad', 'format'], 'demuxers' => ['concat']];
    $missing = [];
    foreach ($required as $kind => $names) {
        foreach ($names as $name) {
            if (preg_match('/^\s*\S+\s+' . preg_quote($name, '/') . '\s/m', $lists[$kind]) !== 1) {
                $missing[] = $name;
            }
        }
    }
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
        $missing[] = 'gd';
    }
    if ($missing !== []) {
        return 'missing ' . implode(', ', $missing);
    }

    $image = imagecreatetruecolor(360, 640);
    imagefill($image, 0, 0, imagecolorallocate($image, 15, 118, 110));
    imagefilledrectangle($image, 80, 200, 280, 440, imagecolorallocate($image, 240, 200, 90));
    $still = $dir . '/still.jpg';
    imagejpeg($image, $still, 85);
    imagedestroy($image);
    $rate = 8000;
    $samples = '';
    for ($i = 0; $i < $rate * 2; $i++) {
        $samples .= pack('v', ((int) (sin(2 * M_PI * 440 * $i / $rate) * 8000)) & 0xFFFF);
    }
    $audio = $dir . '/voice.wav';
    file_put_contents($audio, 'RIFF' . pack('V', 36 + strlen($samples)) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16) . 'data' . pack('V', strlen($samples)) . $samples);

    $clips = [];
    foreach ([0, 1] as $index) {
        $clip = $dir . '/clip-' . $index . '.mp4';
        $err = $dir . '/clip-' . $index . '.err';
        $ok = rateb_video_run_ffmpeg(rateb_video_clip_command($ffmpeg, $still, $index, 25, $clip) . ' 2> ' . escapeshellarg($err), 60);
        clearstatcache(true, $clip);
        if (!$ok) {
            return 'zoompan/libx264 clip render failed: ' . $tail($err);
        }
        if (!is_file($clip) || !str_contains((string) file_get_contents($clip, false, null, 0, 32), 'ftyp')) {
            return 'zoompan/libx264 clip render produced no valid MP4';
        }
        $clips[] = $clip;
    }
    $list = $dir . '/list.txt';
    file_put_contents($list, rateb_video_concat_list($clips));
    $final = $dir . '/final.mp4';
    $err = $dir . '/compose.err';
    if (!rateb_video_run_ffmpeg(rateb_video_compose_command($ffmpeg, $list, $audio, true, $final) . ' 2> ' . escapeshellarg($err), 120)) {
        return 'concat/drawtext/aac compose failed: ' . $tail($err);
    }
    clearstatcache(true, $final);
    $bytes = is_file($final) ? (string) file_get_contents($final) : '';
    if (strlen($bytes) < 1000 || !str_contains(substr($bytes, 0, 32), 'ftyp') || !str_contains($bytes, 'avc1') || !str_contains($bytes, 'mp4a')) {
        return 'composed output is not an MP4 with H.264 video and AAC audio';
    }
    $err = $dir . '/verify.err';
    if (!rateb_video_run_ffmpeg($bin . ' -nostdin -v error -i ' . escapeshellarg($final) . ' -map 0:v:0 -map 0:a:0 -f null - 2> ' . escapeshellarg($err), 60)) {
        return 'composed output did not decode: ' . $tail($err);
    }
    return '';
}
