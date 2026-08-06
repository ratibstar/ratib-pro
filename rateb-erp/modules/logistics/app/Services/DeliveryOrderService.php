<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Logistics\Policies\LogisticsStatusPolicy;
use Rateb\App\Logistics\Repositories\LogisticsDeliveryOrderRepository;

final class DeliveryOrderService
{
    public function __construct(
        private LogisticsDeliveryOrderRepository $orders = new LogisticsDeliveryOrderRepository(),
        private LogisticsStatusService $status = new LogisticsStatusService(),
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function listForCompany(int $companyId, int $limit = 200, int $offset = 0): array
    {
        return $this->orders->listForCompany($companyId, $limit, $offset);
    }

    public function countForCompany(int $companyId): int
    {
        return $this->orders->countForCompany($companyId);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $companyId): ?array
    {
        return $this->orders->find($id, $companyId);
    }

    /** @param array<string, mixed> $data */
    public function create(int $companyId, array $data): int
    {
        $this->assertCompany($companyId);
        TenantContext::setCompanyId($companyId);
        $payload = $this->normalize($companyId, $data, true);
        $payload['created_by'] = $this->userId();

        return $this->orders->create($companyId, $payload);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, int $companyId, array $data): bool
    {
        $existing = $this->require($id, $companyId);
        $payload = $this->normalize($companyId, $data, false);
        $payload['updated_by'] = $this->userId();

        $newStatus = (string) ($payload['status'] ?? $existing['status'] ?? 'draft');
        $oldStatus = (string) ($existing['status'] ?? 'draft');
        unset($payload['status']);

        $ok = $this->orders->update($id, $companyId, $payload);
        if ($ok && $newStatus !== $oldStatus) {
            $this->status->transition(
                $companyId,
                LogisticsStatusPolicy::ENTITY_DELIVERY_ORDER,
                $id,
                $newStatus,
                'delivery_order_update'
            );
        }

        return $ok;
    }

    public function transition(int $id, int $companyId, string $toStatus, ?string $reason = null): array
    {
        $this->require($id, $companyId);

        return $this->status->transition(
            $companyId,
            LogisticsStatusPolicy::ENTITY_DELIVERY_ORDER,
            $id,
            $toStatus,
            $reason
        );
    }

    public function delete(int $id, int $companyId): bool
    {
        $row = $this->require($id, $companyId);
        if (!in_array((string) ($row['status'] ?? ''), ['draft', 'cancelled'], true)) {
            throw new \RuntimeException(__('logistics_delete_blocked'));
        }

        return $this->orders->delete($id, $companyId);
    }

    /** @return array<int, array{value:int,label:string}> */
    public function options(int $companyId): array
    {
        $out = [];
        foreach ($this->listForCompany($companyId, 500) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $out[] = [
                'value' => $id,
                'label' => (string) ($row['delivery_no'] ?? ('#' . $id)) . ' [' . (string) ($row['status'] ?? '') . ']',
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function normalize(int $companyId, array $data, bool $isCreate): array
    {
        $deliveryNo = strtoupper(trim((string) ($data['delivery_no'] ?? '')));
        if ($deliveryNo === '') {
            $deliveryNo = 'DO-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        }
        $status = (string) ($data['status'] ?? 'draft');
        if ($isCreate) {
            $status = 'draft';
        } elseif (!in_array($status, LogisticsStatusPolicy::statuses(LogisticsStatusPolicy::ENTITY_DELIVERY_ORDER), true)) {
            $status = 'draft';
        }

        return [
            'company_id' => $companyId,
            'branch_id' => ((int) ($data['branch_id'] ?? 0)) ?: null,
            'customer_id' => ((int) ($data['customer_id'] ?? 0)) ?: null,
            'order_id' => ((int) ($data['order_id'] ?? 0)) ?: null,
            'order_ref' => trim((string) ($data['order_ref'] ?? '')) ?: null,
            'delivery_no' => $deliveryNo,
            'pickup_location' => trim((string) ($data['pickup_location'] ?? '')) ?: null,
            'delivery_location' => trim((string) ($data['delivery_location'] ?? '')) ?: null,
            'planned_date' => trim((string) ($data['planned_date'] ?? '')) ?: null,
            'status' => $status,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ];
    }

    /** @return array<string, mixed> */
    private function require(int $id, int $companyId): array
    {
        $row = $this->find($id, $companyId);
        if ($row === null) {
            throw new \RuntimeException(__('no_records'));
        }

        return $row;
    }

    private function assertCompany(int $companyId): void
    {
        if ($companyId < 1) {
            throw new \RuntimeException(__('select_company_ops'));
        }
    }

    private function userId(): ?int
    {
        $id = (int) (SessionManager::get('rateb_user_id') ?? 0);

        return $id > 0 ? $id : null;
    }
}
