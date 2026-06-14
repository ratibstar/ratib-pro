<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<form method="post" action="<?php echo rateb_app_url('fiscal-periods'); ?>" class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label"><?php echo __('name'); ?></label>
                <input type="text" name="name" class="form-control" required maxlength="50"
                       placeholder="<?php echo date('Y'); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo __('date_from'); ?></label>
                <input type="date" name="start_date" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo __('date_to'); ?></label>
                <input type="date" name="end_date" class="form-control" required>
            </div>
        </div>
    </div>
    <div class="rateb-card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
        <a href="<?php echo rateb_app_url('fiscal-periods'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
    </div>
</form>
