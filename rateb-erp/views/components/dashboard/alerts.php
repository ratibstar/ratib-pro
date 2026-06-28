<?php
/** @var array<int, array<string, mixed>> $alerts */
/** @var string $empty */
$alerts = $alerts ?? [];
$empty = $empty ?? __('dashboard_no_alerts');
?>
<div class="rp-alerts">
    <div class="rp-alerts__head"><?php echo __('smart_alerts'); ?></div>
    <?php if ($alerts === []) { ?>
    <p class="rp-empty"><?php echo Rateb\App\Core\View::escape($empty); ?></p>
    <?php } else { foreach ($alerts as $alert) { ?>
    <a href="<?php echo Rateb\App\Core\View::escape((string) ($alert['url'] ?? '#')); ?>" class="rp-alert">
        <span><?php echo Rateb\App\Core\View::escape((string) ($alert['message'] ?? '')); ?></span>
        <?php if (!empty($alert['count'])) { ?>
        <span class="rp-alert__num"><?php echo (int) $alert['count']; ?></span>
        <?php } ?>
    </a>
    <?php } } ?>
</div>
