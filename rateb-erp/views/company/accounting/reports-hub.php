<?php
/** @var array<int, array{id: string, label: string, items: array<int, array<string, mixed>>}> $catalog */
/** @var int $reportCount */
$catalog = $catalog ?? [];
$reportCount = (int) ($reportCount ?? 0);
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
?>
<div class="rateb-reports-hub mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="h5 mb-1"><i class="fas fa-chart-pie me-2 text-primary"></i><?php echo __('accounting_reports'); ?></h2>
            <p class="text-muted small mb-0"><?php echo __('accounting_reports_intro'); ?></p>
        </div>
        <span class="badge bg-primary"><?php echo $reportCount; ?> <?php echo __('reports'); ?></span>
    </div>
    <?php if ($catalog === []) { ?>
    <div class="rateb-card">
        <div class="rateb-card-body text-center text-muted py-5"><?php echo __('access_denied'); ?></div>
    </div>
    <?php } else { foreach ($catalog as $group) { ?>
    <section class="rateb-reports-group mb-4" id="reports-<?php echo Rateb\App\Core\View::escape($group['id']); ?>">
        <h3 class="h6 rateb-reports-group-title mb-3">
            <i class="fas fa-folder-open me-2 opacity-75"></i><?php echo Rateb\App\Core\View::escape($group['label']); ?>
        </h3>
        <div class="row g-3">
            <?php foreach ($group['items'] as $item) { ?>
            <div class="col-md-6 col-lg-4">
                <div class="rateb-report-card h-100">
                    <div class="rateb-report-card-icon">
                        <i class="fas <?php echo Rateb\App\Core\View::escape($item['icon']); ?>"></i>
                    </div>
                    <div class="rateb-report-card-body">
                        <h4 class="rateb-report-card-title rateb-ar-text">
                            <a href="<?php echo Rateb\App\Core\View::escape($item['url']); ?>" class="stretched-link text-decoration-none">
                                <?php echo Rateb\App\Core\View::escape($item['label']); ?>
                            </a>
                        </h4>
                        <p class="rateb-report-card-desc rateb-ar-text small text-muted mb-0"><?php echo Rateb\App\Core\View::escape($item['desc']); ?></p>
                    </div>
                    <?php if (!empty($item['exportUrl']) && !empty($item['canExport'])) { ?>
                    <div class="rateb-report-card-actions">
                        <a href="<?php echo Rateb\App\Core\View::escape($item['exportUrl']); ?>" class="btn btn-outline-secondary btn-sm" title="<?php echo __('export'); ?>">
                            <i class="fas fa-download"></i>
                        </a>
                    </div>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
        </div>
    </section>
    <?php } } ?>
</div>
