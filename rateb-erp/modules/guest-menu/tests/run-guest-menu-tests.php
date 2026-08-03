<?php
declare(strict_types=1);

/**
 * Guest Menu unit tests — slug normalization (no DB).
 * Run: php rateb-erp/modules/guest-menu/tests/run-guest-menu-tests.php
 */

require_once dirname(__DIR__, 3) . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init(dirname(__DIR__, 3));

require_once dirname(__DIR__) . '/GuestMenuModule.php';
\Rateb\App\GuestMenu\GuestMenuModule::init();

require_once dirname(__DIR__) . '/app/Services/GuestMenuSettingsService.php';

use Rateb\App\GuestMenu\Services\GuestMenuSettingsService;

$passed = 0;
$failed = 0;

function gm_assert(bool $cond, string $label): void
{
    global $passed, $failed;
    if ($cond) {
        ++$passed;
        echo "PASS: {$label}\n";
    } else {
        ++$failed;
        echo "FAIL: {$label}\n";
    }
}

gm_assert(
    GuestMenuSettingsService::normalizeSlug('  My-Cafe_123  ') === 'my-cafe-123',
    'normalizeSlug trims and lowercases'
);
gm_assert(
    GuestMenuSettingsService::normalizeSlug('ab') === '',
    'normalizeSlug rejects short slugs'
);
gm_assert(
    GuestMenuSettingsService::normalizeSlug('valid-menu') === 'valid-menu',
    'normalizeSlug accepts valid slug'
);
gm_assert(
    GuestMenuSettingsService::normalizeSlug('عربي') === '',
    'normalizeSlug rejects non-latin'
);

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
