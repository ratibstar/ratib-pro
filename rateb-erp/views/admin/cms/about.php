<?php /** @var array<string, mixed>|null $about */ ?>
<div class="rateb-card"><div class="rateb-card-header"><?php echo __('cms_about'); ?></div><div class="rateb-card-body">
<form method="post" action="<?php echo rateb_url('admin/cms/about'); ?>">
<input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
<div class="row g-3">
<div class="col-md-6"><label class="form-label"><?php echo rateb_label('story_en'); ?></label><textarea class="form-control" name="story_en" rows="4"><?php echo Rateb\App\Core\View::escape($about['story_en'] ?? ''); ?></textarea></div>
<div class="col-md-6"><label class="form-label"><?php echo rateb_label('story_ar'); ?></label><textarea class="form-control" name="story_ar" rows="4"><?php echo Rateb\App\Core\View::escape($about['story_ar'] ?? ''); ?></textarea></div>
<div class="col-md-6"><label class="form-label"><?php echo rateb_label('vision_en'); ?></label><textarea class="form-control" name="vision_en" rows="3"><?php echo Rateb\App\Core\View::escape($about['vision_en'] ?? ''); ?></textarea></div>
<div class="col-md-6"><label class="form-label"><?php echo rateb_label('vision_ar'); ?></label><textarea class="form-control" name="vision_ar" rows="3"><?php echo Rateb\App\Core\View::escape($about['vision_ar'] ?? ''); ?></textarea></div>
<div class="col-md-6"><label class="form-label"><?php echo rateb_label('mission_en'); ?></label><textarea class="form-control" name="mission_en" rows="3"><?php echo Rateb\App\Core\View::escape($about['mission_en'] ?? ''); ?></textarea></div>
<div class="col-md-6"><label class="form-label"><?php echo rateb_label('mission_ar'); ?></label><textarea class="form-control" name="mission_ar" rows="3"><?php echo Rateb\App\Core\View::escape($about['mission_ar'] ?? ''); ?></textarea></div>
</div>
<button type="submit" class="btn btn-primary mt-3"><?php echo __('save'); ?></button>
</form>
<p class="mt-3"><a href="<?php echo rateb_url('admin/cms/team'); ?>" class="btn btn-outline-secondary btn-sm"><?php echo __('cms_team'); ?></a>
<a href="<?php echo rateb_url('admin/cms/timeline'); ?>" class="btn btn-outline-secondary btn-sm"><?php echo __('cms_timeline'); ?></a></p>
</div></div>
