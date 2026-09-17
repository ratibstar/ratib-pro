<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Services\Agent\LlmClientInterface;

/**
 * Inventory domain runtime — not a separate agent.
 * Reuses the hardened Unified runtime (ProcurementAgent engine) with Inventory tools only.
 */
final class InventoryAgent
{
    private ProcurementAgent $runtime;

    public function __construct(array $config = [], ?LlmClientInterface $llmClient = null)
    {
        $agentCfg = is_array($config['agent'] ?? null) ? $config['agent'] : [];
        $agentCfg['tool_registry'] = InventoryToolRegistry::class;
        $agentCfg['tool_executor'] = InventoryToolExecutor::class;
        $agentCfg['system_prompt'] = (string) ($agentCfg['inventory_system_prompt']
            ?? 'You are the RATEB ERP Agent operating in the Inventory domain. Use only approved inventory tools. Never invent stock numbers. Tenant-scope every operation. Speak only in the UI language. Prefer analyze_inventory and get_inventory_procurement_links for analysis. Never auto-execute writes — inventory mutations are not available via this agent.');
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
