<?php
/** @var int $companyId */
/** @var list<array<string, mixed>> $items */
/** @var array<string, int> $counts */
/** @var list<string> $deferred */
/** @var string $typeFilter */
/** @var bool $isSuperAdmin */
/** @var string $routePrefix */
$companyId = (int) ($companyId ?? 0);
$items = $items ?? [];
$counts = $counts ?? [];
$deferred = $deferred ?? [];
$typeFilter = (string) ($typeFilter ?? 'all');
$isSuperAdmin = (bool) ($isSuperAdmin ?? false);
$routePrefix = (string) ($routePrefix ?? rateb_app_route('hr/approvals-inbox'));
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'approvals-inbox']);
?>
<?php if ($companyId < 1) { ?>
<div class="alert alert-warning mb-3">
    <i class="fas fa-building me-1"></i> <?php echo __('hr_select_company_hint'); ?>
</div>
<?php } ?>

<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>
            <i class="fas fa-inbox me-1"></i>
            <?php echo __('hr_approval_inbox'); ?>
            <span class="badge bg-warning text-dark ms-2"><?php echo (int) ($counts['total'] ?? 0); ?></span>
        </span>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($isSuperAdmin) { ?>
            <a href="<?php echo rateb_url('admin/oversight/approvals'); ?>?type=hr" class="btn btn-sm btn-outline-warning">
                <i class="fas fa-gavel"></i> <?php echo __('approvals_open_oversight'); ?>
            </a>
            <?php } ?>
            <a href="<?php echo rateb_url(rateb_app_route('hr')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('back'); ?></a>
        </div>
    </div>
    <div class="rateb-card-body">
        <p class="text-muted small mb-3"><?php echo __('hr_approval_inbox_hint'); ?></p>

        <form method="get" action="<?php echo rateb_url($routePrefix); ?>" class="row g-2 mb-3">
            <div class="col-auto">
                <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php
                    $opts = [
                        'all' => __('all'),
                        'leave' => __('hr_leaves'),
                        'permission' => __('hr_permission_requests'),
                        'request' => __('hr_employee_requests'),
                        'payroll' => __('hr_payroll'),
                    ];
                    foreach ($opts as $val => $label) {
                        $sel = $typeFilter === $val ? ' selected' : '';
                        echo '<option value="' . Rateb\App\Core\View::escape($val) . '"' . $sel . '>' . Rateb\App\Core\View::escape($label) . '</option>';
                    }
                    ?>
                </select>
            </div>
        </form>

        <div class="row g-2 mb-3">
            <div class="col-6 col-md-3"><div class="border rounded p-2 small"><?php echo __('hr_leaves'); ?>: <strong><?php echo (int) ($counts['leave'] ?? 0); ?></strong></div></div>
            <div class="col-6 col-md-3"><div class="border rounded p-2 small"><?php echo __('hr_permission_requests'); ?>: <strong><?php echo (int) ($counts['permission'] ?? 0); ?></strong></div></div>
            <div class="col-6 col-md-3"><div class="border rounded p-2 small"><?php echo __('hr_employee_requests'); ?>: <strong><?php echo (int) ($counts['request'] ?? 0); ?></strong></div></div>
            <div class="col-6 col-md-3"><div class="border rounded p-2 small"><?php echo __('hr_payroll'); ?>: <strong><?php echo (int) ($counts['payroll'] ?? 0); ?></strong></div></div>
        </div>

        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                <tr>
                    <th><?php echo __('type'); ?></th>
                    <th><?php echo __('reference'); ?></th>
                    <th><?php echo __('status'); ?></th>
                    <th><?php echo __('date'); ?></th>
                    <th><?php echo __('actions'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($items === []) { ?>
                <tr><td colspan="5" class="text-center text-muted py-4"><?php echo __('hr_approval_inbox_empty'); ?></td></tr>
                <?php } else {
                    foreach ($items as $item) {
                        $title = (string) ($item['title'] ?? '');
                        $ref = (string) ($item['reference'] ?? '');
                        $url = (string) ($item['source_url'] ?? '');
                        $age = $item['age_hours'];
                        ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape($title); ?></td>
                    <td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape($ref); ?></td>
                    <td><span class="badge bg-warning text-dark"><?php echo __('pending'); ?></span>
                        <?php if ($age !== null) { ?>
                        <span class="text-muted small ms-1"><?php echo (int) $age; ?>h</span>
                        <?php } ?>
                    </td>
                    <td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($item['created_at'] ?? '')); ?></td>
                    <td>
                        <?php if ($url !== '') { ?>
                        <a href="<?php echo Rateb\App\Core\View::escape($url); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('view'); ?></a>
                        <?php } ?>
                        <?php if ($isSuperAdmin) { ?>
                        <a href="<?php echo Rateb\App\Core\View::escape((string) ($item['oversight_url'] ?? rateb_url('admin/oversight/approvals'))); ?>" class="btn btn-sm btn-outline-warning"><?php echo __('approvals_open_oversight'); ?></a>
                        <?php } else { ?>
                        <span class="badge bg-light text-dark border"><?php echo __('awaiting_oversight_approval'); ?></span>
                        <?php } ?>
                    </td>
                </tr>
                <?php }
                } ?>
                </tbody>
            </table>
        </div>

        <?php if ($deferred !== []) { ?>
        <div class="alert alert-light border mt-3 mb-0 small">
            <strong><?php echo __('hr_approval_inbox_deferred'); ?></strong>
            <ul class="mb-0 mt-1">
                <?php foreach ($deferred as $note) { ?>
                <li><?php echo Rateb\App\Core\View::escape((string) $note); ?></li>
                <?php } ?>
            </ul>
        </div>
        <?php } ?>
    </div>
</div>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
