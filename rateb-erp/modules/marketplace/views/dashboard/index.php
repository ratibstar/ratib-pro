<?php
declare(strict_types=1);

use Rateb\App\Core\View;

/** @var array<string,int|float> $stats */
$stats = $stats ?? [];
$phaseNote = (string) ($phase_note ?? '');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?php echo View::escape((string) ($title ?? __('marketplace_dashboard'))); ?></h1>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-primary" href="<?php echo rateb_app_url('marketplace/providers'); ?>"><?php echo View::escape(__('marketplace_providers')); ?></a>
        <a class="btn btn-sm btn-outline-primary" href="<?php echo rateb_app_url('marketplace/services'); ?>"><?php echo View::escape(__('marketplace_services')); ?></a>
    </div>
</div>

<?php if ($phaseNote !== ''): ?>
<div class="alert alert-info"><?php echo View::escape($phaseNote); ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['providers', 'marketplace_providers', 'fa-store'],
        ['services', 'marketplace_services', 'fa-concierge-bell'],
        ['orders', 'marketplace_orders', 'fa-receipt'],
        ['reviews', 'marketplace_reviews', 'fa-star'],
    ] as [$key, $label, $icon]): ?>
        <div class="col-md-3">
            <div class="rateb-card h-100">
                <div class="rateb-card-body">
                    <div class="text-muted small"><i class="fas <?php echo View::escape($icon); ?>"></i> <?php echo View::escape(__($label)); ?></div>
                    <div class="fs-3 fw-semibold"><?php echo View::escape((string) ($stats[$key] ?? 0)); ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
