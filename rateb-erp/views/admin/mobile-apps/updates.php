<?php
declare(strict_types=1);

/** @var string $app hr|erp|customer */
/** @var array{package:string, build_dir:string, file_prefix:string} $appInfo */
/** @var array<string,mixed>|null $published newest build shipped by deploy */
/** @var string $syncState updated|current|kept_upload|none|failed */
/** @var array<string,mixed>|null $sharedApk */
/** @var string $sharedUrl */
/** @var string $sharedQr */
/** @var list<array{company_id:int, company_name:string, active:bool, server:string, state:string}> $rows */
/** @var string $csrf */
$e = static fn ($v): string => Rateb\App\Core\View::escape((string) $v);
$app = $app ?? 'hr';
$rows = $rows ?? [];
$published = is_array($published ?? null) ? $published : null;
$sharedApk = is_array($sharedApk ?? null) ? $sharedApk : null;
$sharedIsPublished = $published !== null && $sharedApk !== null
    && hash_equals((string) ($sharedApk['sha256'] ?? ''), (string) $published['sha256']);
$stateBadges = [
    'shared' => ['text-bg-success', 'mobile_apps_updates_state_shared'],
    'own_current' => ['text-bg-info', 'mobile_apps_updates_state_own_current'],
    'own_outdated' => ['text-bg-warning', 'mobile_apps_updates_state_own_outdated'],
    'own_dedicated' => ['text-bg-info', 'mobile_apps_updates_state_own_dedicated'],
    'needs_own_build' => ['text-bg-secondary', 'mobile_apps_updates_state_needs_own_build'],
    'missing' => ['text-bg-danger', 'mobile_apps_updates_state_missing'],
];
$pushStates = ['own_outdated', 'own_current'];
$pushable = array_values(array_filter($rows, static fn (array $r): bool => in_array($r['state'], $pushStates, true)));
?>
<div class="mb-3">
    <h1 class="h4 mb-1"><?php echo $e(__('mobile_apps_updates_title')); ?></h1>
    <p class="text-muted small mb-0"><?php echo $e(__('mobile_apps_updates_intro')); ?></p>
</div>

<?php
$activeApp = $app;
$tabsRoute = 'admin/mobile-apps/updates';
require __DIR__ . '/_tabs.php';
?>

<div class="rateb-card mb-3">
    <div class="rateb-card-header">
        <i class="fas fa-box-open"></i> <?php echo $e(__('mobile_apps_updates_published_title')); ?>
        <span class="small text-muted ms-2" dir="ltr"><?php echo $e($appInfo['package'] ?? ''); ?></span>
    </div>
    <div class="rateb-card-body">
        <?php if ($published === null) { ?>
            <div class="alert alert-info py-2 small mb-0"><?php echo $e(__('mobile_apps_updates_published_none')); ?></div>
        <?php } else { ?>
            <dl class="row small mb-3">
                <?php if ($published['version'] !== '') { ?>
                <dt class="col-sm-3"><?php echo $e(__('mobile_apps_updates_version')); ?></dt>
                <dd class="col-sm-9" dir="ltr"><?php echo $e($published['version']); ?><?php echo $published['version_code'] > 0 ? ' (' . $e($published['version_code']) . ')' : ''; ?></dd>
                <?php } ?>
                <dt class="col-sm-3"><?php echo $e(__('mobile_apps_apk_file')); ?></dt>
                <dd class="col-sm-9" dir="ltr"><?php echo $e($published['file']); ?> — <?php echo $e(number_format($published['size'] / 1048576, 1)); ?> MB</dd>
                <dt class="col-sm-3"><?php echo $e(__('mobile_apps_updates_published_at')); ?></dt>
                <dd class="col-sm-9" dir="ltr"><?php echo $e($published['published_at']); ?></dd>
                <dt class="col-sm-3">SHA-256</dt>
                <dd class="col-sm-9 text-break font-monospace" dir="ltr" style="font-size:.75rem"><?php echo $e($published['sha256']); ?></dd>
                <dt class="col-sm-3"><?php echo $e(__('mobile_apps_updates_direct_link')); ?></dt>
                <dd class="col-sm-9 text-break" dir="ltr"><a href="<?php echo $e($published['url']); ?>" download><?php echo $e($published['url']); ?></a></dd>
            </dl>
            <?php if ($sharedIsPublished) { ?>
                <div class="alert alert-success py-2 small mb-0"><i class="fas fa-check"></i> <?php echo $e(__('mobile_apps_updates_shared_is_published')); ?></div>
            <?php } else { ?>
                <div class="alert alert-warning py-2 small mb-2"><?php echo $e(__($syncState === 'kept_upload' ? 'mobile_apps_updates_shared_kept_upload' : 'mobile_apps_updates_shared_differs')); ?></div>
                <form method="post" action="<?php echo $e(rateb_url('admin/mobile-apps/updates/' . $app . '/apply')); ?>"
                      onsubmit="return confirm(<?php echo $e((string) json_encode(__('mobile_apps_updates_apply_confirm'), JSON_UNESCAPED_UNICODE)); ?>);">
                    <input type="hidden" name="_csrf" value="<?php echo $e($csrf ?? ''); ?>">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-rotate"></i> <?php echo $e(__('mobile_apps_updates_apply_btn')); ?></button>
                </form>
            <?php } ?>
        <?php } ?>
    </div>
</div>

<div class="rateb-card mb-3">
    <div class="rateb-card-header">
        <i class="fas fa-share-nodes"></i> <?php echo $e(__('mobile_apps_shared_title')); ?>
        <?php if ($sharedApk !== null && !empty($sharedApk['version'])) { ?>
            <span class="badge text-bg-info ms-2" dir="ltr">v<?php echo $e($sharedApk['version']); ?></span>
        <?php } ?>
    </div>
    <div class="rateb-card-body">
        <p class="small text-muted"><?php echo $e(__('mobile_apps_shared_hint')); ?></p>
        <?php
        $apk = $sharedApk;
        $sharedApk = null;
        $apkUrl = (string) ($sharedUrl ?? '');
        $apkQr = (string) ($sharedQr ?? '');
        $apkChunkUrl = rateb_url('admin/mobile-apps/platform/' . $app . '/apk-chunk');
        $apkDeleteUrl = rateb_url('admin/mobile-apps/platform/' . $app . '/apk-delete');
        $apkDeleteFields = [];
        $apkInactive = false;
        $apkNoneKey = 'mobile_apps_apk_none_yet_app';
        require __DIR__ . '/_apk-panel.php';
        $e = static fn ($v): string => Rateb\App\Core\View::escape((string) $v);
        ?>
    </div>
</div>

<div class="rateb-card">
    <div class="rateb-card-header"><i class="fas fa-building"></i> <?php echo $e(__('mobile_apps_updates_companies_title')); ?></div>
    <div class="rateb-card-body">
        <p class="small text-muted"><?php echo $e(__('mobile_apps_updates_companies_hint')); ?></p>
        <form method="post" action="<?php echo $e(rateb_url('admin/mobile-apps/updates/' . $app . '/push')); ?>"
              onsubmit="return confirm(<?php echo $e((string) json_encode(__('mobile_apps_updates_push_confirm'), JSON_UNESCAPED_UNICODE)); ?>);">
            <input type="hidden" name="_csrf" value="<?php echo $e($csrf ?? ''); ?>">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-2">
                    <thead>
                    <tr>
                        <th style="width:2rem"></th>
                        <th><?php echo $e(__('company')); ?></th>
                        <th><?php echo $e(__('status')); ?></th>
                        <th><?php echo $e(__('mobile_apps_server')); ?></th>
                        <th><?php echo $e(__('mobile_apps_updates_build_state')); ?></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($rows === []) { ?>
                        <tr><td colspan="6" class="text-muted"><?php echo $e(__('mobile_apps_empty')); ?></td></tr>
                    <?php } ?>
                    <?php foreach ($rows as $row) {
                        [$badgeClass, $badgeKey] = $stateBadges[$row['state']] ?? ['text-bg-secondary', 'mobile_apps_updates_state_missing'];
                        $canPush = in_array($row['state'], $pushStates, true);
                        ?>
                        <tr>
                            <td>
                                <?php if ($canPush) { ?>
                                <input type="checkbox" class="form-check-input" name="company_ids[]" value="<?php echo (int) $row['company_id']; ?>"
                                    <?php echo $row['state'] === 'own_outdated' ? 'checked' : ''; ?>>
                                <?php } ?>
                            </td>
                            <td><?php echo $e($row['company_name']); ?></td>
                            <td>
                                <?php if ($row['active']) { ?>
                                    <span class="badge text-bg-success"><?php echo $e(__('mobile_apps_status_active')); ?></span>
                                <?php } else { ?>
                                    <span class="badge text-bg-secondary"><?php echo $e(__('mobile_apps_status_inactive')); ?></span>
                                <?php } ?>
                            </td>
                            <td class="small text-break" dir="ltr"><?php echo $e($row['server']); ?></td>
                            <td><span class="badge <?php echo $badgeClass; ?>"><?php echo $e(__($badgeKey)); ?></span></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?php echo $e(rateb_url('admin/mobile-apps/' . (int) $row['company_id']) . ($app === 'hr' ? '' : '?app=' . $app)); ?>">
                                    <i class="fas fa-sliders"></i> <?php echo $e(__('mobile_apps_manage')); ?>
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pushable !== []) { ?>
                <button type="submit" class="btn btn-primary"><i class="fas fa-cloud-arrow-up"></i> <?php echo $e(__('mobile_apps_updates_push_btn')); ?></button>
            <?php } else { ?>
                <div class="alert alert-success py-2 small mb-0"><i class="fas fa-check"></i> <?php echo $e(__('mobile_apps_updates_push_nothing')); ?></div>
            <?php } ?>
        </form>
    </div>
</div>
