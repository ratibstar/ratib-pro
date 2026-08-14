<?php
/** @var int $companyId */
/** @var bool $schemaReady */
/** @var list<array<string, mixed>> $items */
/** @var string $csrf */
/** @var string $routePrefix */
/** @var bool $canManage */
$companyId = (int) ($companyId ?? 0);
$schemaReady = (bool) ($schemaReady ?? false);
$items = $items ?? [];
$csrf = (string) ($csrf ?? \Rateb\App\Core\Csrf::token());
$routePrefix = (string) ($routePrefix ?? rateb_app_route('hr/succession'));
$canManage = (bool) ($canManage ?? false);
$escape = static fn ($v): string => \Rateb\App\Core\View::escape((string) $v);
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'succession']);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1"><?php echo __('hr_succession'); ?></h1>
        <p class="text-muted small mb-0"><?php echo __('hr_succession_hint'); ?></p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($canManage && $schemaReady) { ?>
        <a href="<?php echo rateb_url(rateb_app_route('hr/succession/create')); ?>" class="btn btn-sm btn-primary"><?php echo __('hr_succession_new_position'); ?></a>
        <?php } ?>
        <a href="<?php echo rateb_url(rateb_app_route('hr')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('back'); ?></a>
    </div>
</div>
<?php if (!$schemaReady) { ?>
<div class="alert alert-warning"><?php echo __('db_schema_outdated'); ?></div>
<?php } ?>
<div class="rateb-card">
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead><tr>
                    <th><?php echo __('code'); ?></th>
                    <th><?php echo __('title'); ?></th>
                    <th><?php echo __('employee'); ?></th>
                    <th><?php echo __('department'); ?></th>
                    <th><?php echo __('hr_o_candidates'); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php if ($items === []) { ?>
                    <tr><td colspan="6" class="text-muted"><?php echo __('hr_o_empty_succession'); ?></td></tr>
                <?php } ?>
                <?php foreach ($items as $row) {
                    $id = (int) ($row['id'] ?? 0);
                    ?>
                    <tr>
                        <td class="rateb-ltr-num"><?php echo $escape((string) ($row['code'] ?? '')); ?></td>
                        <td><?php echo $escape((string) ($row['title'] ?? '')); ?></td>
                        <td>
                            <?php if ((int) ($row['current_employee_id'] ?? 0) > 0) { ?>
                                <a href="<?php echo rateb_url(rateb_app_route('hr/employees/' . (int) $row['current_employee_id'])); ?>">
                                    <?php echo $escape((string) ($row['current_employee_name'] ?? '')); ?>
                                </a>
                            <?php } else {
                                echo '—';
                            } ?>
                        </td>
                        <td><?php echo $escape((string) ($row['department_name'] ?? '')); ?></td>
                        <td class="rateb-ltr-num"><?php echo (int) ($row['candidate_count'] ?? 0); ?></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?php echo rateb_url($routePrefix . '/' . $id); ?>"><?php echo __('view'); ?></a></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
