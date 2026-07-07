<?php
declare(strict_types=1);
?>
<div class="rateb-pos-page">
    <h1 class="h3 mb-3"><?php echo \Rateb\App\Pos\Support\PosView::escape($title ?? ''); ?></h1>
    <div class="alert alert-secondary mb-3"><?php echo __('pos_scaffold_notice'); ?></div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-2"><?php echo __('pos_demo_setup_title'); ?></h2>
            <p class="text-muted mb-3"><?php echo __('pos_demo_setup_hint'); ?></p>
            <form method="post" action="<?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($demoSetupUrl ?? '')); ?>">
                <input type="hidden" name="_csrf" value="<?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($csrf ?? '')); ?>">
                <button type="submit" class="btn btn-primary">
                    <?php echo __('pos_demo_setup_action'); ?>
                </button>
            </form>
        </div>
    </div>
</div>
<link href="<?php echo rateb_pos_asset('css/pos-module.css'); ?>" rel="stylesheet">
