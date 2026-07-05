<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Services\FormLookupService;

/** Form lookups for POS admin — delegates to ERP FormLookupService. */
final class PosFormLookupService
{
    /** @param array<int, array<string, mixed>> $fields @return array<string, list<array{value: string|int, label: string}>> */
    public function forFields(array $fields): array
    {
        return (new FormLookupService())->forFields($fields);
    }

    /** @return list<array{value: int, label: string}> */
    public function activeTerminals(int $companyId): array
    {
        return $this->terminalOptions(true, $companyId);
    }

    /** @return list<array{value: int, label: string}> */
    public function terminalOptions(bool $activeOnly = false, ?int $companyId = null): array
    {
        $companyId = $companyId ?? (int) (\Rateb\App\Core\TenantContext::companyId() ?? 0);
        if ($companyId < 1) {
            return [];
        }
        \Rateb\App\Core\TenantContext::setCompanyId($companyId);
        $sql = 'SELECT id, code, name, branch_id FROM rateb_pos_terminals WHERE company_id = :cid';
        $params = ['cid' => $companyId];
        if ($activeOnly) {
            $sql .= ' AND status = :st';
            $params['st'] = 'active';
        }
        $sql .= ' ORDER BY name';
        $rows = (new \Rateb\App\Pos\Models\PosTerminal())->query($sql, $params);

        if (function_exists('rateb_bootstrap_branch_context')) {
            rateb_bootstrap_branch_context($companyId);
        }
        $scopeBranchIds = \Rateb\App\Core\BranchContext::effectiveFilterIds();
        if ($scopeBranchIds === [] && !\Rateb\App\Core\BranchContext::accessAll()) {
            $scopeBranchIds = \Rateb\App\Core\BranchContext::allowedIds();
        }

        $out = [];
        foreach ($rows as $row) {
            $termBranch = (int) ($row['branch_id'] ?? 0);
            if ($scopeBranchIds !== [] && $termBranch > 0 && !in_array($termBranch, $scopeBranchIds, true)) {
                continue;
            }
            $label = trim((string) ($row['code'] ?? '') . ' — ' . (string) ($row['name'] ?? ''));
            $out[] = ['value' => (int) $row['id'], 'label' => $label];
        }
        return $out;
    }
}
