<?php
declare(strict_types=1);

/** @var string $app erp|customer */
/** @var array<string,string>|null $info */
/** @var array<string,mixed>|null $apk */
/** @var string $apkUrl */
/** @var string $apkQr */
/** @var string $csrf */
$app = $app ?? 'erp';
$info = is_array($info ?? null) ? $info : [];
$e = static fn ($v): string => Rateb\App\Core\View::escape((string) $v);
$activeApp = $app;
?>
<div class="mb-3">
    <h1 class="h4 mb-1"><?php echo $e(__('mobile_apps_title')); ?></h1>
    <p class="text-muted small mb-0"><?php echo $e(__('mobile_apps_intro_hub')); ?></p>
</div>

<?php require __DIR__ . '/_tabs.php'; ?>

<div class="rateb-card mb-3">
    <div class="rateb-card-header"><i class="fas fa-mobile-screen-button"></i> <?php echo $e(__('mobile_apps_tab_' . $app)); ?></div>
    <div class="rateb-card-body">
        <p class="small text-muted"><?php echo $e(__('mobile_apps_platform_' . $app . '_desc')); ?></p>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label small text-muted mb-1"><?php echo $e(__('mobile_apps_package_id')); ?></label>
                <input class="form-control form-control-sm font-monospace" dir="ltr" readonly value="<?php echo $e($info['package'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label small text-muted mb-1"><?php echo $e(__('mobile_apps_server_url')); ?></label>
                <input class="form-control form-control-sm" dir="ltr" readonly value="<?php echo $e($info['server'] ?? ''); ?>">
            </div>
            <div class="col-12">
                <label class="form-label small text-muted mb-1"><?php echo $e(__('mobile_apps_build_command_in')); ?> <code dir="ltr"><?php echo $e($info['build_dir'] ?? ''); ?></code></label>
                <input class="form-control form-control-sm font-monospace" dir="ltr" readonly onclick="this.select()" value="<?php echo $e($info['build_command'] ?? ''); ?>">
                <div class="form-text"><?php echo $e(__('mobile_apps_build_output')); ?> <code dir="ltr"><?php echo $e($info['output'] ?? ''); ?></code></div>
            </div>
        </div>

        <?php
        $apkChunkUrl = rateb_url('admin/mobile-apps/platform/' . $app . '/apk-chunk');
        $apkDeleteUrl = rateb_url('admin/mobile-apps/platform/' . $app . '/apk-delete');
        $apkInactive = false;
        require __DIR__ . '/_apk-panel.php';
        ?>
    </div>
</div>
