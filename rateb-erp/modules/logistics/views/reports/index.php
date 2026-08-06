<?php
declare(strict_types=1);

use Rateb\App\Core\View;

/** @var list<array{key:string,label:string}> $catalog */
/** @var array<string,int|float> $kpis */
$catalog = $catalog ?? [];
$kpis = $kpis ?? [];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?php echo View::escape(__('logistics_reports')); ?></h1>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo rateb_app_url('logistics'); ?>"><?php echo __('logistics_dashboard'); ?></a>
</div>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['shipments', 'logistics_shipments'],
        ['delivered', 'logistics_status_delivered'],
        ['pending', 'logistics_pending_shipments'],
        ['active_trips', 'logistics_trips_active'],
        ['expenses_total', 'logistics_expenses_summary'],
    ] as [$key, $label]) { ?>
        <div class="col-md-4 col-xl">
            <div class="rateb-card h-100">
                <div class="rateb-card-body">
                    <div class="text-muted small"><?php echo View::escape(__($label)); ?></div>
                    <div class="fs-4 fw-semibold">
                        <?php
                        $val = $kpis[$key] ?? 0;
                        echo is_float($val) ? number_format((float) $val, 2) : (int) $val;
                        ?>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>
</div>

<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('logistics_reports'); ?></div>
    <div class="rateb-card-body p-0">
        <table class="table table-sm mb-0">
            <thead><tr><th><?php echo __('name'); ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($catalog as $item) { ?>
                <tr>
                    <td><?php echo View::escape((string) ($item['label'] ?? $item['key'] ?? '')); ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo rateb_app_url('logistics/reports/' . rawurlencode((string) ($item['key'] ?? ''))); ?>">
                            <?php echo __('view'); ?>
                        </a>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
