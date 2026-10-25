<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/video/worker.php';
require_once __DIR__ . '/support/video-inspect.php';

$failures = 0;
function check(bool $ok, string $name): void
{
    global $failures;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

$posix = PHP_OS_FAMILY !== 'Windows';
$scene = ['scene' => 1, 'image_id' => 9, 'duration' => 5, 'prompt' => 'slow push-in', 'task_id' => '', 'status' => 'queued', 'url' => ''];
check(rateb_video_valid_scenes([$scene]) !== null, 'a well formed storyboard is accepted');
check(rateb_video_valid_scenes([$scene, $scene, $scene]) !== null, 'three scenes (30 seconds) are accepted');
check(rateb_video_valid_scenes([$scene, $scene, $scene, $scene]) === null, 'more than three scenes are rejected');
check(rateb_video_valid_scenes([]) === null && rateb_video_valid_scenes(null) === null && rateb_video_valid_scenes('x') === null, 'empty or non-array storyboards are rejected');
check(rateb_video_valid_scenes([1 => $scene]) === null, 'a storyboard that is not a list is rejected');
check(rateb_video_valid_scenes([['image_id' => '9'] + $scene]) === null, 'a string image id is rejected');
check(rateb_video_valid_scenes([['image_id' => 0] + $scene]) === null, 'a zero image id is rejected');
check(rateb_video_valid_scenes([['duration' => 7] + $scene]) === null, 'a duration other than 5 or 10 is rejected');
check(rateb_video_valid_scenes([['prompt' => '  '] + $scene]) === null, 'a blank prompt is rejected');
check(rateb_video_valid_scenes([['prompt' => str_repeat('a', 1001)] + $scene]) === null, 'an oversized prompt is rejected');
check(rateb_video_valid_scenes([['task_id' => '../../etc/passwd'] + $scene]) === null, 'a task id with path characters is rejected');
check(rateb_video_valid_scenes([['task_id' => 'a5b0c3d2-1111-4222-8333-944455556666', 'status' => 'processing'] + $scene]) !== null, 'a provider uuid task id is accepted');
check(rateb_video_valid_scenes([['status' => 'done'] + $scene]) === null, 'an unknown scene status is rejected');
check(rateb_video_valid_task_id('task-1') && rateb_video_valid_task_id('abc.def:42'), 'plain task ids are valid');
check(!rateb_video_valid_task_id('') && !rateb_video_valid_task_id('-x') && !rateb_video_valid_task_id("a\nb") && !rateb_video_valid_task_id(str_repeat('a', 129)), 'empty, dash-led, multi-line, and long task ids are invalid');

$redacted = rateb_video_worker_redact("SQLSTATE[HY000] password=hunter2 token: abc Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9xyz\nnext");
check(!str_contains($redacted, 'hunter2') && !str_contains($redacted, 'eyJhbGci') && !str_contains($redacted, "\n"), 'log lines redact passwords, tokens, long secrets, and newlines');
check(str_contains($redacted, 'SQLSTATE[HY000]'), 'log lines keep the error class context');
check(mb_strlen(rateb_video_worker_redact(str_repeat('word ', 200))) <= 300, 'log lines are bounded');

$script = dirname(__DIR__) . '/cron/video-worker.php';
$tokens = token_get_all((string) file_get_contents($script));
$firstIf = null;
$firstRequire = null;
foreach ($tokens as $i => $token) {
    if (is_array($token) && $token[0] === T_IF && $firstIf === null) {
        $firstIf = $i;
    }
    if (is_array($token) && in_array($token[0], [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], true) && $firstRequire === null) {
        $firstRequire = $i;
    }
}
check($firstIf !== null && $firstRequire !== null && $firstIf < $firstRequire && str_contains((string) file_get_contents($script), "if (PHP_SAPI !== 'cli'"), 'the worker checks for CLI before loading any code');

$spawn = static function (array $env, array $args = []) use ($script): array {
    $process = proc_open(array_merge([PHP_BINARY, $script], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env + ['PATH' => (string) getenv('PATH'), 'SystemRoot' => (string) getenv('SystemRoot')]);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['code' => proc_close($process), 'out' => (string) $out, 'err' => (string) $err];
};
$web = $spawn(['REQUEST_METHOD' => 'GET']);
check($web['out'] === '' && $web['err'] === '' && $web['code'] === 0, 'a request that looks like a web request gets no output and loads nothing');
$cgi = $spawn(['GATEWAY_INTERFACE' => 'CGI/1.1']);
check($cgi['out'] === '' && $cgi['err'] === '', 'a CGI gateway environment is refused the same way');
$badLog = $spawn([], ['--log=' . dirname(__DIR__) . '/inside-docroot.log']);
check($badLog['code'] === 1 && str_contains($badLog['err'], 'refusing log path') && !is_file(dirname(__DIR__) . '/inside-docroot.log'), 'a log path inside the web root is refused');

$base = sys_get_temp_dir() . '/rateb-worker-env-' . bin2hex(random_bytes(4));
$docroot = $base . '/site';
$private = $base . '/private';
mkdir($docroot, 0700, true);
mkdir($private, 0700, true);
check(rateb_video_worker_load_env($docroot) === 'none', 'no .user.ini means nothing is loaded');
file_put_contents($private . '/env.php', "<?php\nputenv('RATEB_WORKER_TEST_ENV=from-prepend');\necho 'leak';\n");
chmod($private . '/env.php', 0600);
file_put_contents($docroot . '/.user.ini', "upload_max_filesize = 8M\n");
check(rateb_video_worker_load_env($docroot) === 'none', 'a .user.ini without auto_prepend_file loads nothing');
file_put_contents($docroot . '/inside.php', "<?php\nputenv('RATEB_WORKER_TEST_INSIDE=1');\n");
chmod($docroot . '/inside.php', 0600);
file_put_contents($docroot . '/.user.ini', 'auto_prepend_file = "' . $docroot . "/inside.php\"\n");
check(rateb_video_worker_load_env($docroot) === 'refused' && getenv('RATEB_WORKER_TEST_INSIDE') === false, 'a prepend file inside the web root is refused');
file_put_contents($private . '/env.txt', 'x');
file_put_contents($docroot . '/.user.ini', 'auto_prepend_file = "' . $private . "/env.txt\"\n");
check(rateb_video_worker_load_env($docroot) === 'refused', 'a prepend file that is not PHP is refused');
file_put_contents($docroot . '/.user.ini', 'auto_prepend_file = "' . $private . "/missing.php\"\n");
check(rateb_video_worker_load_env($docroot) === 'refused', 'a missing prepend file is refused');
if ($posix) {
    file_put_contents($private . '/open.php', "<?php\nputenv('RATEB_WORKER_TEST_OPEN=1');\n");
    chmod($private . '/open.php', 0666);
    file_put_contents($docroot . '/.user.ini', 'auto_prepend_file = "' . $private . "/open.php\"\n");
    check(rateb_video_worker_load_env($docroot) === 'refused' && getenv('RATEB_WORKER_TEST_OPEN') === false, 'a world-writable prepend file is refused');
    chmod($private . '/open.php', 0620);
    check(rateb_video_worker_load_env($docroot) === 'refused' && getenv('RATEB_WORKER_TEST_OPEN') === false, 'a group-writable prepend file is refused');
    file_put_contents($docroot . '/.user.ini', 'auto_prepend_file = "' . $private . "/env.php\"\n");
    ob_start();
    $loaded = rateb_video_worker_load_env($docroot);
    $printed = (string) ob_get_clean();
    check($loaded === 'loaded' && getenv('RATEB_WORKER_TEST_ENV') === 'from-prepend', 'the site prepend file is loaded so CLI sees the same settings');
    check($printed === '', 'output from the prepend file is discarded');

    $started = microtime(true);
    $finished = rateb_video_run_ffmpeg('sleep 5', 1);
    $elapsed = microtime(true) - $started;
    check(!$finished && $elapsed < 4, 'a hung encoder is stopped at its time limit');
    check(rateb_video_run_ffmpeg('true', 5) && !rateb_video_run_ffmpeg('false', 5), 'encoder exit codes are reported');

    // ffmpeg readiness: a real render, then fake binaries that each lack one thing.
    $tmpBase = $base . '/check-tmp';
    mkdir($tmpBase, 0700);
    $leftovers = static fn (): int => count(glob($tmpBase . '/*') ?: []);
    // The fake lists mirror the production build: everything the worker needs, but no drawtext.
    $fake = static function (string $name, string $render) use ($base): string {
        $lists = [
            'encoders' => " V....D libx264              libx264 H.264\n A....D aac                  AAC (Advanced Audio Coding)\n",
            'decoders' => " VFS..D png                  PNG (Portable Network Graphics) image\n",
            'filters' => " ... format            V->V       pixel format\n ... pad               V->V       pad\n ... scale             V->V       scale\n T.C zoompan           V->V       Apply Zoom & Pan effect.\n TSC overlay           VV->V      Overlay a video source on top of the input.\n",
            'demuxers' => " D  concat          Virtual concatenation script\n",
            'version' => "ffmpeg version 7.0.2-static Copyright (c) 2000-2024 the FFmpeg developers\nconfiguration: --enable-gpl --enable-libx264 --enable-zlib\n",
        ];
        if ($name === 'no-x264-overlay') {
            $lists['encoders'] = " A....D aac                  AAC (Advanced Audio Coding)\n";
            $lists['filters'] = str_replace(" TSC overlay           VV->V      Overlay a video source on top of the input.\n", '', $lists['filters']);
        }
        if ($name === 'no-zoompan-aac-png') {
            $lists['encoders'] = " V....D libx264              libx264 H.264\n";
            $lists['decoders'] = '';
            $lists['filters'] = str_replace(" T.C zoompan           V->V       Apply Zoom & Pan effect.\n", '', $lists['filters']);
        }
        $body = "#!/bin/sh\ncase \" \$* \" in\n";
        foreach ($lists as $kind => $text) {
            $body .= "  *\" -{$kind} \"*) printf '%s' " . escapeshellarg($text) . "; exit 0 ;;\n";
        }
        $body .= "esac\n" . $render . "\n";
        $file = $base . '/ffmpeg-' . $name;
        file_put_contents($file, $body);
        chmod($file, 0700);
        return $file;
    };
    $realFfmpeg = trim((string) shell_exec('command -v ffmpeg 2>/dev/null'));
    if ($realFfmpeg !== '' && function_exists('imagejpeg')) {
        $real = rateb_video_check_ffmpeg($realFfmpeg, $tmpBase);
        check($real['ok'] && $real['cleaned'] && $leftovers() === 0, 'the readiness render passes with a full ffmpeg and leaves no temporary files' . ($real['ok'] ? '' : ' (' . $real['detail'] . ')'));
        check(preg_match('/^drawtext=(present|absent) /', $real['text']) === 1 && str_contains($real['text'], 'libfreetype='), 'readiness reports the build\'s text support (' . $real['text'] . ')');
    } elseif (getenv('RATEB_TEST_REQUIRE_FFMPEG') === '1') {
        check(false, 'the readiness render needs ffmpeg and GD, which CI must provide');
    } else {
        echo "SKIP real ffmpeg readiness render (ffmpeg or GD not installed)\n";
    }

    // A real ffmpeg compiled without drawtext or FreeType (CI builds one), like production's.
    $noDrawtext = (string) getenv('RATEB_TEST_FFMPEG_NO_DRAWTEXT');
    if ($noDrawtext !== '' && function_exists('imagepng')) {
        $builtLists = (string) shell_exec(escapeshellarg($noDrawtext) . ' -hide_banner -filters 2>&1') . (string) shell_exec(escapeshellarg($noDrawtext) . ' -hide_banner -decoders 2>&1') . (string) shell_exec(escapeshellarg($noDrawtext) . ' -hide_banner -encoders 2>&1');
        $has = static fn (string $name): bool => preg_match('/^\s*\S+\s+' . preg_quote($name, '/') . '\s/m', $builtLists) === 1;
        check(!$has('drawtext') && $has('overlay') && $has('png') && $has('libx264') && $has('aac'), 'the test build really has overlay, PNG decoding, libx264, and AAC, and no drawtext');
        $built = rateb_video_check_ffmpeg($noDrawtext, $tmpBase);
        check($built['ok'] && $leftovers() === 0, 'readiness passes with a real ffmpeg that has no drawtext' . ($built['ok'] ? '' : ' (' . $built['detail'] . ')'));
        check(str_starts_with($built['text'], 'drawtext=absent ') && str_contains($built['text'], 'libfreetype=no libharfbuzz=no libfribidi=no libfontconfig=no'), 'readiness reports that build as having no text libraries (' . $built['text'] . ')');
        $renderDir = $tmpBase . '/no-drawtext-render';
        mkdir($renderDir, 0700);
        $free = vt_render($noDrawtext, $renderDir, true, true);
        $freeSeen = $free['file'] !== '' ? vt_inspect($noDrawtext, $free['file'], $renderDir) : [];
        check($free['file'] !== '' && $freeSeen['video'] === 'h264' && $freeSeen['audio'] === 'aac' && $freeSeen['decodes'] === 'yes' && $freeSeen['mark_pixels'] >= 50, 'without drawtext, a free-plan video renders with the PNG watermark visible in the corner and AAC voice (' . $free['detail'] . ' ' . json_encode($freeSeen) . ')');
        $paid = vt_render($noDrawtext, $renderDir, false, true);
        $paidSeen = $paid['file'] !== '' ? vt_inspect($noDrawtext, $paid['file'], $renderDir) : [];
        check($paid['file'] !== '' && $paidSeen['audio'] === 'aac' && $paidSeen['decodes'] === 'yes' && $paidSeen['mark_pixels'] === 0, 'a paid-plan video from the same build has voice and nothing drawn in the watermark corner (' . $paid['detail'] . ' ' . json_encode($paidSeen) . ')');
        $silentFree = vt_render($noDrawtext, $renderDir, true, false);
        $silentSeen = $silentFree['file'] !== '' ? vt_inspect($noDrawtext, $silentFree['file'], $renderDir) : [];
        check($silentFree['file'] !== '' && $silentSeen['audio'] === 'none' && $silentSeen['decodes'] === 'yes' && $silentSeen['mark_pixels'] >= 50, 'a silent free-plan video also carries the watermark (' . $silentFree['detail'] . ' ' . json_encode($silentSeen) . ')');
        foreach (glob($renderDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($renderDir);
    } elseif (getenv('RATEB_TEST_REQUIRE_FFMPEG') === '1') {
        check(false, 'CI must provide a real ffmpeg built without drawtext in RATEB_TEST_FFMPEG_NO_DRAWTEXT');
    } else {
        echo "SKIP real no-drawtext ffmpeg render (RATEB_TEST_FFMPEG_NO_DRAWTEXT not set)\n";
    }
    $missing = rateb_video_check_ffmpeg($fake('no-x264-overlay', 'exit 0'), $tmpBase);
    check(!$missing['ok'] && str_contains($missing['detail'], 'libx264') && str_contains($missing['detail'], 'overlay') && !str_contains($missing['detail'], 'aac') && !str_contains($missing['detail'], 'drawtext') && $leftovers() === 0, 'readiness names each missing ffmpeg capability, does not require drawtext, and cleans up (' . $missing['detail'] . ')');
    $missingMore = rateb_video_check_ffmpeg($fake('no-zoompan-aac-png', 'exit 0'), $tmpBase);
    check(!$missingMore['ok'] && str_contains($missingMore['detail'], 'zoompan') && str_contains($missingMore['detail'], 'aac') && str_contains($missingMore['detail'], 'png') && !str_contains($missingMore['detail'], 'libx264') && $leftovers() === 0, 'a missing zoompan filter, AAC encoder, or PNG decoder fails readiness (' . $missingMore['detail'] . ')');
    check($missing['text'] === 'drawtext=absent version=7.0.2-static libfreetype=no libharfbuzz=no libfribidi=no libfontconfig=no', 'readiness reports a build without FreeType, HarfBuzz, FriBidi, or fontconfig (' . $missing['text'] . ')');
    $noOverlay = rateb_video_check_ffmpeg($fake('compose-fails', "case \" \$* \" in\n  *overlay*) echo \"No such filter: 'overlay'\" >&2; exit 1 ;;\nesac\nfor last; do :; done\nprintf '\\000\\000\\000\\030ftypisom' > \"\$last\"\nexit 0"), $tmpBase);
    check(!$noOverlay['ok'] && str_contains($noOverlay['detail'], 'concat/overlay/aac compose failed') && str_contains($noOverlay['detail'], "No such filter: 'overlay'") && $leftovers() === 0, 'a compose step that cannot overlay the watermark fails readiness and is cleaned up (' . $noOverlay['detail'] . ')');
    $broken = rateb_video_check_ffmpeg($fake('render-fails', "echo \"Unknown encoder 'libx264'\" >&2\nexit 1"), $tmpBase);
    check(!$broken['ok'] && str_contains($broken['detail'], 'clip render failed') && str_contains($broken['detail'], 'Unknown encoder') && $leftovers() === 0, 'a failing render is reported with the encoder error and cleaned up (' . $broken['detail'] . ')');
    $junk = rateb_video_check_ffmpeg($fake('junk-output', "for last; do :; done\necho junk > \"\$last\"\nexit 0"), $tmpBase);
    check(!$junk['ok'] && str_contains($junk['detail'], 'no valid MP4') && $leftovers() === 0, 'a render that writes something other than an MP4 fails readiness (' . $junk['detail'] . ')');
    $absent = rateb_video_check_ffmpeg($base . '/no-such-ffmpeg', $tmpBase);
    check(!$absent['ok'] && str_contains($absent['detail'], 'did not run') && $leftovers() === 0, 'a missing ffmpeg binary fails readiness (' . $absent['detail'] . ')');
    $bare = rateb_video_check_ffmpeg('ffmpeg', $tmpBase);
    check(!$bare['ok'] && $bare['cleaned'] && str_contains($bare['detail'], 'absolute path') && $leftovers() === 0, 'a bare command name is not trusted, because cron PATH differs from the SSH one');

    $exe = $fake('resolved', 'exit 0');
    $plain = $base . '/ffmpeg-not-executable';
    file_put_contents($plain, "#!/bin/sh\nexit 0\n");
    chmod($plain, 0600);
    check(rateb_video_ffmpeg_path(['ffmpeg', 'bin/ffmpeg', $base . '/no-such', $plain, $exe]) === $exe, 'ffmpeg resolves to the first absolute executable file, skipping relative, missing, and non-executable candidates');
    check(rateb_video_ffmpeg_path(['ffmpeg', $base . '/no-such', $plain]) === '', 'no usable candidate resolves to an empty path');
    check(rateb_video_ffmpeg_usable($exe) && !rateb_video_ffmpeg_usable('ffmpeg') && !rateb_video_ffmpeg_usable($plain), 'only an absolute executable file is usable');
    $savedHome = getenv('HOME');
    putenv('HOME=' . $base);
    $candidates = rateb_video_ffmpeg_candidates();
    putenv('HOME=relative/home');
    $relativeHome = rateb_video_ffmpeg_candidates();
    putenv($savedHome === false ? 'HOME' : 'HOME=' . $savedHome);
    check(($candidates[0] ?? '') === $base . '/bin/ffmpeg' && in_array('/usr/bin/ffmpeg', $candidates, true), "the account's own ffmpeg is tried first, then the system paths");
    check(array_filter($relativeHome, static fn (string $p): bool => $p[0] !== '/') === [], 'a relative HOME adds no candidate');
    @unlink($plain);
    @rmdir($tmpBase);
    foreach (glob($base . '/ffmpeg-*') ?: [] as $file) {
        @unlink($file);
    }
} else {
    echo "SKIP posix-only checks (file modes, process time limit, ffmpeg readiness) on Windows\n";
}

$runtimeTokens = token_get_all((string) file_get_contents(dirname(__DIR__) . '/app/video/runtime.php'));
$runtimeIncludes = array_filter($runtimeTokens, static fn ($t): bool => is_array($t) && in_array($t[0], [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], true));
check($runtimeIncludes === [], 'runtime.php loads no other file, so the preflight can run it alone');
$engineSource = (string) file_get_contents(dirname(__DIR__) . '/app/video/engine.php');
check(str_contains($engineSource, 'rateb_video_clip_command(') && str_contains($engineSource, 'rateb_video_compose_command(') && !str_contains($engineSource, 'zoompan='), 'the engine renders with the same command builders the readiness check uses');
$silent = rateb_video_compose_command('ffmpeg', '/x/list.txt', '', '', '/x/out.mp4');
$voiced = rateb_video_compose_command('ffmpeg', '/x/list.txt', '/x/voice.mp3', '', '/x/out.mp4');
$marked = rateb_video_compose_command('ffmpeg', '/x/list.txt', '', '/x/mark.png', '/x/out.mp4');
$both = rateb_video_compose_command('ffmpeg', '/x/list.txt', '/x/voice.mp3', '/x/mark.png', '/x/out.mp4');
check(str_contains($silent, ' -vf ') && str_contains($silent, ' -an ') && !str_contains($silent, 'overlay'), 'a silent unwatermarked compose keeps the simple filter');
check(str_contains($voiced, '-map ' . escapeshellarg('[v]') . ' -map 1:a -shortest') && !str_contains($voiced, 'overlay') && str_contains($voiced, '-c:a aac'), 'a voiced compose maps the voice as the second input');
check(str_contains($marked, ' -i ' . escapeshellarg('/x/mark.png') . ' -filter_complex ') && str_contains($marked, '[base][1:v]overlay=x=W-w-24:y=H-h-24,format=yuv420p[v]') && str_contains($marked, ' -an '), 'a silent watermarked compose overlays the PNG in the bottom corner');
check(strpos($both, escapeshellarg('/x/mark.png')) < strpos($both, escapeshellarg('/x/voice.mp3')) && strpos($both, escapeshellarg('/x/voice.mp3')) < strpos($both, ' -filter_complex ') && str_contains($both, '[1:v]overlay') && str_contains($both, ' -map 2:a -shortest'), 'a voiced watermarked compose lists every input before the output options and maps the voice as the third input');
check(array_filter([$silent, $voiced, $marked, $both], static fn (string $c): bool => str_contains($c, 'drawtext') || str_contains($c, 'font')) === [] && !str_contains((string) file_get_contents(dirname(__DIR__) . '/app/video/runtime.php'), ',drawtext'), 'no compose command needs drawtext or a font, so a build without FreeType can render');
check(rateb_video_text_support(" T.C drawtext V->V Draw text\n", "ffmpeg version n7.1 Copyright\n  configuration: --enable-gpl --enable-libfreetype --enable-libharfbuzz --enable-libfribidi --enable-fontconfig\n") === 'drawtext=present version=n7.1 libfreetype=yes libharfbuzz=yes libfribidi=yes libfontconfig=yes', 'text support reads a build with every text library');
check(rateb_video_text_support('', '') === 'drawtext=absent build=unknown' && rateb_video_text_support('', "configuration: --enable-libfreetype-x --disable-libharfbuzz\n") === 'drawtext=absent libfreetype=no libharfbuzz=no libfribidi=no libfontconfig=no', 'text support reports an unknown build and does not match lookalike flags');
if (function_exists('imagepng') && function_exists('imagecreatefrompng')) {
    $markFile = sys_get_temp_dir() . '/rateb-mark-' . bin2hex(random_bytes(4)) . '.png';
    $drawn = rateb_video_watermark_png($markFile);
    $png = $drawn ? imagecreatefrompng($markFile) : false;
    $white = 0;
    if ($png !== false) {
        for ($y = 0; $y < imagesy($png); $y++) {
            for ($x = 0; $x < imagesx($png); $x++) {
                $white += imagecolorat($png, $x, $y) === 0xFFFFFF ? 1 : 0;
            }
        }
    }
    check($drawn && $png !== false && imagesx($png) === 168 && imagesy($png) === 46 && (imagecolorat($png, 0, 0) >> 24) === 70 && $white > 100, 'the watermark PNG is a translucent box with opaque white text, drawn without fonts');
    @unlink($markFile);
    check(!rateb_video_watermark_png(sys_get_temp_dir() . '/no-such-dir-' . bin2hex(random_bytes(4)) . '/mark.png'), 'a watermark that cannot be written is reported, so the engine fails the job instead of rendering it unwatermarked');
} else {
    echo "SKIP watermark PNG drawing (GD not loaded)\n";
}
$composeSource = substr($engineSource, (int) strpos($engineSource, 'function rateb_video_compose('), 2600);
check(preg_match("/\\\$mark !== '' && !rateb_video_watermark_png\(\\\$mark\)\)\s*\{\s*throw new RuntimeException\('watermark'\)/", $composeSource) === 1, 'the engine fails a free-plan job when its watermark cannot be drawn');
check(preg_match("/\} finally \{\s*@unlink\(\\\$list\);\s*@unlink\(\\\$out\);\s*if \(\\\$mark !== ''\) \{\s*@unlink\(\\\$mark\);/", $composeSource) === 1 && strpos($composeSource, 'try {') < strpos($composeSource, 'rateb_video_watermark_png('), 'the watermark PNG, concat list, and temporary output are removed on every path, even when ffmpeg cannot start');
$q = static fn (string $s): string => escapeshellarg($s);
$scaleOnly = 'scale=720:1280:force_original_aspect_ratio=decrease,pad=720:1280:(ow-iw)/2:(oh-ih)/2,format=yuv420p';
$tail = ' -c:v libx264 -preset veryfast -crf 23 -c:a aac -movflags +faststart ' . $q('/x/out.mp4');
check($silent === $q('ffmpeg') . ' -nostdin -loglevel error -y -f concat -safe 0 -i ' . $q('/x/list.txt') . ' -vf ' . $q($scaleOnly) . ' -an' . $tail, 'a paid silent compose is byte-for-byte the command used before the watermark change');
check($voiced === $q('ffmpeg') . ' -nostdin -loglevel error -y -f concat -safe 0 -i ' . $q('/x/list.txt') . ' -i ' . $q('/x/voice.mp3') . ' -filter_complex ' . $q('[0:v]' . $scaleOnly . '[v]') . ' -map ' . $q('[v]') . ' -map 1:a -shortest' . $tail, 'a paid voiced compose is byte-for-byte the command used before the watermark change');

$preflight = dirname(__DIR__) . '/ops/preflight.php';
$pfSpawn = static function (array $env) use ($preflight): array {
    $process = proc_open([PHP_BINARY, $preflight], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env + ['PATH' => (string) getenv('PATH'), 'SystemRoot' => (string) getenv('SystemRoot')]);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['code' => proc_close($process), 'out' => (string) $out, 'err' => (string) $err];
};
$pfWeb = $pfSpawn(['REQUEST_METHOD' => 'GET']);
check($pfWeb['out'] === '' && $pfWeb['err'] === '', 'the preflight script refuses a web request before doing anything');
$pfEmpty = $pfSpawn(['HOME' => sys_get_temp_dir()]);
check($pfEmpty['code'] === 1 && str_contains($pfEmpty['out'], 'PREFLIGHT_TARGET=FAILED') && str_contains($pfEmpty['out'], 'PREFLIGHT_OK=no'), 'the preflight fails closed without a target');
$pfArgs = static function (array $env, array $args) use ($preflight): array {
    $process = proc_open(array_merge([PHP_BINARY, $preflight], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env + ['PATH' => (string) getenv('PATH'), 'SystemRoot' => (string) getenv('SystemRoot')]);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['code' => proc_close($process), 'out' => (string) $out, 'err' => (string) $err];
};
$pfBad = $pfArgs(['HOME' => sys_get_temp_dir()], ['--runtime=' . dirname(__DIR__) . '/app/video/runtime.php', '--ffmpeg=ffmpeg', '--media-root=' . $base . '/no-such-media']);
check($pfBad['code'] === 1 && str_contains($pfBad['out'], 'PREFLIGHT_FFMPEG=FAILED no executable ffmpeg at an absolute path') && str_contains($pfBad['out'], 'PREFLIGHT_FFMPEG_RENDER=FAILED not checked'), 'the preflight fails when ffmpeg is not an absolute executable path');
check(str_contains($pfBad['out'], $posix ? 'PREFLIGHT_MEDIA_ROOT=FAILED the media root does not exist' : 'PREFLIGHT_MEDIA_ROOT=FAILED'), 'the preflight fails when the media root is missing');
$pfRel = $pfArgs(['HOME' => sys_get_temp_dir()], ['--media-root=relative/media']);
check(str_contains($pfRel['out'], 'PREFLIGHT_MEDIA_ROOT=FAILED no absolute media root was given'), 'the preflight refuses a relative media root');
$pfSession = $pfArgs(['HOME' => sys_get_temp_dir(), 'SSH_CONNECTION' => '203.0.113.1 5000 203.0.113.2 22'], ['--cron-env']);
check(str_contains($pfSession['out'], 'PREFLIGHT_CRON_ENV=FAILED') && str_contains($pfSession['out'], 'PREFLIGHT_OK=no'), "the preflight's cron check fails when run with the SSH session environment");
$pfInherited = $pfArgs(['HOME' => sys_get_temp_dir(), 'RATEB_AI_DB_PASSWORD' => 'from-session'], ['--cron-env']);
check(str_contains($pfInherited['out'], 'PREFLIGHT_CRON_ENV=FAILED the database password came from the session environment') && !str_contains($pfInherited['out'], 'from-session'), "the preflight's cron check fails when the database password comes from the session, without printing it");

$state = $base . '/state';
mkdir($state, 0700);
touch($state . '/video-worker.hold', time() - 7200);
$held = $spawn(['HOME' => $base], ['--log=' . $state . '/video-worker.log']);
check($held['code'] === 0 && $held['out'] === '' && $held['err'] === '' && !is_file($state . '/video-worker.log'), 'a hold file stops the worker before it loads code or connects, however old it is');
unlink($state . '/video-worker.hold');
@rmdir($state);
foreach (glob($docroot . '/{,.}*', GLOB_BRACE) ?: [] as $file) {
    if (is_file($file)) {
        @unlink($file);
    }
}
foreach (glob($private . '/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($docroot);
@rmdir($private);
@rmdir($base);

$status = (string) file_get_contents(dirname(__DIR__) . '/app/ai/video-status.php');
check(str_contains($status, 'rateb_video_reap(') && !str_contains($status, 'rateb_video_advance('), 'the status endpoint only reports and reaps; it never renders');
$webPaths = [dirname(__DIR__) . '/app/ai/video.php', dirname(__DIR__) . '/app/ai/video-status.php', dirname(__DIR__) . '/campaign.php'];
$spawns = array_filter($webPaths, static fn (string $file): bool => preg_match('/(?<![\w>:$])(proc_open|exec|shell_exec|system|passthru|popen|rateb_video_advance|rateb_video_run_ffmpeg)\s*\(/', (string) file_get_contents($file)) === 1);
check($spawns === [], 'the video web endpoints never start a process or render');
check(rateb_video_message('busy', true) !== rateb_video_message('unknown-code', true) && rateb_video_message('busy', false) !== rateb_video_message('unknown-code', false), 'the busy state has its own message in both languages');

echo $failures === 0 ? "RESULT=PASS\n" : "RESULT=FAIL failures={$failures}\n";
exit($failures === 0 ? 0 : 1);
