<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? __('asset_assignments')); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo rateb_app_url('asset-assignments'); ?>" class="row g-3 mb-4">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="col-md-3">
                <label class="form-label"><?php echo __('assets'); ?></label>
                <select class="form-select" name="asset_id" required>
                    <option value=""><?php echo __('select'); ?></option>
                    <?php foreach ($assets ?? [] as $a) { ?>
                    <option value="<?php echo (int) $a['id']; ?>"><?php echo Rateb\App\Core\View::escape($a['name'] ?? ''); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo __('assigned_to'); ?></label>
                <input class="form-control" type="text" name="assigned_to" required>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo __('department'); ?></label>
                <input class="form-control" type="text" name="department">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo __('assigned_at'); ?></label>
                <input class="form-control" type="date" name="assigned_at" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-12">
                <label class="form-label"><?php echo __('notes'); ?></label>
                <textarea class="form-control" name="notes" rows="2"></textarea>
            </div>
            <div class="col-12"><button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button></div>
        </form>
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $items ?? [],
            'columns' => [
                ['name' => 'asset_name', 'label' => 'assets'],
                ['name' => 'assigned_to', 'label' => 'assigned_to'],
                ['name' => 'department', 'label' => 'department'],
                ['name' => 'assigned_at', 'label' => 'assigned_at'],
            ],
        ]); ?>
    </div>
</div>
