<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\SaaS;

use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;

final class WhiteLabelService
{
    public function __construct(private readonly RccAuditService $audit = new RccAuditService())
    {
    }

    public function branding(int $tenantId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM rcc_whitelabel_branding WHERE tenant_id = :tid');
        $stmt->execute(['tid' => $tenantId]);
        return $stmt->fetch() ?: [];
    }

    /** @param array<string, mixed> $data */
    public function saveBranding(int $tenantId, array $data, ?int $userId): array
    {
        $pdo = Database::connection();
        $exists = $this->branding($tenantId);
        if ($exists) {
            $pdo->prepare(
                'UPDATE rcc_whitelabel_branding SET logo_url=:logo, logo_dark_url=:logo_d, favicon_url=:fav,
                 company_name=:name, company_name_ar=:name_ar, support_email=:email, support_phone=:phone,
                 primary_color=:pc, accent_color=:ac, custom_css=:css WHERE tenant_id=:tid'
            )->execute([
                'logo' => $data['logo_url'] ?? null, 'logo_d' => $data['logo_dark_url'] ?? null,
                'fav' => $data['favicon_url'] ?? null, 'name' => $data['company_name'] ?? null,
                'name_ar' => $data['company_name_ar'] ?? null, 'email' => $data['support_email'] ?? null,
                'phone' => $data['support_phone'] ?? null, 'pc' => $data['primary_color'] ?? null,
                'ac' => $data['accent_color'] ?? null, 'css' => $data['custom_css'] ?? null, 'tid' => $tenantId,
            ]);
        } else {
            $pdo->prepare(
                'INSERT INTO rcc_whitelabel_branding (tenant_id, logo_url, company_name, company_name_ar, primary_color, accent_color)
                 VALUES (:tid, :logo, :name, :name_ar, :pc, :ac)'
            )->execute([
                'tid' => $tenantId,
                'logo' => $data['logo_url'] ?? null,
                'name' => $data['company_name'] ?? null,
                'name_ar' => $data['company_name_ar'] ?? null,
                'pc' => $data['primary_color'] ?? '#2563eb',
                'ac' => $data['accent_color'] ?? '#0ea5e9',
            ]);
        }
        $this->audit->log($tenantId, 'whitelabel.branding.save', $userId, 'branding', $tenantId);
        EventBus::instance()->emit(['type' => EventType::WHITELABEL_UPDATED, 'tenant_id' => $tenantId, 'payload' => []]);
        return $this->branding($tenantId);
    }

    /** @return list<array<string, mixed>> */
    public function listDomains(int $tenantId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM rcc_whitelabel_domains WHERE tenant_id = :tid ORDER BY is_primary DESC');
        $stmt->execute(['tid' => $tenantId]);
        return $stmt->fetchAll() ?: [];
    }

    public function addDomain(int $tenantId, string $domain, ?int $userId): array
    {
        $token = bin2hex(random_bytes(16));
        Database::connection()->prepare(
            'INSERT INTO rcc_whitelabel_domains (tenant_id, domain, verification_token) VALUES (:tid, :dom, :tok)'
        )->execute(['tid' => $tenantId, 'dom' => strtolower(trim($domain)), 'tok' => $token]);
        $id = (int) Database::connection()->lastInsertId();
        $this->audit->log($tenantId, 'whitelabel.domain.add', $userId, 'domain', $id);
        return ['id' => $id, 'domain' => $domain, 'verification_token' => $token];
    }

    public function resolveTenantByDomain(string $host): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT tenant_id FROM rcc_whitelabel_domains WHERE domain = :dom AND status = 'active' LIMIT 1"
        );
        $stmt->execute(['dom' => strtolower(trim($host))]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : 0;
    }

    /** @param array<string, mixed> $data */
    public function saveTheme(int $tenantId, array $data, ?int $userId): array
    {
        $key = (string) ($data['theme_key'] ?? 'default');
        $mode = (string) ($data['mode'] ?? 'auto');
        $tokens = isset($data['tokens']) ? json_encode($data['tokens'], JSON_UNESCAPED_UNICODE) : null;
        Database::connection()->prepare(
            'INSERT INTO rcc_whitelabel_themes (tenant_id, theme_key, mode, tokens_json, is_active)
             VALUES (:tid, :key, :mode, :tok, 1)
             ON DUPLICATE KEY UPDATE mode = VALUES(mode), tokens_json = VALUES(tokens_json)'
        )->execute(['tid' => $tenantId, 'key' => $key, 'mode' => $mode, 'tok' => $tokens]);
        $this->audit->log($tenantId, 'whitelabel.theme.save', $userId, 'theme', $tenantId);
        return ['theme_key' => $key, 'mode' => $mode];
    }
}
