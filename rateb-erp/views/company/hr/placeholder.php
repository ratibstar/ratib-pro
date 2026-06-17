<?php
/** @var string $pageTitle */
/** @var string $pageDescription */
/** @var string $hrActive */
Rateb\App\Core\View::partial('hr-shell-start', ['hrActive' => $hrActive ?? '']);
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($pageTitle ?? ''); ?></div>
    <div class="rateb-card-body rateb-hr-placeholder">
        <i class="fas fa-hard-hat"></i>
        <h5 class="mb-2"><?php echo Rateb\App\Core\View::escape($pageTitle ?? ''); ?></h5>
        <p class="text-muted mb-0"><?php echo Rateb\App\Core\View::escape($pageDescription ?? __('hr_coming_soon')); ?></p>
    </div>
</div>
<?php Rateb\App\Core\View::partial('hr-shell-end'); ?>
