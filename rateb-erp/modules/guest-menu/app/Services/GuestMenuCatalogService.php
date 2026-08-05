<?php
declare(strict_types=1);

namespace Rateb\App\GuestMenu\Services;

use Rateb\App\Core\BranchContext;
use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2CatalogScope;
use Rateb\App\Pos\DTO\V2\Catalog\CatalogSearchRequest;
use Rateb\App\Pos\Repositories\V2\Adapters\V1CatalogCategoryAdapter;
use Rateb\App\Pos\Repositories\V2\Adapters\V1CatalogProductAdapter;
use Rateb\App\Pos\Repositories\V2\InMemoryCatalogCategoryCache;
use PDO;

/** Read-only catalog for public guest menu — reuses POS V2 adapters. */
final class GuestMenuCatalogService
{
    private V1CatalogCategoryAdapter $categories;
    private V1CatalogProductAdapter $products;

    public function __construct(
        ?V1CatalogCategoryAdapter $categories = null,
        ?V1CatalogProductAdapter $products = null,
    ) {
        $this->categories = $categories ?? new V1CatalogCategoryAdapter(new InMemoryCatalogCategoryCache());
        $this->products = $products ?? new V1CatalogProductAdapter();
    }

    /** @return list<array<string, mixed>> */
    public function listCategories(int $companyId, bool $rtl): array
    {
        if ($companyId < 1) {
            return [];
        }
        $items = [];
        foreach ($this->categories->listActive($companyId, $rtl) as $cat) {
            $items[] = $cat->toArray();
        }

        return $items;
    }

    /** @return array{product_count:int, category_count:int} */
    public function statsForCompany(int $companyId, ?int $branchId, string $catalogPack = 'all'): array
    {
        $catalog = $this->browse($companyId, $branchId, null, 1, true, $catalogPack);

        return [
            'product_count' => (int) ($catalog['pagination']['total'] ?? 0),
            'category_count' => count($catalog['categories'] ?? []),
        ];
    }

    /**
     * @return array{categories: list<array<string, mixed>>, products: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function browse(
        int $companyId,
        ?int $branchId,
        ?int $categoryId,
        int $page,
        bool $rtl,
        string $catalogPack = 'all',
    ): array {
        $this->bootstrapPublicCatalog($companyId, $branchId);
        $catalogPack = PlatformRetailCatalogSeedData::normalizePack($catalogPack);
        $this->maybeAutoRepairCorruptedNames($companyId, $catalogPack);

        $seedCategoryFilter = $categoryId !== null && $categoryId < 0;
        $adapterCategoryId = $seedCategoryFilter ? null : $categoryId;
        $packFilter = $catalogPack !== 'all';

        $scope = new PosV2CatalogScope(
            companyId: $companyId,
            warehouseId: 0,
            branchId: $branchId ?? 0,
            sessionId: 0,
            currency: 'SAR',
            rtl: $rtl,
        );

        // Wider fetch when PHP-side filters (synthetic category and/or industry pack) apply.
        $needsPhpFilter = $seedCategoryFilter || $packFilter;
        $perPage = $needsPhpFilter ? 200 : 24;
        $request = new CatalogSearchRequest(
            query: '',
            categoryId: $adapterCategoryId,
            page: $needsPhpFilter ? 1 : max(1, $page),
            perPage: $perPage,
        );

        $response = $this->products->search($scope, $request);
        $products = [];
        foreach ($response->products as $product) {
            $products[] = $product->toArray();
        }

        $categories = $this->listCategories($companyId, $rtl);
        $catalog = [
            'categories' => $categories,
            'products' => $products,
            'pagination' => $response->pagination->toArray(),
        ];

        $catalog = $this->applySeedDisplayFallback($companyId, $catalog, $rtl, $catalogPack);
        $catalog = $this->applyCatalogPackFilter($companyId, $catalog, $catalogPack, $rtl);

        if ($seedCategoryFilter && $categoryId !== null) {
            $catalog = $this->filterProductsBySyntheticCategory($catalog, $categoryId, max(1, $page));
        } elseif ($packFilter) {
            $catalog = $this->paginateCatalogProducts($catalog, max(1, $page), 24);
        }

        return $catalog;
    }

    /**
     * If DB still holds ?? for RC-/GM- names, overlay UTF-8 seed names and
     * rebuild category chips from seed slugs when DB only has GEN/عام.
     * Synthetic chips are limited to the company industry pack (never all sectors).
     *
     * @param array{categories: list<array<string, mixed>>, products: list<array<string, mixed>>, pagination: array<string, mixed>} $catalog
     * @return array{categories: list<array<string, mixed>>, products: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    private function applySeedDisplayFallback(int $companyId, array $catalog, bool $rtl, string $catalogPack = 'all'): array
    {
        $nameBySku = PlatformRetailCatalogSeedData::nameBySku();
        $skuMap = PlatformRetailCatalogSeedData::authoritativeSkuMap();
        $products = is_array($catalog['products'] ?? null) ? $catalog['products'] : [];
        $categories = is_array($catalog['categories'] ?? null) ? $catalog['categories'] : [];
        $packSlugs = PlatformRetailCatalogSeedData::packCategorySlugs($catalogPack);
        $packAllowed = $packSlugs === null ? null : array_fill_keys($packSlugs, true);

        $seedCatCodes = [];
        foreach ($products as $i => $product) {
            $sku = trim((string) ($product['sku'] ?? ''));
            if ($sku === '' || (!str_starts_with($sku, 'RC-') && !str_starts_with($sku, 'GM-'))) {
                continue;
            }
            $name = (string) ($product['name'] ?? '');
            if ($this->isCorruptedDisplayName($name) && isset($nameBySku[$sku])) {
                $products[$i]['name'] = $nameBySku[$sku];
            }
            $seed = $skuMap[$sku] ?? null;
            if ($seed === null) {
                if (str_starts_with($sku, 'GM-') && ($packAllowed === null || isset($packAllowed['retail-restaurants']))) {
                    $seedCatCodes['retail-restaurants'] = true;
                    $products[$i]['category_slug'] = 'retail-restaurants';
                }
                continue;
            }
            $slug = (string) ($seed['category_slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            if ($packAllowed !== null && !isset($packAllowed[$slug])) {
                continue;
            }
            $seedCatCodes[$slug] = true;
            $products[$i]['category_slug'] = $slug;
            $products[$i]['category_name'] = $rtl
                ? (string) ($seed['category_name_ar'] ?? '')
                : (string) ($seed['category_name_en'] ?? '');
        }

        if ($this->categoriesNeedSeedFallback($categories)) {
            foreach ($this->companySeedCategorySlugs($companyId, $skuMap, $catalogPack) as $slug) {
                $seedCatCodes[$slug] = true;
            }
            // Prefer pack-defined chips when inventory is empty/mixed but pack is set.
            if ($seedCatCodes === [] && $packSlugs !== null) {
                foreach ($packSlugs as $slug) {
                    $seedCatCodes[$slug] = true;
                }
            }
            if ($seedCatCodes !== []) {
                $categories = $this->syntheticCategoriesFromSeed(array_keys($seedCatCodes), $rtl);
                $slugToId = [];
                foreach ($categories as $cat) {
                    $slugToId[(string) ($cat['slug'] ?? '')] = (int) ($cat['id'] ?? 0);
                }
                foreach ($products as $i => $product) {
                    $slug = (string) ($product['category_slug'] ?? '');
                    if ($slug !== '' && isset($slugToId[$slug])) {
                        $products[$i]['category_id'] = $slugToId[$slug];
                    }
                }
            }
        }

        $catalog['categories'] = $categories;
        $catalog['products'] = $products;

        return $catalog;
    }

    /**
     * Keep only products/categories that belong to the saved industry pack.
     *
     * @param array{categories: list<array<string, mixed>>, products: list<array<string, mixed>>, pagination: array<string, mixed>} $catalog
     * @return array{categories: list<array<string, mixed>>, products: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    private function applyCatalogPackFilter(int $companyId, array $catalog, string $catalogPack, bool $rtl): array
    {
        $catalogPack = PlatformRetailCatalogSeedData::normalizePack($catalogPack);
        if ($catalogPack === 'all') {
            return $catalog;
        }

        $packSlugs = PlatformRetailCatalogSeedData::packCategorySlugs($catalogPack) ?? [];
        $packCodes = PlatformRetailCatalogSeedData::packCategoryCodes($catalogPack) ?? [];
        $slugAllowed = array_fill_keys($packSlugs, true);
        $codeAllowed = array_fill_keys($packCodes, true);
        $codeByCategoryId = $this->companyCategoryCodesById($companyId);

        $packNameAr = [];
        $packNameEn = [];
        foreach (PlatformRetailCatalogSeedData::categoryMetaBySlug() as $slug => $meta) {
            if (!isset($slugAllowed[$slug])) {
                continue;
            }
            $packNameAr[mb_strtolower(trim((string) ($meta['name_ar'] ?? '')), 'UTF-8')] = true;
            $packNameEn[mb_strtolower(trim((string) ($meta['name_en'] ?? '')), 'UTF-8')] = true;
        }

        $filteredProducts = [];
        $usedSlugs = [];
        $usedCategoryIds = [];
        foreach ($catalog['products'] as $product) {
            if (!$this->productBelongsToPack(
                $product,
                $catalogPack,
                $slugAllowed,
                $codeAllowed,
                $codeByCategoryId
            )) {
                continue;
            }
            $filteredProducts[] = $product;
            $slug = (string) ($product['category_slug'] ?? '');
            if ($slug !== '' && isset($slugAllowed[$slug])) {
                $usedSlugs[$slug] = true;
            }
            $cid = (int) ($product['category_id'] ?? 0);
            if ($cid > 0) {
                $usedCategoryIds[$cid] = true;
            }
        }

        $filteredCategories = [];
        foreach ($catalog['categories'] as $cat) {
            $slug = (string) ($cat['slug'] ?? '');
            $code = strtoupper(trim((string) ($cat['code'] ?? '')));
            $name = mb_strtolower(trim((string) ($cat['name'] ?? '')), 'UTF-8');
            $id = (int) ($cat['id'] ?? 0);
            $ok = false;
            if ($slug !== '' && isset($slugAllowed[$slug])) {
                $ok = true;
            } elseif ($code !== '' && isset($codeAllowed[$code])) {
                $ok = true;
            } elseif ($id > 0 && isset($codeByCategoryId[$id]) && isset($codeAllowed[$codeByCategoryId[$id]])) {
                $ok = true;
            } elseif ($name !== '' && (isset($packNameAr[$name]) || isset($packNameEn[$name]))) {
                $ok = true;
            } elseif ($id < 0 && $slug !== '' && isset($slugAllowed[$slug])) {
                $ok = true;
            } elseif ($id > 0 && isset($usedCategoryIds[$id])) {
                $ok = true;
            }
            if ($ok) {
                $filteredCategories[] = $cat;
            }
        }

        // Ensure pack chips exist when DB categories were stripped but products remain.
        if ($filteredCategories === [] && $usedSlugs !== []) {
            $filteredCategories = $this->syntheticCategoriesFromSeed(array_keys($usedSlugs), $rtl);
        } elseif ($filteredCategories === [] && $packSlugs !== []) {
            $filteredCategories = $this->syntheticCategoriesFromSeed($packSlugs, $rtl);
        }

        $catalog['products'] = $filteredProducts;
        $catalog['categories'] = $filteredCategories;

        return $catalog;
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, true> $slugAllowed
     * @param array<string, true> $codeAllowed
     * @param array<int, string> $codeByCategoryId
     */
    private function productBelongsToPack(
        array $product,
        string $catalogPack,
        array $slugAllowed,
        array $codeAllowed,
        array $codeByCategoryId,
    ): bool {
        $sku = trim((string) ($product['sku'] ?? ''));
        if ($sku !== '' && (str_starts_with($sku, 'RC-') || str_starts_with($sku, 'GM-'))) {
            return PlatformRetailCatalogSeedData::skuBelongsToPack($sku, $catalogPack);
        }

        $slug = (string) ($product['category_slug'] ?? '');
        if ($slug !== '' && isset($slugAllowed[$slug])) {
            return true;
        }
        $cid = (int) ($product['category_id'] ?? 0);
        if ($cid > 0 && isset($codeByCategoryId[$cid]) && isset($codeAllowed[$codeByCategoryId[$cid]])) {
            return true;
        }

        // Manual inventory outside pack categories is hidden when a pack is selected.
        return false;
    }

    /**
     * @return array<int, string> category_id => CODE
     */
    private function companyCategoryCodesById(int $companyId): array
    {
        if ($companyId < 1) {
            return [];
        }
        try {
            $stmt = Database::connection()->prepare(
                'SELECT id, code FROM rateb_product_categories WHERE company_id = :cid'
            );
            $stmt->execute(['cid' => $companyId]);
            $map = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $id = (int) ($row['id'] ?? 0);
                $code = strtoupper(trim((string) ($row['code'] ?? '')));
                if ($id > 0 && $code !== '') {
                    $map[$id] = $code;
                }
            }

            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array{categories: list<array<string, mixed>>, products: list<array<string, mixed>>, pagination: array<string, mixed>} $catalog
     * @return array{categories: list<array<string, mixed>>, products: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    private function paginateCatalogProducts(array $catalog, int $page, int $perPage): array
    {
        $products = is_array($catalog['products'] ?? null) ? $catalog['products'] : [];
        $total = count($products);
        $offset = max(0, ($page - 1) * $perPage);
        $catalog['products'] = array_slice($products, $offset, $perPage);
        $catalog['pagination'] = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => max(1, (int) ceil($total / max(1, $perPage))),
        ];

        return $catalog;
    }

    /**
     * @param array<string, array{category_slug?:string}> $skuMap
     * @return list<string>
     */
    private function companySeedCategorySlugs(int $companyId, array $skuMap, string $catalogPack = 'all'): array
    {
        if ($companyId < 1) {
            return [];
        }
        $packSlugs = PlatformRetailCatalogSeedData::packCategorySlugs($catalogPack);
        $packAllowed = $packSlugs === null ? null : array_fill_keys($packSlugs, true);
        try {
            $stmt = Database::connection()->prepare(
                'SELECT sku FROM rateb_inventory
                 WHERE company_id = :cid AND (sku LIKE \'RC-%\' OR sku LIKE \'GM-%\')
                 LIMIT 500'
            );
            $stmt->execute(['cid' => $companyId]);
            $slugs = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $sku = trim((string) ($row['sku'] ?? ''));
                if ($sku !== '' && !PlatformRetailCatalogSeedData::skuBelongsToPack($sku, $catalogPack)) {
                    continue;
                }
                $slug = (string) ($skuMap[$sku]['category_slug'] ?? '');
                if ($slug === '' && str_starts_with($sku, 'GM-')) {
                    $slug = 'retail-restaurants';
                }
                if ($slug === '') {
                    continue;
                }
                if ($packAllowed !== null && !isset($packAllowed[$slug])) {
                    continue;
                }
                $slugs[$slug] = true;
            }

            return array_keys($slugs);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array{categories: list<array<string, mixed>>, products: list<array<string, mixed>>, pagination: array<string, mixed>} $catalog
     * @return array{categories: list<array<string, mixed>>, products: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    private function filterProductsBySyntheticCategory(array $catalog, int $syntheticId, int $page): array
    {
        $slug = '';
        foreach ($catalog['categories'] as $cat) {
            if ((int) ($cat['id'] ?? 0) === $syntheticId) {
                $slug = (string) ($cat['slug'] ?? '');
                break;
            }
        }
        if ($slug === '') {
            return $catalog;
        }

        $filtered = [];
        foreach ($catalog['products'] as $product) {
            if ((string) ($product['category_slug'] ?? '') === $slug) {
                $filtered[] = $product;
            }
        }
        $perPage = 24;
        $total = count($filtered);
        $offset = max(0, ($page - 1) * $perPage);
        $pageRows = array_slice($filtered, $offset, $perPage);
        $catalog['products'] = $pageRows;
        $catalog['pagination'] = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];

        return $catalog;
    }

    /**
     * @param list<array<string, mixed>> $categories
     */
    private function categoriesNeedSeedFallback(array $categories): bool
    {
        if ($categories === []) {
            return true;
        }
        if (count($categories) > 1) {
            return false;
        }
        $name = trim((string) ($categories[0]['name'] ?? ''));
        $generic = ['عام', 'General', 'GEN', 'menu', 'Menu', '????', '??'];
        if (in_array($name, $generic, true)) {
            return true;
        }

        return $this->isCorruptedDisplayName($name);
    }

    /**
     * Stable negative ids from categoryDefs index so chips survive pagination/filter.
     *
     * @param list<string> $slugs
     * @return list<array<string, mixed>>
     */
    private function syntheticCategoriesFromSeed(array $slugs, bool $rtl): array
    {
        $meta = PlatformRetailCatalogSeedData::categoryMetaBySlug();
        $out = [];
        foreach (PlatformRetailCatalogSeedData::categoryDefs() as $idx => [$slug, $ar, $en, $sort]) {
            if (!in_array($slug, $slugs, true)) {
                continue;
            }
            $out[] = [
                'id' => -1000 - (int) $idx,
                'name' => $rtl ? $ar : $en,
                'slug' => $slug,
                'sort_order' => $sort,
            ];
        }
        foreach ($slugs as $slug) {
            $found = false;
            foreach ($out as $row) {
                if (($row['slug'] ?? '') === $slug) {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                continue;
            }
            $m = $meta[$slug] ?? ['name_ar' => $slug, 'name_en' => $slug, 'sort' => 999];
            $out[] = [
                'id' => -9000 - (abs(crc32($slug)) % 800),
                'name' => $rtl ? (string) $m['name_ar'] : (string) $m['name_en'],
                'slug' => $slug,
                'sort_order' => (int) ($m['sort'] ?? 999),
            ];
        }

        return $out;
    }

    private function isCorruptedDisplayName(string $name): bool
    {
        $name = trim($name);
        if ($name === '') {
            return true;
        }
        if (str_contains($name, '??') || str_contains($name, "\u{FFFD}")) {
            return true;
        }
        if (preg_match('/^\?+$/u', $name) === 1) {
            return true;
        }
        $q = substr_count($name, '?');
        if ($q >= 2 && $q >= (int) floor(mb_strlen($name, 'UTF-8') * 0.5)) {
            return true;
        }

        return false;
    }

    /**
     * Auto-run write repair once when most RC-/GM- names are mojibake.
     * Guarded by a 1-hour file flag so public traffic does not hammer the DB.
     */
    private function maybeAutoRepairCorruptedNames(int $companyId, string $catalogPack = 'all'): void
    {
        if ($companyId < 1) {
            return;
        }
        if ($this->autoRepairCooldownActive($companyId)) {
            return;
        }

        try {
            if (!$this->majorityRcNamesCorrupted($companyId)) {
                $this->markAutoRepairCooldown($companyId);

                return;
            }
            (new GuestMenuMenuRepairService())->repairCompany($companyId, $catalogPack);
        } catch (\Throwable) {
            // non-fatal — read-time seed overlay still covers UX
        }
        $this->markAutoRepairCooldown($companyId);
    }

    private function majorityRcNamesCorrupted(int $companyId): bool
    {
        try {
            $stmt = Database::connection()->prepare(
                'SELECT
                    COUNT(*) AS total_n,
                    SUM(
                        CASE
                            WHEN item_name LIKE \'%??%\'
                              OR item_name REGEXP \'^[?]+$\'
                              OR CHAR_LENGTH(IFNULL(item_name, \'\')) = 0
                            THEN 1 ELSE 0
                        END
                    ) AS bad_n
                 FROM rateb_inventory
                 WHERE company_id = :cid
                   AND (sku LIKE \'RC-%\' OR sku LIKE \'GM-%\')'
            );
            $stmt->execute(['cid' => $companyId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $total = (int) ($row['total_n'] ?? 0);
            $bad = (int) ($row['bad_n'] ?? 0);
            if ($total < 1) {
                return false;
            }

            return ($bad / $total) > 0.5;
        } catch (\Throwable) {
            return false;
        }
    }

    private function autoRepairFlagPath(int $companyId): string
    {
        $root = defined('RATEB_ROOT') ? (string) RATEB_ROOT : dirname(__DIR__, 4);
        $dir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir . DIRECTORY_SEPARATOR . 'gm-auto-repair-' . $companyId . '.flag';
    }

    private function autoRepairCooldownActive(int $companyId): bool
    {
        $path = $this->autoRepairFlagPath($companyId);
        if (!is_file($path)) {
            return false;
        }
        $mtime = @filemtime($path);
        if ($mtime === false) {
            return false;
        }

        return (time() - $mtime) < 3600;
    }

    private function markAutoRepairCooldown(int $companyId): void
    {
        $path = $this->autoRepairFlagPath($companyId);
        @file_put_contents($path, (string) time());
    }

    /** Public menu must ignore admin session branch locks; optional menu branch_id only. */
    private function bootstrapPublicCatalog(int $companyId, ?int $branchId): void
    {
        if ($companyId < 1) {
            return;
        }
        TenantContext::setCompanyId($companyId);
        BranchContext::reset();
        if ($branchId !== null && $branchId > 0) {
            BranchContext::setBootstrapped($companyId, false, [$branchId]);
        } else {
            BranchContext::setBootstrapped($companyId, true, []);
        }
    }
}
