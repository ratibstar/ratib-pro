<?php
/** @var array<string, mixed> $report */
$checks = $report['checks'] ?? [];
$pending = $report['pending'] ?? [];
$links = $report['links'] ?? [];
$pct = (int) ($report['total_checks'] ?? 0) > 0
    ? (int) round(100 * (int) ($report['done_count'] ?? 0) / (int) $report['total_checks'])
    : 0;
?>
<div class="rateb-card mb-4">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-clipboard-check text-warning"></i> <?php echo __('branch_setup_check'); ?></span>
        <span class="badge bg-secondary"><?php echo __('branch_setup_temp'); ?></span>
    </div>
    <div class="rateb-card-body">
        <p class="text-muted mb-3"><?php echo __('branch_setup_intro'); ?></p>
        <div class="d-flex flex-wrap gap-2 mb-4">
            <a href="<?php echo Rateb\App\Core\View::escape((string) ($links['list'] ?? '#')); ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-list"></i> <?php echo __('branch_list'); ?>
            </a>
            <a href="<?php echo Rateb\App\Core\View::escape((string) ($links['create'] ?? '#')); ?>" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-plus"></i> <?php echo __('add_branch'); ?>
            </a>
            <a href="<?php echo Rateb\App\Core\View::escape((string) ($links['admin'] ?? '#')); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-chart-line"></i> <?php echo __('dashboard'); ?>
            </a>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted small"><?php echo __('branch_setup_progress'); ?></div>
                    <div class="fs-4 fw-bold"><?php echo (int) ($report['done_count'] ?? 0); ?>/<?php echo (int) ($report['total_checks'] ?? 0); ?></div>
                    <div class="progress mt-2" style="height:8px">
                        <div class="progress-bar bg-success" style="width:<?php echo $pct; ?>%"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted small"><?php echo __('branch_setup_company'); ?></div>
                    <div class="fw-bold">#<?php echo (int) ($report['company_id'] ?? 0); ?></div>
                    <div class="text-muted small mt-1"><?php echo __('branch_count_limit', [
                        'count' => (int) ($report['branch_count'] ?? 0),
                        'limit' => (int) ($report['branch_limit'] ?? 0),
                    ]); ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted small"><?php echo __('branch_setup_direct_link'); ?></div>
                    <code class="small d-block text-break user-select-all"><?php echo Rateb\App\Core\View::escape(rateb_url(rateb_app_route('branches/setup-check'))); ?></code>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="rateb-card h-100">
            <div class="rateb-card-header"><?php echo __('branch_setup_ready'); ?></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($checks as $check) {
                    $ok = !empty($check['done']);
                    ?>
                <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <i class="fas fa-<?php echo $ok ? 'check-circle text-success' : 'circle text-muted'; ?> me-1"></i>
                        <?php echo Rateb\App\Core\View::escape(__((string) ($check['label'] ?? ''))); ?>
                        <?php if (!empty($check['hint'])) { ?>
                        <div class="small text-muted"><?php echo Rateb\App\Core\View::escape((string) $check['hint']); ?></div>
                        <?php } ?>
                    </div>
                    <span class="badge bg-<?php echo $ok ? 'success' : 'secondary'; ?>"><?php echo $ok ? __('yes') : __('no'); ?></span>
                </li>
                <?php } ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="rateb-card h-100">
            <div class="rateb-card-header"><?php echo __('branch_setup_next'); ?></div>
            <ul class="list-group list-group-flush">
                <?php if ($pending === []) { ?>
                <li class="list-group-item text-success">
                    <i class="fas fa-check-circle me-1"></i> <?php echo __('branch_setup_complete'); ?>
                </li>
                <?php } ?>
                <?php foreach ($pending as $item) { ?>
                <li class="list-group-item">
                    <span class="badge bg-info me-1"><?php echo __('branch_phase'); ?> <?php echo (int) ($item['phase'] ?? 0); ?></span>
                    <?php echo Rateb\App\Core\View::escape(__((string) ($item['label'] ?? ''))); ?>
                </li>
                <?php } ?>
            </ul>
        </div>
    </div>
</div>

<?php if (!empty($report['branches'])) { ?>
<div class="rateb-card mt-4">
    <div class="rateb-card-header"><?php echo __('branch_setup_sample_data'); ?></div>
    <div class="rateb-table-wrap">
        <table class="table rateb-table mb-0">
            <thead>
            <tr>
                <th><?php echo __('number'); ?></th>
                <th><?php echo __('branch_name'); ?></th>
                <th><?php echo __('branch_code'); ?></th>
                <th><?php echo __('status'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($report['branches'] as $row) { ?>
            <tr>
                <td><?php echo (int) ($row['id'] ?? 0); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($row['name'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($row['code'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape(rateb_enum_label((string) ($row['status'] ?? ''))); ?></td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<?php } ?>
