<?php /** @var array<int, array<string, mixed>> $items */ ?>
<table class="table table-sm mb-0"><thead><tr><th><?php echo __('id'); ?></th><th><?php echo __('name'); ?></th><th><?php echo __('email'); ?></th><th><?php echo __('lead_type'); ?></th><th><?php echo __('status'); ?></th><th></th></tr></thead><tbody>
<?php foreach ($items as $row) { ?>
<tr><td><?php echo (int)$row['id']; ?></td><td><?php echo Rateb\App\Core\View::escape($row['name']); ?></td><td><?php echo Rateb\App\Core\View::escape($row['email']); ?></td><td><?php echo Rateb\App\Core\View::escape($row['lead_type']); ?></td><td><?php echo Rateb\App\Core\View::escape(rateb_enum_label((string) ($row['status'] ?? ''))); ?></td><td><a href="<?php echo rateb_url('admin/cms/leads/'.$row['id']); ?>"><?php echo __('view'); ?></a></td></tr>
<?php } ?></tbody></table>
