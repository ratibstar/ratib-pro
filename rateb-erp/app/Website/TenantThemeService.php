<?php
declare(strict_types=1);

namespace Rateb\App\Website;

/**
 * Phase WEBSITE-03 — Tenant theme (logo, colors, fonts, brand shell).
 */
final class TenantThemeService
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /** @return array<string, mixed> */
    public function theme(): array
    {
        $cid = $this->repo->companyId();
        if (self::$cache !== null && (int) (self::$cache['_company_id'] ?? -1) === $cid) {
            return self::$cache;
        }

        [$where, $params] = $this->repo->companyWhere();
        $row = $this->repo->fetchOne(
            "SELECT * FROM rateb_cms_theme WHERE {$where} ORDER BY id ASC LIMIT 1",
            $params
        );

        $theme = $row ?: [
            'primary_color' => '#1a5fb4',
            'secondary_color' => '#3584e4',
            'font_family' => 'Tajawal',
            'logo_path' => '',
            'favicon_path' => '',
            'custom_css' => '',
            'custom_js' => '',
            'company_id' => $cid,
        ];
        $theme['_company_id'] = $cid;
        self::$cache = $theme;

        return $theme;
    }

    /** @return array<string, mixed>|null */
    public function contact(): ?array
    {
        [$where, $params] = $this->repo->companyWhere();

        return $this->repo->fetchOne(
            "SELECT * FROM rateb_cms_contact_settings WHERE {$where} ORDER BY id ASC LIMIT 1",
            $params
        );
    }

    /** @return array<string, mixed> */
    public function socialLinks(): array
    {
        $contact = $this->contact();
        if ($contact === null) {
            return [];
        }
        $raw = (string) ($contact['social_json'] ?? '');
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function whatsapp(): string
    {
        $social = $this->socialLinks();
        foreach (['whatsapp', 'wa', 'WhatsApp'] as $key) {
            $v = trim((string) ($social[$key] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }
        $phone = trim((string) (($this->contact() ?? [])['phone'] ?? ''));

        return $phone;
    }
}
