<div class="rateb-card"><div class="rateb-card-header d-flex justify-content-between"><span><?php echo __('cms_media'); ?></span>
<form method="post" action="<?php echo rateb_url('admin/cms/media/upload'); ?>" enctype="multipart/form-data" class="d-flex gap-2">
<input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
<input type="file" name="file" class="form-control form-control-sm" required>
<button type="submit" class="btn btn-primary btn-sm"><?php echo __('cms_upload'); ?></button>
</form></div>
<div class="rateb-card-body p-0">
<table class="table table-sm mb-0"><thead><tr><th>ID</th><th><?php echo __('name'); ?></th><th>MIME</th><th><?php echo __('size'); ?></th></tr></thead><tbody>
<?php foreach ($items as $row) { ?>
<tr><td><?php echo (int)$row['id']; ?></td><td><?php echo Rateb\App\Core\View::escape($row['file_name']); ?></td><td><?php echo Rateb\App\Core\View::escape($row['mime_type']); ?></td><td><?php echo (int)$row['file_size']; ?></td></tr>
<?php } ?>
</tbody></table></div></div>
