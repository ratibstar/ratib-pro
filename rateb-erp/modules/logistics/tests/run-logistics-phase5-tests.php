<?php
declare(strict_types=1);

/**
 * Logistics Phase 5 — Portal + Reports + Dashboard MVP tests (no live DB).
 * Run: php rateb-erp/modules/logistics/tests/run-logistics-phase5-tests.php
 */

$root = dirname(__DIR__, 3);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);
require_once dirname(__DIR__) . '/LogisticsModule.php';
\Rateb\App\Logistics\LogisticsModule::init();

use Rateb\App\Logistics\LogisticsModule;
use Rateb\App\Logistics\Policies\LogisticsStatusPolicy;
use Rateb\App\Logistics\Repositories\LogisticsDeliveryProofRepository;
use Rateb\App\Logistics\Repositories\LogisticsDriverRepository;
use Rateb\App\Logistics\Repositories\LogisticsExpenseRepository;
use Rateb\App\Logistics\Repositories\LogisticsShipmentRepository;
use Rateb\App\Logistics\Repositories\LogisticsStatusHistoryRepository;
use Rateb\App\Logistics\Repositories\LogisticsTripRepository;
use Rateb\App\Logistics\Repositories\LogisticsVehicleRepository;
use Rateb\App\Logistics\Services\CustomerTrackingService;
use Rateb\App\Logistics\Services\LogisticsReportsService;

$passed = 0;
$failed = 0;

function logistics5_assert(bool $cond, string $label): void
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

final class Mem5
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

$shipMem = new Mem5();
$histMem = new Mem5();
$proofMem = new Mem5();
$tripMem = new Mem5();
$driverMem = new Mem5();
$vehMem = new Mem5();
$expMem = new Mem5();

$shipRepo = new class ($shipMem) extends LogisticsShipmentRepository {
    public function __construct(private Mem5 $m) {}
    public function listForCompany(int $companyId, int $limit = 200, int $offset = 0, string $search = ''): array { return $this->m->listForCompany($companyId, $limit, $offset, $search); }
    public function find(int $id, int $companyId): ?array { return $this->m->find($id, $companyId); }
    public function create(int $companyId, array $data): int { return $this->m->create($companyId, $data); }
};
$histRepo = new class ($histMem) extends LogisticsStatusHistoryRepository {
    public function __construct(private Mem5 $m) {}
    public function create(int $companyId, array $data): int { return $this->m->create($companyId, $data); }
    public function listForEntity(int $companyId, string $entityType, int $entityId, int $limit = 100): array { return $this->m->listForEntity($companyId, $entityType, $entityId, $limit); }
    public function find(int $id, int $companyId): ?array { return $this->m->find($id, $companyId); }
};
$proofRepo = new class ($proofMem) extends LogisticsDeliveryProofRepository {
    public function __construct(private Mem5 $m) {}
    public function create(int $companyId, array $data): int { return $this->m->create($companyId, $data); }
    public function findByShipment(int $shipmentId, int $companyId): ?array { return $this->m->findByShipment($shipmentId, $companyId); }
    public function find(int $id, int $companyId): ?array { return $this->m->find($id, $companyId); }
};
$tripRepo = new class ($tripMem) extends LogisticsTripRepository {
    public function __construct(private Mem5 $m) {}
    public function listForCompany(int $companyId, int $limit = 200, int $offset = 0, string $search = ''): array { return $this->m->listForCompany($companyId, $limit, $offset, $search); }
    public function find(int $id, int $companyId): ?array { return $this->m->find($id, $companyId); }
    public function create(int $companyId, array $data): int { return $this->m->create($companyId, $data); }
};
$driverRepo = new class ($driverMem) extends LogisticsDriverRepository {
    public function __construct(private Mem5 $m) {}
    public function listForCompany(int $companyId, int $limit = 200, int $offset = 0, string $search = ''): array { return $this->m->listForCompany($companyId, $limit, $offset, $search); }
    public function find(int $id, int $companyId): ?array { return $this->m->find($id, $companyId); }
    public function create(int $companyId, array $data): int { return $this->m->create($companyId, $data); }
};
$vehRepo = new class ($vehMem) extends LogisticsVehicleRepository {
    public function __construct(private Mem5 $m) {}
    public function listForCompany(int $companyId, int $limit = 200, int $offset = 0, string $search = ''): array { return $this->m->listForCompany($companyId, $limit, $offset, $search); }
    public function find(int $id, int $companyId): ?array { return $this->m->find($id, $companyId); }
    public function create(int $companyId, array $data): int { return $this->m->create($companyId, $data); }
};
$expRepo = new class ($expMem) extends LogisticsExpenseRepository {
    public function __construct(private Mem5 $m) {}
    public function listForCompany(int $companyId, int $limit = 200, int $offset = 0, string $search = ''): array { return $this->m->listForCompany($companyId, $limit, $offset, $search); }
    public function find(int $id, int $companyId): ?array { return $this->m->find($id, $companyId); }
    public function create(int $companyId, array $data): int { return $this->m->create($companyId, $data); }
};

$tracking = new CustomerTrackingService($shipRepo, $histRepo, $proofRepo);
$reports = new LogisticsReportsService($shipRepo, $tripRepo, $driverRepo, $vehRepo, $expRepo);

// seed company 10
$shipId = $shipRepo->create(10, [
    'tracking_number' => 'TRK-P5-001',
    'status' => 'delivered',
    'customer_id' => 55,
    'pickup_location' => 'Riyadh',
    'delivery_location' => 'Jeddah',
    'delivered_at' => '2026-08-06 12:00:00',
]);
$histRepo->create(10, [
    'entity_type' => LogisticsStatusPolicy::ENTITY_SHIPMENT,
    'entity_id' => $shipId,
    'from_status' => 'shipped',
    'to_status' => 'out_for_delivery',
    'reason' => 'on_road',
    'created_at' => '2026-08-06 10:00:00',
]);
$histRepo->create(10, [
    'entity_type' => LogisticsStatusPolicy::ENTITY_SHIPMENT,
    'entity_id' => $shipId,
    'from_status' => 'out_for_delivery',
    'to_status' => 'delivered',
    'reason' => 'handed_over',
    'created_at' => '2026-08-06 12:00:00',
]);
$proofRepo->create(10, [
    'shipment_id' => $shipId,
    'receiver_name' => 'Ali',
    'signature_file' => 'sig.png',
    'photo_file' => 'pod.jpg',
    'delivered_at' => '2026-08-06 12:00:00',
]);
$shipRepo->create(10, [
    'tracking_number' => 'TRK-P5-PENDING',
    'status' => 'shipped',
    'customer_id' => 55,
]);
// other tenant
$shipRepo->create(11, [
    'tracking_number' => 'TRK-P5-001',
    'status' => 'shipped',
    'customer_id' => 99,
]);
$shipRepo->create(10, [
    'tracking_number' => 'TRK-OTHER-CUST',
    'status' => 'shipped',
    'customer_id' => 77,
]);

// --- customer tracking ---
$ok = $tracking->trackByNumber(10, 'TRK-P5-001', 55);
logistics5_assert(($ok['found'] ?? false) === true, 'customer tracking finds shipment');
logistics5_assert(($ok['shipment']['status'] ?? '') === 'delivered', 'delivery status exposed');
logistics5_assert(count($ok['timeline'] ?? []) === 2, 'shipment timeline returned');
logistics5_assert(($ok['timeline'][0]['to_status'] ?? '') === 'out_for_delivery', 'timeline chronological order');
logistics5_assert(is_array($ok['proof'] ?? null) && ($ok['proof']['receiver_name'] ?? '') === 'Ali', 'POD returned when present');

$missing = $tracking->trackByNumber(10, 'NOPE', 55);
logistics5_assert(($missing['found'] ?? true) === false, 'unknown tracking not found');

$crossTenant = $tracking->trackByNumber(10, 'TRK-P5-001'); // company 10 OK
logistics5_assert(($crossTenant['found'] ?? false) === true, 'same company tracking without customer filter');
$wrongCompany = $tracking->trackByNumber(99, 'TRK-P5-001');
logistics5_assert(($wrongCompany['found'] ?? true) === false, 'tenant isolation: other company cannot track');
$wrongCustomer = $tracking->trackByNumber(10, 'TRK-OTHER-CUST', 55);
logistics5_assert(($wrongCustomer['found'] ?? true) === false, 'tenant isolation: other customer shipment hidden');

// --- permissions map ---
$perms = LogisticsModule::entityPermissions();
logistics5_assert(($perms['logistics/routes']['manage'] ?? '') === 'logistics.manage', 'routes manage permission mapped');
logistics5_assert(($perms['logistics/expenses']['manage'] ?? '') === 'logistics.expense', 'expenses manage permission mapped');
logistics5_assert(($perms['logistics/reports']['view'] ?? '') === 'logistics.report', 'reports view permission mapped');

// --- reports access / data ---
$driverRepo->create(10, ['employee_id' => 1, 'status' => 'active']);
$vehRepo->create(10, ['plate_number' => 'ABC-1', 'status' => 'available']);
$tripRepo->create(10, ['driver_id' => 1, 'vehicle_id' => 1, 'status' => 'started', 'origin' => 'A', 'destination' => 'B']);
$tripRepo->create(10, ['driver_id' => 1, 'vehicle_id' => 1, 'status' => 'completed', 'origin' => 'C', 'destination' => 'D']);
$expRepo->create(10, ['expense_type' => 'fuel', 'amount' => 100.5, 'status' => 'posted', 'currency' => 'SAR']);
$expRepo->create(10, ['expense_type' => 'maintenance', 'amount' => 50, 'status' => 'draft', 'currency' => 'SAR']);

$shipReport = $reports->build(10, LogisticsReportsService::REPORT_SHIPMENT);
logistics5_assert(($shipReport['summary']['total'] ?? 0) === 3, 'shipment report company rows only');
$tripReport = $reports->build(10, LogisticsReportsService::REPORT_TRIP);
logistics5_assert(($tripReport['summary']['total'] ?? 0) === 2, 'trip report rows');
$driverReport = $reports->build(10, LogisticsReportsService::REPORT_DRIVER);
logistics5_assert(($driverReport['rows'][0]['trips_completed'] ?? 0) === 1, 'driver performance completed trips');
$vehReport = $reports->build(10, LogisticsReportsService::REPORT_VEHICLE);
logistics5_assert(($vehReport['rows'][0]['trips_total'] ?? 0) === 2, 'vehicle usage trips');
$costReport = $reports->build(10, LogisticsReportsService::REPORT_COST);
logistics5_assert((float) ($costReport['summary']['total'] ?? 0) === 150.5, 'cost report total');

$otherShipReport = $reports->build(11, LogisticsReportsService::REPORT_SHIPMENT);
logistics5_assert(($otherShipReport['summary']['total'] ?? 0) === 1, 'tenant isolation: reports scoped by company');

$kpis = $reports->dashboardKpis(10);
logistics5_assert((int) ($kpis['shipments'] ?? 0) === 3, 'dashboard shipments count');
logistics5_assert((int) ($kpis['delivered'] ?? 0) === 1, 'dashboard delivered count');
logistics5_assert((int) ($kpis['pending'] ?? 0) === 2, 'dashboard pending count');
logistics5_assert((int) ($kpis['active_trips'] ?? 0) === 1, 'dashboard active trips');
logistics5_assert((float) ($kpis['expenses_total'] ?? 0) === 150.5, 'dashboard expenses summary');

// wiring
$adminRoutes = (string) file_get_contents(dirname(__DIR__) . '/routes/logistics.php');
foreach (['logistics/routes', 'logistics/expenses', 'logistics/reports', 'LogisticsRoutesController', 'LogisticsExpensesController', 'LogisticsReportsController'] as $needle) {
    logistics5_assert(str_contains($adminRoutes, $needle), 'admin routes contain ' . $needle);
}
$portalRoutes = (string) file_get_contents(dirname(__DIR__) . '/routes/logistics-portal.php');
logistics5_assert(str_contains($portalRoutes, '/site/customer/logistics'), 'portal route registered');
logistics5_assert(str_contains($portalRoutes, 'rateb_website_portal_mw'), 'portal middleware applied');
$marketing = (string) file_get_contents($root . '/routes/modules/marketing.php');
logistics5_assert(str_contains($marketing, 'logistics-portal.php'), 'marketing loads logistics portal routes');
$sidebar = (string) file_get_contents(dirname(__DIR__) . '/views/partials/sidebar-logistics-nav.php');
logistics5_assert(str_contains($sidebar, 'logistics/routes') && str_contains($sidebar, 'logistics/expenses') && str_contains($sidebar, 'logistics/reports'), 'sidebar includes new sections');
logistics5_assert(class_exists(\Rateb\App\Logistics\Controllers\CustomerLogisticsController::class), 'CustomerLogisticsController autoloads');
logistics5_assert(class_exists(\Rateb\App\Logistics\Controllers\LogisticsRoutesController::class), 'LogisticsRoutesController autoloads');
logistics5_assert(class_exists(\Rateb\App\Logistics\Controllers\LogisticsExpensesController::class), 'LogisticsExpensesController autoloads');
logistics5_assert(class_exists(\Rateb\App\Logistics\Controllers\LogisticsReportsController::class), 'LogisticsReportsController autoloads');
logistics5_assert(is_file(dirname(__DIR__) . '/views/portal/track.php'), 'portal track view exists');
logistics5_assert(is_file(dirname(__DIR__) . '/views/reports/index.php'), 'reports index view exists');

echo "\nLogistics Phase 5 tests: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
