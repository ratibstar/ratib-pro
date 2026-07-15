<?php
declare(strict_types=1);

namespace Rateb\App\Website;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext as ErpTenantContext;
use Rateb\App\Services\DedicatedTenantPolicy;

/**
 * Phase WEBSITE-03 — Per-request website tenant (company_id isolation).
 * Platform (rateb.sa) → company_id = 0. Agency → erp_company_id / primary company.
 */
final class WebsiteContext
{
    private static ?self $current = null;

    private function __construct(
        private readonly TenantContext $tenant,
        private readonly int $companyId,
        private readonly bool $isolationEnabled,
    ) {
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    public static function reset(): void
    {
        self::$current = null;
    }

    public static function requireContext(): self
    {
        $ctx = self::$current;
        if ($ctx === null) {
            throw new \RuntimeException('WebsiteContext is not resolved for this request');
        }

        return $ctx;
    }

    /**
     * Boot after agency DB binding. Safe to call once per public request.
     */
    public static function bootFromRequest(): self
    {
        if (self::$current !== null) {
            return self::$current;
        }

        $tenant = TenantContext::resolveFromRequest();
        $isolationEnabled = self::detectIsolationEnabled();
        $companyId = self::resolveCompanyId($tenant);

        if ($isolationEnabled && !$tenant->isPlatform() && $companyId > 0) {
            self::claimOrphanCmsRows($companyId);
        }

        self::$current = new self($tenant, $companyId, $isolationEnabled);

        if ($companyId > 0) {
            ErpTenantContext::setCompanyId($companyId);
        } else {
            ErpTenantContext::setCompanyId(null);
        }

        return self::$current;
    }

    /**
     * Phase WEBSITE-04 — Boot website stack inside company ERP (builder admin).
     */
    public static function bootForOps(?int $companyId = null): self
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $cid = $companyId ?? (int) (ErpTenantContext::companyId() ?? 0);
        if ($cid < 1 && class_exists(DedicatedTenantPolicy::class)) {
            $cid = DedicatedTenantPolicy::primaryCompanyId();
        }
        if ($cid < 1) {
            throw new \RuntimeException('Website builder requires a company context');
        }

        self::reset();
        $tenant = TenantContext::forOpsCompany($cid);
        $isolationEnabled = self::detectIsolationEnabled();
        if ($isolationEnabled) {
            self::claimOrphanCmsRows($cid);
        }
        self::$current = new self($tenant, $cid, $isolationEnabled);
        ErpTenantContext::setCompanyId($cid);

        return self::$current;
    }

    private static function resolveCompanyId(TenantContext $tenant): int
    {
        if ($tenant->isPlatform()) {
            return 0;
        }

        $agency = $tenant->agency();
        if (is_array($agency)) {
            $linked = (int) ($agency['erp_company_id'] ?? 0);
            if ($linked > 0) {
                return $linked;
            }
        }

        if (class_exists(DedicatedTenantPolicy::class)) {
            $primary = DedicatedTenantPolicy::primaryCompanyId();
            if ($primary > 0) {
                return $primary;
            }
        }

        return 0;
    }

    private static function detectIsolationEnabled(): bool
    {
        try {
            $db = Database::connection();
            $stmt = $db->query("SHOW COLUMNS FROM rateb_cms_pages LIKE 'company_id'");
            $row = $stmt ? $stmt->fetch() : false;

            return is_array($row) && $row !== [];
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Dedicated agency DBs often have legacy CMS rows at company_id=0.
     * Claim them once for the resolved tenant company.
     */
    private static function claimOrphanCmsRows(int $companyId): void
    {
        static $done = false;
        if ($done || $companyId < 1) {
            return;
        }
        $done = true;

        try {
            $db = Database::connection();
            $chk = $db->query("SHOW COLUMNS FROM rateb_cms_theme LIKE 'company_id'");
            if (!$chk || !$chk->fetch()) {
                return;
            }
            $owned = $db->prepare('SELECT id FROM rateb_cms_theme WHERE company_id = :cid LIMIT 1');
            $owned->execute(['cid' => $companyId]);
            if ($owned->fetch()) {
                return;
            }
            $orphan = $db->query('SELECT id FROM rateb_cms_theme WHERE company_id = 0 LIMIT 1');
            if (!$orphan || !$orphan->fetch()) {
                return;
            }

            $tables = [
                'rateb_cms_pages', 'rateb_cms_sections', 'rateb_cms_blocks', 'rateb_cms_menus',
                'rateb_cms_menu_items', 'rateb_cms_footer_columns', 'rateb_cms_about',
                'rateb_cms_theme', 'rateb_cms_contact_settings', 'rateb_cms_seo', 'rateb_cms_redirects',
                'rateb_cms_robots', 'rateb_cms_analytics', 'rateb_cms_media', 'rateb_cms_slides',
                'rateb_cms_testimonials', 'rateb_cms_faqs', 'rateb_cms_services', 'rateb_cms_careers',
                'rateb_cms_blog_articles', 'rateb_cms_partners', 'rateb_cms_offices', 'rateb_cms_leads',
                'rateb_cms_visitors', 'rateb_cms_newsletter_subscribers', 'rateb_cms_faq_categories',
                'rateb_cms_service_categories', 'rateb_cms_blog_categories', 'rateb_cms_blog_tags',
                'rateb_cms_blog_authors', 'rateb_cms_media_categories', 'rateb_cms_team_members',
                'rateb_cms_timeline', 'rateb_cms_partners', 'rateb_cms_kb_articles',
                'rateb_cms_help_articles', 'rateb_cms_system_status',
            ];

            foreach ($tables as $table) {
                $c = $db->query("SHOW COLUMNS FROM {$table} LIKE 'company_id'");
                if (!$c || !$c->fetch()) {
                    continue;
                }
                $stmt = $db->prepare("UPDATE {$table} SET company_id = :cid WHERE company_id = 0");
                $stmt->execute(['cid' => $companyId]);
            }
        } catch (\Throwable $e) {
            error_log('WebsiteContext claim orphans: ' . $e->getMessage());
        }
    }

    public function tenant(): TenantContext
    {
        return $this->tenant;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function isPlatform(): bool
    {
        return $this->tenant->isPlatform();
    }

    public function isolationEnabled(): bool
    {
        return $this->isolationEnabled;
    }

    public function host(): string
    {
        return $this->tenant->host();
    }
}
