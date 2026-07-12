<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Offline\OfflineModule;

/**
 * Phase 12 — Build offline RBAC/nav manifest.
 * Reuses AuthorizationService, PlanLimitService, rateb_nav_can, entity-permissions, permission_implies.
 * Does not duplicate authorization rules.
 */
final class ErpOfflineRbacManifestService
{
    public const SNAPSHOT_KIND = 'erp_rbac';

    /**
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   manifest?: array<string, mixed>
     * }
     */
    public function buildForSession(): array
    {
        $policy = new ErpOfflineRbacPolicy();
        $gate = $policy->assertManifestAllowed();
        if (!($gate['ok'] ?? false)) {
            return ['ok' => false, 'error' => (string) ($gate['error'] ?? 'denied')];
        }

        $companyId = (int) $gate['company_id'];
        $branchId = (int) ($gate['branch_id'] ?? 0);
        $userId = (int) $gate['user_id'];

        $fp = (new ErpOfflineRbacVersionService())->fingerprint($companyId, $userId, $branchId);
        $rawSlugs = $fp['permission_slugs'];
        $effectiveSlugs = $this->expandImplies($rawSlugs);
        $planModules = $fp['plan_modules'];
        $disabled = $policy->offlineDisabledModules();
        $nav = $this->buildNavTree($disabled);

        // Company-bound super-admin may have empty plan_modules / sparse slugs while still
        // passing rateb_nav_can(). Seed modules from the offline catalog so client navCan
        // does not collapse the warm menu to account-only links.
        $isSuper = !empty(SessionManager::get('rateb_is_super_admin'));
        if ($isSuper && $companyId > 0) {
            $disabledFlipLocal = array_fill_keys($disabled, true);
            $catalogMods = [];
            foreach ($nav as $section) {
                foreach (($section['items'] ?? []) as $item) {
                    $mod = (string) ($item['module'] ?? '');
                    if ($mod !== '' && !isset($disabledFlipLocal[$mod])) {
                        $catalogMods[$mod] = true;
                    }
                    $perm = (string) ($item['permission'] ?? '');
                    if ($perm !== '' && !in_array($perm, $effectiveSlugs, true)) {
                        $effectiveSlugs[] = $perm;
                    }
                }
            }
            $planModules = array_values(array_unique(array_merge($planModules, array_keys($catalogMods))));
            sort($planModules);
            $effectiveSlugs = array_values(array_unique($effectiveSlugs));
            sort($effectiveSlugs);
        }

        $now = time();
        $ttl = $policy->ttlSeconds();
        $id = self::SNAPSHOT_KIND . ':' . $companyId . ':' . $branchId . ':' . $userId;

        $manifest = [
            'id' => $id,
            'kind' => self::SNAPSHOT_KIND,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'user_id' => $userId,
            'rbac_version' => $fp['rbac_version'],
            'captured_at' => $now,
            'expires_at' => $now + $ttl,
            'ttl_seconds' => $ttl,
            'permission_slugs' => $effectiveSlugs,
            'plan_modules' => $planModules,
            'offline_disabled_modules' => $disabled,
            'nav' => ['sections' => $nav],
            'ui_only' => true,
            'server_authz_bypass' => false,
        ];

        return ['ok' => true, 'manifest' => $manifest];
    }

    /**
     * @param list<string> $slugs
     * @return list<string>
     */
    public function expandImplies(array $slugs): array
    {
        $set = array_fill_keys($slugs, true);
        $cfgFile = (defined('RATEB_ROOT') ? RATEB_ROOT : '') . '/config/permissions-system.php';
        $cfg = is_file($cfgFile) ? require $cfgFile : [];
        $implies = is_array($cfg['permission_implies'] ?? null) ? $cfg['permission_implies'] : [];
        foreach ($implies as $parent => $children) {
            if (!isset($set[(string) $parent])) {
                continue;
            }
            foreach ((array) $children as $child) {
                $set[(string) $child] = true;
            }
        }

        $out = array_keys($set);
        sort($out);

        return $out;
    }

    /**
     * @param list<string> $disabledModules
     * @return list<array<string, mixed>>
     */
    private function buildNavTree(array $disabledModules): array
    {
        $file = OfflineModule::rootPath() . '/config/offline-nav-catalog.php';
        $cfg = is_file($file) ? require $file : [];
        $sections = is_array($cfg['sections'] ?? null) ? $cfg['sections'] : [];
        $disabledFlip = array_fill_keys($disabledModules, true);
        $out = [];

        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }
            $itemsIn = is_array($section['items'] ?? null) ? $section['items'] : [];
            $itemsOut = [];
            foreach ($itemsIn as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $path = (string) ($item['path'] ?? '');
                if ($path === '') {
                    continue;
                }
                $entity = function_exists('rateb_entity_perms')
                    ? rateb_entity_perms($path)
                    : ['module' => '', 'view' => ''];
                $module = (string) ($item['module'] ?? $entity['module'] ?? '');
                $permission = (string) ($item['permission'] ?? $entity['view'] ?? '');
                if ($module !== '' && isset($disabledFlip[$module])) {
                    continue;
                }
                if ($permission === '' && $module === '') {
                    // Profile / notifications style — allow for tenant users.
                    $itemsOut[] = $this->navItem($path, $item, $module, $permission, true);
                    continue;
                }
                if (!function_exists('rateb_nav_can') || !rateb_nav_can($permission, $module)) {
                    continue;
                }
                $itemsOut[] = $this->navItem($path, $item, $module, $permission, true);
            }
            if ($itemsOut === []) {
                continue;
            }
            $out[] = [
                'title_key' => (string) ($section['title_key'] ?? ''),
                'icon' => (string) ($section['icon'] ?? ''),
                'items' => $itemsOut,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function navItem(string $path, array $item, string $module, string $permission, bool $actionable): array
    {
        $href = function_exists('rateb_app_url') ? rateb_app_url($path) : ('/' . ltrim($path, '/'));
        $label = (string) ($item['label_key'] ?? $path);
        if (function_exists('__')) {
            $label = (string) __($label);
        }

        return [
            'path' => $path,
            'href' => $href,
            'label_key' => (string) ($item['label_key'] ?? $path),
            'label' => $label,
            'icon' => (string) ($item['icon'] ?? 'fa-circle'),
            'module' => $module,
            'permission' => $permission,
            'offline_actionable' => $actionable,
        ];
    }
}
