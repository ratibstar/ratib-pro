<?php
/** @var string $csrf */
/** @var int $id */
/** @var string $viewUrl */
/** @var string|null $editUrl */
/** @var bool $canEdit */
/** @var bool $canSubmit */
/** @var bool $canDelete */
/** @var string|null $deleteUrl */
/** @var string|null $submitUrl */
/** @var bool $submitted */
/** @var string|null $redirectTo */
$csrf = (string) ($csrf ?? '');
$id = (int) ($id ?? 0);
$viewUrl = (string) ($viewUrl ?? '');
$editUrl = $editUrl ?? null;
$canEdit = !empty($canEdit);
$canSubmit = !empty($canSubmit) && empty($submitted);
$canDelete = !empty($canDelete);
$deleteUrl = $deleteUrl ?? null;
$submitUrl = $submitUrl ?? null;
?>
<div class="rateb-actions rateb-accounting-actions text-nowrap">
    <?php if ($canSubmit && $submitUrl) { ?>
    <form method="post" action="<?php echo Rateb\App\Core\View::escape($submitUrl); ?>" class="d-inline rateb-submit-approval-form"
          onsubmit="return confirm('<?php echo Rateb\App\Core\View::escape(__('confirm_submit_for_approval')); ?>');">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <?php if (!empty($redirectTo)) { ?>
        <input type="hidden" name="redirect_to" value="<?php echo Rateb\App\Core\View::escape((string) $redirectTo); ?>">
        <?php } ?>
        <button type="submit" class="btn btn-sm btn-success rateb-btn-submit-approval" title="<?php echo __('submit_for_approval'); ?>">
            <i class="fas fa-paper-plane" aria-hidden="true"></i>
            <span class="rateb-btn-label"><?php echo __('submit_for_approval'); ?></span>
        </button>
    </form>
    <?php } elseif (!empty($submitted)) { ?>
    <span class="badge bg-warning text-dark rateb-badge-awaiting-approval"><?php echo __('awaiting_oversight_approval'); ?></span>
    <?php } ?>
    <?php if ($viewUrl !== '') { ?>
    <a href="<?php echo Rateb\App\Core\View::escape($viewUrl); ?>" class="btn btn-sm btn-outline-info" title="<?php echo __('view'); ?>">
        <i class="fas fa-eye" aria-hidden="true"></i><span class="rateb-btn-label"><?php echo __('view'); ?></span>
    </a>
    <?php } ?>
    <?php if ($canEdit && $editUrl) { ?>
    <a href="<?php echo Rateb\App\Core\View::escape($editUrl); ?>" class="btn btn-sm btn-outline-primary" title="<?php echo __('edit'); ?>">
        <i class="fas fa-edit" aria-hidden="true"></i><span class="rateb-btn-label"><?php echo __('edit'); ?></span>
    </a>
    <?php } ?>
    <?php if ($canDelete && $deleteUrl) { ?>
    <form method="post" action="<?php echo Rateb\App\Core\View::escape($deleteUrl); ?>" class="d-inline"
          onsubmit="return confirm('<?php echo Rateb\App\Core\View::escape(__('confirm_delete')); ?>');">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <button type="submit" class="btn btn-sm btn-outline-danger" title="<?php echo __('delete'); ?>">
            <i class="fas fa-trash" aria-hidden="true"></i>
        </button>
    </form>
    <?php } ?>
</div>
