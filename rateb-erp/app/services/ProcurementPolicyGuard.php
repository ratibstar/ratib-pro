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

        // Step 1: Auth (session user OR trusted API user bound in TenantContext)
        $apiUid = TenantContext::apiUserId();
        $policyChecks['auth'] = Auth::check()
            || ($ctx->userId > 0 && $apiUid !== null && (int) $apiUid === $ctx->userId);
        if (!$policyChecks['auth']) {
            return self::deny('unauthenticated', $policyChecks, $auditData, 'Authentication required');
        }

        // Step 2: Tenant Context (also reject ctx/company drift vs TenantContext when set)
        $tenantCompany = TenantContext::companyId();
        $policyChecks['tenant_context'] = $ctx->verifyCompanyId($requestCompanyId)
            && $ctx->companyId > 0
            && ($tenantCompany === null || (int) $tenantCompany === $ctx->companyId);
        if (!$policyChecks['tenant_context']) {
            return self::deny('tenant_mismatch', $policyChecks, $auditData, 'Request company_id does not match session company_id');
        }

        // Step 3: Tool Allowlist (unified active domains — never hardcode per-domain registries)
        $policyChecks['tool_allowlist'] = ErpToolRegistry::isAllowed($toolName);
        if (!$policyChecks['tool_allowlist']) {
            return self::deny('tool_not_allowed', $policyChecks, $auditData, "Tool not in allowlist: {$toolName}");
        }

        // Step 4: Module Entitlement
        $tool = ErpToolRegistry::getTool($toolName);
        if ($tool === null) {
            return self::deny('tool_not_allowed', $policyChecks, $auditData, "Tool metadata missing: {$toolName}");
        }
        $module = (string) ($tool['module'] ?? '');
        $domainId = ErpDomainRegistry::domainForTool($toolName) ?? '';
        $policyChecks['module_scope'] = $module !== '' && in_array($module, ErpDomainRegistry::allowedModuleKeys(), true);
        if (!$policyChecks['module_scope']) {
            return self::deny('tool_not_allowed', $policyChecks, $auditData, 'Tool outside ERP agent domain scope');
        }
        if ($domainId !== '') {
            $policyChecks['module_entitlement'] = ErpDomainRegistry::isDomainEntitled($domainId, $ctx);
        } elseif ($module === 'dashboard') {
            $policyChecks['module_entitlement'] = $ctx->moduleEnabled('dashboard') || $ctx->can('ai.view');
        } else {
            $policyChecks['module_entitlement'] = $module !== '' && $ctx->moduleEnabled($module);
        }
        if (!$policyChecks['module_entitlement']) {
            return self::deny('module_not_entitled', $policyChecks, $auditData, "Module not enabled for company: {$module}");
        }

        // Step 5: RBAC Permission
        $permission = (string) ($tool['permission'] ?? '');
        $rbac = $permission !== '' && $ctx->can($permission);
        $isWrite = !empty($tool['write']);
        // Read tools: AI operators with ai.view may query any entitled domain pack.
        if (!$rbac && !$isWrite && ($ctx->can('ai.view') || $ctx->isSuperAdmin)) {
            $rbac = true;
        }
        // Executive tools: allow dashboard.view OR ai.view (RATEB AI access).
        if (!$rbac && ExecutiveToolRegistry::isAllowed($toolName)) {
            $rbac = $ctx->can('ai.view') || $ctx->can('reports.view') || $ctx->can('accounting.view');
        }
        $policyChecks['rbac_permission'] = $rbac;
        if (!$policyChecks['rbac_permission']) {
            return self::deny('permission_denied', $policyChecks, $auditData, "Permission required: {$permission}");
        }

        // Step 5b: Parameter sufficiency (prefer owning registry via domain metadata)
        $sufficient = true;
        $registry = '';
        if ($domainId !== '') {
            $meta = ErpDomainRegistry::resolve($domainId);
            $registry = (string) ($meta['tool_registry'] ?? '');
        }
        if ($registry !== '' && class_exists($registry) && method_exists($registry, 'hasSufficientParameters')) {
            $sufficient = (bool) $registry::hasSufficientParameters($toolName, $arguments);
        }
        $policyChecks['parameter_sufficiency'] = $sufficient;
        if (!$policyChecks['parameter_sufficiency']) {
            return self::deny('insufficient_parameters', $policyChecks, $auditData, 'Required parameters are missing or invalid');
        }

        // Step 6: Sensitive/Approval Policy
        $policyChecks['approval_policy'] = self::checkApprovalPolicy($toolName, $arguments, $ctx);
        if (!$policyChecks['approval_policy']) {
            return self::deny('approval_policy_violation', $policyChecks, $auditData, 'Approval policy violation for this operation');
        }

        // Step 6b: Write tools require an explicit confirmation flag set by the agent runtime
        // (never trust LLM-provided confirmation arguments).
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
            $id = $arguments['id'] ?? 0;
            if ($id < 1) {
                return false;
            }
            return true;
        }

        if ($toolName === 'submit_journal_for_approval') {
            $id = (int) ($arguments['id'] ?? 0);
            return $id > 0;
        }

        if ($toolName === 'create_draft_purchase_request'
            || $toolName === 'create_inventory_item'
            || $toolName === 'create_employee'
        ) {
            return true;
        }

        if ($toolName === 'update_purchase_request' || $toolName === 'cancel_purchase_request') {
            $id = (int) ($arguments['id'] ?? 0);
            return $id > 0;
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