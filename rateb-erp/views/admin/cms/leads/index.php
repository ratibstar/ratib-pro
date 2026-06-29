<?php /** @var array<int, array<string, mixed>> $items */ ?>
<div class="table-responsive">
<table class="table table-sm table-hover mb-0 align-middle">
<thead>
<tr>
    <th><?php echo __('id'); ?></th>
    <th><?php echo __('name'); ?></th>
    <th><?php echo __('email'); ?></th>
    <th><?php echo __('lead_type'); ?></th>
    <th><?php echo __('message'); ?></th>
    <th><?php echo __('status'); ?></th>
    <th><?php echo __('created_at'); ?></th>
    <th></th>
</tr>
</thead>
<tbody>
<?php foreach ($items as $row) {
    $isNew = ($row['status'] ?? '') === 'new';
    $typeKey = match ((string) ($row['lead_type'] ?? '')) {
        'demo' => 'cms_lead_type_demo',
        'quote' => 'cms_lead_type_quote',
        'contact' => 'cms_lead_type_contact',
        default => '',
    };
    $typeLabel = $typeKey !== '' ? __($typeKey) : Rateb\App\Core\View::escape((string) ($row['lead_type'] ?? ''));
    $preview = trim((string) ($row['message'] ?? ''));
    if (mb_strlen($preview) > 60) {
        $preview = mb_substr($preview, 0, 60) . '…';
    }
    ?>
<tr class="<?php echo $isNew ? 'table-warning' : ''; ?>">
    <td><?php echo (int) $row['id']; ?></td>
    <td>
        <?php if ($isNew) { ?><span class="badge bg-danger me-1"><?php echo __('new'); ?></span><?php } ?>
        <?php echo Rateb\App\Core\View::escape($row['name']); ?>
    </td>
    <td><a href="mailto:<?php echo Rateb\App\Core\View::escape($row['email']); ?>"><?php echo Rateb\App\Core\View::escape($row['email']); ?></a></td>
    <td><span class="badge bg-secondary"><?php echo $typeLabel; ?></span></td>
    <td class="text-muted small"><?php echo Rateb\App\Core\View::escape($preview); ?></td>
    <td><?php echo Rateb\App\Core\View::escape(rateb_enum_label((string) ($row['status'] ?? ''))); ?></td>
    <td class="small text-muted"><?php echo Rateb\App\Core\View::formatDate($row['created_at'] ?? ''); ?></td>
    <td><a href="<?php echo rateb_url('admin/cms/leads/' . (int) $row['id']); ?>" class="btn btn-sm btn-primary"><?php echo __('view'); ?></a></td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
