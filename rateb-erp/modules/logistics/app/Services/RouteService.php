<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Logistics\Policies\LogisticsStatusPolicy;
use Rateb\App\Logistics\Repositories\LogisticsRouteRepository;

final class RouteService
{
    public function __construct(
        private LogisticsRouteRepository $routes = new LogisticsRouteRepository(),
        private LogisticsStatusService $status = new LogisticsStatusService(),
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function listForCompany(int $companyId, int $limit = 200, int $offset = 0): array
    {
        return $this->routes->listForCompany($companyId, $limit, $offset);
    }

    public function countForCompany(int $companyId): int
    {
        return $this->routes->countForCompany($companyId);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $companyId): ?array
    {
        return $this->routes->find($id, $companyId);
    }

    /** @param array<string, mixed> $data */
    public function create(int $companyId, array $data): int
    {
        $this->assertCompany($companyId);
        TenantContext::setCompanyId($companyId);
        $payload = $this->normalize($companyId, $data);
        $payload['created_by'] = $this->userId();

        return $this->routes->create($companyId, $payload);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, int $companyId, array $data): bool
    {
        $existing = $this->require($id, $companyId);
        $payload = $this->normalize($companyId, $data);
        $payload['updated_by'] = $this->userId();

        $newStatus = (string) ($payload['status'] ?? $existing['status'] ?? 'active');
        $oldStatus = (string) ($existing['status'] ?? 'active');
        unset($payload['status']);

        $ok = $this->routes->update($id, $companyId, $payload);
        if ($ok && $newStatus !== $oldStatus) {
            $this->status->transition(
                $companyId,
                LogisticsStatusPolicy::ENTITY_ROUTE,
                $id,
                $newStatus,
                'route_update'
            );
        }

        return $ok;
    }

    public function delete(int $id, int $companyId): bool
    {
        $this->require($id, $companyId);

        return $this->routes->delete($id, $companyId);
    }

    /** @return array<int, array{value:int,label:string}> */
    public function options(int $companyId): array
    {
        $out = [];
        foreach ($this->listForCompany($companyId, 500) as $row) {
            if ((string) ($row['status'] ?? '') !== 'active') {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $label = (string) ($row['code'] ?? '') . ' — ' . (string) ($row['name'] ?? '');
            $out[] = ['value' => $id, 'label' => $label];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function normalize(int $companyId, array $data): array
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $name = trim((string) ($data['name'] ?? ''));
        if ($code === '' || $name === '') {
            throw new \RuntimeException(__('logistics_route_required'));
        }
        $status = (string) ($data['status'] ?? 'active');
        if (!in_array($status, LogisticsStatusPolicy::statuses(LogisticsStatusPolicy::ENTITY_ROUTE), true)) {
            $status = 'active';
        }

        return [
            'company_id' => $companyId,
            'branch_id' => ((int) ($data['branch_id'] ?? 0)) ?: null,
            'code' => $code,
            'name' => $name,
            'name_ar' => trim((string) ($data['name_ar'] ?? '')) ?: null,
            'origin' => trim((string) ($data['origin'] ?? '')) ?: null,
            'destination' => trim((string) ($data['destination'] ?? '')) ?: null,
            'distance_km' => isset($data['distance_km']) && $data['distance_km'] !== '' ? (float) $data['distance_km'] : null,
            'estimated_minutes' => isset($data['estimated_minutes']) && $data['estimated_minutes'] !== '' ? (int) $data['estimated_minutes'] : null,
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
