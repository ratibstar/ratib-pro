<?php
declare(strict_types=1);

/**
 * Non-billable free-video provider status (optional --probe for account checks).
 * Never starts generation and never deducts RATEB credits.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/video/provider.php';

$probe = in_array('--probe', $argv ?? [], true);
echo 'FREE_ONLY_MODE=' . (rateb_video_free_only_mode() ? 'yes' : 'no') . PHP_EOL;
echo 'ACTIVE_PROVIDER=' . rateb_video_provider()->name() . PHP_EOL;
echo 'ACTIVE_CONFIGURED=' . (rateb_video_provider()->configured() ? 'yes' : 'no') . PHP_EOL;
foreach (rateb_video_free_provider_statuses($probe) as $row) {
    echo 'PROVIDER=' . json_encode([
        'provider' => $row['provider'],
        'configured' => $row['configured'],
        'free_entitlement_flag' => $row['free_entitlement_flag'],
        'quota_status' => $row['quota_status'],
        'free_available' => $row['free_available'],
        'eligible' => $row['eligible'],
        'exhausted' => $row['exhausted'],
        'last_error' => $row['last_error'],
        'last_success_at' => $row['last_success_at'],
        'notes' => $row['notes'],
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
