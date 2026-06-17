<?php
/** Reusable Bootstrap modal + click handler for [data-rateb-image-preview]. Include once per page. */
static $ratebImagePreviewKitLoaded = false;
if ($ratebImagePreviewKitLoaded) {
    return;
}
$ratebImagePreviewKitLoaded = true;
?>
<div class="modal fade" id="ratebImagePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content rateb-image-preview-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title visually-hidden"><?php echo __('view_image'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo __('close'); ?>"></button>
            </div>
            <div class="modal-body pt-2 text-center">
                <img src="" alt="" id="ratebImagePreviewImg" class="img-fluid rounded" style="max-height: min(80vh, 720px); object-fit: contain;">
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('click', function (e) {
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
    var modalEl = document.getElementById('ratebImagePreviewModal');
    var imgEl = document.getElementById('ratebImagePreviewImg');
    if (!modalEl || !imgEl || typeof bootstrap === 'undefined') {
        window.open(src, '_blank', 'noopener,noreferrer');
        return;
    }
    imgEl.setAttribute('src', src);
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
});
document.getElementById('ratebImagePreviewModal')?.addEventListener('hidden.bs.modal', function () {
    var imgEl = document.getElementById('ratebImagePreviewImg');
    if (imgEl) {
        imgEl.setAttribute('src', '');
    }
});
</script>
