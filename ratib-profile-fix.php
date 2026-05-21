<?php
/**
 * One-page status + what to upload (open in browser after any deploy attempt).
 * https://out.ratib.sa/ratib-profile-fix.php
 */
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$root = __DIR__;
$host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : 'out.ratib.sa';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base = $scheme . '://' . $host;

function ratib_fix_has(string $path, string $needle): bool
{
    if (!is_file($path)) {
        return false;
    }
    $s = (string) @file_get_contents($path, false, null, 0, 20000);
    return $needle !== '' && stripos($s, $needle) !== false;
}

$chrome = $root . '/includes/ratib-home-public-chrome-top.php';
$sync = $root . '/includes/ratib-home-public-nav-sync.php';
$home = $root . '/pages/home.php';
$htaccess = $root . '/.htaccess';
$patch = $root . '/includes/ratib_html_global_ai_patch.php';
$build = $root . '/public/ratib-build.txt';

$diskV13 = ratib_fix_has($chrome, 'v13-onclick');
$diskHeadLock = ratib_fix_has($sync, 'ratib-profile-head-lock');
$diskHtRedirect = ratib_fix_has($htaccess, 'pages/company-profile\\.php$ /profile');
$diskHtmlPatch = ratib_fix_has($patch, 'ratib_register_public_profile_nav_patch');
$diskHomeBytes = is_file($home) ? (int) filesize($home) : 0;

$liveHome = '';
if (function_exists('curl_init')) {
    $ch = curl_init($base . '/pages/home.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => ['Cache-Control: no-cache'],
    ]);
    $liveHome = (string) curl_exec($ch);
    curl_close($ch);
}
$liveV13 = stripos($liveHome, 'ratib-profile-nav=v13-onclick') !== false;
$liveStale = stripos($liveHome, '/pages/company-profile.php') !== false;
$liveHeadLock = stripos($liveHome, 'ratib-profile-head-lock') !== false;

$buildMarker = is_file($build) ? trim((string) file_get_contents($build)) : 'missing';

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RATEB Profile fix status</title>
    <style>
        body{font-family:system-ui,sans-serif;max-width:720px;margin:2rem auto;padding:0 1rem;line-height:1.5}
        .ok{color:#0a7}.bad{color:#c30}.box{background:#f4f4f5;border-radius:8px;padding:1rem;margin:1rem 0}
        code{font-size:.9em}
        a.btn{display:inline-block;margin:.5rem .5rem 0 0;padding:.5rem 1rem;background:#6b21a8;color:#fff;text-decoration:none;border-radius:6px}
    </style>
</head>
<body>
<h1>Profile nav — live status</h1>
<p>Time (UTC): <?php echo htmlspecialchars(gmdate('c'), ENT_QUOTES, 'UTF-8'); ?></p>

<table>
    <tr><td>Disk chrome v13</td><td class="<?php echo $diskV13 ? 'ok' : 'bad'; ?>"><?php echo $diskV13 ? 'YES' : 'NO — upload chrome-top.php'; ?></td></tr>
    <tr><td>Disk head-lock script</td><td class="<?php echo $diskHeadLock ? 'ok' : 'bad'; ?>"><?php echo $diskHeadLock ? 'YES' : 'NO — upload nav-sync.php'; ?></td></tr>
    <tr><td>Disk .htaccess redirect</td><td class="<?php echo $diskHtRedirect ? 'ok' : 'bad'; ?>"><?php echo $diskHtRedirect ? 'YES' : 'NO — edit .htaccess'; ?></td></tr>
    <tr><td>Disk HTML auto-patch</td><td class="<?php echo $diskHtmlPatch ? 'ok' : 'bad'; ?>"><?php echo $diskHtmlPatch ? 'YES' : 'NO — upload ratib_html_global_ai_patch.php'; ?></td></tr>
    <tr><td>home.php bytes</td><td><?php echo (int) $diskHomeBytes; ?> (want ~90500)</td></tr>
    <tr><td>Build marker</td><td><code><?php echo htmlspecialchars($buildMarker, ENT_QUOTES, 'UTF-8'); ?></code></td></tr>
    <tr><td>Live home v13</td><td class="<?php echo $liveV13 ? 'ok' : 'bad'; ?>"><?php echo $liveV13 ? 'YES' : 'NO (cached or old)'; ?></td></tr>
    <tr><td>Live home stale links</td><td class="<?php echo $liveStale ? 'bad' : 'ok'; ?>"><?php echo $liveStale ? 'YES company-profile.php' : 'NO'; ?></td></tr>
    <tr><td>Live head-lock injected</td><td class="<?php echo $liveHeadLock ? 'ok' : 'bad'; ?>"><?php echo $liveHeadLock ? 'YES' : 'NO'; ?></td></tr>
</table>

<div class="box">
    <strong>If GitHub push does nothing:</strong> PHP on this server cannot write files (uid 65534).
    Use <strong>cPanel → File Manager</strong> or <strong>Git Version Control → Pull</strong> into
    <code>/home/outratib/public_html</code>.
</div>

<div class="box">
    <strong>Fastest fix (3 files):</strong>
    <ol>
        <li><code>.htaccess</code> — redirects old Profile URLs to <code>/profile</code></li>
        <li><code>pages/company-profile.php</code> — PHP redirect backup</li>
        <li><code>includes/ratib_html_global_ai_patch.php</code> — injects click fix when PHP runs</li>
    </ol>
    Then: cPanel → LiteSpeed → <strong>Purge All</strong>
</div>

<a class="btn" href="<?php echo htmlspecialchars($base . '/profile', ENT_QUOTES, 'UTF-8'); ?>">Open /profile</a>
<a class="btn" href="<?php echo htmlspecialchars($base . '/pages/home.php', ENT_QUOTES, 'UTF-8'); ?>">Open home</a>
<a class="btn" href="<?php echo htmlspecialchars($base . '/ratib-profile-check.php', ENT_QUOTES, 'UTF-8'); ?>">Full check</a>
</body>
</html>
