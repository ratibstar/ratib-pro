<?php
declare(strict_types=1);

/**
 * Logistics Phase 2 — business rules + wiring tests (no DB writes).
 * Run: php rateb-erp/modules/logistics/tests/run-logistics-phase2-tests.php
 */

$root = dirname(__DIR__, 3);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

require_once dirname(__DIR__) . '/LogisticsModule.php';
\Rateb\App\Logistics\LogisticsModule::init();

use Rateb\App\Logistics\Controllers\LogisticsDashboardController;
use Rateb\App\Logistics\Controllers\LogisticsDriversController;
use Rateb\App\Logistics\Controllers\LogisticsFleetController;
use Rateb\App\Logistics\Controllers\LogisticsShipmentsController;
use Rateb\App\Logistics\Controllers\LogisticsTripsController;
use Rateb\App\Logistics\LogisticsModule;
use Rateb\App\Logistics\Policies\LogisticsStatusPolicy;
use Rateb\App\Logistics\Repositories\LogisticsVehicleRepository;
use Rateb\App\Logistics\Services\DeliveryOrderService;
use Rateb\App\Logistics\Services\DriverService;
use Rateb\App\Logistics\Services\FleetService;
use Rateb\App\Logistics\Services\RouteService;
use Rateb\App\Logistics\Services\ShipmentService;
use Rateb\App\Logistics\Services\TripService;

$passed = 0;
$failed = 0;

function logistics2_assert(bool $cond, string $label): void
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

// --- Status policy graph ---
logistics2_assert(
    LogisticsStatusPolicy::canTransition(LogisticsStatusPolicy::ENTITY_TRIP, 'draft', 'assigned'),
    'trip draft → assigned allowed'
);
logistics2_assert(
    LogisticsStatusPolicy::canTransition(LogisticsStatusPolicy::ENTITY_TRIP, 'assigned', 'started'),
    'trip assigned → started allowed'
);
logistics2_assert(
    LogisticsStatusPolicy::canTransition(LogisticsStatusPolicy::ENTITY_TRIP, 'started', 'completed'),
    'trip started → completed allowed'
);
logistics2_assert(
    !LogisticsStatusPolicy::canTransition(LogisticsStatusPolicy::ENTITY_TRIP, 'draft', 'completed'),
    'trip draft → completed denied'
);
logistics2_assert(
    !LogisticsStatusPolicy::canTransition(LogisticsStatusPolicy::ENTITY_TRIP, 'completed', 'started'),
    'trip completed is terminal'
);

logistics2_assert(
    LogisticsStatusPolicy::canTransition(LogisticsStatusPolicy::ENTITY_SHIPMENT, 'created', 'picked'),
    'shipment created → picked'
);
logistics2_assert(
    LogisticsStatusPolicy::canTransition(LogisticsStatusPolicy::ENTITY_SHIPMENT, 'shipped', 'out_for_delivery'),
    'shipment shipped → out_for_delivery'
);
logistics2_assert(
    LogisticsStatusPolicy::canTransition(LogisticsStatusPolicy::ENTITY_SHIPMENT, 'out_for_delivery', 'delivered'),
    'shipment out_for_delivery → delivered'
);
logistics2_assert(
    !LogisticsStatusPolicy::canTransition(LogisticsStatusPolicy::ENTITY_SHIPMENT, 'created', 'delivered'),
    'shipment cannot skip to delivered'
);
logistics2_assert(
    LogisticsStatusPolicy::canTransition(LogisticsStatusPolicy::ENTITY_DELIVERY_ORDER, 'draft', 'confirmed'),
    'delivery order draft → confirmed'
);
logistics2_assert(
    LogisticsStatusPolicy::canTransition(LogisticsStatusPolicy::ENTITY_VEHICLE, 'available', 'assigned'),
    'vehicle available → assigned'
);
logistics2_assert(
    LogisticsStatusPolicy::canTransition(LogisticsStatusPolicy::ENTITY_DRIVER, 'active', 'suspended'),
    'driver active → suspended'
);

$denied = false;
try {
    LogisticsStatusPolicy::assertTransition(LogisticsStatusPolicy::ENTITY_TRIP, 'draft', 'started');
} catch (\Throwable $e) {
    $denied = str_contains($e->getMessage(), 'logistics_transition_denied');
}
logistics2_assert($denied, 'assertTransition throws on illegal trip hop');

// --- Services / repositories load ---
logistics2_assert(class_exists(FleetService::class), 'FleetService');
logistics2_assert(class_exists(DriverService::class), 'DriverService');
logistics2_assert(class_exists(RouteService::class), 'RouteService');
logistics2_assert(class_exists(DeliveryOrderService::class), 'DeliveryOrderService');
logistics2_assert(class_exists(TripService::class), 'TripService');
logistics2_assert(class_exists(ShipmentService::class), 'ShipmentService');
logistics2_assert(class_exists(LogisticsVehicleRepository::class), 'LogisticsVehicleRepository');

// Empty company lists stay safe
logistics2_assert((new FleetService())->listForCompany(0) === [], 'fleet empty company');
logistics2_assert((new DriverService())->listForCompany(0) === [], 'driver empty company');
logistics2_assert((new TripService())->listForCompany(0) === [], 'trip empty company');
logistics2_assert((new ShipmentService())->listForCompany(0) === [], 'shipment empty company');

// Controllers autoload
logistics2_assert(class_exists(LogisticsDashboardController::class), 'Dashboard controller');
logistics2_assert(class_exists(LogisticsFleetController::class), 'Fleet controller');
logistics2_assert(class_exists(LogisticsDriversController::class), 'Drivers controller');
logistics2_assert(class_exists(LogisticsTripsController::class), 'Trips controller');
logistics2_assert(class_exists(LogisticsShipmentsController::class), 'Shipments controller');

// Models live in LogisticsModels.php (bundle autoload)
logistics2_assert(class_exists(\Rateb\App\Logistics\Models\LogisticsShipment::class), 'LogisticsShipment model autoload');
logistics2_assert(class_exists(\Rateb\App\Logistics\Models\LogisticsTrip::class), 'LogisticsTrip model autoload');
logistics2_assert(class_exists(\Rateb\App\Logistics\Models\LogisticsDriver::class), 'LogisticsDriver model autoload');

// Views
$views = [
    'dashboard/index',
    'vehicles/index',
    'vehicles/form',
    'drivers/index',
    'drivers/form',
    'trips/index',
    'trips/form',
    'shipments/index',
    'shipments/form',
    'partials/sidebar-logistics-nav',
];
foreach ($views as $view) {
    $path = LogisticsModule::viewsPath() . '/' . $view . '.php';
    logistics2_assert(is_file($path), 'view exists: ' . $view);
}

// Routes file registers Admin endpoints
$routes = (string) file_get_contents(dirname(__DIR__) . '/routes/logistics.php');
foreach ([
    'LogisticsDashboardController',
    'LogisticsFleetController',
    'LogisticsDriversController',
    'LogisticsTripsController',
    'LogisticsShipmentsController',
    'vehicles/{id}/edit',
    'trips/{id}/transition',
    'shipments/{id}/transition',
] as $needle) {
    logistics2_assert(str_contains($routes, $needle), 'routes contain ' . $needle);
}
logistics2_assert(!str_contains($routes, '/api/'), 'routes have no API paths');

// Migration 225
$migration = $root . '/migrations/225_logistics_status_history.sql';
logistics2_assert(is_file($migration), 'migration 225 exists');
$sql = (string) file_get_contents($migration);
logistics2_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_logistics_status_history'), 'creates status history');
logistics2_assert(!preg_match('/\b(DROP|TRUNCATE|ALTER TABLE)\b/i', $sql), 'migration 225 additive only');

// Sidebar wiring
$opsNav = (string) file_get_contents($root . '/views/partials/sidebar-ops-nav.php');
logistics2_assert(str_contains($opsNav, 'sidebar-logistics-nav.php'), 'ops nav includes logistics sidebar');

// Scenario: full legal trip + shipment chains
$tripChain = ['draft', 'assigned', 'started', 'completed'];
for ($i = 0; $i < count($tripChain) - 1; $i++) {
    logistics2_assert(
        LogisticsStatusPolicy::canTransition(LogisticsStatusPolicy::ENTITY_TRIP, $tripChain[$i], $tripChain[$i + 1]),
        'scenario trip ' . $tripChain[$i] . ' → ' . $tripChain[$i + 1]
    );
}
$shipChain = ['created', 'picked', 'packed', 'shipped', 'out_for_delivery', 'delivered'];
for ($i = 0; $i < count($shipChain) - 1; $i++) {
    logistics2_assert(
        LogisticsStatusPolicy::canTransition(LogisticsStatusPolicy::ENTITY_SHIPMENT, $shipChain[$i], $shipChain[$i + 1]),
        'scenario shipment ' . $shipChain[$i] . ' → ' . $shipChain[$i + 1]
    );
}

$en = require dirname(__DIR__) . '/config/lang/en.php';
$ar = require dirname(__DIR__) . '/config/lang/ar.php';
logistics2_assert(($en['logistics_status_out_for_delivery'] ?? '') !== '', 'en status labels');
logistics2_assert(($ar['logistics_status_delivered'] ?? '') !== '', 'ar status labels');

echo "\nLogistics Phase 2 tests: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
