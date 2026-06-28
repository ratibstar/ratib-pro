<?php
/** @var array<int, array<string, mixed>> $alerts */
/** @var string $empty */
$alerts = $alerts ?? [];
$empty = $empty ?? __('dashboard_no_alerts');
?>
<div class="rdx-card">
    <div class="rdx-card-head"><?php echo __('smart_alerts'); ?></div>
    <div class="rdx-card-body rdx-card-body--flush">
        <?php if ($alerts === []) { ?>
        <p class="rdx-empty"><?php echo Rateb\App\Core\View::escape($empty); ?></p>
        <?php } else { foreach ($alerts as $alert) { ?>
        <a href="<?php echo Rateb\App\Core\View::escape((string) ($alert['url'] ?? '#')); ?>" class="rdx-alert">
            <span class="rdx-alert-ico"><i class="fas <?php echo Rateb\App\Core\View::escape((string) ($alert['icon'] ?? 'fa-bell')); ?>"></i></span>
            <span class="rdx-alert-txt"><?php echo Rateb\App\Core\View::escape((string) ($alert['message'] ?? '')); ?></span>
            <?php if (!empty($alert['count'])) { ?>
            <span class="rdx-alert-num"><?php echo (int) $alert['count']; ?></span>
            <?php } ?>
        </a>
        <?php } } ?>
    </div>
</div>
