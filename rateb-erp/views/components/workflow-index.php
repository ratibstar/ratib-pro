<?php
/** @var string $title */
/** @var string $entitySlug */
/** @var string $routePrefix */
/** @var array<int, array<string, mixed>>|null $formFields */
/** @var string|null $formAction */
/** @var array<int, array<string, mixed>> $items */
/** @var array<int, array{name:string,label:string,type?:string}> $columns */
/** @var string $csrf */
/** @var bool|null $canManage */
/** @var string|null $exportRoute */
/** @var bool|null $exportEnabled */
/** @var array<string, list<array{value: string|int, label: string}>>|null $lookups */
$entitySlug = (string) ($entitySlug ?? '');
$routePrefix = (string) ($routePrefix ?? rateb_app_route($entitySlug));
$canManage = $canManage ?? rateb_can_manage_entity($entitySlug);
$exportEnabled = $exportEnabled ?? rateb_can_export_entity($entitySlug);
$lookups = $lookups ?? [];
if ($formFields !== null && $lookups === []) {
    $lookups = (new \Rateb\App\Services\FormLookupService())->forFields($formFields);
}
?>
<div class="rateb-card mb-4">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></span>
        <?php if (!empty($exportRoute) && $exportEnabled) {
            Rateb\App\Core\View::partial('export-toolbar', [
                'exportRoute' => $exportRoute,
                'exportEnabled' => true,
            ]);
        } ?>
    </div>
    <div class="rateb-card-body">
        <?php if ($canManage && !empty($formFields) && !empty($formAction)) { ?>
        <?php Rateb\App\Core\View::partial('workflow-form', [
            'formFields' => $formFields,
            'formAction' => $formAction,
            'csrf' => $csrf,
            'lookups' => $lookups,
        ]); ?>
        <?php } ?>
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $items ?? [],
            'columns' => $columns,
            'bulkEnabled' => $canManage,
            'actionsEnabled' => $canManage,
            'routePrefix' => $routePrefix,
            'csrf' => $csrf,
        ]); ?>
    </div>
</div>
