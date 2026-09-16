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
     * Tool Allowlist — ONLY these 8 tools are executable
     */
    'tools' => [
        'list_purchase_requests' => [
            'description' => 'List purchase requests for the current company',
            'permission' => 'procurement.manage',
            'module' => 'procurement',
            'write' => false,
        ],
        'get_purchase_request' => [
            'description' => 'Get a single purchase request by ID (tenant-scoped)',
            'permission' => 'procurement.manage',
            'module' => 'procurement',
            'write' => false,
        ],
        'list_purchase_orders' => [
            'description' => 'List purchase orders for the current company',
            'permission' => 'procurement.manage',
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
            'permission' => 'procurement.manage',
            'module' => 'procurement',
            'write' => false,
        ],
        'get_approval_detail' => [
            'description' => 'Get approval workflow detail by instance ID',
            'permission' => 'procurement.manage',
            'module' => 'procurement',
            'write' => false,
        ],
        'create_draft_purchase_request' => [
            'description' => 'Create a draft purchase request',
            'permission' => 'procurement.manage',
            'module' => 'procurement',
            'write' => true,
        ],
        'submit_purchase_request' => [
            'description' => 'Submit a draft purchase request for approval workflow',
            'permission' => 'procurement.manage',
            'module' => 'procurement',
            'write' => true,
        ],
    ],

    /*
     * Agent Behavior
     */
    'agent' => [
        'system_prompt' => 'You are the Procurement Ops Agent inside RATEB ERP. Use only the approved tools. Never attempt SQL or unregistered tools. Tenant-scope every operation. Never expose tool ids, function names, JSON, or API samples in user-facing replies. Speak only in the UI language with zero language mixing.',
        'max_tool_calls_per_request' => 10,
        'require_confirmation_for_write' => true,
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