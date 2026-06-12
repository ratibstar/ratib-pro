<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? __('device_spare_parts')); ?></div>
    <div class="rateb-card-body">
        <?php if ($canManage ?? rateb_can_manage_entity('device-spare-parts')) { ?>
        <form method="post" action="<?php echo rateb_app_url('device-spare-parts'); ?>" class="row g-3 mb-4">
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
            <div class="col-md-3">
                <label class="form-label"><?php echo __('part_name'); ?></label>
                <input class="form-control" type="text" name="part_name" required>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo __('part_no'); ?></label>
                <input class="form-control" type="text" name="part_no">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo __('quantity'); ?></label>
                <input class="form-control" type="number" step="0.001" name="quantity">
            </div>
            <div class="col-md-1">
                <label class="form-label"><?php echo __('reorder_level'); ?></label>
                <input class="form-control" type="number" step="0.001" name="reorder_level">
            </div>
            <div class="col-12"><button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button></div>
        </form>
        <?php } ?>
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $items ?? [],
            'columns' => [
                ['name' => 'device_name', 'label' => 'medical_devices'],
                ['name' => 'part_name', 'label' => 'part_name'],
                ['name' => 'part_no', 'label' => 'part_no'],
                ['name' => 'quantity', 'label' => 'quantity'],
                ['name' => 'reorder_level', 'label' => 'reorder_level'],
            ],
        ]); ?>
    </div>
</div>
