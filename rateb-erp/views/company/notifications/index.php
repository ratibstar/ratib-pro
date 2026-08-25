<?php
$listHelp = (string) ($listHelp ?? '');
if ($listHelp !== '') { ?>
<div class="alert alert-secondary py-2 small mb-3" role="status">
    <?php echo Rateb\App\Core\View::escape($listHelp); ?>
</div>
<?php }
Rateb\App\Core\View::partial('crud-index', [
    'title' => $title,
    'items' => $items ?? [],
    'fields' => $fields ?? null,
    'csrf' => $csrf,
    'routePrefix' => rateb_app_route('notifications'),
    'bulkEnabled' => false,
    'createEnabled' => false,
    'actionsEnabled' => false,
]);
?>
