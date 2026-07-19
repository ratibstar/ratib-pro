<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $rows */
/** @var bool $canManage */
/** @var bool $consoleAccessible */
/** @var string $consoleUrl */
/** @var string $csrf */
$rows = $rows ?? [];
$canManage = !empty($canManage);
$consoleAccessible = !empty($consoleAccessible);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_title')); ?></h1>
        <p class="text-muted small mb-0"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_intro')); ?></p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($consoleAccessible) { ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?php echo Rateb\App\Core\View::escape($consoleUrl ?? rateb_url('admin/hr-mobile')); ?>">
            <i class="fas fa-flask"></i> <?php echo Rateb\App\Core\View::escape(__('hr_mobile_nav')); ?>
        </a>
        <?php } ?>
    </div>
</div>

<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_companies')); ?></div>
    <div class="rateb-card-body table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
            <tr>
                <th><?php echo Rateb\App\Core\View::escape(__('company')); ?></th>
                <th><?php echo Rateb\App\Core\View::escape(__('mobile_apps_app_name')); ?></th>
                <th><?php echo Rateb\App\Core\View::escape(__('status')); ?></th>
                <th><?php echo Rateb\App\Core\View::escape(__('mobile_apps_theme')); ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows === []) { ?>
                <tr><td colspan="5" class="text-muted"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_empty')); ?></td></tr>
            <?php } ?>
            <?php foreach ($rows as $row) {
                $cid = (int) ($row['company_id'] ?? 0);
                $active = !empty($row['mobile_active']);
                ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['company_name'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['app_name'] ?? '—')); ?></td>
                    <td>
                        <?php if ($active) { ?>
                            <span class="badge text-bg-success"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_status_active')); ?></span>
                        <?php } else { ?>
                            <span class="badge text-bg-secondary"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_status_inactive')); ?></span>
                        <?php } ?>
                    </td>
                    <td>
                        <?php $color = (string) ($row['theme_color'] ?? ''); ?>
                        <?php if ($color !== '') { ?>
                            <span class="d-inline-block rounded border" style="width:1.25rem;height:1.25rem;background:<?php echo Rateb\App\Core\View::escape($color); ?>"></span>
                            <span class="small text-muted"><?php echo Rateb\App\Core\View::escape($color); ?></span>
                        <?php } else { ?>
                            —
                        <?php } ?>
                    </td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo rateb_url('admin/mobile-apps/' . $cid); ?>">
                            <?php echo Rateb\App\Core\View::escape($canManage ? __('edit') : __('view')); ?>
                        </a>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
