<div class="rateb-card mb-4">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo Rateb\App\Core\View::escape($title ?? __('device_warranty')); ?></span>
    </div>
    <div class="rateb-card-body">
        <?php if (!empty($due)) { ?>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> <?php echo __('warranty_expiring_soon'); ?></div>
        <?php Rateb\App\Core\View::partial('table-search', ['mode' => 'client']); ?>
        <div data-rateb-table-search-host="1">
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $due,
            'columns' => [
                ['name' => 'device_name', 'label' => 'medical_devices'],
                ['name' => 'serial_no', 'label' => 'serial_no'],
                ['name' => 'warranty_expiry', 'label' => 'warranty_expiry'],
            ],
        ]); ?>
        </div>
        <hr>
        <?php } ?>
        <h6 class="mb-3"><?php echo __('update_warranty'); ?></h6>
        <?php if ($canManage ?? rateb_can_manage_entity('device-warranty')) { ?>
        <?php foreach ($devices ?? [] as $d) { ?>
        <form method="post" action="<?php echo rateb_app_url('device-warranty/' . (int) $d['id']); ?>" class="row g-2 align-items-end mb-2">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="col-md-5"><strong><?php echo Rateb\App\Core\View::escape($d['device_name'] ?? ''); ?></strong></div>
            <div class="col-md-4">
                <input class="form-control form-control-sm" type="date" name="warranty_expiry" value="<?php echo Rateb\App\Core\View::escape($d['warranty_expiry'] ?? ''); ?>">
            </div>
            <div class="col-md-3"><button type="submit" class="btn btn-sm btn-primary"><?php echo __('save'); ?></button></div>
        </form>
        <?php } ?>
        <?php } ?>
    </div>
</div>
