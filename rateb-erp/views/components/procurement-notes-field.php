<?php
/** @var array<string, mixed>|null $item */
/** @var string $entityType purchase_request|purchase_order */
/** @var int $companyId */
$notes = (string) ($item['notes'] ?? '');
$history = \Rateb\App\Helpers\ProcurementNotes::decodeHistory(
    isset($item['notes_history']) ? (string) $item['notes_history'] : null
);
$templates = \Rateb\App\Helpers\ProcurementNotes::templates();
$entityType = (string) ($entityType ?? 'purchase_request');
$entityId = (int) ($item['id'] ?? 0);
$companyId = (int) ($companyId ?? 0);
$maxLen = 2000;
$attachments = [];
if ($companyId > 0 && $entityId > 0) {
    $attachments = (new \Rateb\App\Services\DocumentService())->listForEntity($entityType, $entityId, $companyId);
}
?>
<div class="col-12" data-procurement-notes>
    <label class="form-label rateb-form-label" for="f_notes">
        <?php echo __('notes'); ?>
        <span class="text-muted small" data-notes-counter>0 / <?php echo $maxLen; ?></span>
    </label>
    <div class="d-flex flex-wrap gap-1 mb-2" data-notes-templates>
        <?php foreach ($templates as $tpl) { ?>
        <button type="button" class="btn btn-sm btn-outline-<?php echo Rateb\App\Core\View::escape($tpl['color']); ?>"
                data-notes-template="<?php echo Rateb\App\Core\View::escape($tpl['text']); ?>">
            <?php echo Rateb\App\Core\View::escape($tpl['text']); ?>
        </button>
        <?php } ?>
    </div>
    <textarea class="form-control rateb-form-control" id="f_notes" name="notes" rows="4"
              maxlength="<?php echo $maxLen; ?>" data-notes-input><?php echo Rateb\App\Core\View::escape($notes); ?></textarea>
    <div class="row g-3 mt-2">
        <div class="col-md-6">
            <label class="form-label rateb-form-label" for="f_quote_attachment">
                <i class="fas fa-paperclip"></i> <?php echo __('quote_attachment'); ?>
            </label>
            <input class="form-control rateb-form-control" type="file" id="f_quote_attachment"
                   name="quote_attachment[]" multiple
                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp">
            <small class="text-muted d-block mt-1"><?php echo __('attachment_hint'); ?></small>
            <?php foreach ($attachments as $doc) { ?>
            <div class="mt-2 p-2 border rounded d-flex flex-wrap align-items-center justify-content-between gap-2 rateb-attachment-box">
                <span class="small"><i class="fas fa-file"></i> <?php echo Rateb\App\Core\View::escape($doc['file_name'] ?? ''); ?></span>
                <a href="<?php echo rateb_url('documents/download/' . (int) ($doc['id'] ?? 0)); ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-download"></i> <?php echo __('download_file'); ?>
                </a>
            </div>
            <?php } ?>
        </div>
        <?php if ($history !== []) { ?>
        <div class="col-md-6">
            <label class="form-label rateb-form-label small"><?php echo __('notes_edit_history'); ?></label>
            <div class="border rounded p-2 small" style="max-height:180px;overflow-y:auto">
                <?php foreach ($history as $entry) { ?>
                <div class="mb-2 pb-2 border-bottom">
                    <div class="text-muted"><?php echo Rateb\App\Core\View::escape((string) ($entry['at'] ?? '')); ?> — <?php echo Rateb\App\Core\View::escape((string) ($entry['by'] ?? '')); ?></div>
                    <?php if (!empty($entry['from'])) { ?>
                    <div class="text-danger text-truncate" title="<?php echo Rateb\App\Core\View::escape((string) $entry['from']); ?>"><?php echo __('before'); ?>: <?php echo Rateb\App\Core\View::escape(substr((string) $entry['from'], 0, 80)); ?></div>
                    <?php } ?>
                    <?php if (!empty($entry['to'])) { ?>
                    <div class="text-success text-truncate" title="<?php echo Rateb\App\Core\View::escape((string) $entry['to']); ?>"><?php echo __('after'); ?>: <?php echo Rateb\App\Core\View::escape(substr((string) $entry['to'], 0, 80)); ?></div>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
        </div>
        <?php } ?>
    </div>
</div>
