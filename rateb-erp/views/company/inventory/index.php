<?php Rateb\App\Core\View::partial('crud-index', get_defined_vars()); ?>
<?php if (!empty($items)) { ?>
<div class="rateb-card mt-3">
    <div class="rateb-card-header"><?php echo __('barcode_qr'); ?></div>
    <div class="rateb-card-body">
        <?php foreach ($items as $row) { ?>
        <a class="btn btn-sm btn-outline-secondary me-1 mb-1" href="<?php echo rateb_url('company/inventory/' . (int) $row['id'] . '/codes'); ?>">
            <i class="fas fa-barcode"></i> <?php echo Rateb\App\Core\View::escape($row['item_name'] ?? ('#' . (int) $row['id'])); ?>
        </a>
        <?php } ?>
    </div>
</div>
<?php } ?>
