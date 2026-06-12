<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? __('device_maintenance')); ?></div>
    <div class="rateb-card-body">
        <?php if ($canManage ?? rateb_can_manage_entity('device-maintenance')) { ?>
        <form method="post" action="<?php echo rateb_app_url('device-maintenance'); ?>" class="row g-3 mb-4">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="col-md-4">
                <label class="form-label"><?php echo __('medical_devices'); ?></label>
                <select class="form-select" name="device_id" required>
                    <option value=""><?php echo __('select'); ?></option>
                    <?php foreach ($devices ?? [] as $d) { ?>
                    <option value="<?php echo (int) $d['id']; ?>"><?php echo Rateb\App\Core\View::escape($d['device_name'] ?? ''); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo __('service_date'); ?></label>
                <input class="form-control" type="date" name="service_date" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo __('service_type'); ?></label>
                <input class="form-control" type="text" name="service_type" value="maintenance">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo __('provider'); ?></label>
                <input class="form-control" type="text" name="provider">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo __('cost'); ?></label>
                <input class="form-control" type="number" step="0.01" name="cost">
            </div>
            <div class="col-12">
                <label class="form-label"><?php echo __('notes'); ?></label>
                <textarea class="form-control" name="notes" rows="2"></textarea>
            </div>
            <div class="col-12"><button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button></div>
        </form>
        <?php } ?>
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $items ?? [],
            'columns' => [
                ['name' => 'device_name', 'label' => 'medical_devices'],
                ['name' => 'service_date', 'label' => 'service_date'],
                ['name' => 'service_type', 'label' => 'service_type'],
                ['name' => 'provider', 'label' => 'provider'],
                ['name' => 'cost', 'label' => 'cost'],
            ],
        ]); ?>
    </div>
</div>
