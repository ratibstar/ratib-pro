<?php /** @var array<string, mixed>|null $item */ ?>
<div class="rateb-card"><div class="rateb-card-header"><?php echo __('cms_theme'); ?></div><div class="rateb-card-body">
<form method="post" action="<?php echo rateb_url('admin/cms/theme'); ?>">
<input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
<div class="row g-3">
<div class="col-md-4"><label class="form-label"><?php echo rateb_label('primary_color'); ?></label><input class="form-control" name="primary_color" value="<?php echo Rateb\App\Core\View::escape($item['primary_color'] ?? '#1a5fb4'); ?>"></div>
<div class="col-md-4"><label class="form-label"><?php echo rateb_label('secondary_color'); ?></label><input class="form-control" name="secondary_color" value="<?php echo Rateb\App\Core\View::escape($item['secondary_color'] ?? '#3584e4'); ?>"></div>
<div class="col-md-4"><label class="form-label"><?php echo rateb_label('font_family'); ?></label><input class="form-control" name="font_family" value="<?php echo Rateb\App\Core\View::escape($item['font_family'] ?? 'Tajawal'); ?>"></div>
<div class="col-md-6"><label class="form-label"><?php echo rateb_label('logo_path'); ?></label><input class="form-control" name="logo_path" value="<?php echo Rateb\App\Core\View::escape($item['logo_path'] ?? ''); ?>"></div>
<div class="col-md-6"><label class="form-label"><?php echo rateb_label('favicon_path'); ?></label><input class="form-control" name="favicon_path" value="<?php echo Rateb\App\Core\View::escape($item['favicon_path'] ?? ''); ?>"></div>
<div class="col-12"><label class="form-label"><?php echo rateb_label('custom_css'); ?></label><textarea class="form-control" name="custom_css" rows="6"><?php echo Rateb\App\Core\View::escape($item['custom_css'] ?? ''); ?></textarea></div>
<div class="col-12"><label class="form-label"><?php echo rateb_label('custom_js'); ?></label><textarea class="form-control" name="custom_js" rows="4"><?php echo Rateb\App\Core\View::escape($item['custom_js'] ?? ''); ?></textarea></div>
</div>
<button type="submit" class="btn btn-primary mt-3"><?php echo __('save'); ?></button>
</form></div></div>
