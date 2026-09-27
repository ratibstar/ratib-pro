<?php
declare(strict_types=1);

use Rateb\App\Models\Company;

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
$colCount = $canToggleEnable ? 7 : 4;
?>
<div class="rateb-mobile-apps-sticky-head">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1"><?php echo $e(__('mobile_apps_title')); ?></h1>
        <p class="text-muted small mb-0"><?php echo $e(__($canToggleEnable ? 'mobile_apps_intro_hub' : 'mobile_apps_intro')); ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
    <?php if ($canToggleEnable) { ?>
    <?php if (!empty($platformUpdate['pending'])) { ?>
    <form method="post" action="<?php echo $e(rateb_url('admin/mobile-apps/updates/' . $app . '/apply-platform')); ?>" class="d-inline">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf ?? ''); ?>">
        <input type="hidden" name="back" value="<?php echo $e(rateb_url('admin/mobile-apps') . '?app=' . $app); ?>">
        <button type="submit" class="btn btn-sm btn-warning fw-semibold">
            <i class="fas fa-bell"></i> <?php echo $e(__('mobile_platform_update_btn')); ?>
        </button>
    </form>
    <?php } ?>
    <a class="btn btn-sm btn-primary" href="<?php echo $e(rateb_url('admin/mobile-apps/updates') . '?app=' . $app); ?>">
        <i class="fas fa-cloud-arrow-up"></i> <?php echo $e(__('mobile_apps_updates_title')); ?>
        <?php if (!empty($platformUpdate['pending'])) { ?>
            <span class="badge text-bg-danger ms-1">!</span>
        <?php } ?>
    </a>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $e(rateb_url('admin/mobile-apps/branded-queue') . '?app=' . $app); ?>">
        <i class="fas fa-hammer"></i> <?php echo $e(__('mobile_branded_queue_link')); ?>
    </a>
    <?php } ?>
    <?php if ($consoleAccessible && $app === 'hr') { ?>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $e($consoleUrl ?? rateb_url('admin/hr-mobile')); ?>">
        <i class="fas fa-flask"></i> <?php echo $e(__('hr_mobile_nav')); ?>
    </a>
    <?php } ?>
    </div>
</div>

<?php if ($canToggleEnable && !empty($platformUpdate)) {
    $backUrl = rateb_url('admin/mobile-apps') . '?app=' . $app;
    require __DIR__ . '/_platform-update-alert.php';
} ?>

<?php if ($canToggleEnable) {
    $activeApp = $app;
    require __DIR__ . '/_tabs.php';
} ?>
</div>

<?php if ($canToggleEnable) { ?>
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

<?php if (!empty($activationDuplicates)) { ?>
<div class="alert alert-danger py-2 small" role="alert">
    <i class="fas fa-triangle-exclamation"></i>
    <?php echo $e(__('mobile_activation_duplicate_alert')); ?>
    <ul class="mb-0 mt-1">
        <?php foreach ($activationDuplicates as $dupe) { ?>
            <li dir="ltr"><code><?php echo $e($dupe['code'] ?? ''); ?></code>
                — <?php echo $e(__('mobile_activation_duplicate_companies')); ?>:
                #<?php echo $e(implode(', #', array_map('strval', $dupe['company_ids'] ?? []))); ?></li>
        <?php } ?>
    </ul>
</div>
<?php } ?>

<div class="rateb-card rateb-mobile-apps-companies-card">
    <div class="rateb-card-header"><?php echo $e(__('mobile_apps_companies')); ?></div>
    <div class="rateb-card-body pt-2 pb-0 px-3">
        <?php Rateb\App\Core\View::partial('table-search', ['mode' => 'client']); ?>
    </div>
    <div class="rateb-card-body p-0 pt-0">
        <div class="table-responsive rateb-mobile-apps-table-wrap" data-rateb-table-search-host="1">
        <table class="table table-sm rateb-table rateb-mobile-apps-companies-table align-middle mb-0">
            <thead>
            <tr>
                <th data-col-name="com_ref"><?php echo $e(__('mobile_apps_company_ref')); ?></th>
                <th><?php echo $e(__('company')); ?></th>
                <th><?php echo $e(__('status')); ?></th>
                <?php if ($canToggleEnable) { ?>
                <th><?php echo $e(__('mobile_activation_code')); ?></th>
                <th data-col-name="server"><?php echo $e(__('mobile_apps_server')); ?></th>
                <th><?php echo $e(__('mobile_apps_apk')); ?></th>
                <?php } ?>
                <th class="rateb-th-actions"><?php echo $e(__('actions')); ?></th>
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
                    <td class="font-monospace small" dir="ltr" data-col-name="com_ref"><?php echo $e(Company::publicRef($cid)); ?></td>
                    <td><?php echo $e($row['company_name'] ?? ''); ?></td>
                    <td>
                        <?php if ($active) { ?>
                            <span class="badge text-bg-success"><?php echo $e(__('mobile_apps_status_active')); ?></span>
                        <?php } else { ?>
                            <span class="badge text-bg-secondary"><?php echo $e(__('mobile_apps_status_inactive')); ?></span>
                        <?php } ?>
                    </td>
                    <?php if ($canToggleEnable) { ?>
                    <td class="small font-monospace" dir="ltr">
                        <?php if (($row['activation_code'] ?? '') !== '') { ?>
                            <span title="#<?php echo $e((string) $cid); ?>"><?php echo $e($row['activation_code']); ?></span>
                        <?php } else { ?>
                            <span class="text-muted">—</span>
                        <?php } ?>
                    </td>
                    <td class="small text-break" dir="ltr" data-col-name="server"><?php echo $e($row['server'] ?? ''); ?></td>
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
                    <td class="rateb-actions-cell text-nowrap">
                        <div class="rateb-actions">
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
                        <?php if ($canToggleEnable && !empty($row['needs_app_update'])) { ?>
                        <form method="post" action="<?php echo $e(rateb_url('admin/mobile-apps/' . $cid . '/app-update')); ?>" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?php echo $e($csrf ?? ''); ?>">
                            <input type="hidden" name="app" value="<?php echo $e($app); ?>">
                            <input type="hidden" name="back" value="<?php echo $e(rateb_url('admin/mobile-apps') . '?app=' . $app); ?>">
                            <button type="submit" class="btn btn-sm btn-warning" title="<?php echo $e(__('mobile_company_update_btn')); ?>">
                                <i class="fas fa-cloud-arrow-up"></i> <?php echo $e(__('mobile_company_update_btn_short')); ?>
                            </button>
                        </form>
                        <?php } ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo $e($manageUrl); ?>">
                            <i class="fas fa-sliders"></i> <?php echo $e($canManage ? __('mobile_apps_manage') : __('view')); ?>
                        </a>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
