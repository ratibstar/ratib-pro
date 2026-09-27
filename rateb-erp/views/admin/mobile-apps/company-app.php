<?php
declare(strict_types=1);

/** @var string $app erp|customer */
/** @var array<string,mixed> $company */
/** @var array<string,mixed> $appCard */
/** @var string $csrf */
$e = static fn ($v): string => Rateb\App\Core\View::escape((string) $v);
$cid = (int) ($company['id'] ?? 0);
?>
<div class="mb-3">
    <a href="<?php echo $e(rateb_url('admin/mobile-apps') . '?app=' . $app); ?>" class="btn btn-sm btn-outline-secondary">
        &larr; <?php echo $e(__('mobile_apps_tab_' . $app)); ?>
    </a>
</div>

<div class="alert alert-secondary py-2 small mb-3" role="note">
    <?php echo $e(__('mobile_unified_platform_note')); ?>
</div>

<p class="mb-3">
    <strong><?php echo $e(__('company')); ?>:</strong>
    <?php echo $e($company['name'] ?? ''); ?>
    <span class="text-muted small">#<?php echo $cid; ?></span>
</p>

<?php require __DIR__ . '/_company-app-card.php'; ?>
