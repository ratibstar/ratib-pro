<?php
/** @var array<string, mixed>|null $item */
/** @var array<int, array<string, mixed>> $fields */
/** @var array<string, list<array{value: string|int, label: string}>> $lookups */
/** @var array<int, array<string, mixed>>|null $existingDocuments */
$item = $item ?? [];
$lookups = $lookups ?? [];
$fields = $fields ?? [];
$responsibleDefault = $responsibleDefault ?? '';
$showAttachments = !empty($showAttachments);
$existingDocuments = $existingDocuments ?? [];

$defaults = [
    'comm_date' => date('Y-m-d'),
    'comm_status' => 'new',
    'follow_up_priority' => 'medium',
    'channel' => 'phone',
    'responsible_name' => $responsibleDefault,
];
?>
<div class="rateb-sc-form-section">
    <div class="rateb-sc-section-title"><?php echo __('comm_section_basic'); ?></div>
    <div class="row g-3">
        <?php foreach ($fields as $field) {
            $name = (string) $field['name'];
            if (!in_array($name, ['supplier_id', 'channel', 'comm_date', 'comm_time', 'comm_status', 'subject', 'details', 'body'], true)) {
                continue;
            }
            $value = $item[$name] ?? ($defaults[$name] ?? ($field['default'] ?? ''));
            Rateb\App\Core\View::partial('form-field', ['field' => $field, 'value' => $value, 'lookups' => $lookups]);
        } ?>
    </div>
</div>
<div class="rateb-sc-form-section">
    <div class="rateb-sc-section-title"><?php echo __('comm_section_contacts'); ?></div>
    <div class="row g-3">
        <?php foreach ($fields as $field) {
            $name = (string) $field['name'];
            if (!in_array($name, ['responsible_name', 'supplier_contact', 'supplier_phone', 'supplier_email'], true)) {
                continue;
            }
            $value = $item[$name] ?? ($defaults[$name] ?? ($field['default'] ?? ''));
            Rateb\App\Core\View::partial('form-field', ['field' => $field, 'value' => $value, 'lookups' => $lookups]);
        } ?>
    </div>
</div>
<div class="rateb-sc-form-section">
    <div class="rateb-sc-section-title"><?php echo __('comm_section_followup'); ?></div>
    <div class="row g-3">
        <?php foreach ($fields as $field) {
            $name = (string) $field['name'];
            if (!in_array($name, ['follow_up_date', 'follow_up_priority'], true)) {
                continue;
            }
            $value = $item[$name] ?? ($defaults[$name] ?? ($field['default'] ?? ''));
            Rateb\App\Core\View::partial('form-field', ['field' => $field, 'value' => $value, 'lookups' => $lookups]);
        } ?>
    </div>
</div>
<div class="rateb-sc-form-section">
    <div class="rateb-sc-section-title"><?php echo __('comm_section_links'); ?></div>
    <div class="row g-3">
        <?php foreach ($fields as $field) {
            $name = (string) $field['name'];
            if (!in_array($name, ['purchase_order_id', 'rfq_id'], true)) {
                continue;
            }
            $value = $item[$name] ?? '';
            Rateb\App\Core\View::partial('form-field', ['field' => $field, 'value' => $value, 'lookups' => $lookups]);
        } ?>
    </div>
</div>
<?php if ($showAttachments) { ?>
<div class="rateb-sc-form-section">
    <div class="rateb-sc-section-title"><?php echo __('comm_attachments'); ?></div>
    <p class="text-muted small mb-2"><?php echo __('comm_attachments_hint'); ?></p>
    <input class="form-control rateb-form-control" type="file" name="comm_attachments[]" multiple
        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp">
    <?php if ($existingDocuments !== []) { ?>
    <ul class="list-unstyled small mt-2 mb-0">
        <?php foreach ($existingDocuments as $doc) {
            $docId = (int) ($doc['id'] ?? 0); ?>
        <li class="mb-1">
            <a href="<?php echo rateb_url('documents/view/' . $docId); ?>" target="_blank" rel="noopener">
                <i class="fas fa-paperclip"></i> <?php echo Rateb\App\Core\View::escape((string) ($doc['file_name'] ?? $doc['title'] ?? '')); ?>
            </a>
        </li>
        <?php } ?>
    </ul>
    <?php } ?>
</div>
<?php } ?>
