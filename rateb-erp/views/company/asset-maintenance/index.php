<?php
$fields = [
    ['name' => 'asset_id', 'label' => 'assets', 'type' => 'select_asset'],
    ['name' => 'maintenance_type', 'label' => 'maintenance_type', 'type' => 'text'],
    ['name' => 'scheduled_date', 'label' => 'scheduled_date', 'type' => 'date'],
    ['name' => 'cost', 'label' => 'cost', 'type' => 'number'],
    ['name' => 'status', 'label' => 'status', 'type' => 'select', 'options' => ['scheduled', 'in_progress', 'completed']],
    ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea'],
];
$action = rateb_url('company/asset-maintenance');
?>
<div class="rateb-card mb-4">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? __('asset_maintenance')); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo $action; ?>" class="row g-3 mb-4">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="col-md-4">
                <label class="form-label"><?php echo __('assets'); ?></label>
                <select class="form-select" name="asset_id" required>
                    <option value=""><?php echo __('select'); ?></option>
                    <?php foreach ($assets ?? [] as $a) { ?>
                    <option value="<?php echo (int) $a['id']; ?>"><?php echo Rateb\App\Core\View::escape($a['name'] ?? ''); ?></option>
                    <?php } ?>
                </select>
            </div>
            <?php foreach (array_slice($fields, 1) as $field) {
                $name = $field['name'];
                $type = $field['type'] ?? 'text';
                ?>
            <div class="col-md-4">
                <label class="form-label"><?php echo __( $field['label']); ?></label>
                <?php if ($type === 'textarea') { ?>
                <textarea class="form-control" name="<?php echo $name; ?>" rows="2"></textarea>
                <?php } elseif ($type === 'select') { ?>
                <select class="form-select" name="<?php echo $name; ?>">
                    <?php foreach ($field['options'] ?? [] as $opt) { ?>
                    <option value="<?php echo Rateb\App\Core\View::escape($opt); ?>"><?php echo Rateb\App\Core\View::escape($opt); ?></option>
                    <?php } ?>
                </select>
                <?php } else { ?>
                <input class="form-control" type="<?php echo Rateb\App\Core\View::escape($type); ?>" name="<?php echo $name; ?>">
                <?php } ?>
            </div>
            <?php } ?>
            <div class="col-12"><button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button></div>
        </form>
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $items ?? [],
            'columns' => [
                ['name' => 'asset_name', 'label' => 'assets'],
                ['name' => 'maintenance_type', 'label' => 'maintenance_type'],
                ['name' => 'scheduled_date', 'label' => 'scheduled_date'],
                ['name' => 'cost', 'label' => 'cost'],
                ['name' => 'status', 'label' => 'status'],
            ],
        ]); ?>
    </div>
</div>
