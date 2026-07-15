<?php
declare(strict_types=1);

namespace Rateb\App\Website;

/** Phase WEBSITE-03 — Tenant page sections / blocks. */
final class TenantBlockService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /** @return list<array<string, mixed>> */
    public function sectionsForPage(string $pageSlug): array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['p'] = $pageSlug;

        return $this->repo->fetchAll(
            "SELECT * FROM rateb_cms_sections WHERE {$where} AND page_slug = :p AND is_active = 1
             ORDER BY sort_order ASC",
            $params
        );
    }

    /** @return list<array<string, mixed>> */
    public function blocksForSection(int $sectionId): array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['id'] = $sectionId;

        return $this->repo->fetchAll(
            "SELECT * FROM rateb_cms_blocks WHERE {$where} AND section_id = :id AND is_active = 1
             ORDER BY sort_order ASC",
            $params
        );
    }

    /** @return array<string, array{section:array<string,mixed>,blocks:list<array<string,mixed>>}> */
    public function pageContent(string $pageSlug): array
    {
        $sections = $this->sectionsForPage($pageSlug);
        $out = [];
        foreach ($sections as $section) {
            $key = (string) ($section['section_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $out[$key] = [
                'section' => $section,
                'blocks' => $this->blocksForSection((int) ($section['id'] ?? 0)),
            ];
        }

        return $out;
    }
}
