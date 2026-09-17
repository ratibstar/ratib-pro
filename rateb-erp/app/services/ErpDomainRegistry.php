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
     * User-facing capabilities from active domains/tools the user can actually access.
     *
     * @return list<array{id:string,label:string,prompt:string,domain:string}>
     */
    public static function userFacingCapabilities(ProcurementAgentContext $ctx): array
    {
        $out = [];
        $labelMap = [
            self::DOMAIN_PROCUREMENT => ['label' => 'ai_cap_procurement', 'prompt' => 'ai_suggest_list_pr'],
            self::DOMAIN_INVENTORY => ['label' => 'ai_cap_inventory', 'prompt' => 'ai_suggest_inventory'],
            self::DOMAIN_SUPPLIERS => ['label' => 'ai_cap_suppliers', 'prompt' => 'ai_suggest_search_suppliers'],
            self::DOMAIN_SALES => ['label' => 'ai_cap_sales', 'prompt' => 'ai_suggest_sales'],
            self::DOMAIN_CRM => ['label' => 'ai_cap_crm', 'prompt' => 'ai_suggest_crm'],
            self::DOMAIN_LOGISTICS => ['label' => 'ai_cap_logistics', 'prompt' => 'ai_suggest_logistics'],
            self::DOMAIN_ACCOUNTING => ['label' => 'ai_cap_accounting', 'prompt' => 'ai_suggest_accounting'],
            self::DOMAIN_EXECUTIVE => ['label' => 'ai_cap_executive', 'prompt' => 'ai_suggest_executive'],
        ];
        foreach (self::getActiveDomains() as $id => $meta) {
            if (empty($meta['active'])) {
                continue;
            }
            $module = (string) ($meta['module'] ?? '');
            if ($module !== '' && $module !== 'dashboard' && !$ctx->moduleEnabled($module)) {
                continue;
            }
            if ($id === self::DOMAIN_EXECUTIVE && !$ctx->can('dashboard.view') && !$ctx->can('ai.view') && !$ctx->isSuperAdmin) {
                continue;
            }
            $tools = self::toolsForDomain($id);
            if ($tools === []) {
                continue;
            }
            $map = $labelMap[$id] ?? ['label' => (string) ($meta['label'] ?? $id), 'prompt' => ''];
            $labelKey = (string) ($map['label'] ?? $id);
            $promptKey = (string) ($map['prompt'] ?? '');
            $label = function_exists('__') ? __($labelKey) : $labelKey;
            if ($label === $labelKey) {
                $label = (string) ($meta['label'] ?? $id);
            }
            $prompt = $promptKey !== '' && function_exists('__') ? __($promptKey) : '';
            if ($prompt === '' || $prompt === $promptKey) {
                $prompt = $label;
            }
            $out[] = [
                'id' => $id,
                'domain' => $id,
                'label' => $label,
                'prompt' => $prompt,
            ];
        }
        // Feature chips when executive tools exist
        if (self::isActive(self::DOMAIN_EXECUTIVE) && ($ctx->can('ai.view') || $ctx->can('dashboard.view') || $ctx->isSuperAdmin)) {
            $execTools = self::toolsForDomain(self::DOMAIN_EXECUTIVE);
            $extra = [
                'scan_early_warnings' => ['id' => 'early_warnings', 'label' => 'ai_cap_warnings', 'prompt' => 'ai_suggest_warnings'],
                'analyze_operational_learning' => ['id' => 'learning', 'label' => 'ai_cap_learning', 'prompt' => 'ai_suggest_learning'],
                'plan_multi_step_workflow' => ['id' => 'workflows', 'label' => 'ai_cap_workflows', 'prompt' => 'ai_suggest_workflow'],
                'get_control_tower_snapshot' => ['id' => 'control_tower', 'label' => 'ai_cap_control_tower', 'prompt' => 'ai_suggest_control_tower'],
                'get_relevant_operational_context' => ['id' => 'memory', 'label' => 'ai_cap_memory', 'prompt' => 'ai_suggest_memory'],
            ];
            $seen = array_column($out, 'id');
            foreach ($extra as $tool => $chip) {
                if (!isset($execTools[$tool]) || in_array($chip['id'], $seen, true)) {
                    continue;
                }
                $lk = $chip['label'];
                $pk = $chip['prompt'];
                $label = function_exists('__') ? __($lk) : $lk;
                $prompt = function_exists('__') ? __($pk) : $pk;
                if ($label === $lk) {
                    $label = $chip['id'];
                }
                if ($prompt === $pk) {
                    $prompt = $label;
                }
                $out[] = [
                    'id' => $chip['id'],
                    'domain' => self::DOMAIN_EXECUTIVE,
                    'label' => $label,
                    'prompt' => $prompt,
                ];
                $seen[] = $chip['id'];
            }
        }
        return $out;
    }

    /**
     * Dynamic tool labels for UI (active tools only).
     *
     * @return array<string, string>
     */
    public static function toolLabelsForUi(ProcurementAgentContext $ctx): array
    {
        $labels = [];
        foreach (self::allActiveTools() as $name => $tool) {
            $module = (string) ($tool['module'] ?? '');
            $domain = (string) ($tool['domain'] ?? '');
            if ($module !== '' && $module !== 'dashboard' && !$ctx->moduleEnabled($module)) {
                continue;
            }
            if ($domain === self::DOMAIN_EXECUTIVE && !$ctx->can('dashboard.view') && !$ctx->can('ai.view') && !$ctx->isSuperAdmin) {
                continue;
            }
            $key = 'ai_tool_' . $name;
            $translated = function_exists('__') ? __($key) : $key;
            $labels[$name] = ($translated !== $key && $translated !== '')
                ? $translated
                : (string) ($tool['description'] ?? $name);
        }
        return $labels;
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
