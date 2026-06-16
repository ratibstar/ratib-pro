<?php
declare(strict_types=1);

/**
 * Global AI workflow (api/workflows).
 */
$dir = realpath(__DIR__) ?: __DIR__;
for ($i = 0; $i < 12; $i++) {
    $standalone = $dir . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'worker_onboarding_standalone.php';
    if (is_file($standalone)) {
        require_once $standalone;
        rateb_worker_onboarding_standalone_run();
        return;
    }
    $parent = dirname($dir);
    if ($parent === $dir) {
        break;
    }
    $dir = $parent;
}

header('Content-Type: application/json; charset=utf-8');
http_response_code(503);
echo json_encode(['success' => false, 'message' => 'Could not locate includes/worker_onboarding_standalone.php']);
