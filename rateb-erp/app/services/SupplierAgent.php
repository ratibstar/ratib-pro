<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Services\Agent\LlmClientInterface;

/**
 * Supplier domain runtime — not a separate agent.
 * Reuses the hardened Unified runtime (ProcurementAgent engine) with Supplier tools only.
 */
final class SupplierAgent
{
    private ProcurementAgent $runtime;

    public function __construct(array $config = [], ?LlmClientInterface $llmClient = null)
    {
        $agentCfg = is_array($config['agent'] ?? null) ? $config['agent'] : [];
        $agentCfg['tool_registry'] = SupplierToolRegistry::class;
        $agentCfg['tool_executor'] = SupplierToolExecutor::class;
        $agentCfg['system_prompt'] = (string) ($agentCfg['supplier_system_prompt']
            ?? 'You are the RATEB ERP Agent operating in the Supplier domain. Use only approved supplier tools. Never invent supplier or spend numbers. Tenant-scope every operation. Speak only in the UI language. Prefer analyze_suppliers, get_supplier_procurement_links, get_supplier_inventory_links, and analyze_supplier_cross_domain for analysis. Never auto-execute writes — supplier mutations are not available via this agent.');
        $config['agent'] = $agentCfg;
        $this->runtime = new ProcurementAgent($config, $llmClient);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function process(array $input, ProcurementAgentContext $ctx): array
    {
        return $this->runtime->process($input, $ctx);
    }
}
