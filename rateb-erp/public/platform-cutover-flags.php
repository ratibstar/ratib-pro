<?php
/**
 * Platform cutover remote flags (B1-Prep G3).
 * Change flags without application code deploy via:
 * - env RATEB_PLATFORM_CUTOVER_FLAGS (JSON object)
 * - file rateb-erp/storage/platform-cutover-flags.json
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Rateb-Cutover-Flags: 1');

$defaults = [
    'CompatGateEnabled' => false,
    'PlatformEnabled' => false,
    'PlatformShadow' => false,
    'PlatformCutover' => false,
    'EmergencyRollback' => false,
    'PlatformQueueMigrate' => false,
    'PlatformIdentityBridge' => false,
    'PlatformAdminSW' => false,
];

$flags = $defaults;
$source = 'defaults';

$envJson = getenv('RATEB_PLATFORM_CUTOVER_FLAGS');
if (is_string($envJson) && $envJson !== '') {
    $decoded = json_decode($envJson, true);
    if (is_array($decoded)) {
        $payload = isset($decoded['flags']) && is_array($decoded['flags']) ? $decoded['flags'] : $decoded;
        foreach ($defaults as $key => $_v) {
            if (array_key_exists($key, $payload)) {
                $flags[$key] = (bool) $payload[$key];
            }
        }
        $source = 'env';
    }
}

$storageFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'platform-cutover-flags.json';
if (is_file($storageFile)) {
    $raw = @file_get_contents($storageFile);
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $payload = isset($decoded['flags']) && is_array($decoded['flags']) ? $decoded['flags'] : $decoded;
            foreach ($defaults as $key => $_v) {
                if (array_key_exists($key, $payload)) {
                    $flags[$key] = (bool) $payload[$key];
                }
            }
            $source = 'storage';
        }
    }
}

if (isset($_GET['EmergencyRollback']) && (string) $_GET['EmergencyRollback'] === '1') {
    $flags['EmergencyRollback'] = true;
    $source .= '+query';
}

echo json_encode([
    'ok' => true,
    'source' => $source,
    'flags' => $flags,
    'at' => gmdate('c'),
], JSON_UNESCAPED_SLASHES);
