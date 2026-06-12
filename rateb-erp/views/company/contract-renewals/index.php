<div class="rateb-card mb-4">
    <div class="rateb-card-header"><?php echo __('contract_renewals'); ?></div>
    <div class="rateb-card-body">
        <?php if ($canManage ?? rateb_can_manage_entity('contract-renewals')) { ?>
        <form method="post" action="<?php echo rateb_app_url('contract-renewals'); ?>" class="row g-3 mb-4">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="col-md-4">
                <label class="form-label"><?php echo __('contracts'); ?></label>
                <select class="form-select" name="contract_id" required>
                    <option value=""><?php echo __('select'); ?></option>
                    <?php foreach ($contracts ?? [] as $c) { ?>
                    <option value="<?php echo (int) $c['id']; ?>"><?php echo Rateb\App\Core\View::escape(($c['contract_no'] ?? '') . ' — ' . ($c['title'] ?? '')); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo __('renewal_date'); ?></label>
                <input class="form-control" type="date" name="renewal_date" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo __('new_end_date'); ?></label>
                <input class="form-control" type="date" name="new_end_date">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo __('new_value'); ?></label>
                <input class="form-control" type="number" step="0.01" name="new_value">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo __('status'); ?></label>
                <select class="form-select" name="status">
                    <option value="planned">planned</option>
                    <option value="approved">approved</option>
                    <option value="completed">completed</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label"><?php echo __('notes'); ?></label>
                <textarea class="form-control" name="notes" rows="2"></textarea>
            </div>
            <div class="col-12"><button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button></div>
        </form>
        <?php } ?>
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $renewals ?? [],
            'columns' => [
                ['name' => 'contract_no', 'label' => 'contract_no'],
                ['name' => 'renewal_date', 'label' => 'renewal_date'],
                ['name' => 'new_end_date', 'label' => 'new_end_date'],
                ['name' => 'new_value', 'label' => 'new_value'],
                ['name' => 'status', 'label' => 'status'],
            ],
        ]); ?>
    </div>
</div>
<?php if (!empty($expiring)) { ?>
<div class="rateb-card">
    <div class="rateb-card-header text-warning"><i class="fas fa-exclamation-triangle"></i> <?php echo __('contract_expiry_alerts'); ?></div>
    <div class="rateb-card-body">
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $expiring,
            'columns' => [
                ['name' => 'contract_no', 'label' => 'contract_no'],
                ['name' => 'title', 'label' => 'title'],
                ['name' => 'supplier_name', 'label' => 'suppliers'],
                ['name' => 'end_date', 'label' => 'end_date'],
            ],
        ]); ?>
    </div>
</div>
<?php } ?>
