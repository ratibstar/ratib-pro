<?php
/**
 * Visual check: are you on marketing home or company profile?
 * https://rateb.sa/pages/rateb-which-page.php
 */
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
header('Pragma: no-cache');
header('X-LiteSpeed-Cache-Control: no-cache');

$host = $_SERVER['HTTP_HOST'] ?? 'rateb.sa';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base = $scheme . '://' . $host;
$buildPath = dirname(__DIR__) . '/public/rateb-build.txt';
$build = is_file($buildPath) ? trim((string) file_get_contents($buildPath)) : 'missing';

function rateb_which_fetch(string $url): string
{
    if (!function_exists('curl_init')) {
        return '';
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'RATEBWhichPage/1',
        CURLOPT_HTTPHEADER => ['Cache-Control: no-cache', 'Pragma: no-cache'],
    ]);
    $body = curl_exec($ch);
    curl_close($ch);

    return is_string($body) ? $body : '';
}

$profileHtml = rateb_which_fetch($base . '/profile/?_r=' . time());
$homeHtml = rateb_which_fetch($base . '/pages/home.php?_r=' . time());

$profileOk = str_contains($profileHtml, 'rateb-profile-distinct-banner')
    && str_contains($profileHtml, 'RATEB <span class="rateb-about-gradient">dashboard</span>');
$homeOk = str_contains($homeHtml, 'rateb-hero__title')
    && !str_contains($homeHtml, 'rateb-profile-distinct-banner');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store">
    <title>RATEB — which page am I on?</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; padding: 1.25rem; }
        h1 { font-size: 1.35rem; margin: 0 0 1rem; }
        .card { border-radius: 12px; padding: 1rem 1.1rem; margin-bottom: 1rem; border: 2px solid; }
        .ok { background: #052e16; border-color: #22c55e; }
        .bad { background: #450a0a; border-color: #ef4444; }
        .card h2 { margin: 0 0 .5rem; font-size: 1.05rem; }
        a.btn { display: inline-block; margin: .35rem .5rem .35rem 0; padding: .65rem 1rem; border-radius: 8px; font-weight: 700; text-decoration: none; color: #fff; }
        .btn-profile { background: #7c3aed; }
        .btn-home { background: #0369a1; }
        .btn-purge { background: #b45309; }
        code { background: #1e293b; padding: .15rem .4rem; border-radius: 4px; font-size: .85rem; }
        p { line-height: 1.5; margin: .5rem 0; }
        ul { margin: .5rem 0; padding-left: 1.2rem; }
    </style>
</head>
<body>
    <h1>Which page should you see?</h1>
    <p>Server build on disk: <code><?php echo htmlspecialchars($build, ENT_QUOTES, 'UTF-8'); ?></code></p>

    <div class="card <?php echo $profileOk ? 'ok' : 'bad'; ?>">
        <h2>Company profile — <?php echo $profileOk ? 'LIVE OK' : 'PROBLEM'; ?></h2>
        <p>URL: <code>/profile/</code></p>
        <p>You must see: top banner, headline <strong>RATEB dashboard</strong>, bottom-right badge <strong>COMPANY PROFILE</strong>.</p>
        <a class="btn btn-profile" href="<?php echo htmlspecialchars($base . '/profile/?_r=' . time(), ENT_QUOTES, 'UTF-8'); ?>">Open profile now</a>
    </div>

    <div class="card <?php echo $homeOk ? 'ok' : 'bad'; ?>">
        <h2>Marketing home — <?php echo $homeOk ? 'LIVE OK' : 'PROBLEM'; ?></h2>
        <p>URL: <code>/pages/home.php</code></p>
        <p>You must see: headline <strong>Orchestration Platform</strong>, video section, badge <strong>MARKETING HOME</strong> (bottom-right).</p>
        <a class="btn btn-home" href="<?php echo htmlspecialchars($base . '/pages/home.php?_r=' . time(), ENT_QUOTES, 'UTF-8'); ?>">Open marketing home</a>
    </div>

    <div class="card ok">
        <h2>If profile still looks like home on your PC</h2>
        <ul>
            <li>Use the buttons above (they add <code>?_r=</code> to bust cache).</li>
            <li>Hard refresh: <strong>Ctrl+Shift+R</strong></li>
            <li>cPanel → LiteSpeed → <strong>Purge All</strong></li>
            <li>Try Incognito / another browser</li>
        </ul>
        <a class="btn btn-purge" href="<?php echo htmlspecialchars($base . '/pages/rateb-purge-cache.php?key=rateb-deploy-sync-2026', ENT_QUOTES, 'UTF-8'); ?>">Purge LiteSpeed cache</a>
    </div>
</body>
</html>
