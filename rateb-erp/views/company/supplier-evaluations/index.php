<?php
$approvalsRoute = $approvalsRoute ?? rateb_app_url('supplier-evaluations/approvals');
$pendingApprovalCount = (int) ($pendingApprovalCount ?? 0);
?>
<div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
    <a href="<?php echo rateb_url($approvalsRoute); ?>" class="btn btn-outline-warning btn-sm">
        <i class="fas fa-clipboard-check"></i> <?php echo __('evaluation_approvals'); ?>
        <?php if ($pendingApprovalCount > 0) { ?>
        <span class="badge bg-danger ms-1"><?php echo $pendingApprovalCount; ?></span>
        <?php } ?>
    </a>
</div>
<?php Rateb\App\Core\View::partial('crud-index', get_defined_vars()); ?>
