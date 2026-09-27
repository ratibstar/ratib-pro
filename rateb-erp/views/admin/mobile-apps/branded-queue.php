<?php
declare(strict_types=1);

/** @var string $app */
/** @var list<array<string,mixed>> $rows */
/** @var list<array<string,mixed>> $actionable */
/** @var array<string,int> $platformVersions */
/** @var bool $dispatchEnabled */
/** @var bool $apiConfigured */
/** @var string $csrf */
$e = static fn ($v): string => Rateb\App\Core\View::escape((string) $v);
$stateLabels = [
    'pending' => ['text-bg-warning', 'mobile_branded_state_pending'],
    'outdated' => ['text-bg-info', 'mobile_branded_state_outdated'],
    'queued' => ['text-bg-primary', 'mobile_branded_state_queued'],
    'ready' => ['text-bg-success', 'mobile_branded_state_ready'],
];
?>
<div class="mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div>
        <a href="<?php echo $e(rateb_url('admin/mobile-apps') . '?app=' . $app); ?>" class="btn btn-sm btn-outline-secondary">&larr; <?php echo $e(__('mobile_apps_title')); ?></a>
        <h1 class="h4 mt-2 mb-0"><?php echo $e(__('mobile_branded_queue_title')); ?></h1>
        <p class="text-muted small mb-0"><?php echo $e(__('mobile_branded_queue_intro')); ?></p>
    </div>
    <?php if ($actionable !== []) { ?>
    <form method="post" action="<?php echo $e(rateb_url('admin/mobile-apps/branded-queue/' . $app . '/build-all')); ?>">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf); ?>">
        <button type="submit" class="btn btn-primary"><i class="fas fa-hammer"></i> <?php echo $e(__('mobile_branded_build_all_btn')); ?></button>
    </form>
    <?php } ?>
</div>

<?php
$activeApp = $app;
$tabsRoute = 'admin/mobile-apps/branded-queue';
require __DIR__ . '/_tabs.php';
?>

<div class="rateb-card mb-3">
    <div class="rateb-card-body small">
        <div class="mb-2"><?php echo $e(__('mobile_branded_queue_platform')); ?> <span dir="ltr" class="font-monospace"><?php echo $e((string) ($platformVersions[$app] ?? 0)); ?></span></div>
        <?php if (!$apiConfigured) { ?>
            <div class="alert alert-warning py-2 mb-0"><?php echo $e(__('mobile_branded_queue_no_secret')); ?></div>
        <?php } elseif (!$dispatchEnabled) { ?>
            <div class="alert alert-info py-2 mb-0"><?php echo $e(__('mobile_branded_queue_no_dispatch')); ?></div>
        <?php } else { ?>
            <div class="alert alert-success py-2 mb-0"><?php echo $e(__('mobile_branded_queue_automation_on')); ?></div>
        <?php } ?>
    </div>
</div>

<div class="rateb-card">
    <div class="rateb-card-header"><i class="fas fa-list"></i> <?php echo $e(__('mobile_branded_queue_list')); ?></div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th><?php echo $e(__('company')); ?></th>
                    <th><?php echo $e(__('mobile_branded_queue_state')); ?></th>
                    <th dir="ltr"><?php echo $e(__('mobile_apps_updates_version')); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rows === []) { ?>
                <tr><td colspan="4" class="text-muted"><?php echo $e(__('mobile_branded_queue_empty')); ?></td></tr>
            <?php } else { foreach ($rows as $row) {
                $st = $stateLabels[$row['state']] ?? $stateLabels['ready'];
                ?>
                <tr>
                    <td>
                        <a href="<?php echo $e(rateb_url('admin/mobile-apps/' . $row['company_id']) . ($row['app'] === 'hr' ? '' : '?app=' . $row['app'])); ?>"><?php echo $e($row['company_name']); ?></a>
                        <span class="text-muted small">#<?php echo (int) $row['company_id']; ?></span>
                    </td>
                    <td><span class="badge <?php echo $e($st[0]); ?>"><?php echo $e(__($st[1])); ?></span></td>
                    <td dir="ltr" class="font-monospace small">
                        <?php if ((int) $row['version_code'] > 0) {
                            echo $e($row['version'] . ' (' . $row['version_code'] . ')');
                        } else {
                            echo '—';
                        }
                        if ((int) $row['target_version_code'] > 0 && $row['state'] === 'outdated') {
                            echo ' → ' . $e((string) $row['target_version_code']);
                        } ?>
                    </td>
                    <td class="text-end">
                        <?php if (in_array($row['state'], ['pending', 'outdated', 'queued'], true)) { ?>
                        <form method="post" action="<?php echo $e(rateb_url('admin/mobile-apps/' . $row['company_id'] . '/branded-build')); ?>" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?php echo $e($csrf); ?>">
                            <input type="hidden" name="app" value="<?php echo $e($row['app']); ?>">
                            <input type="hidden" name="back" value="<?php echo $e(rateb_url('admin/mobile-apps/branded-queue') . '?app=' . $app); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-primary"><?php echo $e(__('mobile_branded_build_btn')); ?></button>
                        </form>
                        <?php } ?>
                    </td>
                </tr>
            <?php } } ?>
            </tbody>
        </table>
    </div>
</div>
