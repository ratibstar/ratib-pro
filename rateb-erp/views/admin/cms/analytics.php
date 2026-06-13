<?php /** @var array<string, mixed>|null $item */ ?>
<div class="rateb-card"><div class="rateb-card-header"><?php echo __('cms_analytics'); ?></div><div class="rateb-card-body">
<form method="post" action="<?php echo rateb_url('admin/cms/analytics'); ?>">
<input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
<div class="row g-3">
<div class="col-md-6"><label class="form-label">Google Analytics ID</label><input class="form-control" name="google_analytics_id" value="<?php echo Rateb\App\Core\View::escape($item['google_analytics_id'] ?? ''); ?>"></div>
<div class="col-md-6"><label class="form-label">Google Tag Manager ID</label><input class="form-control" name="google_tag_manager_id" value="<?php echo Rateb\App\Core\View::escape($item['google_tag_manager_id'] ?? ''); ?>"></div>
<div class="col-md-6"><label class="form-label">Meta Pixel ID</label><input class="form-control" name="meta_pixel_id" value="<?php echo Rateb\App\Core\View::escape($item['meta_pixel_id'] ?? ''); ?>"></div>
<div class="col-md-6"><label class="form-label">TikTok Pixel ID</label><input class="form-control" name="tiktok_pixel_id" value="<?php echo Rateb\App\Core\View::escape($item['tiktok_pixel_id'] ?? ''); ?>"></div>
<div class="col-12"><label class="form-label">Custom Head Code</label><textarea class="form-control" name="custom_head_code" rows="4"><?php echo Rateb\App\Core\View::escape($item['custom_head_code'] ?? ''); ?></textarea></div>
<div class="col-12"><label class="form-label">Custom Body Code</label><textarea class="form-control" name="custom_body_code" rows="4"><?php echo Rateb\App\Core\View::escape($item['custom_body_code'] ?? ''); ?></textarea></div>
</div>
<button type="submit" class="btn btn-primary mt-3"><?php echo __('save'); ?></button>
</form></div></div>
