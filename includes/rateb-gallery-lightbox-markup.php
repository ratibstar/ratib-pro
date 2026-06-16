<?php
/**
 * Shared image lightbox (Prev/Next) for public home and company profile.
 */
declare(strict_types=1);
?>
<div class="rateb-program-lightbox" id="rateb-program-lightbox" hidden data-rateb-program-lightbox>
    <div class="rateb-program-lightbox__backdrop" data-rateb-program-lightbox-close tabindex="-1"></div>
    <div class="rateb-program-lightbox__panel" role="dialog" aria-modal="true" aria-label="Image preview">
        <button type="button" class="rateb-program-lightbox__close" data-rateb-program-lightbox-close aria-label="Dismiss image preview">&times;</button>
        <div class="rateb-program-lightbox__stage">
            <img src="" alt="" class="rateb-program-lightbox__img" id="rateb-program-lightbox-img" decoding="async">
            <div class="rateb-program-lightbox__overlay-nav" id="rateb-program-lightbox-controls" hidden>
                <button type="button" class="rateb-program-lightbox__btn rateb-program-lightbox__btn--prev" data-rateb-program-lightbox-prev aria-label="Previous image">
                    <span class="rateb-program-lightbox__btn-ic" aria-hidden="true">&#8249;</span>
                    <span class="rateb-program-lightbox__btn-lbl">Prev</span>
                </button>
                <span class="rateb-program-lightbox__counter" id="rateb-program-lightbox-counter" aria-live="polite"></span>
                <button type="button" class="rateb-program-lightbox__btn rateb-program-lightbox__btn--next" data-rateb-program-lightbox-next aria-label="Next image">
                    <span class="rateb-program-lightbox__btn-lbl">Next</span>
                    <span class="rateb-program-lightbox__btn-ic" aria-hidden="true">&#8250;</span>
                </button>
            </div>
        </div>
        <p class="rateb-program-lightbox__caption" id="rateb-program-lightbox-caption" hidden></p>
    </div>
</div>
