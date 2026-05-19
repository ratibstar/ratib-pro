/**
 * Shared image lightbox: operational-proof screenshots/diagrams (+ optional extra slides).
 */
(function () {
    'use strict';

    var bound = false;

    function readSlideFromBtn(btn) {
        var full = btn.getAttribute('data-full-src') || '';
        var im = btn.querySelector('img');
        var altText = im ? im.getAttribute('alt') || '' : '';
        var cap = btn.getAttribute('data-caption');
        if (cap === null || typeof cap === 'undefined') {
            cap = '';
        }
        if (!full && im) {
            full = im.getAttribute('src') || '';
        }
        return { src: full, alt: altText, caption: cap };
    }

    function collectDefaultSlides() {
        var out = [];
        var opRoot = document.getElementById('operational-proof');
        if (opRoot) {
            out = Array.prototype.slice.call(opRoot.querySelectorAll('[data-ratib-gallery-open]'));
        }
        return out;
    }

    function init(options) {
        options = options || {};
        if (bound) {
            return;
        }

        var lb = document.getElementById('ratib-program-lightbox');
        if (!lb) {
            return;
        }

        function collectSlides() {
            var out = collectDefaultSlides();
            if (typeof options.collectExtraSlides === 'function') {
                var extra = options.collectExtraSlides();
                if (extra && extra.length) {
                    out = out.concat(extra);
                }
            }
            return out;
        }

        var slides = collectSlides();
        if (!slides.length) {
            return;
        }

        bound = true;

        var imgEl = lb.querySelector('.ratib-program-lightbox__img');
        var capEl = lb.querySelector('.ratib-program-lightbox__caption');
        var controlsEl = document.getElementById('ratib-program-lightbox-controls');
        var counterEl = document.getElementById('ratib-program-lightbox-counter');
        var lbIndex = 0;
        var marqueeGuard = !!options.marqueeClickGuard;

        function indexOfGallerySlide(btn) {
            var idx = slides.indexOf(btn);
            if (idx >= 0) {
                return idx;
            }
            var full = btn.getAttribute('data-full-src') || '';
            var im0 = btn.querySelector('img');
            if (!full && im0) {
                full = im0.getAttribute('src') || '';
            }
            var capBtn = String(btn.getAttribute('data-caption') || '');
            var si;
            for (si = 0; si < slides.length; si++) {
                var b = slides[si];
                var f = b.getAttribute('data-full-src') || '';
                var im = b.querySelector('img');
                if (!f && im) {
                    f = im.getAttribute('src') || '';
                }
                var capS = String(b.getAttribute('data-caption') || '');
                if (full && f === full && capBtn === capS) {
                    return si;
                }
            }
            for (si = 0; si < slides.length; si++) {
                var b2 = slides[si];
                var f2 = b2.getAttribute('data-full-src') || '';
                var im2 = b2.querySelector('img');
                if (!f2 && im2) {
                    f2 = im2.getAttribute('src') || '';
                }
                if (full && f2 === full) {
                    return si;
                }
            }
            return 0;
        }

        function syncLbNav() {
            var hide = slides.length <= 1;
            if (controlsEl) {
                controlsEl.hidden = hide;
            }
        }

        function applyLbIndex(i) {
            if (!slides.length || !imgEl) {
                return;
            }
            lbIndex = (i % slides.length + slides.length) % slides.length;
            var o = readSlideFromBtn(slides[lbIndex]);
            imgEl.src = o.src || '';
            imgEl.alt = o.alt || '';
            if (capEl) {
                var t = (o.caption || '').trim();
                capEl.textContent = t;
                capEl.hidden = !t;
            }
            if (counterEl) {
                counterEl.textContent =
                    slides.length > 1 ? String(lbIndex + 1) + ' / ' + String(slides.length) : '';
            }
        }

        function openLbAt(i) {
            applyLbIndex(i);
            lb.hidden = false;
            lb.classList.add('ratib-program-lightbox--open');
            document.body.classList.add('ratib-program-lightbox-open');
            syncLbNav();
        }

        function closeLb() {
            lb.hidden = true;
            lb.classList.remove('ratib-program-lightbox--open');
            document.body.classList.remove('ratib-program-lightbox-open');
            if (imgEl) {
                imgEl.removeAttribute('src');
                imgEl.alt = '';
            }
            if (capEl) {
                capEl.textContent = '';
                capEl.hidden = true;
            }
            if (counterEl) {
                counterEl.textContent = '';
            }
        }

        document.addEventListener('click', function (ev) {
            var closeHit = ev.target.closest('[data-ratib-program-lightbox-close]');
            if (closeHit && !lb.hidden) {
                ev.preventDefault();
                closeLb();
                return;
            }

            if (marqueeGuard) {
                var vpMarq = ev.target.closest('.ratib-program-marquee__viewport');
                if (vpMarq && vpMarq.getAttribute('data-ratib-marquee-suppress-click')) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    vpMarq.removeAttribute('data-ratib-marquee-suppress-click');
                    return;
                }
            }

            if (!lb.hidden) {
                if (ev.target.closest('[data-ratib-program-lightbox-prev]')) {
                    ev.preventDefault();
                    openLbAt(lbIndex - 1);
                    return;
                }
                if (ev.target.closest('[data-ratib-program-lightbox-next]')) {
                    ev.preventDefault();
                    openLbAt(lbIndex + 1);
                    return;
                }
            }

            var btn = ev.target.closest('[data-ratib-gallery-open], [data-ratib-program-open]');
            if (!btn) {
                return;
            }
            ev.preventDefault();
            openLbAt(indexOfGallerySlide(btn));
        });

        document.addEventListener('keydown', function (ev) {
            if (lb.hidden) {
                return;
            }
            if (ev.key === 'Escape') {
                closeLb();
                return;
            }
            if (slides.length <= 1) {
                return;
            }
            if (ev.key === 'ArrowLeft') {
                ev.preventDefault();
                openLbAt(lbIndex - 1);
            } else if (ev.key === 'ArrowRight') {
                ev.preventDefault();
                openLbAt(lbIndex + 1);
            }
        });
    }

    window.RatibGalleryLightbox = { init: init };
})();
