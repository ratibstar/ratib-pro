<?php
declare(strict_types=1);

/**
 * Phase 9 — Subscription Admin ViewModel / pagination tests.
 * Run: php rateb-erp/modules/subscription/tests/SubscriptionAdminPhase9Test.php
 */

$mod = dirname(__DIR__);
foreach ([
    'admin/SubscriptionAdminDashboard.php',
    'admin/SubscriptionAdminViewModel.php',
] as $file) {
    require_once $mod . '/' . $file;
}

use Rateb\App\Subscription\Admin\SubscriptionAdminDashboard;
use Rateb\App\Subscription\Admin\SubscriptionAdminViewModel;

$failed = 0;
function expect(bool $cond, string $msg): void
{
    global $failed;
    if (!$cond) {
        echo "FAIL: {$msg}\n";
        $failed++;
        return;
    }
    echo "OK: {$msg}\n";
}

$today = '2026-08-10';

// Pagination
$p = SubscriptionAdminViewModel::pagination(0, 0);
expect($p['page'] === 1 && $p['limit'] === 1 && $p['offset'] === 0, 'pagination clamps low');
$p2 = SubscriptionAdminViewModel::pagination(3, 25);
expect($p2['offset'] === 50, 'pagination offset page 3');
$p3 = SubscriptionAdminViewModel::pagination(1, 999);
expect($p3['limit'] === 100, 'pagination clamps high limit');

// Active tenant row
$active = SubscriptionAdminViewModel::mapTenantRow([
    'company_id' => 10,
    'company_name' => 'Acme',
    'current_status' => 'ACTIVE',
    'subscription_start' => '2026-01-01',
    'subscription_end' => '2026-08-20',
    'suspended_at' => null,
    'renewed_at' => '2026-07-01 10:00:00',
    'grace_started_at' => null,
    'grace_end_at' => null,
], $today);
expect($active['status'] === 'ACTIVE', 'active status');
expect($active['days_remaining'] === 10, 'active days remaining');
expect($active['expiring_soon'] === true, 'active expiring soon (<=14)');
expect($active['grace_status'] === 'none', 'active grace none');
expect($active['suspension_status'] === 'clear', 'active suspension clear');
expect($active['company_name'] === 'Acme', 'company name');

// Suspended
$sus = SubscriptionAdminViewModel::mapTenantRow([
    'company_id' => 20,
    'company_name' => 'Down Co',
    'current_status' => 'SUSPENDED',
    'subscription_start' => '2025-01-01',
    'subscription_end' => '2026-07-01',
    'suspended_at' => '2026-07-10 12:00:00',
    'renewed_at' => null,
], $today);
expect($sus['suspension_status'] === 'suspended', 'suspended label');
expect($sus['grace_status'] === 'n/a', 'suspended grace n/a');
expect($sus['expiring_soon'] === false, 'suspended not expiring soon');

// Grace
$grace = SubscriptionAdminViewModel::mapTenantRow([
    'company_id' => 30,
    'company_name' => 'Grace Co',
    'current_status' => 'GRACE',
    'subscription_start' => '2025-01-01',
    'subscription_end' => '2026-08-01',
    'suspended_at' => null,
    'grace_started_at' => '2026-08-02',
    'grace_end_at' => '2026-08-08',
], $today);
expect($grace['grace_status'] === 'active', 'grace active label');

// Dashboard DTO
$dash = new SubscriptionAdminDashboard(100, 80, 5, 7, 8, 12);
expect($dash->totalTenants() === 100, 'dashboard total');
expect($dash->toArray()['expiring_soon'] === 12, 'dashboard array');

// Timeline ordering (newest first)
$timeline = SubscriptionAdminViewModel::buildTimeline(
    [
        'created_at' => '2026-01-01 00:00:00',
        'subscription_start' => '2026-01-01',
        'subscription_end' => '2026-06-01',
    ],
    [
        ['action' => 'RENEWED', 'old_status' => 'SUSPENDED', 'new_status' => 'ACTIVE', 'actor_id' => 1, 'created_at' => '2026-08-01 12:00:00'],
        ['action' => 'EXTENDED', 'old_status' => 'ACTIVE', 'new_status' => 'ACTIVE', 'actor_id' => 1, 'created_at' => '2026-08-05 09:00:00'],
    ],
    [
        ['previous_expiry_date' => '2026-06-01', 'new_expiry_date' => '2026-12-01', 'period' => '6m', 'reference' => 'R1', 'actor_id' => 1, 'created_at' => '2026-08-01 12:00:01'],
    ],
    [
        ['decision' => 'eligible', 'reason' => 'grace_expired', 'created_at' => '2026-07-15 00:00:00'],
    ]
);
expect(count($timeline) >= 4, 'timeline has events');
expect(($timeline[0]['type'] ?? '') === 'EXTENDED' || str_contains((string) ($timeline[0]['at'] ?? ''), '2026-08-05'), 'timeline newest first');

if ($failed > 0) {
    echo "\n{$failed} failure(s)\n";
    exit(1);
}
echo "\nAll Subscription Admin Phase 9 checks passed.\n";
exit(0);
