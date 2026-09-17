<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Unified ERP Domain Registry
 *
 * Single RATEB ERP Agent — domains are logical packs, not separate agents.
 * Only domains with real services may be active. Future domains stay reserved.
 */
final class ErpDomainRegistry
{
    public const DOMAIN_PROCUREMENT = 'procurement';
    public const DOMAIN_INVENTORY = 'inventory';
    public const DOMAIN_SUPPLIERS = 'suppliers';
    public const DOMAIN_SALES = 'sales';
    public const DOMAIN_CRM = 'crm';
    public const DOMAIN_LOGISTICS = 'logistics';
    public const DOMAIN_ACCOUNTING = 'accounting';
    public const DOMAIN_EXECUTIVE = 'executive';

    /**
     * Active domains with real runtimes/tools only.
     *
     * @return array<string, array{
     *     id: string,
     *     active: bool,
     *     module: string,
     *     label: string,
     *     tool_registry: class-string,
     *     tool_executor: class-string,
     *     policy_guard: class-string,
     *     runtime: class-string
     * }>
     */
    public static function getActiveDomains(): array
    {
        return [
            self::DOMAIN_PROCUREMENT => [
                'id' => self::DOMAIN_PROCUREMENT,
                'active' => true,
                'module' => 'procurement',
                'label' => 'Procurement',
                'tool_registry' => ProcurementToolRegistry::class,
                'tool_executor' => ProcurementToolExecutor::class,
                'policy_guard' => ProcurementPolicyGuard::class,
                'runtime' => ProcurementAgent::class,
            ],
            self::DOMAIN_INVENTORY => [
                'id' => self::DOMAIN_INVENTORY,
                'active' => true,
                'module' => 'inventory',
                'label' => 'Inventory',
                'tool_registry' => InventoryToolRegistry::class,
                'tool_executor' => InventoryToolExecutor::class,
                'policy_guard' => ProcurementPolicyGuard::class,
                'runtime' => InventoryAgent::class,
            ],
            self::DOMAIN_SUPPLIERS => [
                'id' => self::DOMAIN_SUPPLIERS,
                'active' => true,
                'module' => 'suppliers',
                'label' => 'Suppliers',
                'tool_registry' => SupplierToolRegistry::class,
                'tool_executor' => SupplierToolExecutor::class,
                'policy_guard' => ProcurementPolicyGuard::class,
                'runtime' => SupplierAgent::class,
            ],
            // Sales uses existing ProcurementAgent engine via ErpAgent config override — no SalesAgent.
            self::DOMAIN_SALES => [
                'id' => self::DOMAIN_SALES,
                'active' => true,
                'module' => 'pos',
                'label' => 'Sales',
                'tool_registry' => SalesToolRegistry::class,
                'tool_executor' => SalesToolExecutor::class,
                'policy_guard' => ProcurementPolicyGuard::class,
                'runtime' => ProcurementAgent::class,
            ],
            // CRM uses existing ProcurementAgent engine via ErpAgent config override — no CrmAgent.
            self::DOMAIN_CRM => [
                'id' => self::DOMAIN_CRM,
                'active' => true,
                'module' => 'crm',
                'label' => 'CRM',
                'tool_registry' => CrmToolRegistry::class,
                'tool_executor' => CrmToolExecutor::class,
                'policy_guard' => ProcurementPolicyGuard::class,
                'runtime' => ProcurementAgent::class,
            ],
            // Logistics uses existing ProcurementAgent engine via ErpAgent config override — no LogisticsAgent.
            self::DOMAIN_LOGISTICS => [
                'id' => self::DOMAIN_LOGISTICS,
                'active' => true,
                'module' => 'logistics',
                'label' => 'Logistics',
                'tool_registry' => LogisticsToolRegistry::class,
                'tool_executor' => LogisticsToolExecutor::class,
                'policy_guard' => ProcurementPolicyGuard::class,
                'runtime' => ProcurementAgent::class,
            ],
            // Accounting uses existing ProcurementAgent engine via ErpAgent config override — no AccountingAgent.
            self::DOMAIN_ACCOUNTING => [
                'id' => self::DOMAIN_ACCOUNTING,
                'active' => true,
                'module' => 'accounting',
                'label' => 'Accounting',
                'tool_registry' => AccountingToolRegistry::class,
                'tool_executor' => AccountingToolExecutor::class,
                'policy_guard' => ProcurementPolicyGuard::class,
                'runtime' => ProcurementAgent::class,
            ],
            // Executive Intelligence — cross-domain KPI/forecast layer; no ExecutiveAgent.
            self::DOMAIN_EXECUTIVE => [
                'id' => self::DOMAIN_EXECUTIVE,
                'active' => true,
                'module' => 'dashboard',
                'label' => 'Executive',
                'tool_registry' => ExecutiveToolRegistry::class,
                'tool_executor' => ExecutiveToolExecutor::class,
                'policy_guard' => ProcurementPolicyGuard::class,
                'runtime' => ProcurementAgent::class,
            ],
        ];
    }

    /**
     * Reserved future ERP domains — not implemented, no tools, no fake handlers.
     *
     * @return list<string>
     */
    public static function getReservedFutureDomains(): array
    {
        return [
            'hr',
            'projects',
            'contracts',
            'payroll',
            'manufacturing',
            'quality',
            'bi',
        ];
    }

    public static function defaultDomainId(): string
    {
        return self::DOMAIN_PROCUREMENT;
    }

    public static function isActive(string $domainId): bool
    {
        $domains = self::getActiveDomains();
        return isset($domains[$domainId]) && !empty($domains[$domainId]['active']);
    }

    public static function isReservedFuture(string $domainId): bool
    {
        return in_array($domainId, self::getReservedFutureDomains(), true)
            && !self::isActive($domainId);
    }

    /**
     * @return array{
     *     id: string,
     *     active: bool,
     *     module: string,
     *     label: string,
     *     tool_registry: class-string,
     *     tool_executor: class-string,
     *     policy_guard: class-string,
     *     runtime: class-string
     * }|null
     */
    public static function resolve(string $domainId): ?array
    {
        $domains = self::getActiveDomains();
        return $domains[$domainId] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function activeDomainIds(): array
    {
        return array_keys(self::getActiveDomains());
    }

    /**
     * Tools for one active domain — never invents tools for reserved domains.
     *
     * @return array<string, array>
     */
    public static function toolsForDomain(string $domainId): array
    {
        $domain = self::resolve($domainId);
        if ($domain === null || empty($domain['active'])) {
            return [];
        }
        $registry = (string) ($domain['tool_registry'] ?? '');
        if ($registry === '' || !class_exists($registry) || !method_exists($registry, 'getTools')) {
            return [];
        }
        $tools = $registry::getTools();
        return is_array($tools) ? $tools : [];
    }

    /**
     * Aggregated tools across active domains only (unique names).
     *
     * @return array<string, array>
     */
    public static function allActiveTools(): array
    {
        $out = [];
        foreach (self::getActiveDomains() as $domainId => $domain) {
            foreach (self::toolsForDomain($domainId) as $name => $tool) {
                if (!isset($out[$name])) {
                    $tool['domain'] = $domainId;
                    $out[$name] = $tool;
                }
            }
        }
        return $out;
    }

    /**
     * Which active domain owns a tool name (if any).
     */
    public static function domainForTool(string $toolName): ?string
    {
        foreach (self::getActiveDomains() as $domainId => $_domain) {
            $tools = self::toolsForDomain($domainId);
            if (isset($tools[$toolName])) {
                return $domainId;
            }
        }
        return null;
    }

    /**
     * Future-readiness snapshot (no fake implementations).
     *
     * @return array{
     *     active: list<string>,
     *     reserved_future: list<string>,
     *     can_add_domain_without_new_agent: bool,
     *     single_agent: bool
     * }
     */
    public static function futureReadiness(): array
    {
        return [
            'active' => self::activeDomainIds(),
            'reserved_future' => self::getReservedFutureDomains(),
            'can_add_domain_without_new_agent' => true,
            'single_agent' => true,
            'architecture' => 'unified_erp_agent_core',
        ];
    }
}
