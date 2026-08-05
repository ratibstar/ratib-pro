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
require_once dirname(__DIR__) . '/app/Services/PlatformRetailCatalogSeedData.php';

use Rateb\App\GuestMenu\Services\GuestMenuSettingsService;
use Rateb\App\GuestMenu\Services\PlatformRetailCatalogSeedData;

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

gm_assert(
    PlatformRetailCatalogSeedData::normalizePack('Restaurant') === 'restaurant',
    'normalizePack accepts restaurant'
);
gm_assert(
    PlatformRetailCatalogSeedData::normalizePack('nope') === 'all',
    'normalizePack unknown → all'
);
gm_assert(
    PlatformRetailCatalogSeedData::skuBelongsToPack('RC-RST-001', 'restaurant') === true,
    'restaurant pack includes RC-RST'
);
gm_assert(
    PlatformRetailCatalogSeedData::skuBelongsToPack('RC-SHO-001', 'restaurant') === false,
    'restaurant pack excludes shoes'
);
gm_assert(
    PlatformRetailCatalogSeedData::skuBelongsToPack('GM-BURGER', 'restaurant') === true,
    'restaurant pack includes GM demo'
);
gm_assert(
    PlatformRetailCatalogSeedData::skuBelongsToPack('GM-BURGER', 'clothing') === false,
    'clothing pack excludes GM demo'
);
$restSlugs = PlatformRetailCatalogSeedData::packCategorySlugs('restaurant');
gm_assert(
    is_array($restSlugs) && in_array('retail-restaurants', $restSlugs, true),
    'restaurant pack has retail-restaurants'
);
gm_assert(
    PlatformRetailCatalogSeedData::packCategorySlugs('all') === null,
    'all pack has no slug filter'
);

$restSkuSet = PlatformRetailCatalogSeedData::allowedSkuSetForPack('restaurant');
gm_assert(
    is_array($restSkuSet) && isset($restSkuSet['RC-RST-001']) && !isset($restSkuSet['RC-SHO-001']),
    'allowedSkuSetForPack restaurant includes RST excludes SHO'
);
gm_assert(
    PlatformRetailCatalogSeedData::allowedSkuSetForPack('all') === null,
    'allowedSkuSetForPack all is null'
);
gm_assert(
    PlatformRetailCatalogSeedData::detectPackFromSkus([
        'RC-RST-001', 'RC-CAF-001', 'RC-BEV-001', 'RC-BKY-001',
    ]) === 'restaurant',
    'detectPackFromSkus majority restaurant'
);
gm_assert(
    PlatformRetailCatalogSeedData::detectPackFromSkus([
        'RC-SHO-001', 'RC-CLM-001', 'RC-CLW-001',
    ]) === 'clothing',
    'detectPackFromSkus clothing pack'
);
gm_assert(
    PlatformRetailCatalogSeedData::detectPackFromSkus([]) === null,
    'detectPackFromSkus empty → null'
);

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
