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
    public const DOMAIN_HR = 'hr';
    public const DOMAIN_RECRUITMENT = 'recruitment';
    public const DOMAIN_PROJECTS = 'projects';
    public const DOMAIN_CONTRACTS = 'contracts';
    public const DOMAIN_ASSETS = 'assets';
    public const DOMAIN_PAYROLL = 'payroll';
    public const DOMAIN_MANUFACTURING = 'manufacturing';
    public const DOMAIN_QUALITY = 'quality';
    public const DOMAIN_APPROVALS = 'approvals';
    public const DOMAIN_MARKETPLACE = 'marketplace';
    public const DOMAIN_NOTIFICATIONS = 'notifications';
    public const DOMAIN_BI = 'bi';
    public const DOMAIN_WEBSITE = 'website';

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
            self::DOMAIN_HR => [
                'id' => self::DOMAIN_HR,
                'active' => true,
                'module' => 'hr',
                'label' => 'HR',
                'tool_registry' => HrToolRegistry::class,
                'tool_executor' => HrToolExecutor::class,
                'policy_guard' => ProcurementPolicyGuard::class,
                'runtime' => ProcurementAgent::class,
            ],
            self::DOMAIN_RECRUITMENT => [
                'id' => self::DOMAIN_RECRUITMENT,
                'active' => true,
                'module' => 'recruitment',
                'label' => 'Recruitment',
                'tool_registry' => RecruitmentToolRegistry::class,
                'tool_executor' => RecruitmentToolExecutor::class,
                'policy_guard' => ProcurementPolicyGuard::class,
                'runtime' => ProcurementAgent::class,
            ],
            self::DOMAIN_PROJECTS => [
                'id' => self::DOMAIN_PROJECTS,
                'active' => true,
                'module' => 'projects',
                'label' => 'Projects',
                'tool_registry' => ProjectsToolRegistry::class,
                'tool_executor' => ProjectsToolExecutor::class,
                'policy_guard' => ProcurementPolicyGuard::class,
                'runtime' => ProcurementAgent::class,
            ],
            self::DOMAIN_CONTRACTS => [
                'id' => self::DOMAIN_CONTRACTS,
                'active' => true,
                'module' => 'contracts',
                'label' => 'Contracts',
                'tool_registry' => ContractsToolRegistry::class,
                'tool_executor' => ContractsToolExecutor::class,
                'policy_guard' => ProcurementPolicyGuard::class,
                'runtime' => ProcurementAgent::class,
            ],
            self::DOMAIN_ASSETS => [
                'id' => self::DOMAIN_ASSETS,
                'active' => true,
                'module' => 'assets',
                'label' => 'Assets',
                'tool_registry' => AssetsToolRegistry::class,
                'tool_executor' => AssetsToolExecutor::class,
                'policy_guard' => ProcurementPolicyGuard::class,
                'runtime' => ProcurementAgent::class,
            ],
            self::DOMAIN_PAYROLL => [
                'id' => self::DOMAIN_PAYROLL,
                'active' => true,
                'module' => 'payroll',
                'label' => 'Payroll',
                'tool_registry' => PayrollToolRegistry::class,
                'tool_executor' => PayrollToolExecutor::class,
                'policy_guard' => ProcurementPolicyGuard::class,
                'runtime' => ProcurementAgent::class,
            ],
            self::DOMAIN_MANUFACTURING => [
                'id' => self::DOMAIN_MANUFACTURING,
                'active' => true,
                'module' => 'manufacturing',
                'label' => 'Manufacturing',
                'tool_registry' => ManufacturingToolRegistry::class,
                'tool_executor' => ManufacturingToolExecutor::class,
                'policy_guard' => ProcurementPolicyGuard::class,
                'runtime' => ProcurementAgent::class,
            ],
            self::DOMAIN_QUALITY => [
                'id' => self::DOMAIN_QUALITY,
                'active' => true,
                'module' => 'quality',
                'label' => 'Quality',
                'tool_registry' => QualityToolRegistry::class,
                'tool_executor' => QualityToolExecutor::class,
                'policy_guard' => ProcurementPolicyGuard::class,
                'runtime' => ProcurementAgent::class,
            ],
            self::DOMAIN_APPROVALS => [
                'id' => self::DOMAIN_APPROVALS,
                'active' => true,
                'module' => 'workflows',
                'label' => 'Approvals',
                'tool_registry' => ApprovalsToolRegistry::class,
                'tool_executor' => ApprovalsToolExecutor::class,
                'policy_guard' => ProcurementPolicyGuard::class,
                'runtime' => ProcurementAgent::class,
            ],
            self::DOMAIN_MARKETPLACE => [
                'id' => self::DOMAIN_MARKETPLACE,
                'active' => true,
                'module' => 'marketplace',
                'label' => 'Marketplace',
                'tool_registry' => MarketplaceToolRegistry::class,
                'tool_executor' => MarketplaceToolExecutor::class,
                'policy_guard' => ProcurementPolicyGuard::class,
                'runtime' => ProcurementAgent::class,
            ],
            self::DOMAIN_NOTIFICATIONS => [
                'id' => self::DOMAIN_NOTIFICATIONS,
                'active' => true,
                'module' => 'dashboard',
                'label' => 'Notifications',
                'tool_registry' => NotificationsToolRegistry::class,
                'tool_executor' => NotificationsToolExecutor::class,
                'policy_guard' => ProcurementPolicyGuard::class,
                'runtime' => ProcurementAgent::class,
            ],
            self::DOMAIN_BI => [
                'id' => self::DOMAIN_BI,
                'active' => true,
                'module' => 'reports',
                'label' => 'Business Intelligence',
                'tool_registry' => BiToolRegistry::class,
                'tool_executor' => BiToolExecutor::class,
                'policy_guard' => ProcurementPolicyGuard::class,
                'runtime' => ProcurementAgent::class,
            ],
            self::DOMAIN_WEBSITE => [
                'id' => self::DOMAIN_WEBSITE,
                'active' => true,
                'module' => 'website',
                'label' => 'Website / CMS',
                'tool_registry' => WebsiteToolRegistry::class,
                'tool_executor' => WebsiteToolExecutor::class,
                'policy_guard' => ProcurementPolicyGuard::class,
                'runtime' => ProcurementAgent::class,
            ],
        ];
    }

    /**
     * Reserved future ERP domains — empty once packs are live.
     *
     * @return list<string>
     */
    public static function getReservedFutureDomains(): array
    {
        return [];
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
            self::DOMAIN_HR => ['label' => 'ai_cap_hr', 'prompt' => 'ai_suggest_hr'],
            self::DOMAIN_RECRUITMENT => ['label' => 'ai_cap_recruitment', 'prompt' => 'ai_suggest_recruitment'],
            self::DOMAIN_PROJECTS => ['label' => 'ai_cap_projects', 'prompt' => 'ai_suggest_projects'],
            self::DOMAIN_CONTRACTS => ['label' => 'ai_cap_contracts', 'prompt' => 'ai_suggest_contracts'],
            self::DOMAIN_ASSETS => ['label' => 'ai_cap_assets', 'prompt' => 'ai_suggest_assets'],
            self::DOMAIN_PAYROLL => ['label' => 'ai_cap_payroll', 'prompt' => 'ai_suggest_payroll'],
            self::DOMAIN_MANUFACTURING => ['label' => 'ai_cap_manufacturing', 'prompt' => 'ai_suggest_manufacturing'],
            self::DOMAIN_QUALITY => ['label' => 'ai_cap_quality', 'prompt' => 'ai_suggest_quality'],
            self::DOMAIN_APPROVALS => ['label' => 'ai_cap_approvals', 'prompt' => 'ai_suggest_approvals'],
            self::DOMAIN_MARKETPLACE => ['label' => 'ai_cap_marketplace', 'prompt' => 'ai_suggest_marketplace'],
            self::DOMAIN_NOTIFICATIONS => ['label' => 'ai_cap_notifications', 'prompt' => 'ai_suggest_notifications'],
            self::DOMAIN_BI => ['label' => 'ai_cap_bi', 'prompt' => 'ai_suggest_bi'],
            self::DOMAIN_WEBSITE => ['label' => 'ai_cap_website', 'prompt' => 'ai_suggest_website'],
        ];
        foreach (self::getActiveDomains() as $id => $meta) {
            if (empty($meta['active'])) {
                continue;
            }
            $module = (string) ($meta['module'] ?? '');
            $moduleOk = $module === '' || $module === 'dashboard'
                || $ctx->moduleEnabled($module)
                || ($id === self::DOMAIN_PAYROLL && $ctx->moduleEnabled('hr'))
                || ($id === self::DOMAIN_QUALITY && ($ctx->moduleEnabled('manufacturing') || $ctx->moduleEnabled('quality')))
                || ($id === self::DOMAIN_BI && ($ctx->moduleEnabled('reports') || $ctx->can('reports.view') || $ctx->can('dashboard.view')))
                || ($id === self::DOMAIN_APPROVALS && ($ctx->moduleEnabled('workflows') || $ctx->moduleEnabled('procurement') || $ctx->can('workflows.view')))
                || ($id === self::DOMAIN_NOTIFICATIONS && ($ctx->can('dashboard.view') || $ctx->can('ai.view')));
            if (!$moduleOk && !$ctx->isSuperAdmin) {
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
