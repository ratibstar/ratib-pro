<?php
declare(strict_types=1);
use Rateb\App\Core\View;
$release = $release ?? [];
$kinds = $kinds ?? [];
$total = 0;
foreach ($kinds as $kind) {
    $total += (int) $kind['models'];
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?php echo View::escape((string) ($title ?? '')); ?></h1>
</div>
<?php if (!empty($missing_release)): ?><p class="text-muted"><?php echo View::escape(__('auto_open_no_release')); ?></p><?php return; ?><?php endif; ?>
<p class="text-muted"><?php echo View::escape(__('auto_open_note')); ?></p>
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3"><div class="text-muted small"><?php echo View::escape(__('auto_open_source')); ?></div><div><?php echo View::escape((string) ($release['provider_code'] ?? '') . ' ' . (string) ($release['version_label'] ?? '')); ?></div></div>
            <div class="col-md-3"><div class="text-muted small"><?php echo View::escape(__('auto_open_retrieved')); ?></div><div><?php echo View::escape((string) ($release['retrieved_on'] ?? '')); ?></div></div>
            <div class="col-md-3"><div class="text-muted small"><?php echo View::escape(__('auto_open_count')); ?></div><div><?php echo (int) $total; ?></div></div>
            <div class="col-md-12"><div class="text-muted small"><?php echo View::escape(__('auto_open_sources')); ?></div><div><?php echo View::escape((string) ($release['attribution'] ?? '')); ?></div></div>
        </div>
    </div>
</div>
<div class="row g-3">
<?php foreach ($kinds as $kind): ?>
    <div class="col-6 col-md-4">
        <a class="card h-100 text-decoration-none" href="<?php echo rateb_app_url('automotive/open-catalog/makes'); ?>?kind=<?php echo View::escape((string) $kind['kind']); ?>">
            <div class="card-body">
                <div class="text-muted small"><?php echo View::escape(__('auto_open_kind_' . $kind['kind'])); ?></div>
                <div class="h4 mb-0"><?php echo (int) $kind['models']; ?></div>
            </div>
        </a>
    </div>
<?php endforeach; ?>
</div>
