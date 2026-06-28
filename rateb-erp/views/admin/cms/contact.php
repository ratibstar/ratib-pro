<?php /** @var array<string, mixed>|null $item */ ?>
<div class="rateb-card"><div class="rateb-card-header"><?php echo __('cms_contact'); ?></div><div class="rateb-card-body">
<form method="post" action="<?php echo rateb_url('admin/cms/contact'); ?>">
<input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
<div class="row g-3">
<div class="col-md-6"><label class="form-label"><?php echo __('email'); ?></label><input class="form-control" name="email" value="<?php echo Rateb\App\Core\View::escape($item['email'] ?? ''); ?>"></div>
<div class="col-md-6"><label class="form-label"><?php echo __('phone'); ?></label><input class="form-control" name="phone" value="<?php echo Rateb\App\Core\View::escape($item['phone'] ?? ''); ?>"></div>
<div class="col-md-6"><label class="form-label"><?php echo rateb_label('address_en'); ?></label><textarea class="form-control" name="address_en" rows="3"><?php echo Rateb\App\Core\View::escape($item['address_en'] ?? ''); ?></textarea></div>
<div class="col-md-6"><label class="form-label"><?php echo rateb_label('address_ar'); ?></label><textarea class="form-control" name="address_ar" rows="3"><?php echo Rateb\App\Core\View::escape($item['address_ar'] ?? ''); ?></textarea></div>
<div class="col-md-6"><label class="form-label"><?php echo rateb_label('working_hours_en'); ?></label><textarea class="form-control" name="working_hours_en" rows="2"><?php echo Rateb\App\Core\View::escape($item['working_hours_en'] ?? ''); ?></textarea></div>
<div class="col-md-6"><label class="form-label"><?php echo rateb_label('working_hours_ar'); ?></label><textarea class="form-control" name="working_hours_ar" rows="2"><?php echo Rateb\App\Core\View::escape($item['working_hours_ar'] ?? ''); ?></textarea></div>
<div class="col-12"><label class="form-label"><?php echo rateb_label('map_embed'); ?></label><textarea class="form-control" name="map_embed" rows="3"><?php echo Rateb\App\Core\View::escape($item['map_embed'] ?? ''); ?></textarea></div>
</div>
<button type="submit" class="btn btn-primary mt-3"><?php echo __('save'); ?></button>
<a href="<?php echo rateb_url('admin/cms/offices'); ?>" class="btn btn-outline-secondary mt-3 ms-2"><?php echo __('cms_offices'); ?></a>
</form></div></div>
