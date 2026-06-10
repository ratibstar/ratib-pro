<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('settings'); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo rateb_url('admin/settings'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <?php foreach ($items ?? [] as $item) { ?>
            <div class="row g-2 mb-2 align-items-center">
                <div class="col-md-4"><input class="form-control" name="setting_key[]" value="<?php echo Rateb\App\Core\View::escape($item['setting_key']); ?>" readonly></div>
                <div class="col-md-8"><input class="form-control" name="setting_value[]" value="<?php echo Rateb\App\Core\View::escape($item['setting_value']); ?>"></div>
            </div>
            <?php } ?>
            <button type="submit" class="btn btn-primary mt-3"><?php echo __('save'); ?></button>
        </form>
    </div>
</div>
