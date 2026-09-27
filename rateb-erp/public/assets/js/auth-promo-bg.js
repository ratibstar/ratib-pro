/**
 * Login / app-download background: promo phrases drift in random directions, bounce off the
 * edges and change colors on each bounce. Decorative only (the container is aria-hidden).
 */
(function () {
    'use strict';

    var bg = document.querySelector('.rateb-auth-bg');
    if (!bg || !window.requestAnimationFrame) {
        return;
    }
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    var palette = [
        ['#38bdf8', '#6366f1'], ['#34d399', '#22d3ee'], ['#a78bfa', '#f472b6'], ['#fb7185', '#f59e0b'],
        ['#fbbf24', '#f472b6'], ['#22d3ee', '#818cf8'], ['#4ade80', '#a3e635'], ['#f97316', '#facc15']
    ];
    var tags = Array.prototype.slice.call(bg.querySelectorAll('.rateb-auth-tag'));
    // The first phrase (company slogan) always shows; the rest are shuffled so small screens vary.
    for (var s = tags.length - 1; s > 1; s--) {
        var r = 1 + Math.floor(Math.random() * s);
        var tmp = tags[s];
        tags[s] = tags[r];
        tags[r] = tmp;
    }
    var max = window.innerWidth < 576 ? 6 : (window.innerWidth < 992 ? 8 : tags.length);
    var items = [];

    function rand(a, b) {
        return a + Math.random() * (b - a);
    }

    function paint(el) {
        var c = palette[Math.floor(Math.random() * palette.length)];
        el.style.setProperty('--c1', c[0]);
        el.style.setProperty('--c2', c[1]);
        el.style.setProperty('--glow', c[0] + '66');
    }

    tags.forEach(function (el, i) {
        if (i >= max) {
            el.style.display = 'none';
            return;
        }
        el.classList.add('is-roaming');
        paint(el);
        var angle = rand(0, Math.PI * 2);
        var speed = rand(18, 42);
        items.push({ el: el, x: 0, y: 0, w: 0, h: 0, vx: Math.cos(angle) * speed, vy: Math.sin(angle) * speed, placed: false });
    });

    function measure() {
        var W = bg.clientWidth;
        var H = bg.clientHeight;
        items.forEach(function (it) {
            it.w = it.el.offsetWidth;
            it.h = it.el.offsetHeight * 1.6;
            if (!it.placed) {
                it.x = rand(0, Math.max(1, W - it.w));
                it.y = rand(0, Math.max(1, H - it.h));
                it.placed = true;
            }
        });
    }

    var last = 0;
    function frame(t) {
        var dt = last ? Math.min(0.05, (t - last) / 1000) : 0;
        last = t;
        var W = bg.clientWidth;
        var H = bg.clientHeight;
        items.forEach(function (it) {
            // Gentle random steering so paths never repeat.
            var turn = rand(-0.6, 0.6) * dt;
            var vx = it.vx * Math.cos(turn) - it.vy * Math.sin(turn);
            it.vy = it.vx * Math.sin(turn) + it.vy * Math.cos(turn);
            it.vx = vx;
            it.x += it.vx * dt;
            it.y += it.vy * dt;
            var hit = false;
            if (it.x < 0) { it.x = 0; it.vx = Math.abs(it.vx); hit = true; }
            if (it.x > W - it.w) { it.x = Math.max(0, W - it.w); it.vx = -Math.abs(it.vx); hit = true; }
            if (it.y < 0) { it.y = 0; it.vy = Math.abs(it.vy); hit = true; }
            if (it.y > H - it.h) { it.y = Math.max(0, H - it.h); it.vy = -Math.abs(it.vy); hit = true; }
            if (hit) {
                paint(it.el);
            }
            it.el.style.transform = 'translate3d(' + it.x.toFixed(1) + 'px,' + it.y.toFixed(1) + 'px,0)';
        });
        window.requestAnimationFrame(frame);
    }

    measure();
    window.addEventListener('resize', measure);
    window.requestAnimationFrame(frame);
})();
