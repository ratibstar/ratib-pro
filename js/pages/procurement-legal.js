/**
 * RATIB procurement & legal page — scroll reveals.
 */
(function () {
    'use strict';

    var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function initReveal() {
        var nodes = document.querySelectorAll('[data-ratib-reveal]');
        if (!nodes.length) {
            return;
        }

        if (prefersReduced || typeof IntersectionObserver === 'undefined') {
            nodes.forEach(function (el) {
                el.classList.add('is-visible');
            });
            return;
        }

        var io = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) {
                        return;
                    }
                    var el = entry.target;
                    var delay = parseInt(el.getAttribute('data-ratib-delay') || '0', 10);
                    window.setTimeout(function () {
                        el.classList.add('is-visible');
                    }, delay);
                    io.unobserve(el);
                });
            },
            { root: null, rootMargin: '0px 0px -6% 0px', threshold: 0.08 }
        );

        nodes.forEach(function (el) {
            io.observe(el);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initReveal);
    } else {
        initReveal();
    }
})();
