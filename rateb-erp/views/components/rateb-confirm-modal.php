<div class="modal fade rateb-confirm-modal" id="ratebConfirmModal" tabindex="-1" role="dialog"
     aria-labelledby="ratebConfirmModalLabel" data-bs-focus="false"
     data-label-yes="<?php echo Rateb\App\Core\View::escape(__('yes')); ?>"
     data-label-cancel="<?php echo Rateb\App\Core\View::escape(__('cancel')); ?>"
     data-label-ok="<?php echo Rateb\App\Core\View::escape(__('ok')); ?>"
     data-label-title="<?php echo Rateb\App\Core\View::escape(__('confirm_action')); ?>">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title d-flex align-items-center" id="ratebConfirmModalLabel">
                    <i class="fas fa-question-circle me-2" data-rateb-confirm-icon aria-hidden="true"></i>
                    <span data-rateb-confirm-title><?php echo __('confirm_action'); ?></span>
                </h5>
                <button type="button" class="btn-close" data-rateb-modal-close aria-label="<?php echo __('close'); ?>"></button>
            </div>
            <div class="modal-body pt-2 pb-3" data-rateb-confirm-message></div>
            <div class="modal-footer border-0 pt-0 gap-2">
                <button type="button" class="btn btn-secondary" data-rateb-confirm-cancel><?php echo __('cancel'); ?></button>
                <button type="button" class="btn btn-primary" data-rateb-confirm-ok><?php echo __('yes'); ?></button>
            </div>
        </div>
    </div>
</div>
