<?php
declare(strict_types=1);

/**
 * @var string $app
 * @var array{pending:bool,shared_apply:bool,outdated_count:int,version:string,version_code:int} $platformUpdate
 * @var string $csrf
 * @var string $backUrl
 * @var bool $showUpToDate
 */
$e = static fn ($v): string => Rateb\App\Core\View::escape((string) $v);
$platformUpdate = is_array($platformUpdate ?? null) ? $platformUpdate : ['pending' => false];
$backUrl = (string) ($backUrl ?? rateb_url('admin/mobile-apps') . '?app=' . ($app ?? 'hr'));
$app = (string) ($app ?? 'hr');
$showUpToDate = !empty($showUpToDate);
if (empty($platformUpdate['pending'])) {
    if ($showUpToDate) {
        ?>
<div class="alert alert-success py-2 small mb-3 d-flex align-items-center gap-2" role="status">
    <i class="fas fa-circle-check"></i>
    <span><?php echo $e(__('mobile_platform_update_up_to_date')); ?></span>
</div>
        <?php
    }
    return;
}
$verLabel = ($platformUpdate['version'] ?? '') !== ''
    ? (string) $platformUpdate['version'] . (($platformUpdate['version_code'] ?? 0) > 0 ? ' (' . (int) $platformUpdate['version_code'] . ')' : '')
    : (string) (int) ($platformUpdate['version_code'] ?? 0);
?>
<div class="alert alert-warning border-warning mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2" role="alert">
    <div class="small">
        <div class="fw-semibold mb-1"><i class="fas fa-bell"></i> <?php echo $e(__('mobile_platform_update_notice_title')); ?></div>
        <div><?php echo $e(sprintf(__('mobile_platform_update_notice_body'), $verLabel)); ?></div>
        <?php if (!empty($platformUpdate['shared_apply'])) { ?>
            <div class="text-muted"><?php echo $e(__('mobile_platform_update_notice_shared')); ?></div>
        <?php } ?>
        <?php if ((int) ($platformUpdate['outdated_count'] ?? 0) > 0) { ?>
            <div class="text-muted"><?php echo $e(sprintf(__('mobile_platform_update_notice_companies'), (int) $platformUpdate['outdated_count'])); ?></div>
        <?php } ?>
    </div>
    <form method="post" action="<?php echo $e(rateb_url('admin/mobile-apps/updates/' . $app . '/apply-platform')); ?>" class="m-0">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf ?? ''); ?>">
        <input type="hidden" name="back" value="<?php echo $e($backUrl); ?>">
        <button type="submit" class="btn btn-warning fw-semibold">
            <i class="fas fa-cloud-arrow-up"></i> <?php echo $e(__('mobile_platform_update_btn')); ?>
        </button>
    </form>
</div>
