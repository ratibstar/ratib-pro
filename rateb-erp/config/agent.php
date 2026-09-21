<?php
declare(strict_types=1);

/**
 * Procurement Ops Agent Configuration
 * LLM provider settings — all secrets from environment
 */

// Load .env from project root if not already loaded
if (!function_exists('rateb_agent_load_env')) {
    function rateb_agent_load_env(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $envFile = dirname(__DIR__, 2) . '/.env';
        if (is_file($envFile)) {
            $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                if (strpos($line, '=') === false) {
                    continue;
                }
                [$key, $val] = explode('=', $line, 2);
                $key = trim($key);
                if ($key !== '' && getenv($key) === false) {
                    putenv($key . '=' . trim($val, " \t\"'"));
                }
            }
        }
    }
}

rateb_agent_load_env();

/**
 * Procurement Ops Agent Configuration
 * LLM provider settings — all secrets from environment
 */

return [
    /*
     * LLM Provider Configuration
     * All secrets must come from environment variables (.env)
     */
    'llm' => [
        'provider' => getenv('RATEB_AGENT_LLM_PROVIDER') ?: 'openai_compatible',
        'model' => getenv('RATEB_AGENT_LLM_MODEL') ?: 'gpt-4o-mini',
        'base_url' => getenv('RATEB_AGENT_LLM_BASE_URL') ?: 'https://api.openai.com/v1',
        'api_key' => getenv('RATEB_AGENT_LLM_API_KEY') ?: '',
        'timeout' => (int) (getenv('RATEB_AGENT_LLM_TIMEOUT') ?: 30),
        'max_tokens' => (int) (getenv('RATEB_AGENT_LLM_MAX_TOKENS') ?: 2000),
        'temperature' => (float) (getenv('RATEB_AGENT_LLM_TEMPERATURE') ?: 0.1),
    ],

    /*
     * Tool Allowlist — mirrored by ProcurementToolRegistry (authoritative at runtime)
     */
    'tools' => [
        'list_purchase_requests' => [
            'description' => 'List purchase requests for the current company',
            'permission' => 'procurement.view',
            'module' => 'procurement',
            'write' => false,
        ],
        'get_purchase_request' => [
            'description' => 'Get a single purchase request by ID (tenant-scoped)',
            'permission' => 'procurement.view',
            'module' => 'procurement',
            'write' => false,
        ],
        'list_purchase_orders' => [
            'description' => 'List purchase orders for the current company',
            'permission' => 'procurement.view',
            'module' => 'procurement',
            'write' => false,
        ],
        'get_purchase_order' => [
            'description' => 'Get a single purchase order by ID (tenant-scoped)',
            'permission' => 'procurement.view',
            'module' => 'procurement',
            'write' => false,
        ],
        'search_suppliers' => [
            'description' => 'Search suppliers for the current company',
            'permission' => 'suppliers.manage',
            'module' => 'suppliers',
            'write' => false,
        ],
        'list_pending_approvals' => [
            'description' => 'List pending approvals for purchase_request and purchase_order entities',
            'permission' => 'procurement.view',
            'module' => 'procurement',
            'write' => false,
        ],
        'get_approval_detail' => [
            'description' => 'Get approval workflow detail by instance ID',
            'permission' => 'procurement.view',
            'module' => 'procurement',
            'write' => false,
        ],
        'summarize_procurement' => [
            'description' => 'Procurement summary from live tenant data',
            'permission' => 'procurement.view',
            'module' => 'procurement',
            'write' => false,
        ],
        'analyze_procurement_intelligence' => [
            'description' => 'Deep procurement intelligence: pending, overdue, abnormal, PR↔approval↔PO links',
            'permission' => 'procurement.view',
            'module' => 'procurement',
            'write' => false,
        ],
        'get_purchase_request_cycle' => [
            'description' => 'One purchase request cycle with approvals, POs, and amounts',
            'permission' => 'procurement.view',
            'module' => 'procurement',
            'write' => false,
        ],
        'analyze_advanced_procurement_operations' => [
            'description' => 'Advanced procurement operations: spend, frequency, bottlenecks, priorities, executive summary',
            'permission' => 'procurement.view',
            'module' => 'procurement',
            'write' => false,
        ],
        'get_procurement_operational_guidance' => [
            'description' => 'Operational guidance for what to do next (never auto-writes)',
            'permission' => 'procurement.view',
            'module' => 'procurement',
            'write' => false,
        ],
        'create_draft_purchase_request' => [
            'description' => 'Create a draft purchase request',
            'permission' => 'procurement.create',
            'module' => 'procurement',
            'write' => true,
        ],
        'update_purchase_request' => [
            'description' => 'Update a purchase request',
            'permission' => 'procurement.update',
            'module' => 'procurement',
            'write' => true,
        ],
        'cancel_purchase_request' => [
            'description' => 'Cancel a purchase request',
            'permission' => 'procurement.update',
            'module' => 'procurement',
            'write' => true,
        ],
        'submit_purchase_request' => [
            'description' => 'Submit a draft purchase request for approval workflow',
            'permission' => 'procurement.submit',
            'module' => 'procurement',
            'write' => true,
        ],
    ],

    /*
     * Agent Behavior
     */
    'agent' => [
        'executive_system_prompt' => 'You are the RATEB ERP Agent in Executive Intelligence mode. Use only approved executive tools. Never invent KPIs, trends, risks, or forecasts. Present evidence-backed summaries only. Never auto-execute writes — route recommended actions through confirmation and governance.',
        'system_prompt' => 'You are the RATEB ERP Agent (single unified agent). Active domains include: Procurement, Inventory, Suppliers, Sales/POS, CRM, Logistics, Accounting, Executive, HR, Recruitment, Projects, Contracts, Assets, Payroll, Manufacturing, Quality, Approvals, Marketplace, Notifications, Business Intelligence, and Website/CMS. When the user asks to list or show data in any of these domains, you MUST call the matching approved read tools and answer from live tool results only — never refuse with “cannot display” if a read tool exists. Cross-domain questions are orchestrated with approved tools only. Always be evidence-first: never invent numbers, statuses, relations, or entities. Recommendations are guidance only — never auto-execute writes. Write actions use the Action Layer only for registered tools, with Governance → Authorization → Policy → State validation → Confirmation → Execution → Verification → Audit. Controlled autonomy is disabled by default and never bypasses confirmation or approval. Tenant-scope every operation. Never expose tool ids, function names, JSON, or API samples in user-facing replies. Speak only in the UI language with zero language mixing. Never invent numbers; say when data is unavailable. Writes require confirmed_writes confirmation and sufficient parameters. Prefer one confirmed write at a time.',
        'max_tool_calls_per_request' => 10,
        'max_orchestration_tools' => 10,
        'max_writes_per_request' => 1,
        'require_confirmation_for_write' => true,
        'default_domain' => 'procurement',
        'agent_id' => 'rateb_erp_agent',
    ],

    /*
     * Governance / Controlled Autonomy (application-owned; LLM cannot override)
     */
    'governance' => [
        'controlled_autonomy_enabled' => false,
        'autonomy_allowlist' => [],
        'max_domains_per_request' => 6,
        'max_tools_per_request' => 10,
        'max_writes_per_request' => 1,
        'max_action_chain' => 3,
    ],

    /*
     * Audit Configuration
     */
    'audit' => [
        'enabled' => true,
        'table' => 'rateb_agent_audit_events',
        'log_input' => true,
        'log_output' => true,
        'exclude_secrets' => true,
    ],
];