<?php
/** Reusable Bootstrap modal + click handler for [data-rateb-image-preview]. Include once per page. */
static $ratebImagePreviewKitLoaded = false;
if ($ratebImagePreviewKitLoaded) {
    return;
}
$ratebImagePreviewKitLoaded = true;
?>
<div class="modal fade" id="ratebImagePreviewModal" tabindex="-1" aria-labelledby="ratebImagePreviewLabel" aria-hidden="true"
    data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content rateb-modal rateb-image-preview-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="ratebImagePreviewLabel"><?php echo __('view_image'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo __('close'); ?>"></button>
            </div>
            <div class="modal-body pt-2 text-center">
                <img src="" alt="" id="ratebImagePreviewImg" class="img-fluid rounded" style="max-height: min(80vh, 720px); object-fit: contain;">
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('close'); ?></button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var modalEl = document.getElementById('ratebImagePreviewModal');
    if (!modalEl) {
        return;
    }
    if (modalEl.parentElement && modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }

    var previewClosedAt = 0;
    var previewModal = null;

    function getPreviewModal() {
        if (typeof bootstrap === 'undefined') {
            return null;
        }
        if (!previewModal) {
            previewModal = bootstrap.Modal.getOrCreateInstance(modalEl, {
                backdrop: true,
                keyboard: true,
                focus: true
            });
        }
        return previewModal;
    }

    function cleanupModalArtifacts() {
        document.querySelectorAll('.modal-backdrop').forEach(function (el) {
            el.remove();
        });
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    }

    modalEl.addEventListener('hidden.bs.modal', function () {
        previewClosedAt = Date.now();
        var imgEl = document.getElementById('ratebImagePreviewImg');
        if (imgEl) {
            imgEl.setAttribute('src', '');
        }
        cleanupModalArtifacts();
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('#ratebImagePreviewModal') || e.target.closest('.modal-backdrop')) {
            return;
        }
        if (Date.now() - previewClosedAt < 350) {
            return;
        }
        var trigger = e.target.closest('[data-rateb-image-preview]');
        if (!trigger) {
            return;
        }
        var src = trigger.getAttribute('data-rateb-image-preview') || '';
        if (src === '' && trigger.tagName === 'IMG') {
            src = trigger.getAttribute('src') || '';
        }
        if (src === '') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        var imgEl = document.getElementById('ratebImagePreviewImg');
        var modal = getPreviewModal();
        if (!imgEl || !modal) {
            window.open(src, '_blank', 'noopener,noreferrer');
            return;
        }
        imgEl.setAttribute('src', src);
        modal.show();
    }, true);

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || !modalEl.classList.contains('show')) {
            return;
        }
        var modal = getPreviewModal();
        if (modal) {
            modal.hide();
        }
    });
})();
</script>
