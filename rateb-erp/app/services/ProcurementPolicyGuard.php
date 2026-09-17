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

        // Step 3: Tool Allowlist (unified active domains)
        $policyChecks['tool_allowlist'] = ErpToolRegistry::isAllowed($toolName)
            || ProcurementToolRegistry::isAllowed($toolName)
            || InventoryToolRegistry::isAllowed($toolName)
            || SupplierToolRegistry::isAllowed($toolName)
            || SalesToolRegistry::isAllowed($toolName)
            || CrmToolRegistry::isAllowed($toolName)
            || LogisticsToolRegistry::isAllowed($toolName)
            || AccountingToolRegistry::isAllowed($toolName)
            || ExecutiveToolRegistry::isAllowed($toolName);
        if (!$policyChecks['tool_allowlist']) {
            return self::deny('tool_not_allowed', $policyChecks, $auditData, "Tool not in allowlist: {$toolName}");
        }

        // Step 4: Module Entitlement
        $tool = ErpToolRegistry::getTool($toolName)
            ?? ProcurementToolRegistry::getTool($toolName)
            ?? InventoryToolRegistry::getTool($toolName)
            ?? SupplierToolRegistry::getTool($toolName)
            ?? SalesToolRegistry::getTool($toolName)
            ?? CrmToolRegistry::getTool($toolName)
            ?? LogisticsToolRegistry::getTool($toolName)
            ?? AccountingToolRegistry::getTool($toolName)
            ?? ExecutiveToolRegistry::getTool($toolName);
        $module = (string) ($tool['module'] ?? '');
        $policyChecks['module_scope'] = in_array($module, ['procurement', 'suppliers', 'inventory', 'pos', 'crm', 'logistics', 'accounting', 'dashboard'], true);
        if (!$policyChecks['module_scope']) {
            return self::deny('tool_not_allowed', $policyChecks, $auditData, 'Tool outside ERP agent domain scope');
        }
        if ($module === 'dashboard') {
            // Executive visibility: entitled if dashboard enabled OR any active ERP domain module is enabled.
            $entitled = $ctx->moduleEnabled('dashboard');
            if (!$entitled) {
                foreach (ErpDomainRegistry::activeDomainIds() as $domainId) {
                    if ($domainId === ErpDomainRegistry::DOMAIN_EXECUTIVE) {
                        continue;
                    }
                    $meta = ErpDomainRegistry::resolve($domainId);
                    if ($meta !== null && $ctx->moduleEnabled((string) $meta['module'])) {
                        $entitled = true;
                        break;
                    }
                }
            }
            $policyChecks['module_entitlement'] = $entitled;
        } else {
            $policyChecks['module_entitlement'] = $module !== '' && $ctx->moduleEnabled($module);
        }
        if (!$policyChecks['module_entitlement']) {
            return self::deny('module_not_entitled', $policyChecks, $auditData, "Module not enabled for company: {$module}");
        }

        // Step 5: RBAC Permission
        $permission = $tool['permission'] ?? '';
        $rbac = $permission !== '' && $ctx->can($permission);
        // Executive tools: allow dashboard.view OR ai.view (RATEB AI access).
        if (!$rbac && ExecutiveToolRegistry::isAllowed($toolName)) {
            $rbac = $ctx->can('ai.view') || $ctx->can('reports.view') || $ctx->can('accounting.view');
        }
        $policyChecks['rbac_permission'] = $rbac;
        if (!$policyChecks['rbac_permission']) {
            return self::deny('permission_denied', $policyChecks, $auditData, "Permission required: {$permission}");
        }

        // Step 5b: Parameter sufficiency
        $sufficient = true;
        if (ExecutiveToolRegistry::isAllowed($toolName)) {
            $sufficient = ExecutiveToolRegistry::hasSufficientParameters($toolName, $arguments);
        } elseif (AccountingToolRegistry::isAllowed($toolName)) {
            $sufficient = AccountingToolRegistry::hasSufficientParameters($toolName, $arguments);
        } elseif (LogisticsToolRegistry::isAllowed($toolName)) {
            $sufficient = LogisticsToolRegistry::hasSufficientParameters($toolName, $arguments);
        } elseif (CrmToolRegistry::isAllowed($toolName)) {
            $sufficient = CrmToolRegistry::hasSufficientParameters($toolName, $arguments);
        } elseif (SalesToolRegistry::isAllowed($toolName)) {
            $sufficient = SalesToolRegistry::hasSufficientParameters($toolName, $arguments);
        } elseif (SupplierToolRegistry::isAllowed($toolName)) {
            $sufficient = SupplierToolRegistry::hasSufficientParameters($toolName, $arguments);
        } elseif (InventoryToolRegistry::isAllowed($toolName)) {
            $sufficient = InventoryToolRegistry::hasSufficientParameters($toolName, $arguments);
        } elseif (ProcurementToolRegistry::isAllowed($toolName)) {
            $sufficient = ProcurementToolRegistry::hasSufficientParameters($toolName, $arguments);
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

        if ($toolName === 'create_draft_purchase_request') {
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