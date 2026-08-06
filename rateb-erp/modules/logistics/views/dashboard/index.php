<?php
declare(strict_types=1);

use Rateb\App\Core\View;

/** @var array<string,int|float> $stats */
/** @var array<string,int> $tripCounts */
/** @var array<string,int> $shipmentCounts */
/** @var array<int,array<string,mixed>> $recentTrips */
/** @var array<int,array<string,mixed>> $recentShipments */
$stats = $stats ?? [];
$tripCounts = $tripCounts ?? [];
$shipmentCounts = $shipmentCounts ?? [];
$recentTrips = $recentTrips ?? [];
$recentShipments = $recentShipments ?? [];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?php echo View::escape(__('logistics_dashboard')); ?></h1>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-primary" href="<?php echo rateb_app_url('logistics/routes'); ?>"><?php echo __('logistics_routes'); ?></a>
        <a class="btn btn-sm btn-outline-primary" href="<?php echo rateb_app_url('logistics/expenses'); ?>"><?php echo __('logistics_expenses'); ?></a>
        <a class="btn btn-sm btn-outline-primary" href="<?php echo rateb_app_url('logistics/reports'); ?>"><?php echo __('logistics_reports'); ?></a>
        <a class="btn btn-sm btn-primary" href="<?php echo rateb_app_url('logistics/shipments'); ?>"><?php echo __('logistics_shipments'); ?></a>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['shipments', 'logistics_shipments', 'fa-box', false],
        ['delivered', 'logistics_status_delivered', 'fa-circle-check', false],
        ['pending', 'logistics_pending_shipments', 'fa-clock', false],
        ['trips_active', 'logistics_trips_active', 'fa-route', false],
        ['expenses_total', 'logistics_expenses_summary', 'fa-coins', true],
    ] as [$key, $label, $icon, $money]) { ?>
        <div class="col-md-4 col-xl">
            <div class="rateb-card h-100">
                <div class="rateb-card-body">
                    <div class="text-muted small"><i class="fas <?php echo View::escape($icon); ?>"></i> <?php echo View::escape(__($label)); ?></div>
                    <div class="fs-3 fw-semibold">
                        <?php
                        $val = $stats[$key] ?? 0;
                        echo $money ? number_format((float) $val, 2) : (int) $val;
                        ?>
                    </div>
                    <?php if ($key === 'expenses_total') { ?>
                        <div class="small text-muted">
                            <?php echo __('logistics_status_posted'); ?>: <?php echo number_format((float) ($stats['expenses_posted'] ?? 0), 2); ?>
                            ·
                            <?php echo __('logistics_status_draft'); ?>: <?php echo number_format((float) ($stats['expenses_draft'] ?? 0), 2); ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    <?php } ?>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo __('logistics_trips'); ?></div>
            <div class="rateb-card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>#</th><th><?php echo __('logistics_origin'); ?></th><th><?php echo __('status'); ?></th></tr></thead>
                    <tbody>
                    <?php if ($recentTrips === []) { ?>
                        <tr><td colspan="3" class="text-muted px-3 py-3"><?php echo __('no_records'); ?></td></tr>
                    <?php } ?>
                    <?php foreach ($recentTrips as $row) { ?>
                        <tr>
                            <td><a href="<?php echo rateb_app_url('logistics/trips/' . (int) $row['id'] . '/edit'); ?>">#<?php echo (int) $row['id']; ?></a></td>
                            <td><?php echo View::escape((string) ($row['origin'] ?? '') . ' → ' . (string) ($row['destination'] ?? '')); ?></td>
                            <td><?php echo View::escape((string) ($row['status'] ?? '')); ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo __('logistics_shipments'); ?></div>
            <div class="rateb-card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th><?php echo __('logistics_tracking_number'); ?></th><th><?php echo __('status'); ?></th></tr></thead>
                    <tbody>
                    <?php if ($recentShipments === []) { ?>
                        <tr><td colspan="2" class="text-muted px-3 py-3"><?php echo __('no_records'); ?></td></tr>
                    <?php } ?>
                    <?php foreach ($recentShipments as $row) { ?>
                        <tr>
                            <td><a href="<?php echo rateb_app_url('logistics/shipments/' . (int) $row['id'] . '/edit'); ?>"><?php echo View::escape((string) ($row['tracking_number'] ?? '')); ?></a></td>
                            <td><?php echo View::escape((string) ($row['status'] ?? '')); ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
