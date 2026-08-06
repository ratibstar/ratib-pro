<?php
declare(strict_types=1);

/**
 * Logistics Phase 4 — Driver API tests (no live DB / no Offline Engine changes).
 * Run: php rateb-erp/modules/logistics/tests/run-logistics-phase4-tests.php
 */

$root = dirname(__DIR__, 3);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);
require_once dirname(__DIR__) . '/LogisticsModule.php';
\Rateb\App\Logistics\LogisticsModule::init();

use Rateb\App\Logistics\Contracts\ApiEmployeeResolver;
use Rateb\App\Logistics\Contracts\DriverPermissionChecker;
use Rateb\App\Logistics\Repositories\LogisticsApiIdempotencyRepository;
use Rateb\App\Logistics\Repositories\LogisticsDeliveryProofRepository;
use Rateb\App\Logistics\Repositories\LogisticsDriverLocationRepository;
use Rateb\App\Logistics\Repositories\LogisticsDriverRepository;
use Rateb\App\Logistics\Repositories\LogisticsExpenseRepository;
use Rateb\App\Logistics\Repositories\LogisticsShipmentRepository;
use Rateb\App\Logistics\Repositories\LogisticsStatusHistoryRepository;
use Rateb\App\Logistics\Repositories\LogisticsTripRepository;
use Rateb\App\Logistics\Repositories\LogisticsVehicleRepository;
use Rateb\App\Logistics\Repositories\LogisticsRouteRepository;
use Rateb\App\Logistics\Repositories\LogisticsDeliveryOrderRepository;
use Rateb\App\Logistics\Services\DriverApi\LogisticsDriverContextService;
use Rateb\App\Logistics\Services\DriverApi\LogisticsDriverLocationApiService;
use Rateb\App\Logistics\Services\DriverApi\LogisticsDriverShipmentApiService;
use Rateb\App\Logistics\Services\DriverApi\LogisticsDriverTripApiService;
use Rateb\App\Logistics\Services\DriverApi\LogisticsIdempotencyService;
use Rateb\App\Logistics\Services\LogisticsStatusService;
use Rateb\App\Logistics\Services\ShipmentService;
use Rateb\App\Logistics\Services\TripService;
use Rateb\App\Logistics\Services\LogisticsNotificationService;
use Rateb\App\Logistics\Contracts\CompanyNotifier;

$passed = 0;
$failed = 0;

function logistics4_assert(bool $cond, string $label): void
{
    global $passed, $failed;
    if ($cond) {
        ++$passed;
        echo "PASS: {$label}\n";
    } else {
        ++$failed;
        echo "FAIL: {$label}\n";
    }
}

final class MemStore
{
    /** @var array<int, array<string,mixed>> */
    public array $rows = [];
    private int $seq = 1;

    public function seed(array $row): int
    {
        $id = (int) ($row['id'] ?? 0);
        if ($id < 1) {
            $id = $this->seq++;
            $row['id'] = $id;
        } else {
            $this->seq = max($this->seq, $id + 1);
        }
        $this->rows[$id] = $row;

        return $id;
    }

    public function find(int $id, int $companyId): ?array
    {
        $row = $this->rows[$id] ?? null;
        if ($row === null || (int) ($row['company_id'] ?? 0) !== $companyId) {
            return null;
        }

        return $row;
    }

    public function create(int $companyId, array $data): int
    {
        $data['company_id'] = $companyId;

        return $this->seed($data);
    }

    public function update(int $id, int $companyId, array $data): bool
    {
        if ($this->find($id, $companyId) === null) {
            return false;
        }
        $this->rows[$id] = array_merge($this->rows[$id], $data, ['company_id' => $companyId]);

        return true;
    }

    public function listForCompany(int $companyId, int $limit = 200, int $offset = 0, string $search = ''): array
    {
        $out = [];
        foreach ($this->rows as $row) {
            if ((int) ($row['company_id'] ?? 0) === $companyId) {
                $out[] = $row;
            }
        }

        return array_slice($out, $offset, $limit);
    }

    public function findByKey(int $companyId, int $driverId, string $key): ?array
    {
        foreach ($this->listForCompany($companyId, 500) as $row) {
            if ((int) ($row['driver_id'] ?? 0) === $driverId && (string) ($row['idempotency_key'] ?? '') === $key) {
                return $row;
            }
        }

        return null;
    }

    public function findByShipment(int $shipmentId, int $companyId): ?array
    {
        foreach ($this->listForCompany($companyId, 500) as $row) {
            if ((int) ($row['shipment_id'] ?? 0) === $shipmentId) {
                return $row;
            }
        }

        return null;
    }

    public function listForEntity(int $companyId, string $entityType, int $entityId, int $limit = 100): array
    {
        $out = [];
        foreach ($this->listForCompany($companyId, 500) as $row) {
            if ((string) ($row['entity_type'] ?? '') === $entityType && (int) ($row['entity_id'] ?? 0) === $entityId) {
                $out[] = $row;
            }
        }

        return array_slice($out, 0, $limit);
    }
}

// Typed memory repos
$tripMem = new MemStore();
$shipMem = new MemStore();
$driverMem = new MemStore();
$histMem = new MemStore();
$idemMem = new MemStore();
$locMem = new MemStore();
$proofMem = new MemStore();
$expenseMem = new MemStore();

$tripRepo = new class ($tripMem) extends LogisticsTripRepository {
    public function __construct(private MemStore $m) {}
    public function listForCompany(int $companyId, int $limit = 200, int $offset = 0, string $search = ''): array { return $this->m->listForCompany($companyId, $limit, $offset, $search); }
    public function find(int $id, int $companyId): ?array { return $this->m->find($id, $companyId); }
    public function create(int $companyId, array $data): int { return $this->m->create($companyId, $data); }
    public function update(int $id, int $companyId, array $data): bool { return $this->m->update($id, $companyId, $data); }
};
$shipRepo = new class ($shipMem) extends LogisticsShipmentRepository {
    public function __construct(private MemStore $m) {}
    public function listForCompany(int $companyId, int $limit = 200, int $offset = 0, string $search = ''): array { return $this->m->listForCompany($companyId, $limit, $offset, $search); }
    public function find(int $id, int $companyId): ?array { return $this->m->find($id, $companyId); }
    public function create(int $companyId, array $data): int { return $this->m->create($companyId, $data); }
    public function update(int $id, int $companyId, array $data): bool { return $this->m->update($id, $companyId, $data); }
};
$driverRepo = new class ($driverMem) extends LogisticsDriverRepository {
    public function __construct(private MemStore $m) {}
    public function listForCompany(int $companyId, int $limit = 200, int $offset = 0, string $search = ''): array { return $this->m->listForCompany($companyId, $limit, $offset, $search); }
    public function find(int $id, int $companyId): ?array { return $this->m->find($id, $companyId); }
    public function create(int $companyId, array $data): int { return $this->m->create($companyId, $data); }
};
$histRepo = new class ($histMem) extends LogisticsStatusHistoryRepository {
    public function __construct(private MemStore $m) {}
    public function create(int $companyId, array $data): int { return $this->m->create($companyId, $data); }
    public function listForEntity(int $companyId, string $entityType, int $entityId, int $limit = 100): array { return $this->m->listForEntity($companyId, $entityType, $entityId, $limit); }
    public function find(int $id, int $companyId): ?array { return $this->m->find($id, $companyId); }
};
$idemRepo = new class ($idemMem) extends LogisticsApiIdempotencyRepository {
    public function __construct(private MemStore $m) {}
    public function findByKey(int $companyId, int $driverId, string $key): ?array { return $this->m->findByKey($companyId, $driverId, $key); }
    public function create(int $companyId, array $data): int { return $this->m->create($companyId, $data); }
    public function find(int $id, int $companyId): ?array { return $this->m->find($id, $companyId); }
};
$locRepo = new class ($locMem) extends LogisticsDriverLocationRepository {
    public function __construct(private MemStore $m) {}
    public function create(int $companyId, array $data): int { return $this->m->create($companyId, $data); }
    public function find(int $id, int $companyId): ?array { return $this->m->find($id, $companyId); }
    public function listForCompany(int $companyId, int $limit = 200, int $offset = 0, string $search = ''): array { return $this->m->listForCompany($companyId, $limit, $offset, $search); }
};
$proofRepo = new class ($proofMem) extends LogisticsDeliveryProofRepository {
    public function __construct(private MemStore $m) {}
    public function create(int $companyId, array $data): int { return $this->m->create($companyId, $data); }
    public function update(int $id, int $companyId, array $data): bool { return $this->m->update($id, $companyId, $data); }
    public function findByShipment(int $shipmentId, int $companyId): ?array { return $this->m->findByShipment($shipmentId, $companyId); }
    public function find(int $id, int $companyId): ?array { return $this->m->find($id, $companyId); }
};
$expenseRepo = new class ($expenseMem) extends LogisticsExpenseRepository {
    public function __construct(private MemStore $m) {}
    public function find(int $id, int $companyId): ?array { return $this->m->find($id, $companyId); }
    public function update(int $id, int $companyId, array $data): bool { return $this->m->update($id, $companyId, $data); }
    public function create(int $companyId, array $data): int { return $this->m->create($companyId, $data); }
};

$status = new LogisticsStatusService(
    $histRepo,
    new LogisticsVehicleRepository(),
    $driverRepo,
    new LogisticsRouteRepository(),
    new LogisticsDeliveryOrderRepository(),
    $tripRepo,
    $shipRepo,
    $expenseRepo
);

$tripService = new TripService($tripRepo, new \Rateb\App\Logistics\Services\FleetService(), new \Rateb\App\Logistics\Services\DriverService($driverRepo, $status), new \Rateb\App\Logistics\Services\RouteService(), $status);
$notifier = new class implements CompanyNotifier {
    public array $calls = [];
    public function notifyCompany(int $companyId, string $title, string $message, string $type = 'info', ?string $triggerType = null, ?string $entityType = null, ?int $entityId = null): int {
        $this->calls[] = compact('companyId', 'title', 'message', 'type', 'triggerType', 'entityType', 'entityId');
        return count($this->calls);
    }
};
$shipmentService = new ShipmentService($shipRepo, $proofRepo, new \Rateb\App\Logistics\Services\DeliveryOrderService(), $tripService, $status, new LogisticsNotificationService($notifier));

$tripApi = new LogisticsDriverTripApiService($tripRepo, $tripService);
$shipApi = new LogisticsDriverShipmentApiService($shipRepo, $tripRepo, $proofRepo, $shipmentService, $status);
$locApi = new LogisticsDriverLocationApiService($locRepo, $tripRepo, $shipRepo);
$idem = new LogisticsIdempotencyService($idemRepo);

$perms = new class implements DriverPermissionChecker {
    public bool $allow = true;
    public function canDrive(int $userId, int $companyId): bool { return $this->allow; }
};
$employees = new class implements ApiEmployeeResolver {
    public array $employee = ['id' => 8, 'company_id' => 10, 'name' => 'Driver A', 'user_id' => 100];
    public function resolveCurrentEmployee(?int $userId, ?int $companyId): array {
        if ((int) $userId < 1) {
            return ['status' => 401, 'body' => ['success' => false, 'code' => 'unauthorized']];
        }
        if ((int) ($this->employee['company_id'] ?? 0) !== (int) $companyId) {
            return ['status' => 403, 'body' => ['success' => false, 'code' => 'tenant_mismatch', 'message' => 'Employee does not belong to this company']];
        }
        return ['status' => 200, 'body' => ['success' => true, 'employee' => $this->employee]];
    }
};

$driverRepo->create(10, ['id' => 1, 'employee_id' => 8, 'status' => 'active', 'license_number' => 'L1']);
$contextSvc = new LogisticsDriverContextService($driverRepo, $employees, $perms);

// --- driver access ---
$ok = $contextSvc->resolve(100, 10);
logistics4_assert(($ok['ok'] ?? false) === true, 'driver access granted with logistics.driver permission');
$perms->allow = false;
$denied = $contextSvc->resolve(100, 10);
logistics4_assert(($denied['ok'] ?? true) === false && (int) ($denied['status'] ?? 0) === 403, 'driver access denied without permission');
$perms->allow = true;

// --- tenant isolation ---
$employees->employee = ['id' => 8, 'company_id' => 99, 'name' => 'Other', 'user_id' => 100];
$cross = $contextSvc->resolve(100, 10);
logistics4_assert(($cross['ok'] ?? true) === false, 'tenant isolation: employee from other company rejected');
$employees->employee = ['id' => 8, 'company_id' => 10, 'name' => 'Driver A', 'user_id' => 100];
$ctx = $contextSvc->resolve(100, 10)['context'];

// seed trip + shipment for company 10
$tripId = $tripRepo->create(10, [
    'driver_id' => 1,
    'vehicle_id' => 1,
    'status' => 'assigned',
    'origin' => 'Riyadh',
    'destination' => 'Jeddah',
]);
$otherTrip = $tripRepo->create(10, [
    'driver_id' => 99,
    'status' => 'assigned',
    'origin' => 'X',
    'destination' => 'Y',
]);
$shipmentId = $shipRepo->create(10, [
    'trip_id' => $tripId,
    'tracking_number' => 'TRK-D1',
    'status' => 'shipped',
]);
$foreignShipment = $shipRepo->create(11, [
    'trip_id' => 1,
    'tracking_number' => 'TRK-OTHER',
    'status' => 'shipped',
    'company_id' => 11,
]);
// force company on foreign
$shipMem->rows[$foreignShipment]['company_id'] = 11;

$list = $tripApi->listTrips($ctx);
logistics4_assert(count($list['body']['data']['trips'] ?? []) === 1, 'trip list only owned trips');
logistics4_assert((int) ($list['body']['data']['trips'][0]['id'] ?? 0) === $tripId, 'owned trip id');

// --- trip lifecycle ---
$start = $tripApi->startTrip($ctx, $tripId);
logistics4_assert(($start['body']['success'] ?? false) === true, 'trip start ok');
logistics4_assert(($tripRepo->find($tripId, 10)['status'] ?? '') === 'started', 'trip status started');
$complete = $tripApi->completeTrip($ctx, $tripId);
logistics4_assert(($complete['body']['success'] ?? false) === true, 'trip complete ok');
logistics4_assert(($tripRepo->find($tripId, 10)['status'] ?? '') === 'completed', 'trip status completed');

$otherStart = false;
try {
    $tripApi->requireOwnedTrip($ctx, $otherTrip);
} catch (\Throwable $e) {
    $otherStart = true;
}
logistics4_assert($otherStart, 'cannot access another driver trip');

// --- delivery lifecycle ---
// reset a deliverable shipment on a started-owned trip
$trip2 = $tripRepo->create(10, ['driver_id' => 1, 'vehicle_id' => 1, 'status' => 'started', 'origin' => 'A', 'destination' => 'B']);
$ship2 = $shipRepo->create(10, ['trip_id' => $trip2, 'tracking_number' => 'TRK-D2', 'status' => 'shipped']);
$deliver = $shipApi->deliver($ctx, $ship2, [
    'receiver_name' => 'Ali',
    'signature_file' => 'sig.png',
    'photo_file' => 'pod.jpg',
    'gps_lat' => 24.7,
    'gps_long' => 46.7,
]);
logistics4_assert(($deliver['body']['success'] ?? false) === true, 'delivery ok');
logistics4_assert(($shipRepo->find($ship2, 10)['status'] ?? '') === 'delivered', 'shipment delivered');
$pod = $shipApi->uploadPod($ctx, $ship2, ['receiver_name' => 'Ali 2', 'signature_file' => 'sig2.png']);
logistics4_assert(($pod['body']['success'] ?? false) === true, 'POD upload ok');
logistics4_assert($proofRepo->findByShipment($ship2, 10) !== null, 'POD stored');

$foreignDeliver = $shipApi->deliver($ctx, $foreignShipment, []);
logistics4_assert(($foreignDeliver['status'] ?? 0) === 404, 'tenant isolation: foreign shipment deliver blocked');

// --- location ---
$loc = $locApi->updateLocation($ctx, [
    'gps_lat' => 24.71,
    'gps_long' => 46.68,
    'trip_id' => $trip2,
    'client_timestamp' => '2026-08-06T18:00:00+03:00',
]);
logistics4_assert(($loc['body']['success'] ?? false) === true, 'location update ok');
logistics4_assert(count($locMem->rows) === 1, 'location persisted');

// --- duplicate / idempotency ---
$payload = [
    'idempotency_key' => 'idem-loc-1',
    'client_timestamp' => '2026-08-06T18:01:00+03:00',
    'gps_lat' => 24.72,
    'gps_long' => 46.69,
    'trip_id' => $trip2,
];
$first = $idem->run(10, 1, 'location.update', $payload, fn () => $locApi->updateLocation($ctx, $payload));
$second = $idem->run(10, 1, 'location.update', $payload, fn () => $locApi->updateLocation($ctx, $payload));
logistics4_assert(($first['replay'] ?? true) === false, 'first idempotent call not replay');
logistics4_assert(($second['replay'] ?? false) === true, 'duplicate request replayed');
logistics4_assert(($second['body']['idempotent_replay'] ?? false) === true, 'replay flag set');
logistics4_assert(count($locMem->rows) === 2, 'duplicate did not create third location (only first+setup)');

$conflictPayload = $payload;
$conflictPayload['gps_lat'] = 25.0;
$conflict = $idem->run(10, 1, 'location.update', $conflictPayload, fn () => $locApi->updateLocation($ctx, $conflictPayload));
logistics4_assert((int) ($conflict['status'] ?? 0) === 409, 'idempotency conflict on different payload');

// routes wiring
$apiRoutes = (string) file_get_contents(dirname(__DIR__) . '/routes/logistics-api.php');
foreach ([
    '/api/v1/logistics/driver/trips',
    'trips/{id}/start',
    'trips/{id}/complete',
    'driver/location',
    'shipments/{id}/deliver',
    'shipments/{id}/pod',
    'ApiAuthMiddleware',
] as $needle) {
    logistics4_assert(str_contains($apiRoutes, $needle), 'routes contain ' . $needle);
}
$apiMod = (string) file_get_contents($root . '/routes/modules/api.php');
logistics4_assert(str_contains($apiMod, 'logistics-api.php'), 'api module loads logistics-api');
$migration = (string) file_get_contents($root . '/migrations/226_logistics_driver_api.sql');
logistics4_assert(str_contains($migration, 'rateb_logistics_api_idempotency'), 'migration 226 idempotency table');
logistics4_assert(str_contains($migration, 'rateb_logistics_driver_locations'), 'migration 226 locations table');

echo "\nLogistics Phase 4 tests: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
