<?php
/**
 * Shared image lightbox (Prev/Next) for public home and company profile.
 */
declare(strict_types=1);
?>
<div class="ratib-program-lightbox" id="ratib-program-lightbox" hidden data-ratib-program-lightbox>
    <div class="ratib-program-lightbox__backdrop" data-ratib-program-lightbox-close tabindex="-1"></div>
    <div class="ratib-program-lightbox__panel" role="dialog" aria-modal="true" aria-label="Image preview">
        <button type="button" class="ratib-program-lightbox__close" data-ratib-program-lightbox-close aria-label="Close preview">&times;</button>
        <div class="ratib-program-lightbox__stage">
            <img src="" alt="" class="ratib-program-lightbox__img" id="ratib-program-lightbox-img" decoding="async">
            <div class="ratib-program-lightbox__overlay-nav" id="ratib-program-lightbox-controls" hidden>
                <button type="button" class="ratib-program-lightbox__btn ratib-program-lightbox__btn--prev" data-ratib-program-lightbox-prev aria-label="Previous image">
                    <span class="ratib-program-lightbox__btn-ic" aria-hidden="true">&#8249;</span>
                    <span class="ratib-program-lightbox__btn-lbl">Prev</span>
                </button>
                <span class="ratib-program-lightbox__counter" id="ratib-program-lightbox-counter" aria-live="polite"></span>
                <button type="button" class="ratib-program-lightbox__btn ratib-program-lightbox__btn--next" data-ratib-program-lightbox-next aria-label="Next image">
                    <span class="ratib-program-lightbox__btn-lbl">Next</span>
                    <span class="ratib-program-lightbox__btn-ic" aria-hidden="true">&#8250;</span>
                </button>
            </div>
        </div>
        <p class="ratib-program-lightbox__caption" id="ratib-program-lightbox-caption" hidden></p>
    </div>
</div>
