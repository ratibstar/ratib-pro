<?php
/** @var array<int, array<string, mixed>> $alerts */
/** @var string $empty */
$alerts = $alerts ?? [];
$empty = $empty ?? __('dashboard_no_alerts');
?>
<?php if ($alerts === []) { ?>
<?php if (!empty($showEmpty)) { ?>
<p class="cm-empty"><?php echo Rateb\App\Core\View::escape($empty); ?></p>
<?php } ?>
<?php } else { ?>
<div class="cm-ticker" role="region" aria-label="<?php echo __('smart_alerts'); ?>">
    <span class="cm-ticker__label"><?php echo __('smart_alerts'); ?></span>
    <div class="cm-ticker__track">
        <?php foreach ($alerts as $alert) { ?>
        <a href="<?php echo Rateb\App\Core\View::escape((string) ($alert['url'] ?? '#')); ?>" class="cm-ticker__item">
            <span><?php echo Rateb\App\Core\View::escape((string) ($alert['message'] ?? '')); ?></span>
            <?php if (!empty($alert['count'])) { ?>
            <span class="cm-ticker__n"><?php echo (int) $alert['count']; ?></span>
            <?php } ?>
        </a>
        <?php } ?>
    </div>
</div>
<?php } ?>
