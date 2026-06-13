<?php
/** @var string $entityType */
/** @var int $entityId */
/** @var int $companyId */
/** @var string|null $documentPath */
/** @var string $inputName */
/** @var string $label */
$entityType = (string) ($entityType ?? 'general');
$entityId = (int) ($entityId ?? 0);
$companyId = (int) ($companyId ?? 0);
$documentPath = isset($documentPath) ? (string) $documentPath : '';
$inputName = (string) ($inputName ?? 'entity_attachment');
$label = (string) ($label ?? __('attachment'));
$doc = null;
if ($companyId > 0 && $entityId > 0) {
    $doc = (new \Rateb\App\Services\DocumentService())->latestForEntity($companyId, $entityType, $entityId);
}
$displayName = '';
if ($doc) {
    $displayName = (string) ($doc['file_name'] ?? '');
} elseif ($documentPath !== '') {
    $displayName = basename($documentPath);
}
?>
<div class="col-12">
    <label class="form-label rateb-form-label" for="f_<?php echo Rateb\App\Core\View::escape($inputName); ?>">
        <i class="fas fa-paperclip"></i> <?php echo Rateb\App\Core\View::escape($label); ?>
    </label>
    <input class="form-control rateb-form-control" type="file" id="f_<?php echo Rateb\App\Core\View::escape($inputName); ?>"
        name="<?php echo Rateb\App\Core\View::escape($inputName); ?>"
        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp">
    <small class="text-muted d-block mt-1"><?php echo __('attachment_hint'); ?></small>
    <?php if ($displayName !== '') { ?>
    <div class="mt-2 p-2 border rounded d-flex flex-wrap align-items-center justify-content-between gap-2 rateb-attachment-box">
        <span class="small">
            <i class="fas fa-file"></i>
            <?php echo Rateb\App\Core\View::escape($displayName); ?>
        </span>
        <?php if ($doc) { ?>
        <a href="<?php echo rateb_url('documents/download/' . (int) $doc['id']); ?>" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-download"></i> <?php echo __('download_file'); ?>
        </a>
        <?php } ?>
    </div>
    <?php } elseif ($entityId > 0) { ?>
    <p class="small text-muted mt-2 mb-0"><?php echo __('no_attachment'); ?></p>
    <?php } ?>
</div>
