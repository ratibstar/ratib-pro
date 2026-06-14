<?php
/** @var string $status draft|posted|void */
/** @var string $docType journal|voucher */
/** @var bool $canManage */
/** @var bool $canApprove */
/** @var string $csrf */
/** @var int $docId */
/** @var string $postUrl */
/** @var string $voidUrl */
/** @var string $editUrl */
/** @var string $deleteUrl */
/** @var string $listUrl */
$status = (string) ($status ?? 'draft');
$docType = (string) ($docType ?? 'journal');
$canManage = $canManage ?? false;
$canApprove = $canApprove ?? false;
$steps = [
    ['key' => 'create', 'label' => __('accounting_step_create'), 'done' => true],
    ['key' => 'review', 'label' => __('accounting_step_review'), 'done' => true, 'active' => $status === 'draft'],
    ['key' => 'post', 'label' => __('accounting_step_approve'), 'done' => $status === 'posted', 'active' => $status === 'posted'],
];
if ($status === 'void') {
    $steps[2] = ['key' => 'void', 'label' => __('accounting_step_void'), 'done' => true, 'active' => true];
}
?>
<div class="rateb-doc-workflow mb-3">
    <div class="rateb-doc-workflow-steps">
        <?php foreach ($steps as $i => $step) {
            $cls = 'rateb-doc-step';
            if (!empty($step['active'])) {
                $cls .= ' active';
            } elseif (!empty($step['done'])) {
                $cls .= ' done';
            }
            ?>
        <div class="<?php echo $cls; ?>">
            <span class="rateb-doc-step-num"><?php echo $i + 1; ?></span>
            <span class="rateb-doc-step-label"><?php echo Rateb\App\Core\View::escape($step['label']); ?></span>
        </div>
        <?php if ($i < count($steps) - 1) { ?>
        <i class="fas fa-chevron-left rateb-doc-step-arrow" aria-hidden="true"></i>
        <?php } ?>
        <?php } ?>
    </div>

    <?php if ($status === 'draft') { ?>
    <div class="alert alert-warning border-0 mb-0 mt-3 py-2 px-3">
        <i class="fas fa-clock me-1"></i>
        <?php echo $docType === 'voucher' ? __('voucher_workflow_draft_hint') : __('journal_workflow_draft_hint'); ?>
    </div>
    <div class="rateb-doc-workflow-actions mt-3">
        <?php if ($canApprove) { ?>
        <form method="post" action="<?php echo Rateb\App\Core\View::escape($postUrl); ?>" class="d-inline">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-check"></i>
                <?php echo $docType === 'voucher' ? __('approve_voucher') : __('approve_entry'); ?>
            </button>
        </form>
        <?php } ?>
        <?php if ($canManage) { ?>
        <a href="<?php echo Rateb\App\Core\View::escape($editUrl); ?>" class="btn btn-outline-primary">
            <i class="fas fa-edit"></i> <?php echo __('edit'); ?>
        </a>
        <form method="post" action="<?php echo Rateb\App\Core\View::escape($deleteUrl); ?>" class="d-inline"
              onsubmit="return confirm('<?php echo __('bulk_confirm_delete_drafts'); ?>');">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <button type="submit" class="btn btn-outline-danger">
                <i class="fas fa-trash"></i> <?php echo __('delete_draft'); ?>
            </button>
        </form>
        <?php } ?>
        <?php if (!$canApprove && $canManage) { ?>
        <p class="text-muted small mb-0 mt-2"><i class="fas fa-lock me-1"></i><?php echo __('accounting_perm_approve_hint'); ?></p>
        <?php } ?>
    </div>
    <?php } elseif ($status === 'posted') { ?>
    <div class="alert alert-success border-0 mb-0 mt-3 py-2 px-3">
        <i class="fas fa-check-circle me-1"></i>
        <?php echo $docType === 'voucher' ? __('voucher_workflow_posted_hint') : __('journal_workflow_posted_hint'); ?>
    </div>
    <?php if ($canApprove) { ?>
    <div class="rateb-doc-workflow-actions mt-3">
        <form method="post" action="<?php echo Rateb\App\Core\View::escape($voidUrl); ?>" class="d-inline"
              onsubmit="return confirm('<?php echo __('journal_void_confirm'); ?>');">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <button type="submit" class="btn btn-outline-danger">
                <i class="fas fa-ban"></i> <?php echo __('void_entry'); ?>
            </button>
        </form>
    </div>
    <?php } ?>
    <?php } elseif ($status === 'void') { ?>
    <div class="alert alert-secondary border-0 mb-0 mt-3 py-2 px-3">
        <i class="fas fa-ban me-1"></i>
        <?php echo __('accounting_workflow_void_hint'); ?>
    </div>
    <?php } ?>

    <div class="mt-3">
        <a href="<?php echo Rateb\App\Core\View::escape($listUrl); ?>" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-list"></i> <?php echo __('back_to_list'); ?>
        </a>
    </div>
</div>
