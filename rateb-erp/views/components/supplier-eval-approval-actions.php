<?php
/** @var array<string, mixed> $row */
/** @var string $routePrefix */
/** @var string $csrf */
if (empty($row['_pending_approval'])) {
    return;
}
$id = (int) ($row['id'] ?? 0);
if ($id < 1) {
    return;
}
?>
<form method="post" action="<?php echo rateb_url($routePrefix . '/' . $id . '/approve'); ?>" class="d-inline">
    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
    <button type="submit" class="btn btn-sm btn-success" title="<?php echo __('approve_evaluation'); ?>">
        <i class="fas fa-check"></i>
    </button>
</form>
<form method="post" action="<?php echo rateb_url($routePrefix . '/' . $id . '/reject'); ?>" class="d-inline">
    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
    <button type="submit" class="btn btn-sm btn-outline-danger" title="<?php echo __('reject_evaluation'); ?>">
        <i class="fas fa-times"></i>
    </button>
</form>
