<div class="rateb-card"><div class="rateb-card-header"><?php echo __('cms_leads'); ?></div><div class="rateb-card-body p-0">
<table class="table table-sm mb-0"><thead><tr><th>ID</th><th><?php echo __('name'); ?></th><th><?php echo __('email'); ?></th><th>Type</th><th><?php echo __('status'); ?></th><th></th></tr></thead><tbody>
<?php foreach ($items as $row) { ?>
<tr>
<td><?php echo (int)$row['id']; ?></td>
<td><?php echo Rateb\App\Core\View::escape($row['name']); ?></td>
<td><?php echo Rateb\App\Core\View::escape($row['email']); ?></td>
<td><?php echo Rateb\App\Core\View::escape($row['lead_type']); ?></td>
<td><?php echo Rateb\App\Core\View::escape($row['status']); ?></td>
<td><a href="<?php echo rateb_url('admin/cms/leads/' . (int)$row['id']); ?>" class="btn btn-sm btn-outline-primary"><?php echo __('view'); ?></a></td>
</tr>
<?php } ?>
</tbody></table></div></div>
