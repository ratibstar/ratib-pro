<?php
declare(strict_types=1);

/**
 * Logistics Phase 3 — Core integration tests (no live DB / no Core edits).
 * Run: php rateb-erp/modules/logistics/tests/run-logistics-phase3-tests.php
 */

$root = dirname(__DIR__, 3);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

require_once dirname(__DIR__) . '/LogisticsModule.php';
\Rateb\App\Logistics\LogisticsModule::init();

use Rateb\App\Logistics\Contracts\AccountingPoster;
use Rateb\App\Logistics\Contracts\CompanyNotifier;
use Rateb\App\Logistics\Contracts\EmployeeDirectory;
use Rateb\App\Logistics\Contracts\StockMovementRecorder;
use Rateb\App\Logistics\Contracts\StockMovementReferenceLookup;
use Rateb\App\Logistics\Policies\LogisticsStatusPolicy;
use Rateb\App\Logistics\Repositories\LogisticsDeliveryOrderRepository;
use Rateb\App\Logistics\Repositories\LogisticsDeliveryProofRepository;
use Rateb\App\Logistics\Repositories\LogisticsDriverRepository;
use Rateb\App\Logistics\Repositories\LogisticsExpenseRepository;
use Rateb\App\Logistics\Repositories\LogisticsRouteRepository;
use Rateb\App\Logistics\Repositories\LogisticsShipmentRepository;
use Rateb\App\Logistics\Repositories\LogisticsStatusHistoryRepository;
use Rateb\App\Logistics\Repositories\LogisticsTripRepository;
use Rateb\App\Logistics\Repositories\LogisticsVehicleRepository;
use Rateb\App\Logistics\Services\DriverService;
use Rateb\App\Logistics\Services\LogisticsDispatchService;
use Rateb\App\Logistics\Services\LogisticsExpenseService;
use Rateb\App\Logistics\Services\LogisticsNotificationService;
use Rateb\App\Logistics\Services\LogisticsStatusService;
use Rateb\App\Logistics\Services\ShipmentService;

$passed = 0;
$failed = 0;

function logistics3_assert(bool $cond, string $label): void
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

/** @internal */
final class MemoryLogisticsRepo
{
    /** @var array<int, array<string, mixed>> */
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
        if ($row === null) {
            return null;
        }
        if ((int) ($row['company_id'] ?? 0) !== $companyId) {
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

    public function delete(int $id, int $companyId): bool
    {
        if ($this->find($id, $companyId) === null) {
            return false;
        }
        unset($this->rows[$id]);

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

    public function countForCompany(int $companyId, string $search = ''): int
    {
        return count($this->listForCompany($companyId));
    }

    public function listByStatus(int $companyId, string $status, int $limit = 200): array
    {
        return array_values(array_filter(
            $this->listForCompany($companyId, $limit),
            static fn(array $row): bool => (string) ($row['status'] ?? '') === $status
        ));
    }

    public function listForEntity(int $companyId, string $entityType, int $entityId, int $limit = 100): array
    {
        $matched = [];
        foreach ($this->listForCompany($companyId, 500) as $row) {
            if ((string) ($row['entity_type'] ?? '') === $entityType && (int) ($row['entity_id'] ?? 0) === $entityId) {
                $matched[] = $row;
            }
        }

        return array_slice($matched, 0, $limit);
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
}

final class FakeShipmentRepository extends LogisticsShipmentRepository
{
    public MemoryLogisticsRepo $mem;
    public function __construct() { $this->mem = new MemoryLogisticsRepo(); }
    public function listForCompany(int $companyId, int $limit = 200, int $offset = 0, string $search = ''): array { return $this->mem->listForCompany($companyId, $limit, $offset, $search); }
    public function countForCompany(int $companyId, string $search = ''): int { return $this->mem->countForCompany($companyId, $search); }
    public function find(int $id, int $companyId): ?array { return $this->mem->find($id, $companyId); }
    public function create(int $companyId, array $data): int { return $this->mem->create($companyId, $data); }
    public function update(int $id, int $companyId, array $data): bool { return $this->mem->update($id, $companyId, $data); }
    public function delete(int $id, int $companyId): bool { return $this->mem->delete($id, $companyId); }
}

final class FakeExpenseRepository extends LogisticsExpenseRepository
{
    public MemoryLogisticsRepo $mem;
    public function __construct() { $this->mem = new MemoryLogisticsRepo(); }
    public function listForCompany(int $companyId, int $limit = 200, int $offset = 0, string $search = ''): array { return $this->mem->listForCompany($companyId, $limit, $offset, $search); }
    public function find(int $id, int $companyId): ?array { return $this->mem->find($id, $companyId); }
    public function create(int $companyId, array $data): int { return $this->mem->create($companyId, $data); }
    public function update(int $id, int $companyId, array $data): bool { return $this->mem->update($id, $companyId, $data); }
    public function delete(int $id, int $companyId): bool { return $this->mem->delete($id, $companyId); }
}

final class FakeHistoryRepository extends LogisticsStatusHistoryRepository
{
    public MemoryLogisticsRepo $mem;
    public function __construct() { $this->mem = new MemoryLogisticsRepo(); }
    public function create(int $companyId, array $data): int { return $this->mem->create($companyId, $data); }
    public function listForEntity(int $companyId, string $entityType, int $entityId, int $limit = 100): array { return $this->mem->listForEntity($companyId, $entityType, $entityId, $limit); }
    public function find(int $id, int $companyId): ?array { return $this->mem->find($id, $companyId); }
}

final class FakeStockRecorder implements StockMovementRecorder
{
    /** @var list<array<string,mixed>> */
    public array $calls = [];
    public function record(array $data): int
    {
        $this->calls[] = $data;

        return count($this->calls);
    }
}

final class FakeStockLookup implements StockMovementReferenceLookup
{
    public function __construct(private FakeStockRecorder $recorder)
    {
    }

    public function existsForReference(int $companyId, string $referenceType, int $referenceId): bool
    {
        return $this->idsForReference($companyId, $referenceType, $referenceId) !== [];
    }

    public function idsForReference(int $companyId, string $referenceType, int $referenceId): array
    {
        $ids = [];
        foreach ($this->recorder->calls as $i => $call) {
            if ((string) ($call['reference_type'] ?? '') === $referenceType
                && (int) ($call['reference_id'] ?? 0) === $referenceId) {
                $ids[] = $i + 1;
            }
        }

        return $ids;
    }
}

final class FakeAccountingPoster implements AccountingPoster
{
    /** @var list<array<string,mixed>> */
    public array $entries = [];
    public array $accounts = ['1100' => 11, '5300' => 53, '5800' => 58];

    public function ensureDefaultAccounts(int $companyId): void
    {
    }

    public function accountIdByCode(int $companyId, string $code): ?int
    {
        return $this->accounts[$code] ?? null;
    }

    public function journalExistsForSource(string $sourceType, int $sourceId): bool
    {
        foreach ($this->entries as $entry) {
            if (($entry['source_type'] ?? '') === $sourceType && (int) ($entry['source_id'] ?? 0) === $sourceId) {
                return true;
            }
        }

        return false;
    }

    public function createPostedEntry(
        int $companyId,
        string $sourceType,
        int $sourceId,
        array $lines,
        string $description,
        string $descriptionAr,
        string $entryDate
    ): ?int {
        $id = count($this->entries) + 100;
        $this->entries[] = compact('companyId', 'sourceType', 'sourceId', 'lines', 'description', 'descriptionAr', 'entryDate') + [
            'id' => $id,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ];

        return $id;
    }
}

final class FakeNotifier implements CompanyNotifier
{
    /** @var list<array<string,mixed>> */
    public array $calls = [];

    public function notifyCompany(
        int $companyId,
        string $title,
        string $message,
        string $type = 'info',
        ?string $triggerType = null,
        ?string $entityType = null,
        ?int $entityId = null
    ): int {
        $this->calls[] = compact('companyId', 'title', 'message', 'type', 'triggerType', 'entityType', 'entityId');

        return count($this->calls);
    }
}

final class FakeEmployeeDirectory implements EmployeeDirectory
{
    /** @var array<int, array<string,mixed>> */
    public array $employees = [];

    public function findEmployee(int $employeeId): ?array
    {
        return $this->employees[$employeeId] ?? null;
    }
}

function logistics3_status_service(
    FakeShipmentRepository $shipments,
    FakeExpenseRepository $expenses,
    FakeHistoryRepository $history
): LogisticsStatusService {
    return new LogisticsStatusService(
        $history,
        new LogisticsVehicleRepository(),
        new LogisticsDriverRepository(),
        new LogisticsRouteRepository(),
        new LogisticsDeliveryOrderRepository(),
        new LogisticsTripRepository(),
        $shipments,
        $expenses
    );
}

// ---------------------------------------------------------------------------
// 1) Shipment dispatch + stock movement + idempotency
// ---------------------------------------------------------------------------
$shipments = new FakeShipmentRepository();
$expenses = new FakeExpenseRepository();
$history = new FakeHistoryRepository();
$status = logistics3_status_service($shipments, $expenses, $history);
$stock = new FakeStockRecorder();
$lookup = new FakeStockLookup($stock);
$notifier = new FakeNotifier();
$notifications = new LogisticsNotificationService($notifier);

$shipmentId = $shipments->create(10, [
    'tracking_number' => 'TRK-TEST-1',
    'status' => 'created',
    'pickup_location' => 'A',
    'delivery_location' => 'B',
]);

$dispatch = new LogisticsDispatchService($shipments, $status, $stock, $lookup, $notifications);
$result = $dispatch->dispatch(10, $shipmentId, [
    ['inventory_id' => 55, 'quantity' => 3, 'warehouse_id' => 2],
]);

logistics3_assert(($result['to'] ?? '') === 'shipped', 'dispatch advances shipment to shipped');
logistics3_assert(count($stock->calls) === 1, 'dispatch records one stock movement');
logistics3_assert(($stock->calls[0]['reference_type'] ?? '') === 'logistics_shipment', 'reference_type=logistics_shipment');
logistics3_assert((int) ($stock->calls[0]['reference_id'] ?? 0) === $shipmentId, 'reference_id=shipment id');
logistics3_assert(($stock->calls[0]['movement_type'] ?? '') === 'out', 'movement type out');
logistics3_assert((float) ($stock->calls[0]['quantity'] ?? 0) === 3.0, 'movement quantity');
logistics3_assert($dispatch->isDispatched(10, $shipmentId), 'isDispatched true after dispatch');

$dupBlocked = false;
$stockBeforeDup = count($stock->calls);
try {
    $dispatch->dispatch(10, $shipmentId, [['inventory_id' => 55, 'quantity' => 1]]);
} catch (\Throwable $e) {
    $dupBlocked = true;
}
logistics3_assert($dupBlocked && count($stock->calls) === $stockBeforeDup, 'duplicate dispatch blocked');

$notifyDispatch = array_values(array_filter(
    $notifier->calls,
    static fn(array $c): bool => ($c['triggerType'] ?? '') === 'logistics_shipment_dispatched'
));
logistics3_assert($notifyDispatch !== [], 'notification trigger on dispatched');

// ---------------------------------------------------------------------------
// 2) Expense posting via AccountingPoster
// ---------------------------------------------------------------------------
$accounting = new FakeAccountingPoster();
$expenseSvc = new LogisticsExpenseService($expenses, $accounting, $status);
$expenseId = $expenseSvc->create(10, [
    'expense_type' => 'fuel',
    'amount' => 150.5,
    'expense_date' => '2026-08-06',
    'description' => 'Fuel for trip',
]);
$posted = $expenseSvc->post($expenseId, 10);
logistics3_assert(($posted['ok'] ?? false) === true, 'expense post ok');
logistics3_assert((int) ($posted['journal_entry_id'] ?? 0) === 100, 'journal entry id saved');
$expenseRow = $expenses->find($expenseId, 10);
logistics3_assert((int) ($expenseRow['journal_entry_id'] ?? 0) === 100, 'expense.journal_entry_id persisted');
logistics3_assert((string) ($expenseRow['status'] ?? '') === 'posted', 'expense status posted');
logistics3_assert(($accounting->entries[0]['source_type'] ?? '') === 'logistics_expense', 'accounting source_type');
logistics3_assert((int) ($accounting->entries[0]['source_id'] ?? 0) === $expenseId, 'accounting source_id');
logistics3_assert(count($accounting->entries[0]['lines'] ?? []) === 2, 'balanced journal lines');

$repostBlocked = false;
try {
    $expenseSvc->post($expenseId, 10);
} catch (\Throwable $e) {
    $repostBlocked = true;
}
logistics3_assert($repostBlocked, 'expense re-post blocked');

// ---------------------------------------------------------------------------
// 3) Notification triggers: created / delivered / failed
// ---------------------------------------------------------------------------
$notifier2 = new FakeNotifier();
$notifySvc = new LogisticsNotificationService($notifier2);
$shipSvc = new ShipmentService(
    $shipments,
    new LogisticsDeliveryProofRepository(),
    new \Rateb\App\Logistics\Services\DeliveryOrderService(),
    new \Rateb\App\Logistics\Services\TripService(),
    $status,
    $notifySvc
);

// Avoid DeliveryOrder/Trip DB by creating via repository then notifying manually for created,
// and using transition for delivered/failed on seeded rows.
$createdId = $shipments->create(10, [
    'tracking_number' => 'TRK-CREATED',
    'status' => 'created',
]);
$notifySvc->shipmentCreated(10, $shipments->find($createdId, 10) ?? []);

$deliverId = $shipments->create(10, [
    'tracking_number' => 'TRK-DELIVER',
    'status' => 'out_for_delivery',
]);
$shipSvc->transition($deliverId, 10, 'delivered');

$failId = $shipments->create(10, [
    'tracking_number' => 'TRK-FAIL',
    'status' => 'created',
]);
$shipSvc->transition($failId, 10, 'failed');

$triggers = array_column($notifier2->calls, 'triggerType');
logistics3_assert(in_array('logistics_shipment_created', $triggers, true), 'notification trigger on created');
logistics3_assert(in_array('logistics_shipment_delivered', $triggers, true), 'notification trigger on delivered');
logistics3_assert(in_array('logistics_shipment_failed', $triggers, true), 'notification trigger on failed');

// ---------------------------------------------------------------------------
// 4) HR tenant isolation via EmployeeDirectory / DriverService
// ---------------------------------------------------------------------------
$dir = new FakeEmployeeDirectory();
$dir->employees[7] = ['id' => 7, 'company_id' => 99, 'name' => 'Other Co Driver'];
$dir->employees[8] = ['id' => 8, 'company_id' => 10, 'name' => 'Same Co Driver'];
$driversMem = new MemoryLogisticsRepo();
$driverRepo = new class ($driversMem) extends LogisticsDriverRepository {
    public function __construct(private MemoryLogisticsRepo $mem) {}
    public function find(int $id, int $companyId): ?array { return $this->mem->find($id, $companyId); }
    public function create(int $companyId, array $data): int { return $this->mem->create($companyId, $data); }
    public function update(int $id, int $companyId, array $data): bool { return $this->mem->update($id, $companyId, $data); }
    public function delete(int $id, int $companyId): bool { return $this->mem->delete($id, $companyId); }
    public function listForCompany(int $companyId, int $limit = 200, int $offset = 0, string $search = ''): array { return $this->mem->listForCompany($companyId, $limit, $offset, $search); }
    public function countForCompany(int $companyId, string $search = ''): int { return $this->mem->countForCompany($companyId, $search); }
};
$driverSvc = new DriverService($driverRepo, $status, $dir);

$crossTenantBlocked = false;
try {
    $driverSvc->create(10, ['employee_id' => 7, 'license_number' => 'X1']);
} catch (\Throwable $e) {
    $crossTenantBlocked = true;
}
logistics3_assert($crossTenantBlocked && $driverRepo->countForCompany(10) === 0, 'tenant isolation: foreign employee rejected');

$okDriverId = $driverSvc->create(10, ['employee_id' => 8, 'license_number' => 'L-8']);
logistics3_assert($okDriverId > 0, 'tenant isolation: same-company employee accepted');

// Cross-company shipment find isolation
logistics3_assert($shipments->find($shipmentId, 999) === null, 'tenant isolation: shipment hidden from other company');
logistics3_assert($expenses->find($expenseId, 999) === null, 'tenant isolation: expense hidden from other company');

// Wiring sanity
$routes = (string) file_get_contents(dirname(__DIR__) . '/routes/logistics.php');
logistics3_assert(str_contains($routes, 'shipments/{id}/dispatch'), 'admin dispatch route registered');
logistics3_assert(class_exists(LogisticsDispatchService::class), 'LogisticsDispatchService loaded');
logistics3_assert(class_exists(LogisticsExpenseService::class), 'LogisticsExpenseService loaded');
logistics3_assert(LogisticsStatusPolicy::canTransition('shipment', 'packed', 'shipped'), 'policy still allows packed→shipped');

echo "\nLogistics Phase 3 tests: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
