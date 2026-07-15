<?php
declare(strict_types=1);

namespace Rateb\App\Website\Theme;

use Rateb\App\Website\TenantWebsiteRepository;
use Rateb\App\Website\WebsiteBuilderService;
use Rateb\App\Website\WebsiteFormService;
use Rateb\App\Website\WebsiteMenuBuilderService;
use Rateb\App\Website\WebsiteSeoEditorService;

/**
 * Phase WEBSITE-05 — One-click demo import (pages/menus/forms/SEO/tokens).
 * All writes stamped with company_id via tenant services.
 */
final class ThemeDemoImportService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /**
     * @return array{ok:bool,message:string,stats:array<string,int>}
     */
    public function importForInstalled(int $installedId): array
    {
        $catalog = new ThemeCatalogService($this->repo);
        $row = $catalog->findInstalled($installedId);
        if ($row === null) {
            return ['ok' => false, 'message' => 'Theme not found', 'stats' => []];
        }
        $slug = (string) ($row['package_slug'] ?? '');
        try {
            $pkg = ThemePackage::load($slug);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Package unavailable for demo: ' . $e->getMessage(), 'stats' => []];
        }
        $demo = $pkg->manifest()->demo();
        $stats = ['pages' => 0, 'sections' => 0, 'blocks' => 0, 'menus' => 0, 'forms' => 0, 'seo' => 0];

        $builder = new WebsiteBuilderService($this->repo);
        $pages = $demo['pages'] ?? [];
        if (is_array($pages)) {
            foreach ($pages as $page) {
                if (!is_array($page)) {
                    continue;
                }
                $pageSlug = preg_replace('/[^a-z0-9\-]+/', '-', strtolower((string) ($page['slug'] ?? 'page'))) ?: 'page';
                $existing = $builder->pageBySlug($pageSlug);
                $pageId = $existing
                    ? (int) $existing['id']
                    : $builder->savePage([
                        'slug' => $pageSlug,
                        'title_en' => (string) ($page['title_en'] ?? $pageSlug),
                        'title_ar' => (string) ($page['title_ar'] ?? ''),
                        'template' => (string) ($page['template'] ?? 'builder'),
                        'status' => (string) ($page['status'] ?? 'published'),
                    ]);
                $stats['pages']++;
                $sections = $page['sections'] ?? [];
                if (!is_array($sections)) {
                    continue;
                }
                foreach ($sections as $section) {
                    if (!is_array($section)) {
                        continue;
                    }
                    $sectionId = $builder->addSection($pageSlug, [
                        'section_key' => (string) ($section['section_key'] ?? 'section_' . $stats['sections']),
                        'title_en' => (string) ($section['title_en'] ?? ''),
                        'title_ar' => (string) ($section['title_ar'] ?? ''),
                        'body_en' => (string) ($section['body_en'] ?? ''),
                        'body_ar' => (string) ($section['body_ar'] ?? ''),
                    ]);
                    $stats['sections']++;
                    $blocks = $section['blocks'] ?? [];
                    if (!is_array($blocks)) {
                        continue;
                    }
                    foreach ($blocks as $block) {
                        if (!is_array($block)) {
                            continue;
                        }
                        $type = (string) ($block['block_type'] ?? 'text');
                        try {
                            $builder->addBlock($sectionId, $type, $block);
                            $stats['blocks']++;
                        } catch (\Throwable $e) {
                            // skip unsupported
                        }
                    }
                }
                if ($pageId > 0 && isset($page['seo']) && is_array($page['seo'])) {
                    (new WebsiteSeoEditorService($this->repo))->saveForSlug($pageSlug, $page['seo']);
                    $stats['seo']++;
                }
            }
        }

        $seoDefaults = $pkg->manifest()->seoDefaults();
        if ($seoDefaults !== []) {
            (new WebsiteSeoEditorService($this->repo))->saveForSlug('home', $seoDefaults);
            $stats['seo']++;
        }

        $menus = $demo['menus'] ?? [];
        if (is_array($menus)) {
            $menuSvc = new WebsiteMenuBuilderService($this->repo);
            foreach ($menus as $menu) {
                if (!is_array($menu)) {
                    continue;
                }
                $menuId = $menuSvc->saveMenu([
                    'slug' => (string) ($menu['slug'] ?? 'main'),
                    'name_en' => (string) ($menu['name_en'] ?? 'Main'),
                    'name_ar' => (string) ($menu['name_ar'] ?? ''),
                    'location' => (string) ($menu['location'] ?? 'header'),
                ]);
                $items = $menu['items'] ?? [];
                if (is_array($items)) {
                    $flat = [];
                    foreach (array_values($items) as $i => $item) {
                        if (!is_array($item)) {
                            continue;
                        }
                        $flat[] = [
                            '_key' => 'd' . $i,
                            'label_en' => (string) ($item['label_en'] ?? ''),
                            'label_ar' => (string) ($item['label_ar'] ?? ''),
                            'url' => (string) ($item['url'] ?? '#'),
                            'parent_key' => (string) ($item['parent_key'] ?? ''),
                            'sort_order' => $i,
                        ];
                    }
                    $menuSvc->replaceItems($menuId, $flat);
                }
                $stats['menus']++;
            }
        }

        $forms = $demo['forms'] ?? [];
        if (is_array($forms)) {
            $formSvc = new WebsiteFormService($this->repo);
            foreach ($forms as $form) {
                if (!is_array($form)) {
                    continue;
                }
                $fields = $form['fields'] ?? [];
                if (!is_array($fields)) {
                    $fields = [];
                }
                $formSvc->saveForm($form, $fields);
                $stats['forms']++;
            }
        }

        // Apply package tokens as override defaults if empty.
        $overrideSvc = new ThemeOverrideService($this->repo);
        $existing = $overrideSvc->get($installedId);
        if (($existing['tokens'] ?? []) === [] || $existing === []) {
            $overrideSvc->save($installedId, [
                'tokens' => $pkg->manifest()->tokens(),
            ]);
        }

        return ['ok' => true, 'message' => 'Demo imported', 'stats' => $stats];
    }
}
