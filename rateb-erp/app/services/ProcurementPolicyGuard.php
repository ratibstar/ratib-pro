<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Auth;
use Rateb\App\Core\TenantContext;

/**
 * Procurement Policy Guard
 * Enforces the exact 8-step security order for every tool invocation.
 */
final class ProcurementPolicyGuard
{
    /**
     * @param array{
     *     tool: string,
     *     arguments: array,
     *     request_company_id: int|null,
     *     request_id: string
     * } $call
     * @param ProcurementAgentContext $ctx
     * @return array{
     *     allowed: bool,
     *     error_code: string|null,
     *     policy_checks: array<string, bool>,
     *     audit_data: array
     * }
     */
    public static function checkAndExecute(array $call, ProcurementAgentContext $ctx): array
    {
        $toolName = $call['tool'] ?? '';
        $arguments = $call['arguments'] ?? [];
        $requestCompanyId = $call['request_company_id'] ?? null;
        $requestId = $call['request_id'] ?? '';

        $policyChecks = [];
        $auditData = [
            'tool' => $toolName,
            'arguments' => $arguments,
            'request_company_id' => $requestCompanyId,
            'request_id' => $requestId,
        ];

        // Step 1: Auth
        $policyChecks['auth'] = Auth::check();
        if (!$policyChecks['auth']) {
            return self::deny('unauthenticated', $policyChecks, $auditData, 'Authentication required');
        }

        // Step 2: Tenant Context
        $policyChecks['tenant_context'] = $ctx->verifyCompanyId($requestCompanyId);
        if (!$policyChecks['tenant_context']) {
            return self::deny('tenant_mismatch', $policyChecks, $auditData, 'Request company_id does not match session company_id');
        }

        // Step 3: Tool Allowlist
        $policyChecks['tool_allowlist'] = ProcurementToolRegistry::isAllowed($toolName);
        if (!$policyChecks['tool_allowlist']) {
            return self::deny('tool_not_allowed', $policyChecks, $auditData, "Tool not in allowlist: {$toolName}");
        }

        // Step 4: Module Entitlement
        $tool = ProcurementToolRegistry::getTool($toolName);
        $module = $tool['module'] ?? '';
        $policyChecks['module_entitlement'] = $module !== '' && $ctx->moduleEnabled($module);
        if (!$policyChecks['module_entitlement']) {
            return self::deny('module_not_entitled', $policyChecks, $auditData, "Module not enabled for company: {$module}");
        }

        // Step 5: RBAC Permission
        $permission = $tool['permission'] ?? '';
        $policyChecks['rbac_permission'] = $permission !== '' && $ctx->can($permission);
        if (!$policyChecks['rbac_permission']) {
            return self::deny('permission_denied', $policyChecks, $auditData, "Permission required: {$permission}");
        }

        // Step 6: Sensitive/Approval Policy
        $policyChecks['approval_policy'] = self::checkApprovalPolicy($toolName, $arguments, $ctx);
        if (!$policyChecks['approval_policy']) {
            return self::deny('approval_policy_violation', $policyChecks, $auditData, 'Approval policy violation for this operation');
        }

        // Step 6b: Write tools require an explicit confirmation flag set by the agent runtime
        // (never trust LLM-provided confirmation arguments).
        $isWrite = !empty($tool['write']);
        $policyChecks['write_confirmation'] = !$isWrite || !empty($call['write_confirmed']);
        if (!$policyChecks['write_confirmation']) {
            return self::deny('write_confirmation_required', $policyChecks, $auditData, 'Write tool requires user confirmation');
        }

        // Step 7: Execute (handled by caller)
        $policyChecks['execute'] = true;

        // Step 8: Audit (handled by caller)
        $policyChecks['audit'] = true;

        return [
            'allowed' => true,
            'error_code' => null,
            'policy_checks' => $policyChecks,
            'audit_data' => $auditData,
        ];
    }

    /**
     * Check approval-specific policies for write tools
     */
    private static function checkApprovalPolicy(string $toolName, array $arguments, ProcurementAgentContext $ctx): bool
    {
        if ($toolName === 'submit_purchase_request') {
            // Only allow submit if PR is in draft status
            $id = $arguments['id'] ?? 0;
            if ($id < 1) {
                return false;
            }
            // The actual status check is delegated to WorkflowSubmissionService
            // which will verify the PR exists and is in draft status
            return true;
        }

        if ($toolName === 'create_draft_purchase_request') {
            // Draft creation is always allowed if permissions pass
            return true;
        }

        // Read tools have no additional approval policy
        return true;
    }

    private static function deny(string $errorCode, array $policyChecks, array $auditData, string $message): array
    {
        return [
            'allowed' => false,
            'error_code' => $errorCode,
            'policy_checks' => $policyChecks,
            'audit_data' => array_merge($auditData, ['deny_reason' => $message]),
        ];
    }
}