<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\Routing\AI;

use Ratib\ContactCenter\App\Application\Services\SoftphoneErpService;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\ErpBridge;

/**
 * ERP + CRM context for routing priority boosts.
 */
final class ErpContextAnalyzer
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(
        private readonly ?SoftphoneErpService $erpService = null,
        ?array $config = null
    ) {
        $this->config = $config ?? $this->loadConfig();
    }

    /** @return array<string, mixed> */
    public function analyze(int $tenantId, string $customerPhone, ?int $erpCustomerId = null): array
    {
        $service = $this->erpService ?? new SoftphoneErpService();
        $profile = $service->customerProfileByPhone($tenantId, $customerPhone);

        $contact = is_array($profile['contact'] ?? null) ? $profile['contact'] : null;
        $company = is_array($profile['company'] ?? null) ? $profile['company'] : null;
        $contactId = $contact !== null ? (int) $contact['id'] : null;

        $flags = [
            'vip_customer' => $this->isVip($contact, $company),
            'open_sla_breach' => ($profile['sla_status'] ?? '') === 'breached',
            'high_value_company' => $this->isHighValueCompany($tenantId, $company, $erpCustomerId),
            'repeat_caller' => $this->isRepeatCaller($tenantId, $customerPhone),
        ];

        $boosts = $this->config['erp_priority_boosts'] ?? [];
        $priorityMultiplier = 1.0;
        $applied = [];
        foreach ($flags as $flag => $active) {
            if (!$active) {
                continue;
            }
            $boost = (float) ($boosts[$flag] ?? 0.0);
            $priorityMultiplier += $boost;
            $applied[] = $flag;
        }

        return [
            'contact_id' => $contactId,
            'contact' => $contact,
            'company' => $company,
            'recent_tickets' => $profile['recent_tickets'] ?? [],
            'sla_status' => $profile['sla_status'] ?? 'unknown',
            'flags' => $flags,
            'applied_boosts' => $applied,
            'priority_multiplier' => round($priorityMultiplier, 3),
            'priority_score' => round(($priorityMultiplier - 1.0) * 100, 1),
        ];
    }

    /** @param array<string, mixed>|null $contact */
    /** @param array<string, mixed>|null $company */
    private function isVip(?array $contact, ?array $company): bool
    {
        if ($contact !== null && ($contact['contact_type'] ?? '') === 'vip') {
            return true;
        }
        if ($company !== null && ($company['tier'] ?? '') === 'vip') {
            return true;
        }
        return false;
    }

    /** @param array<string, mixed>|null $company */
    private function isHighValueCompany(int $tenantId, ?array $company, ?int $erpCustomerId): bool
    {
        if ($company !== null && ($company['tier'] ?? '') === 'enterprise') {
            return true;
        }
        if ($erpCustomerId !== null && $erpCustomerId > 0) {
            $erpCompany = ErpBridge::companyById($erpCustomerId);
            if ($erpCompany !== null) {
                $settings = $erpCompany['settings'] ?? null;
                if (is_string($settings)) {
                    $decoded = json_decode($settings, true);
                    if (is_array($decoded) && !empty($decoded['high_value'])) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function isRepeatCaller(int $tenantId, string $phone): bool
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return false;
        }
        try {
            $stmt = Database::connection()->prepare(
                "SELECT COUNT(*) FROM rcc_calls
                 WHERE tenant_id = :tid
                   AND started_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                   AND REPLACE(REPLACE(caller_number, '+', ''), ' ', '') LIKE :phone"
            );
            $stmt->execute(['tid' => $tenantId, 'phone' => '%' . substr($digits, -9)]);
            return (int) $stmt->fetchColumn() > 1;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @return array<string, mixed> */
    private function loadConfig(): array
    {
        $path = dirname(__DIR__, 4) . '/config/routing.php';
        if (!is_file($path)) {
            return [];
        }
        $loaded = require $path;
        return is_array($loaded) ? $loaded : [];
    }
}
