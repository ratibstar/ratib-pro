<?php
/** @var array<int, array<string, mixed>> $alerts */
/** @var string $empty */
$alerts = $alerts ?? [];
$empty = $empty ?? __('dashboard_no_alerts');
?>
<div class="nx-glass">
    <div class="nx-glass__top">
        <span class="nx-glass__title"><?php echo __('smart_alerts'); ?></span>
    </div>
    <?php if ($alerts === []) { ?>
    <p class="nx-zero"><?php echo Rateb\App\Core\View::escape($empty); ?></p>
    <?php } else { ?>
    <div class="nx-alerts">
        <?php foreach ($alerts as $alert) { ?>
        <a href="<?php echo Rateb\App\Core\View::escape((string) ($alert['url'] ?? '#')); ?>" class="nx-alert">
            <span><?php echo Rateb\App\Core\View::escape((string) ($alert['message'] ?? '')); ?></span>
            <?php if (!empty($alert['count'])) { ?>
            <span class="nx-alert__n"><?php echo (int) $alert['count']; ?></span>
            <?php } ?>
        </a>
        <?php } ?>
    </div>
    <?php } ?>
</div>
