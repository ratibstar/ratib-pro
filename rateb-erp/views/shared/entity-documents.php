<?php
/** @var string $title */
/** @var array<string, mixed> $item */
/** @var string $routePrefix */
/** @var string $backLabel */
$backLabel = (string) ($backLabel ?? __($entityName ?? 'record'));
?>
<div class="rateb-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>
            <i class="fas fa-paperclip"></i>
            <?php echo Rateb\App\Core\View::escape($title ?? __('entity_documents')); ?>
        </span>
        <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-right"></i> <?php echo Rateb\App\Core\View::escape($backLabel); ?>
        </a>
    </div>
    <div class="rateb-card-body">
        <?php Rateb\App\Core\View::partial('entity-documents-panel', [
            'item' => $item,
            'entityName' => $entityName ?? '',
            'entityType' => $entityType,
            'entityId' => $entityId,
            'companyId' => $companyId,
            'documents' => $documents,
            'routePrefix' => $routePrefix,
            'backLabel' => $backLabel,
            'csrf' => $csrf,
            'canManage' => $canManage ?? false,
            'modalMode' => false,
        ]); ?>
    </div>
</div>
