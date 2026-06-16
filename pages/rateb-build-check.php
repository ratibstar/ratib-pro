<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$root = dirname(__DIR__);
$aboutPath = $root . '/pages/about.php';
$homePath = $root . '/pages/home.php';
$homeHead = is_file($homePath) ? (string) file_get_contents($homePath, false, null, 0, 8000) : '';

$checks = [
    'bundle' => 'about-enterprise-20260516-v9',
    'git_marker' => is_file($root . '/public/rateb-build.txt') ? trim((string) file_get_contents($root . '/public/rateb-build.txt')) : 'missing',
    'about_php' => is_file($aboutPath) ? 'yes' : 'no',
    'home_open_about' => str_contains($homeHead, "'about'") ? 'yes' : 'no',
    'workflow_index' => is_file($root . '/public/workflows/worker-onboarding/index.php')
        ? (string) filemtime($root . '/public/workflows/worker-onboarding/index.php')
        : 'missing',
    'standalone_include' => is_file($root . '/includes/worker_onboarding_standalone.php') ? 'yes' : 'no',
];

$workflowHead = '';
$wf = $root . '/public/workflows/worker-onboarding/index.php';
if (is_file($wf)) {
    $head = (string) file_get_contents($wf, false, null, 0, 400);
    if (str_contains($head, 'standalone-single-file-20260516-v4')) {
        $workflowHead = 'v4-single-file';
    } elseif (str_contains($head, 'Autoloader')) {
        $workflowHead = 'OLD-autoloader-version';
    } else {
        $workflowHead = 'other';
    }
}
$checks['workflow_version'] = $workflowHead;

echo "rateb-build-check\n";
foreach ($checks as $k => $v) {
    echo $k . '=' . $v . "\n";
}

