<?php
declare(strict_types=1);
use Rateb\App\Core\View;
?>
<h1 class="h4 mb-3"><?php echo View::escape((string) ($title ?? '')); ?></h1>
<?php if (!empty($missing_release)): ?><p class="text-muted"><?php echo View::escape(__('auto_open_no_release')); ?></p><?php return; ?><?php endif; ?>
<div class="row g-3">
<?php foreach (($kinds ?? []) as $kind): ?>
    <div class="col-6 col-md-4">
        <a class="card h-100 text-decoration-none" href="<?php echo rateb_app_url('automotive/open-catalog/makes'); ?>?kind=<?php echo View::escape((string) $kind['kind']); ?>">
            <div class="card-body">
                <div><?php echo View::escape(__('auto_open_kind_' . $kind['kind'])); ?></div>
                <div class="h4 mb-0"><?php echo (int) $kind['models']; ?></div>
            </div>
        </a>
    </div>
<?php endforeach; ?>
</div>
