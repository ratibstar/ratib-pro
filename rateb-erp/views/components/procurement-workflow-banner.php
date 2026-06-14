<?php
/** @var array<string, mixed>|null $workflow */
$workflow = $workflow ?? null;
if ($workflow === null || ($workflow['status'] ?? '') === '') {
    return;
}
$status = (string) ($workflow['status'] ?? '');
$badge = 'secondary';
if ($status === 'approved') {
    $badge = 'success';
} elseif ($status === 'rejected') {
    $badge = 'danger';
} elseif ($status === 'pending') {
    $badge = 'warning';
}
?>
<div class="col-12">
    <div class="alert alert-<?php echo $badge; ?> d-flex align-items-center gap-2 mb-0">
        <i class="fas fa-route fa-lg"></i>
        <div>
            <strong><?php echo __('approval_workflow'); ?>:</strong>
            <?php echo __('workflow_status_' . $status); ?>
            <?php if (!empty($workflow['current_step'])) { ?>
            — <?php echo __('current_step'); ?>: <?php echo Rateb\App\Core\View::escape((string) $workflow['current_step']); ?>
            <?php } ?>
        </div>
    </div>
</div>
