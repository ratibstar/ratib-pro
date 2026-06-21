<?php
/** @var array<string, mixed>|null $item */
/** @var array<string, mixed> $lookups */
$item = $item ?? [];
?>
<div class="col-12">
    <div class="rateb-card border-secondary">
        <div class="rateb-card-header bg-secondary bg-opacity-10">
            <i class="fas fa-passport me-1"></i> <?php echo __('customs_clearance_section'); ?>
        </div>
        <div class="rateb-card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label rateb-form-label" for="f_customs_declaration_no"><?php echo __('customs_declaration_no'); ?></label>
                    <input class="form-control rateb-form-control" type="text" maxlength="80"
                           id="f_customs_declaration_no" name="customs_declaration_no"
                           value="<?php echo Rateb\App\Core\View::escape((string) ($item['customs_declaration_no'] ?? '')); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label rateb-form-label" for="f_customs_clearance_date"><?php echo __('customs_clearance_date'); ?></label>
                    <input class="form-control rateb-form-control" type="date"
                           id="f_customs_clearance_date" name="customs_clearance_date"
                           value="<?php echo Rateb\App\Core\View::escape((string) ($item['customs_clearance_date'] ?? '')); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label rateb-form-label" for="f_customs_broker_id"><?php echo __('customs_broker'); ?></label>
                    <select class="form-select rateb-form-control" id="f_customs_broker_id" name="customs_broker_id">
                        <option value="">—</option>
                        <?php foreach ($lookups['suppliers'] ?? [] as $opt) { ?>
                        <option value="<?php echo Rateb\App\Core\View::escape((string) ($opt['value'] ?? '')); ?>"<?php echo (string) ($item['customs_broker_id'] ?? '') === (string) ($opt['value'] ?? '') ? ' selected' : ''; ?>>
                            <?php echo Rateb\App\Core\View::escape((string) ($opt['label'] ?? '')); ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label rateb-form-label" for="f_customs_clearance_status"><?php echo __('customs_clearance_status'); ?></label>
                    <select class="form-select rateb-form-control" id="f_customs_clearance_status" name="customs_clearance_status">
                        <option value="">—</option>
                        <?php foreach ($lookups['customs_clearance_statuses'] ?? [] as $opt) { ?>
                        <option value="<?php echo Rateb\App\Core\View::escape((string) ($opt['value'] ?? '')); ?>"<?php echo (string) ($item['customs_clearance_status'] ?? '') === (string) ($opt['value'] ?? '') ? ' selected' : ''; ?>>
                            <?php echo Rateb\App\Core\View::escape((string) ($opt['label'] ?? '')); ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>
