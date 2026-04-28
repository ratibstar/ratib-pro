<?php
declare(strict_types=1);

/**
 * Architecture lock checker.
 *
 * Rules enforced:
 * - No SQL patterns in includes/pages UI layers
 * - Single onboarding endpoint usage in JS
 * - No duplicate worker AI workflow submit helpers
 */

$root = dirname(__DIR__);

$checks = [
    [
        'name' => 'No SQL in includes/',
        'directory' => $root . '/includes',
        'pattern' => '/SELECT |INSERT |UPDATE |DELETE |->query\(|->prepare\(/i',
        'allowPaths' => [
            '/includes/helpers/SecureQueryExample.php',
            '/includes/helpers/CountryFilter.php',
        ],
    ],
    [
        'name' => 'No SQL in pages/',
        'directory' => $root . '/pages',
        'pattern' => '/SELECT |INSERT |UPDATE |DELETE |->query\(|->prepare\(/i',
        'allowPaths' => [],
    ],
    [
        'name' => 'Single onboarding endpoint in JS',
        'directory' => $root . '/js',
        'pattern' => '/worker-platform\.php\/workflows|\/workflows\/worker-onboarding/i',
        'allowPaths' => [
            '/js/utils/global-ai-action.js',
        ],
    ],
    [
        'name' => 'No duplicate worker AI submit helpers',
        'directory' => $root . '/js',
        'pattern' => '/runAiWorkerWorkflow|getWorkflowEndpoint|buildAiWorkflowPayload/i',
        'allowPaths' => [],
    ],
];

$scan = static function (string $directory, string $pattern): array {
    $violations = [];
    if (!is_dir($directory)) {
        return $violations;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile()) {
            continue;
        }
        $path = $file->getPathname();
        $content = @file_get_contents($path);
        if ($content === false || $content === '') {
            continue;
        }
        if (preg_match($pattern, $content) === 1) {
            $violations[] = str_replace('\\', '/', $path);
        }
    }

    return $violations;
};

$failed = false;
foreach ($checks as $check) {
    $output = $scan($check['directory'], $check['pattern']);
    $violations = [];
    foreach ($output as $line) {
        $normalized = str_replace('\\', '/', (string) $line);
        $allowed = false;
        foreach ($check['allowPaths'] as $allowPath) {
            if (str_contains($normalized, $allowPath)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            $violations[] = $line;
        }
    }

    if (!empty($violations)) {
        $failed = true;
        echo "[FAIL] {$check['name']}\n";
        foreach ($violations as $violation) {
            echo "  - {$violation}\n";
        }
        echo "\n";
    } else {
        echo "[PASS] {$check['name']}\n";
    }
}

exit($failed ? 1 : 0);
