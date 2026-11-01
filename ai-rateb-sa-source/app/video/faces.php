<?php
declare(strict_types=1);

function rateb_video_ffmpeg(): string
{
    return is_file('/home/admin/bin/ffmpeg') ? '/home/admin/bin/ffmpeg' : 'ffmpeg';
}

function rateb_video_ffprobe(): string
{
    $ffmpeg = rateb_video_ffmpeg();
    if ($ffmpeg !== 'ffmpeg') {
        $probe = dirname($ffmpeg) . '/ffprobe';
        if (is_file($probe)) {
            return $probe;
        }
    }
    return 'ffprobe';
}

/** @return array{kind:string,command:array<int,string>}|null */
function rateb_video_face_detector(): ?array
{
    $custom = trim((string) getenv('RATEB_FACE_DETECT_CMD'));
    if ($custom !== '') {
        return ['kind' => 'command', 'command' => [$custom]];
    }
    $cascade = trim((string) getenv('RATEB_FACE_CASCADE'));
    if ($cascade === '' || !is_file($cascade)) {
        foreach ([
            '/usr/share/opencv4/haarcascades/haarcascade_frontalface_default.xml',
            '/usr/share/opencv/haarcascades/haarcascade_frontalface_default.xml',
        ] as $path) {
            if (is_file($path)) {
                $cascade = $path;
                break;
            }
        }
    }
    $script = __DIR__ . '/face_detect.py';
    if ($cascade === '' || !is_file($script)) {
        return null;
    }
    $python = trim((string) (getenv('RATEB_FACE_PYTHON') ?: 'python'));
    $ran = rateb_video_face_exec([$python, $script, $cascade, '--probe']);
    if ($ran['code'] !== 0) {
        return null;
    }
    return ['kind' => 'haar', 'command' => [$python, $script, $cascade]];
}

/** @param array<int,string> $args @return array{code:int,out:string} */
function rateb_video_face_exec(array $args): array
{
    if (!function_exists('proc_open') || $args === []) {
        return ['code' => 1, 'out' => ''];
    }
    $cmd = implode(' ', array_map(static fn (string $arg): string => escapeshellarg($arg), $args));
    $process = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        return ['code' => 1, 'out' => ''];
    }
    $out = stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['code' => proc_close($process), 'out' => is_string($out) ? $out : ''];
}

/**
 * @param array<int,array{n:int,w:int,h:int,faces:array<int,array{x:int,y:int,w:int,h:int}>}> $samples
 * @return array<int,array<int,array{n:int,x:int,y:int,w:int,h:int}>>
 */
function rateb_video_face_tracks(array $samples): array
{
    if ($samples === []) {
        throw new RuntimeException('face_protection');
    }
    usort($samples, static fn (array $a, array $b): int => $a['n'] <=> $b['n']);
    $count = null;
    foreach ($samples as $sample) {
        if (($sample['w'] ?? 0) < 2 || ($sample['h'] ?? 0) < 2) {
            throw new RuntimeException('face_protection');
        }
        $faces = $sample['faces'];
        if ($count === null) {
            $count = count($faces);
        }
        if (count($faces) !== $count) {
            throw new RuntimeException('face_protection');
        }
        foreach ($faces as $face) {
            if ($face['w'] < 8 || $face['h'] < 8 || $face['x'] < 0 || $face['y'] < 0 || $face['x'] + $face['w'] > $sample['w'] || $face['y'] + $face['h'] > $sample['h']) {
                throw new RuntimeException('face_protection');
            }
        }
    }
    if ($count === 0) {
        return [];
    }
    if ($samples[0]['n'] !== 0) {
        throw new RuntimeException('face_protection');
    }
    $tracks = [];
    foreach ($samples[0]['faces'] as $face) {
        $tracks[] = [['n' => 0] + $face];
    }
    for ($i = 1; $i < count($samples); $i++) {
        $prev = $samples[$i - 1];
        $next = $samples[$i];
        $span = max(1, $next['n'] - $prev['n']);
        $limit = 0.12 * $prev['w'] * $span;
        $used = [];
        foreach ($tracks as $index => $points) {
            $last = $points[count($points) - 1];
            $best = null;
            $bestDistance = null;
            foreach ($next['faces'] as $faceIndex => $face) {
                if (isset($used[$faceIndex])) {
                    continue;
                }
                $distance = hypot(($face['x'] + $face['w'] / 2) - ($last['x'] + $last['w'] / 2), ($face['y'] + $face['h'] / 2) - ($last['y'] + $last['h'] / 2));
                if ($bestDistance === null || $distance < $bestDistance) {
                    $best = $faceIndex;
                    $bestDistance = $distance;
                }
            }
            if ($best === null || $bestDistance === null || $bestDistance > $limit) {
                throw new RuntimeException('face_protection');
            }
            $used[$best] = true;
            $tracks[$index][] = ['n' => $next['n']] + $next['faces'][$best];
        }
    }
    return $tracks;
}

/** @param array<int,array{n:int,x:int,y:int,w:int,h:int}> $points */
function rateb_video_face_expr(array $points, string $key): string
{
    $expr = (string) (int) $points[count($points) - 1][$key];
    for ($i = count($points) - 2; $i >= 0; $i--) {
        $a = $points[$i];
        $b = $points[$i + 1];
        $span = max(1, $b['n'] - $a['n']);
        $segment = (int) $a[$key] . '+(' . (int) $b[$key] . '-' . (int) $a[$key] . ')*(n-' . $a['n'] . ')/' . $span;
        $expr = 'if(lte(n\\,' . $b['n'] . ')\\,' . $segment . '\\,' . $expr . ')';
    }
    return $expr;
}

/** @param array<int,array<int,array{n:int,x:int,y:int,w:int,h:int}>> $tracks */
function rateb_video_face_blur_filter(array $tracks, int $width, int $height): string
{
    if ($tracks === [] || $width < 2 || $height < 2) {
        return '';
    }
    $chain = '[0:v]split[base][src];[src]boxblur=18:1[blurred]';
    $current = 'base';
    foreach ($tracks as $index => $points) {
        $x = rateb_video_face_expr($points, 'x');
        $y = rateb_video_face_expr($points, 'y');
        $w = rateb_video_face_expr($points, 'w');
        $h = rateb_video_face_expr($points, 'h');
        $chain .= ';[blurred]crop=w=' . "'{$w}'" . ':h=' . "'{$h}'" . ':x=' . "'{$x}'" . ':y=' . "'{$y}'" . '[face' . $index . ']';
        $next = 'v' . $index;
        $chain .= ';[' . $current . '][face' . $index . ']overlay=x=' . "'{$x}'" . ':y=' . "'{$y}'" . '[' . $next . ']';
        $current = $next;
    }
    return $chain;
}

/** @param array<int,string> $clips */
function rateb_video_face_marks_ready(array $clips): void
{
    foreach ($clips as $clip) {
        $decoded = json_decode((string) @file_get_contents($clip . '.face.json'), true);
        if (!is_array($decoded) || empty($decoded['protected']) || !isset($decoded['faces'])) {
            throw new RuntimeException('face_protection');
        }
    }
}

function rateb_video_face_mark(string $path, int $faces, string $kind): void
{
    $payload = json_encode(['protected' => true, 'faces' => $faces, 'detector' => $kind], JSON_UNESCAPED_UNICODE);
    if ($payload === false || file_put_contents($path . '.face.json', $payload, LOCK_EX) === false) {
        throw new RuntimeException('face_protection');
    }
    chmod($path . '.face.json', 0600);
}

function rateb_video_protect_file(string $path): void
{
    $detector = rateb_video_face_detector();
    if ($detector === null || !is_file($path)) {
        throw new RuntimeException('face_protection');
    }
    $info = rateb_video_face_exec([rateb_video_ffprobe(), '-v', 'error', '-select_streams', 'v:0', '-show_entries', 'stream=width,height,nb_frames', '-of', 'json', $path]);
    $decoded = json_decode($info['out'], true);
    $stream = is_array($decoded) ? ($decoded['streams'][0] ?? null) : null;
    $width = (int) ($stream['width'] ?? 0);
    $height = (int) ($stream['height'] ?? 0);
    $frames = (int) ($stream['nb_frames'] ?? 0);
    if ($info['code'] !== 0 || $width < 2 || $height < 2 || $frames < 1) {
        throw new RuntimeException('face_protection');
    }
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rateb-faces-' . bin2hex(random_bytes(6));
    if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('face_protection');
    }
    try {
        $wanted = [0];
        for ($n = 5; $n < $frames; $n += 5) {
            $wanted[] = $n;
        }
        if ($wanted[count($wanted) - 1] !== $frames - 1) {
            $wanted[] = $frames - 1;
        }
        $select = implode('+', array_map(static fn (int $n): string => 'eq(n\\,' . $n . ')', $wanted));
        $extract = rateb_video_face_exec([rateb_video_ffmpeg(), '-y', '-i', $path, '-vf', 'select=' . "'" . $select . "'", '-vsync', 'vfr', $dir . DIRECTORY_SEPARATOR . 'frame-%06d.jpg']);
        $files = glob($dir . DIRECTORY_SEPARATOR . 'frame-*.jpg') ?: [];
        sort($files);
        if ($extract['code'] !== 0 || count($files) !== count($wanted)) {
            throw new RuntimeException('face_protection');
        }
        $ran = rateb_video_face_exec(array_merge($detector['command'], [$dir]));
        $parsed = json_decode($ran['out'], true);
        if ($ran['code'] !== 0 || !is_array($parsed) || !is_array($parsed['frames'] ?? null) || count($parsed['frames']) !== count($wanted)) {
            throw new RuntimeException('face_protection');
        }
        $samples = [];
        foreach ($wanted as $index => $n) {
            $row = $parsed['frames'][$index] ?? null;
            if (!is_array($row) || !is_array($row['faces'] ?? null)) {
                throw new RuntimeException('face_protection');
            }
            $faces = [];
            foreach ($row['faces'] as $face) {
                if (!is_array($face)) {
                    throw new RuntimeException('face_protection');
                }
                $faces[] = ['x' => (int) ($face['x'] ?? -1), 'y' => (int) ($face['y'] ?? -1), 'w' => (int) ($face['w'] ?? 0), 'h' => (int) ($face['h'] ?? 0)];
            }
            $samples[] = ['n' => $n, 'w' => $width, 'h' => $height, 'faces' => $faces];
        }
        $tracks = rateb_video_face_tracks($samples);
        if ($tracks !== []) {
            $filter = rateb_video_face_blur_filter($tracks, $width, $height);
            $out = $dir . DIRECTORY_SEPARATOR . 'protected.mp4';
            $blur = rateb_video_face_exec([rateb_video_ffmpeg(), '-y', '-i', $path, '-filter_complex', $filter, '-map', '[v' . (count($tracks) - 1) . ']', '-an', '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', $out]);
            if ($blur['code'] !== 0 || !is_file($out) || !copy($out, $path)) {
                throw new RuntimeException('face_protection');
            }
        }
        rateb_video_face_mark($path, count($tracks), $detector['kind']);
    } finally {
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        @rmdir($dir);
    }
}
