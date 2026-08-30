<?php
/** @var array<string, mixed>|null $item */
/** @var array<int, array<string, mixed>> $fields */
/** @var array<string, list<array{value: string|int, label: string}>> $lookups */
/** @var array<int, array<string, mixed>>|null $existingDocuments */
/** @var \Rateb\App\Services\SupplierCommService|null $commSvc */
$item = $item ?? [];
$lookups = $lookups ?? [];
$fields = $fields ?? [];
$responsibleDefault = $responsibleDefault ?? '';
$showAttachments = !empty($showAttachments);
$existingDocuments = $existingDocuments ?? [];
$commSvc = $commSvc ?? new \Rateb\App\Services\SupplierCommService();
$isEdit = !empty($item['id']);
$sendStatus = (string) ($item['send_status'] ?? 'not_sent');
$attachTypes = $lookups['comm_attachment_types'] ?? [];

$defaults = [
    'comm_date' => date('Y-m-d'),
    'comm_status' => 'new',
    'follow_up_priority' => 'medium',
    'channel' => 'email',
    'responsible_name' => $responsibleDefault,
];

$renderFields = static function (array $names) use ($fields, $item, $defaults, $lookups): void {
    foreach ($fields as $field) {
        $name = (string) $field['name'];
        if (!in_array($name, $names, true)) {
            continue;
        }
        $value = $item[$name] ?? ($defaults[$name] ?? ($field['default'] ?? ''));
        Rateb\App\Core\View::partial('form-field', ['field' => $field, 'value' => $value, 'lookups' => $lookups]);
    }
};
?>
<div class="rateb-sc-form-compact">
    <?php if ($isEdit && $sendStatus !== 'not_sent') { ?>
    <div class="mb-3 d-flex flex-wrap align-items-center gap-2">
        <span class="text-muted small"><?php echo __('comm_send_status'); ?>:</span>
        <span class="badge bg-<?php echo $commSvc->sendStatusBadgeClass($sendStatus); ?>">
            <?php echo Rateb\App\Core\View::escape(__('comm_send_' . $sendStatus)); ?>
        </span>
        <?php if (!empty($item['sent_at'])) { ?>
        <span class="text-muted small"><?php echo __('sent_at'); ?>: <?php echo Rateb\App\Core\View::formatDate((string) $item['sent_at']); ?></span>
        <?php } ?>
    </div>
    <?php } ?>
    <div class="rateb-sc-form-section">
        <div class="rateb-sc-section-title"><?php echo __('comm_section_basic'); ?></div>
        <div class="row g-2 g-md-3">
            <?php $renderFields(['supplier_id', 'channel', 'comm_date', 'comm_time', 'comm_status', 'subject', 'details']); ?>
        </div>
    </div>
    <div class="rateb-sc-form-section">
        <div class="rateb-sc-section-title"><?php echo __('comm_section_contacts'); ?></div>
        <div class="row g-2 g-md-3">
            <?php $renderFields(['responsible_name', 'supplier_contact', 'supplier_phone', 'supplier_email']); ?>
        </div>
        <div id="sc_channel_actions" class="rateb-sc-channel-actions mt-2" hidden>
            <span class="text-muted small me-2"><?php echo __('comm_channel_actions'); ?>:</span>
            <a href="#" class="btn btn-outline-primary btn-sm" id="sc_act_email" target="_blank" rel="noopener"><i class="fas fa-envelope"></i> <?php echo __('comm_channel_email'); ?></a>
            <a href="#" class="btn btn-outline-success btn-sm" id="sc_act_whatsapp" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> <?php echo __('comm_channel_whatsapp'); ?></a>
            <a href="#" class="btn btn-outline-secondary btn-sm" id="sc_act_phone"><i class="fas fa-phone"></i> <?php echo __('comm_call_supplier'); ?></a>
        </div>
    </div>
    <div class="rateb-sc-form-section">
        <div class="rateb-sc-section-title"><?php echo __('comm_message'); ?></div>
        <div class="row g-2 g-md-3">
            <?php $renderFields(['body']); ?>
        </div>
    </div>
    <div class="rateb-sc-form-section">
        <div class="rateb-sc-section-title"><?php echo __('comm_section_followup'); ?> / <?php echo __('comm_section_links'); ?></div>
        <div class="row g-2 g-md-3">
            <?php $renderFields(['follow_up_date', 'follow_up_priority', 'purchase_order_id', 'rfq_id']); ?>
        </div>
    </div>
    <div class="rateb-sc-form-section">
        <div class="rateb-sc-section-title"><?php echo __('comm_section_evaluation'); ?></div>
        <div class="row g-2 g-md-3">
            <?php $renderFields(['response_rating', 'response_notes']); ?>
        </div>
    </div>
    <?php if ($showAttachments) { ?>
    <div class="rateb-sc-form-section">
        <div class="rateb-sc-section-title"><?php echo __('comm_attachments'); ?></div>
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label rateb-form-label"><?php echo __('comm_attachment_type'); ?></label>
                <select class="form-select rateb-form-control" name="attachment_category">
                    <?php foreach ($attachTypes as $opt) {
                        $val = (string) ($opt['value'] ?? ''); ?>
                    <option value="<?php echo Rateb\App\Core\View::escape($val); ?>"><?php echo Rateb\App\Core\View::escape((string) ($opt['label'] ?? $val)); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label rateb-form-label"><?php echo __('comm_attachments'); ?></label>
                <p class="text-muted small mb-2"><?php echo __('comm_attachments_hint'); ?></p>
                <input class="form-control rateb-form-control" type="file" name="comm_attachments[]" multiple
                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp">
            </div>
            <?php if ($existingDocuments !== []) { ?>
            <div class="col-12">
                <p class="text-muted small mb-2"><?php echo __('attachment'); ?></p>
                <ul class="list-unstyled small mb-0">
                    <?php foreach ($existingDocuments as $doc) {
                        $docId = (int) ($doc['id'] ?? 0); ?>
                    <li class="mb-1">
                        <a href="<?php echo rateb_url('documents/view/' . $docId); ?>" target="_blank" rel="noopener">
                            <i class="fas fa-paperclip"></i> <?php echo Rateb\App\Core\View::escape((string) ($doc['file_name'] ?? $doc['title'] ?? '')); ?>
                        </a>
                    </li>
                    <?php } ?>
                </ul>
            </div>
            <?php } ?>
        </div>
    </div>
    <?php } ?>
</div>
