<?php
declare(strict_types=1);

/** @var int $articleCount */
/** @var int $moduleCount */
/** @var string $helpHomeUrl */

use Rateb\App\Core\View;
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/help-center.css'); ?>">
<div class="hc-page hc-admin-page">
    <?php View::partial('help/breadcrumb', [
        'crumbs' => [
            ['label' => __('help_center'), 'url' => $helpHomeUrl],
            ['label' => __('help_admin_title'), 'url' => null],
        ],
    ]); ?>

    <header class="hc-module-hero">
        <span class="hc-module-hero__icon" aria-hidden="true"><i class="fas fa-pen-to-square"></i></span>
        <div>
            <h2><?php echo View::escape(__('help_admin_title')); ?></h2>
            <p><?php echo View::escape(__('help_admin_intro')); ?></p>
        </div>
    </header>

    <div class="hc-admin-stats">
        <div class="hc-panel"><strong><?php echo (int) $moduleCount; ?></strong><span><?php echo View::escape(__('help_modules_title')); ?></span></div>
        <div class="hc-panel"><strong><?php echo (int) $articleCount; ?></strong><span><?php echo View::escape(__('help_articles')); ?></span></div>
    </div>

    <section class="hc-panel">
        <h3><?php echo View::escape(__('help_admin_architecture')); ?></h3>
        <ul class="hc-start-list">
            <li><?php echo View::escape(__('help_admin_arch_1')); ?></li>
            <li><?php echo View::escape(__('help_admin_arch_2')); ?></li>
            <li><?php echo View::escape(__('help_admin_arch_3')); ?></li>
            <li><?php echo View::escape(__('help_admin_arch_4')); ?></li>
        </ul>
        <p class="text-muted mb-0"><?php echo View::escape(__('help_admin_arch_note')); ?></p>
    </section>
</div>
