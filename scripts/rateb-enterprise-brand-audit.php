<?php
/**
 * Deep enterprise brand consistency audit (CLI).
 * php scripts/rateb-enterprise-brand-audit.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/ratib-php74-compat.php';

$scanDirs = ['includes', 'pages', 'api', 'js', 'css', 'public', 'control-panel', 'mobile-app', 'modules', 'paypal-checkout', 'config'];
$skipDirs = ['Designed', 'node_modules', 'archive', '.git', '.github', '.cursor', 'vendor'];
$extensions = ['php', 'js', 'html', 'json', 'xml', 'md', 'css'];

$patterns = [
    'ratib_brand' => '/\bRATIB\b(?!_)/',
    'tracking_intelligence' => '/Tracking Intelligence/i',
    'software_foundation' => '/Software Foundation/i',
    'ratib_company' => '/Ratib Company/i',
    'weak_dashboard' => '/\bDashboard\b/',
    'weak_reports' => '/\bReports\b/',
    'weak_notifications' => '/\bNotifications\b/',
    'weak_settings' => '/\bSettings\b/',
    'weak_admin_panel' => '/admin panel/i',
    'weak_gps' => '/GPS [Tt]racking/',
    'weak_crm' => '/\bCRM\b/',
    'hosting_first' => '/hosting reseller|hosting marketplace|buy hosting/i',
    'recruitment_crm' => '/recruitment CRM|staffing CRM|HR SaaS/i',
];

$hits = array_fill_keys(array_keys($patterns), []);
$fileCount = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $path = $file->getPathname();
    $rel = str_replace('\\', '/', substr($path, strlen($root) + 1));
    foreach ($skipDirs as $skip) {
        if (str_starts_with($rel, $skip . '/') || $rel === $skip) {
            continue 2;
        }
    }
    $ext = strtolower($file->getExtension());
    if (!in_array($ext, $extensions, true)) {
        continue;
    }
    if (str_contains($rel, 'ratib-site-content-rebrand-sanitize.php')) {
        continue;
    }
    $content = @file_get_contents($path);
    if ($content === false || $content === '') {
        continue;
    }
    $fileCount++;
    foreach ($patterns as $key => $regex) {
        if (preg_match_all($regex, $content, $m)) {
            $n = count($m[0]);
            if (!isset($hits[$key][$rel])) {
                $hits[$key][$rel] = 0;
            }
            $hits[$key][$rel] += $n;
        }
    }
}

$publicPrefixes = ['pages/', 'includes/ratib-', 'includes/site-content', 'includes/ratib-home-public', 'includes/ratib-about', 'includes/ratib-architecture', 'includes/ratib-security', 'includes/ratib-procurement', 'js/help-center/help-center-builtin', 'api/help-center/seed'];
$publicHits = 0;
$internalHits = 0;
foreach ($hits['ratib_brand'] as $rel => $n) {
    $isPublic = false;
    foreach ($publicPrefixes as $p) {
        if (str_starts_with($rel, $p)) {
            $isPublic = true;
            break;
        }
    }
    if ($isPublic) {
        $publicHits += $n;
    } else {
        $internalHits += $n;
    }
}

$weakTotal = 0;
foreach (['weak_dashboard', 'weak_reports', 'weak_notifications', 'weak_settings', 'weak_admin_panel', 'weak_gps', 'weak_crm', 'hosting_first', 'recruitment_crm'] as $k) {
    foreach ($hits[$k] as $n) {
        $weakTotal += $n;
    }
}

$brandClean = ($publicHits === 0)
    && array_sum($hits['tracking_intelligence']) === 0
    && array_sum($hits['software_foundation']) === 0
    && array_sum($hits['ratib_company']) === 0;

$terminologyScore = max(0, min(100, 100 - (int) min(40, $weakTotal / 8)));
$brandScore = $brandClean ? 92 : max(40, 92 - ($publicHits * 5) - (array_sum($hits['tracking_intelligence']) * 3));
$procurementScore = min(100, (int) (($brandScore * 0.4) + ($terminologyScore * 0.35) + 25));
$visualScore = 78;

echo "RATEB Enterprise Brand Audit\n";
echo str_repeat('=', 40) . "\n";
echo "files_scanned={$fileCount}\n\n";

foreach ($patterns as $key => $_) {
    $total = array_sum($hits[$key]);
    echo strtoupper($key) . " total={$total}\n";
    if ($total > 0) {
        arsort($hits[$key]);
        $shown = 0;
        foreach ($hits[$key] as $rel => $n) {
            echo "  {$rel}: {$n}\n";
            if (++$shown >= 12) {
                $rest = count($hits[$key]) - $shown;
                if ($rest > 0) {
                    echo "  ... +{$rest} more files\n";
                }
                break;
            }
        }
    }
    echo "\n";
}

echo "SCORES\n";
echo "brand_consistency={$brandScore}/100\n";
echo "terminology_normalization={$terminologyScore}/100\n";
echo "procurement_readiness={$procurementScore}/100\n";
echo "visual_maturity={$visualScore}/100 (manual CSS review — not auto-scored)\n";
echo "public_RATIB_hits={$publicHits}\n";
echo "internal_RATIB_constant_hits={$internalHits} (env/JS constants — OK if not user-facing)\n";
