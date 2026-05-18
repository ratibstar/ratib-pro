<?php
/**
 * Check (and optionally fix) permissions for out.ratib.sa on cPanel.
 *
 * Check:  https://out.ratib.sa/pages/ratib-perms-check.php
 * Fix:    https://out.ratib.sa/pages/ratib-perms-check.php?fix=1&key=ratib-deploy-sync-2026
 *
 * Expected:
 *   /home/USER     → 0711 or 0750+ (owner can enter)
 *   public_html    → 0755
 *   subdirs        → 0755
 *   .php/.css/.htaccess → 0644
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$root = dirname(__DIR__);
$doc = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$home = dirname($root);
$host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';

$doFix = isset($_GET['fix']) && (string) $_GET['fix'] === '1';
$key = isset($_GET['key']) ? (string) $_GET['key'] : '';
if ($doFix && !hash_equals('ratib-deploy-sync-2026', $key)) {
    http_response_code(403);
    echo "Forbidden. Use: ?fix=1&key=ratib-deploy-sync-2026\n";
    exit;
}

$skipDirs = ['.git', 'node_modules', 'vendor', 'Designed'];

function ratib_mode($path)
{
    if (!file_exists($path)) {
        return null;
    }
    $p = @fileperms($path);
    if ($p === false) {
        return null;
    }

    return sprintf('%04o', $p & 07777);
}

function ratib_owner($path)
{
    if (!function_exists('posix_getpwuid') || !file_exists($path)) {
        return '';
    }
    $uid = @fileowner($path);
    if ($uid === false) {
        return '';
    }
    $info = @posix_getpwuid($uid);

    return is_array($info) && isset($info['name']) ? (string) $info['name'] : (string) $uid;
}

function ratib_dir_ok($mode)
{
    if ($mode === null) {
        return false;
    }
    $m = octdec($mode);
    // Owner must have execute (enter directory)
    if (($m & 0100) === 0) {
        return false;
    }
    // 0644 on a directory is always wrong
    if (($m & 0111) === 0) {
        return false;
    }

    return true;
}

function ratib_home_ok($mode)
{
    if ($mode === null) {
        return false;
    }
    $m = octdec($mode);
    if (($m & 0100) === 0) {
        return false;
    }
    // Accept 0711, 0750, 0755, etc.
    return true;
}

function ratib_file_ok($mode)
{
    if ($mode === null) {
        return false;
    }
    $m = octdec($mode);

    return ($m & 0777) === 0644;
}

function ratib_should_skip($path, array $skipDirs)
{
    foreach ($skipDirs as $skip) {
        if (strpos($path, DIRECTORY_SEPARATOR . $skip . DIRECTORY_SEPARATOR) !== false) {
            return true;
        }
        if (substr($path, -strlen(DIRECTORY_SEPARATOR . $skip)) === DIRECTORY_SEPARATOR . $skip) {
            return true;
        }
    }

    return false;
}

$issues = 0;
$fixed = 0;

echo "ratib-perms-check\n";
echo 'php=' . PHP_VERSION . "\n";
echo 'time=' . gmdate('c') . "\n";
echo 'document_root=' . $doc . "\n";
echo 'public_html=' . $root . "\n";
echo 'home=' . $home . "\n";
echo 'mode=' . ($doFix ? 'FIX' : 'CHECK') . "\n\n";

// --- Home ---
$homeMode = ratib_mode($home);
$homeOk = ratib_home_ok($homeMode);
echo "--- Home directory ---\n";
echo 'path=' . $home . "\n";
echo 'mode=' . ($homeMode ?? 'missing') . ' owner=' . ratib_owner($home) . ' expected=0711|0750+ ' . ($homeOk ? '[OK]' : '[BAD]') . "\n";
if (!$homeOk) {
    $issues++;
    if ($doFix && is_dir($home)) {
        if (@chmod($home, 0711)) {
            echo "FIXED home -> 0711\n";
            $fixed++;
        }
    }
}
echo "\n";

// --- public_html ---
$rootMode = ratib_mode($root);
$rootOk = $rootMode === '0755' || ratib_dir_ok($rootMode);
echo "--- public_html ---\n";
echo 'path=' . $root . "\n";
echo 'mode=' . ($rootMode ?? 'missing') . ' owner=' . ratib_owner($root) . ' expected=0755 ' . ($rootMode === '0755' ? '[OK]' : ($rootOk ? '[OK-ish]' : '[BAD]')) . "\n";
echo 'readable=' . (is_readable($root) ? 'yes' : 'no') . "\n";
if ($rootMode !== '0755') {
  if (!ratib_dir_ok($rootMode)) {
    $issues++;
  }
  if ($doFix && is_dir($root)) {
    if (@chmod($root, 0755)) {
      echo "FIXED public_html -> 0755\n";
      $fixed++;
    }
  }
}
echo "\n";

// --- Root .htaccess ---
$ht = $root . '/.htaccess';
$htMode = ratib_mode($ht);
$htOk = ratib_file_ok($htMode) && is_readable($ht);
echo "--- Root .htaccess ---\n";
echo 'path=' . $ht . "\n";
echo 'exists=' . (is_file($ht) ? 'yes' : 'no') . "\n";
echo 'mode=' . ($htMode ?? 'n/a') . ' owner=' . ratib_owner($ht) . ' expected=0644 ' . ($htOk ? '[OK]' : '[BAD]') . "\n";
echo 'readable=' . (is_readable($ht) ? 'yes' : 'no') . "\n";
if (!$htOk) {
    $issues++;
    if ($doFix && is_file($ht)) {
        if (@chmod($ht, 0644)) {
            echo "FIXED .htaccess -> 0644\n";
            $fixed++;
        }
    }
}
echo "\n";

// --- Walk tree (sample + all bad) ---
echo "--- Scan public_html (folders must be 755, key files 644) ---\n";
$badDirs = 0;
$badFiles = 0;
$scannedDirs = 0;
$scannedFiles = 0;
$maxBadPrint = 40;
$printed = 0;

$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iter as $item) {
    $path = $item->getPathname();
    if (ratib_should_skip($path, $skipDirs)) {
        continue;
    }

    if ($item->isDir()) {
        $scannedDirs++;
        $mode = ratib_mode($path);
        if ($mode === '0644' || !ratib_dir_ok($mode)) {
            $badDirs++;
            $issues++;
            if ($printed < $maxBadPrint) {
                echo "[BAD DIR] mode={$mode} {$path}\n";
                $printed++;
            }
            if ($doFix) {
                if (@chmod($path, 0755)) {
                    $fixed++;
                }
            }
        }
        continue;
    }

    $base = $item->getBasename();
    $ext = strtolower($item->getExtension());
    $check = ($base === '.htaccess')
        || in_array($ext, ['php', 'css', 'js', 'svg', 'html', 'json', 'txt', 'ico', 'woff', 'woff2'], true);
    if (!$check) {
        continue;
    }

    $scannedFiles++;
    $mode = ratib_mode($path);
    if (!ratib_file_ok($mode)) {
        $badFiles++;
        $issues++;
        if ($printed < $maxBadPrint) {
            echo "[BAD FILE] mode={$mode} {$path}\n";
            $printed++;
        }
        if ($doFix) {
            if (@chmod($path, 0644)) {
                $fixed++;
            }
        }
    }
}

if ($printed >= $maxBadPrint) {
    echo "(only first {$maxBadPrint} problems shown)\n";
}

echo "\n--- Summary ---\n";
echo "scanned_dirs={$scannedDirs} bad_dirs={$badDirs}\n";
echo "scanned_key_files={$scannedFiles} bad_files={$badFiles}\n";
echo "issues={$issues} fixed={$fixed}\n";

// Quick HTTP self-check
if (function_exists('curl_init')) {
    $cssUrl = 'https://' . $host . '/css/pages/home-public.css';
    $ch = curl_init($cssUrl);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    curl_exec($ch);
    $cssHttp = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "http_css={$cssHttp} " . ($cssHttp === 200 ? '[OK]' : '[BAD]') . "\n";
}

echo "\n";
if ($doFix) {
    echo "Fix applied. Test: https://{$host}/pages/home.php\n";
    echo "Re-run check: https://{$host}/pages/ratib-perms-check.php\n";
} else {
    echo "Auto-fix: https://{$host}/pages/ratib-perms-check.php?fix=1&key=ratib-deploy-sync-2026\n";
}
