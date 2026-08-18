<?php
declare(strict_types=1);

/** @var array<string, mixed> $context */
/** @var array<string, string> $hardware */
?>
<div class="rateb-pos-page rateb-pos-dashboard">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo \Rateb\App\Pos\Support\PosView::escape($title ?? __('pos_dashboard')); ?></h1>
        <?php
        $posOpenDash = function_exists('rateb_pos_register_open_form_parts') ? rateb_pos_register_open_form_parts() : ['action' => rateb_app_url('pos/register'), 'fields' => ['rateb_live' => '1']];
        ?>
        <form method="get" action="<?php echo \Rateb\App\Pos\Support\PosView::escape((string) $posOpenDash['action']); ?>" class="rateb-pos-register-open d-inline" data-pos-open-register="1">
            <?php foreach ($posOpenDash['fields'] as $posFieldName => $posFieldValue) { ?>
            <input type="hidden" name="<?php echo \Rateb\App\Pos\Support\PosView::escape((string) $posFieldName); ?>" value="<?php echo \Rateb\App\Pos\Support\PosView::escape((string) $posFieldValue); ?>">
            <?php } ?>
            <button type="submit" class="btn btn-primary"><?php echo __('pos_open_register'); ?></button>
        </form>
    </div>
    <div class="alert alert-info"><?php echo __('pos_scaffold_notice'); ?></div>
    <div class="row g-3">
        <div class="col-md-6 col-lg-3">
            <a class="rateb-pos-card-link" href="<?php echo rateb_app_url('pos/terminals'); ?>"><?php echo __('pos_manage_terminals'); ?></a>
        </div>
        <div class="col-md-6 col-lg-3">
            <a class="rateb-pos-card-link" href="<?php echo rateb_app_url('pos/orders'); ?>"><?php echo __('pos_view_orders'); ?></a>
        </div>
        <div class="col-md-6 col-lg-3">
            <a class="rateb-pos-card-link" href="<?php echo rateb_app_url('pos/sync'); ?>"><?php echo __('pos_sync_status'); ?></a>
        </div>
        <div class="col-md-6 col-lg-3">
            <span class="rateb-pos-card-link rateb-pos-card-link--muted"><?php echo __('pos_hardware'); ?></span>
        </div>
    </div>
</div>
<link href="<?php echo rateb_pos_asset('css/pos-module.css'); ?>" rel="stylesheet">
