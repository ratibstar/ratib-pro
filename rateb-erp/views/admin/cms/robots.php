<?php /** @var array<string, mixed>|null $item */ ?>
<div class="rateb-card"><div class="rateb-card-header"><?php echo __('cms_robots'); ?></div><div class="rateb-card-body">
<form method="post" action="<?php echo rateb_url('admin/cms/robots'); ?>">
<input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
<textarea class="form-control" name="content" rows="12"><?php echo Rateb\App\Core\View::escape($item['content'] ?? "User-agent: *\nAllow: /"); ?></textarea>
<button type="submit" class="btn btn-primary mt-3"><?php echo __('save'); ?></button>
</form></div></div>
