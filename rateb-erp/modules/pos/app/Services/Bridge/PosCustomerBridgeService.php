<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Bridge;

use Rateb\App\Models\Customer;

/** Customer/CRM bridge — reuse rateb_customers master. */
final class PosCustomerBridgeService
{
    /** @return array<string, mixed>|null */
    public function findById(int $customerId): ?array
    {
        if ($customerId < 1) {
            return null;
        }
        $row = (new Customer())->find($customerId);
        return $row ? $this->formatCustomer($row) : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function search(string $query, int $limit = 20): array
    {
        $term = trim($query);
        if ($term === '') {
            return [];
        }
        $safeLimit = max(1, min(50, $limit));
        $rows = (new Customer())->all($safeLimit, 0, ['is_active' => 1], $term);
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->formatCustomer($row);
        }
        return $out;
    }

    /** @return array<string, mixed> */
    public function quickCreate(string $name, ?string $phone, int $branchId): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new \RuntimeException(__('invalid_request'));
        }
        $companyId = (int) (\Rateb\App\Core\TenantContext::companyId() ?? 0);
        if ($companyId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $code = 'POS-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $id = (new Customer())->create([
            'company_id' => $companyId,
            'branch_id' => $branchId > 0 ? $branchId : null,
            'code' => $code,
            'name' => $name,
            'phone' => trim((string) $phone) ?: null,
            'is_active' => 1,
        ]);
        $row = (new Customer())->find((int) $id);
        if (!$row) {
            throw new \RuntimeException(__('invalid_request'));
        }
        return $this->formatCustomer($row);
    }

    /** @param array<string, mixed> $row */
    private function formatCustomer(array $row): array
    {
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '' && !empty($row['name_ar'])) {
            $name = trim((string) $row['name_ar']);
        }
        return [
            'id' => (int) ($row['id'] ?? 0),
            'code' => (string) ($row['code'] ?? ''),
            'name' => $name,
            'phone' => (string) ($row['phone'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'tax_id' => (string) ($row['tax_id'] ?? ''),
            'price_group_id' => (int) ($row['price_group_id'] ?? 0) ?: null,
        ];
    }
}
