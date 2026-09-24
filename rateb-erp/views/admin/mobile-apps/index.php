<?php
declare(strict_types=1);

/** @var string $app hr|erp|customer */
/** @var array{package:string, build_dir:string, file_prefix:string} $appInfo */
/** @var list<array<string,mixed>> $rows */
/** @var array<string,mixed>|null $shared */
/** @var bool $canManage */
/** @var bool $canToggleEnable */
/** @var bool $consoleAccessible */
/** @var string $consoleUrl */
/** @var string $csrf */
$e = static fn ($v): string => Rateb\App\Core\View::escape((string) $v);
$app = $app ?? 'hr';
$rows = $rows ?? [];
$canManage = !empty($canManage);
$canToggleEnable = !empty($canToggleEnable);
$consoleAccessible = !empty($consoleAccessible);
$colCount = $canToggleEnable ? 5 : 3;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1"><?php echo $e(__('mobile_apps_title')); ?></h1>
        <p class="text-muted small mb-0"><?php echo $e(__($canToggleEnable ? 'mobile_apps_intro_hub' : 'mobile_apps_intro')); ?></p>
    </div>
    <?php if ($consoleAccessible && $app === 'hr') { ?>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $e($consoleUrl ?? rateb_url('admin/hr-mobile')); ?>">
        <i class="fas fa-flask"></i> <?php echo $e(__('hr_mobile_nav')); ?>
    </a>
    <?php } ?>
</div>

<?php if ($canToggleEnable) {
    $activeApp = $app;
    require __DIR__ . '/_tabs.php';
    ?>
<details class="rateb-card mb-3"<?php echo is_array($shared) && $shared['apk'] === null ? ' open' : ''; ?>>
    <summary class="rateb-card-header" style="cursor:pointer">
        <i class="fas fa-share-nodes"></i> <?php echo $e(__('mobile_apps_shared_title')); ?>
        <span class="small text-muted ms-2" dir="ltr"><?php echo $e($appInfo['package'] ?? ''); ?></span>
        <?php if (is_array($shared) && $shared['apk'] !== null) { ?>
            <span class="badge text-bg-info ms-2"><?php echo $e(number_format(((int) ($shared['apk']['size'] ?? 0)) / 1048576, 1)); ?> MB</span>
        <?php } else { ?>
            <span class="badge text-bg-warning ms-2"><?php echo $e(__('mobile_apps_apk_missing')); ?></span>
        <?php } ?>
    </summary>
    <div class="rateb-card-body">
        <p class="small text-muted"><?php echo $e(__('mobile_apps_shared_hint')); ?></p>
        <div class="mb-3">
            <label class="form-label small text-muted mb-1"><?php echo $e(__('mobile_apps_server')); ?></label>
            <input class="form-control form-control-sm" dir="ltr" readonly value="<?php echo $e($shared['server'] ?? ''); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label small text-muted mb-1"><?php echo $e(__('mobile_apps_build_command_in')); ?> <code dir="ltr"><?php echo $e($appInfo['build_dir'] ?? ''); ?></code></label>
            <input class="form-control form-control-sm font-monospace" dir="ltr" readonly onclick="this.select()" value="<?php echo $e($shared['build_command'] ?? ''); ?>">
        </div>
        <?php
        $apk = $shared['apk'] ?? null;
        $sharedApk = null;
        $apkUrl = (string) ($shared['url'] ?? '');
        $apkQr = (string) ($shared['qr'] ?? '');
        $apkChunkUrl = rateb_url('admin/mobile-apps/platform/' . $app . '/apk-chunk');
        $apkDeleteUrl = rateb_url('admin/mobile-apps/platform/' . $app . '/apk-delete');
        $apkDeleteFields = [];
        $apkInactive = false;
        $apkNoneKey = 'mobile_apps_apk_none_yet_app';
        require __DIR__ . '/_apk-panel.php';
        ?>
    </div>
</details>
<?php } ?>

<div class="rateb-card">
    <div class="rateb-card-header"><?php echo $e(__('mobile_apps_companies')); ?></div>
    <div class="rateb-card-body table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
            <tr>
                <th><?php echo $e(__('company')); ?></th>
                <th><?php echo $e(__('status')); ?></th>
                <?php if ($canToggleEnable) { ?>
                <th><?php echo $e(__('mobile_apps_server')); ?></th>
                <th><?php echo $e(__('mobile_apps_apk')); ?></th>
                <?php } ?>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows === []) { ?>
                <tr><td colspan="<?php echo $colCount; ?>" class="text-muted"><?php echo $e(__('mobile_apps_empty')); ?></td></tr>
            <?php } ?>
            <?php foreach ($rows as $row) {
                $cid = (int) ($row['company_id'] ?? 0);
                $active = !empty($row['mobile_active']);
                $apk = is_array($row['apk'] ?? null) ? $row['apk'] : null;
                $manageUrl = rateb_url('admin/mobile-apps/' . $cid) . ($app === 'hr' ? '' : '?app=' . $app);
                ?>
                <tr>
                    <td><?php echo $e($row['company_name'] ?? ''); ?></td>
                    <td>
                        <?php if ($active) { ?>
                            <span class="badge text-bg-success"><?php echo $e(__('mobile_apps_status_active')); ?></span>
                        <?php } else { ?>
                            <span class="badge text-bg-secondary"><?php echo $e(__('mobile_apps_status_inactive')); ?></span>
                        <?php } ?>
                    </td>
                    <?php if ($canToggleEnable) { ?>
                    <td class="small text-break" dir="ltr"><?php echo $e($row['server'] ?? ''); ?></td>
                    <td>
                        <?php if ($apk !== null) { ?>
                            <span class="badge text-bg-info" title="<?php echo $e($apk['uploaded_at'] ?? ''); ?>">
                                <i class="fas fa-check"></i> <?php echo $e(number_format(((int) ($apk['size'] ?? 0)) / 1048576, 1)); ?> MB
                            </span>
                        <?php } elseif (!empty($row['uses_shared'])) { ?>
                            <span class="badge text-bg-primary"><i class="fas fa-share-nodes"></i> <?php echo $e(__('mobile_apps_apk_shared')); ?></span>
                        <?php } else { ?>
                            <span class="badge text-bg-warning"><?php echo $e(__('mobile_apps_apk_missing')); ?></span>
                        <?php } ?>
                    </td>
                    <?php } ?>
                    <td class="text-end text-nowrap">
                        <?php if ($canToggleEnable) { ?>
                        <form method="post" action="<?php echo rateb_url('admin/mobile-apps/' . $cid . '/toggle'); ?>" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?php echo $e($csrf ?? ''); ?>">
                            <input type="hidden" name="app" value="<?php echo $e($app); ?>">
                            <input type="hidden" name="status" value="<?php echo $active ? 'inactive' : 'active'; ?>">
                            <?php if ($active) { ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-power-off"></i> <?php echo $e(__('mobile_apps_disable_btn')); ?>
                                </button>
                            <?php } else { ?>
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="fas fa-plus"></i> <?php echo $e(__('mobile_apps_enable_btn')); ?>
                                </button>
                            <?php } ?>
                        </form>
                        <?php } ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo $e($manageUrl); ?>">
                            <i class="fas fa-sliders"></i> <?php echo $e($canManage ? __('mobile_apps_manage') : __('view')); ?>
                        </a>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
