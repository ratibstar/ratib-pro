<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? __('asset_depreciation')); ?></div>
    <div class="rateb-card-body">
        <?php if ($canManage ?? rateb_can_manage_entity('asset-depreciation')) { ?>
        <form method="post" action="<?php echo rateb_app_url('asset-depreciation'); ?>" class="row g-3 mb-4">
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
                <label class="form-label"><?php echo __('period_date'); ?></label>
                <input class="form-control" type="date" name="period_date" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo __('depreciation_amount'); ?></label>
                <input class="form-control" type="number" step="0.01" name="amount" required>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo __('book_value'); ?></label>
                <input class="form-control" type="number" step="0.01" name="book_value" required>
            </div>
            <div class="col-12"><button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button></div>
        </form>
        <?php } ?>
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $items ?? [],
            'columns' => [
                ['name' => 'asset_name', 'label' => 'assets'],
                ['name' => 'period_date', 'label' => 'period_date'],
                ['name' => 'amount', 'label' => 'depreciation_amount'],
                ['name' => 'book_value', 'label' => 'book_value'],
            ],
        ]); ?>
    </div>
</div>
