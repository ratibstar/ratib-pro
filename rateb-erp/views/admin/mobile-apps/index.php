<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $rows */
/** @var bool $canManage */
/** @var bool $canToggleEnable */
/** @var bool $consoleAccessible */
/** @var string $consoleUrl */
/** @var string $csrf */
$rows = $rows ?? [];
$canManage = !empty($canManage);
$canToggleEnable = !empty($canToggleEnable);
$consoleAccessible = !empty($consoleAccessible);
$colCount = $canToggleEnable ? 7 : 5;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_title')); ?></h1>
        <p class="text-muted small mb-0"><?php echo Rateb\App\Core\View::escape(__($canToggleEnable ? 'mobile_apps_intro_platform' : 'mobile_apps_intro')); ?></p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($consoleAccessible) { ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?php echo Rateb\App\Core\View::escape($consoleUrl ?? rateb_url('admin/hr-mobile')); ?>">
            <i class="fas fa-flask"></i> <?php echo Rateb\App\Core\View::escape(__('hr_mobile_nav')); ?>
        </a>
        <?php } ?>
    </div>
</div>

<?php if ($canToggleEnable) {
    $activeApp = 'hr';
    require __DIR__ . '/_tabs.php';
} ?>

<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_companies')); ?></div>
    <div class="rateb-card-body table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
            <tr>
                <th><?php echo Rateb\App\Core\View::escape(__('company')); ?></th>
                <th><?php echo Rateb\App\Core\View::escape(__('mobile_apps_app_name')); ?></th>
                <th><?php echo Rateb\App\Core\View::escape(__('status')); ?></th>
                <?php if ($canToggleEnable) { ?>
                <th><?php echo Rateb\App\Core\View::escape(__('mobile_apps_server_url')); ?></th>
                <th><?php echo Rateb\App\Core\View::escape(__('mobile_apps_apk')); ?></th>
                <?php } ?>
                <th><?php echo Rateb\App\Core\View::escape(__('mobile_apps_theme')); ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows === []) { ?>
                <tr><td colspan="<?php echo $colCount; ?>" class="text-muted"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_empty')); ?></td></tr>
            <?php } ?>
            <?php foreach ($rows as $row) {
                $cid = (int) ($row['company_id'] ?? 0);
                $active = !empty($row['mobile_active']);
                $apk = is_array($row['apk'] ?? null) ? $row['apk'] : null;
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
                    <?php if ($canToggleEnable) { ?>
                    <td class="small text-break" dir="ltr"><?php echo Rateb\App\Core\View::escape((string) ($row['erp_base_url'] ?? '')); ?></td>
                    <td>
                        <?php if ($apk !== null) { ?>
                            <span class="badge text-bg-info" title="<?php echo Rateb\App\Core\View::escape((string) ($apk['uploaded_at'] ?? '')); ?>">
                                <i class="fas fa-check"></i> <?php echo Rateb\App\Core\View::escape(number_format(((int) ($apk['size'] ?? 0)) / 1048576, 1)); ?> MB
                            </span>
                        <?php } else { ?>
                            <span class="badge text-bg-warning"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_apk_missing')); ?></span>
                        <?php } ?>
                    </td>
                    <?php } ?>
                    <td>
                        <?php $color = (string) ($row['theme_color'] ?? ''); ?>
                        <?php if ($color !== '') { ?>
                            <span class="d-inline-block rounded border" style="width:1.25rem;height:1.25rem;background:<?php echo Rateb\App\Core\View::escape($color); ?>"></span>
                            <span class="small text-muted"><?php echo Rateb\App\Core\View::escape($color); ?></span>
                        <?php } else { ?>
                            —
                        <?php } ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <?php if ($canToggleEnable) { ?>
                        <form method="post" action="<?php echo rateb_url('admin/mobile-apps/' . $cid . '/toggle'); ?>" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
                            <input type="hidden" name="status" value="<?php echo $active ? 'inactive' : 'active'; ?>">
                            <?php if ($active) { ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-power-off"></i> <?php echo Rateb\App\Core\View::escape(__('mobile_apps_disable_btn')); ?>
                                </button>
                            <?php } else { ?>
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="fas fa-plus"></i> <?php echo Rateb\App\Core\View::escape(__('mobile_apps_enable_btn')); ?>
                                </button>
                            <?php } ?>
                        </form>
                        <?php } ?>
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
