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
            <?php Rateb\App\Core\View::partial('entity-attachment-field', [
                'entityType' => $entityType,
                'entityId' => $entityId,
                'companyId' => $companyId,
                'inputName' => 'quote_attachment',
                'label' => __('quote_attachment'),
            ]); ?>
        </div>
        <?php if ($history !== []) { ?>
        <div class="col-md-6">
            <label class="form-label rateb-form-label small"><?php echo __('notes_edit_history'); ?></label>
            <div class="border rounded p-2 small" style="max-height:140px;overflow-y:auto">
                <?php foreach ($history as $entry) { ?>
                <div class="mb-2 pb-2 border-bottom">
                    <span class="text-muted"><?php echo Rateb\App\Core\View::escape((string) ($entry['at'] ?? '')); ?></span>
                    — <?php echo Rateb\App\Core\View::escape((string) ($entry['by'] ?? '')); ?>
                </div>
                <?php } ?>
            </div>
        </div>
        <?php } ?>
    </div>
</div>
