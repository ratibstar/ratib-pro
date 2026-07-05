<?php
declare(strict_types=1);
?>
<link href="<?php echo rateb_pos_asset('css/pos-module.css'); ?>" rel="stylesheet">
<div class="rateb-pos-page mb-2 d-flex justify-content-end">
    <?php if (!empty($createEnabled)) { ?>
    <a href="<?php echo rateb_app_url('pos/shifts/open'); ?>" class="btn btn-primary btn-sm">
        <i class="fas fa-play"></i> <?php echo __('pos_shift_open'); ?>
    </a>
    <?php } ?>
</div>
<?php
$createEnabled = false;
\Rateb\App\Core\View::partial('crud-index', get_defined_vars());
