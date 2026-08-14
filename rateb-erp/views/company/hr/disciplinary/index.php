<?php
/** @var int $companyId */
/** @var list<array<string, mixed>> $items */
/** @var string $csrf */
/** @var string $routePrefix */
/** @var bool $canManage */
$companyId = (int) ($companyId ?? 0);
$items = $items ?? [];
$csrf = (string) ($csrf ?? \Rateb\App\Core\Csrf::token());
$routePrefix = (string) ($routePrefix ?? rateb_app_route('hr/disciplinary'));
$canManage = (bool) ($canManage ?? false);
$escape = static fn ($v): string => \Rateb\App\Core\View::escape((string) $v);
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'disciplinary']);
?>
<?php if ($companyId < 1) { ?>
<div class="alert alert-warning mb-3"><?php echo __('hr_select_company_hint'); ?></div>
<?php } ?>

<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-triangle-exclamation me-1"></i> <?php echo __('hr_disciplinary'); ?></span>
        <div class="d-flex gap-2">
            <?php if ($canManage) { ?>
            <a href="<?php echo rateb_url(rateb_app_route('hr/disciplinary/create')); ?>" class="btn btn-sm btn-primary"><?php echo __('hr_disciplinary_new'); ?></a>
            <?php } ?>
            <a href="<?php echo rateb_url(rateb_app_route('hr')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('back'); ?></a>
        </div>
    </div>
    <div class="rateb-card-body">
        <p class="text-muted small mb-3"><?php echo __('hr_disciplinary_hint'); ?></p>
        <div class="table-responsive">
            <table class="table rateb-table mb-0 align-middle">
                <thead>
                <tr>
                    <th><?php echo __('code'); ?></th>
                    <th><?php echo __('employee'); ?></th>
                    <th><?php echo __('type'); ?></th>
                    <th><?php echo __('title'); ?></th>
                    <th><?php echo __('date'); ?></th>
                    <th><?php echo __('status'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($items === []) { ?>
                    <tr><td colspan="6" class="text-muted"><?php echo __('no_records'); ?></td></tr>
                <?php } ?>
                <?php foreach ($items as $row) { ?>
                    <tr>
                        <td class="rateb-ltr-num"><?php echo $escape((string) ($row['code'] ?? '')); ?></td>
                        <td>
                            <?php echo $escape((string) ($row['employee_name'] ?? '')); ?>
                            <div class="small text-muted rateb-ltr-num"><?php echo $escape((string) ($row['employee_code'] ?? '')); ?></div>
                        </td>
                        <td><?php echo $escape((string) ($row['action_type'] ?? '')); ?></td>
                        <td><?php echo $escape((string) ($row['title'] ?? '')); ?></td>
                        <td class="rateb-ltr-num"><?php echo $escape((string) ($row['action_date'] ?? '')); ?></td>
                        <td><?php echo $escape((string) ($row['status'] ?? '')); ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
