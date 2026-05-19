/**
 * RATIB enterprise company profile — scroll reveals, architecture hover, live metrics.
 */
(function () {
  'use strict';

  var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function initReveal() {
    var nodes = document.querySelectorAll('[data-ratib-reveal]');
    if (!nodes.length) return;

    if (prefersReduced || typeof IntersectionObserver === 'undefined') {
      nodes.forEach(function (el) {
        el.classList.add('is-visible');
      });
      return;
    }

    var io = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          var el = entry.target;
          var delay = parseInt(el.getAttribute('data-ratib-delay') || '0', 10);
          window.setTimeout(function () {
            el.classList.add('is-visible');
          }, delay);
          io.unobserve(el);
        });
      },
      { root: null, rootMargin: '0px 0px -8% 0px', threshold: 0.12 }
    );

    nodes.forEach(function (el) {
      io.observe(el);
    });
  }

  function initArchSync() {
    var cards = document.querySelectorAll('[data-layer-card]');
    var svgNodes = document.querySelectorAll('.ratib-about-arch__node[data-layer]');
    if (!cards.length || !svgNodes.length) return;

    function activate(id) {
      cards.forEach(function (c) {
        c.classList.toggle('is-active', c.getAttribute('data-layer-card') === id);
      });
      svgNodes.forEach(function (n) {
        n.style.opacity = n.getAttribute('data-layer') === id ? '1' : '0.55';
      });
    }

    cards.forEach(function (card) {
      card.addEventListener('mouseenter', function () {
        activate(card.getAttribute('data-layer-card'));
      });
    });

    var stack = document.querySelector('.ratib-about-arch__stack');
    if (stack) {
      stack.addEventListener('mouseleave', function () {
        cards.forEach(function (c) {
          c.classList.remove('is-active');
        });
        svgNodes.forEach(function (n) {
          n.style.opacity = '';
        });
      });
    }

    if (cards[0]) {
      activate(cards[0].getAttribute('data-layer-card'));
    }
  }

  function scrollToProfileHash(hash, pushState) {
    if (!hash || hash === '#') {
      return false;
    }
    var id = String(hash).replace(/^#/, '');
    if (!id) {
      return false;
    }
    var target = document.getElementById(id);
    if (!target) {
      return false;
    }
    target.scrollIntoView({ behavior: prefersReduced ? 'auto' : 'smooth', block: 'start' });
    if (pushState && window.history && typeof history.pushState === 'function') {
      history.pushState(null, '', '#' + id);
    }
    return true;
  }

  function initScrollToCompanyProfile() {
    var hash = (window.location.hash || '').replace(/^#/, '');
    if (hash === '' || hash === 'top') {
      scrollToProfileHash('#company-profile', false);
      return;
    }
    window.setTimeout(function () {
      scrollToProfileHash('#' + hash, false);
    }, 80);
  }

  document.addEventListener('click', function (ev) {
    var a = ev.target.closest('a[href*="#"]');
    if (!a || a.closest('[data-ratib-profile-nav]') || a.hasAttribute('data-ratib-profile-nav')) {
      return;
    }
    var href = a.getAttribute('href') || '';
    if (!href) {
      return;
    }
    if (href.charAt(0) === '#') {
      if (scrollToProfileHash(href, true)) {
        ev.preventDefault();
      }
      return;
    }
    try {
      var url = new URL(href, window.location.href);
      var here = new URL(window.location.href);
      if (url.pathname.replace(/\/$/, '') !== here.pathname.replace(/\/$/, '') || !url.hash) {
        return;
      }
      if (scrollToProfileHash(url.hash, true)) {
        ev.preventDefault();
      }
    } catch (eNav) {
      /* ignore */
    }
  }, false);

  function initProfileNavHighlight() {
    var profile = (window.location.origin || '') + '/profile/#company-profile';
    document
      .querySelectorAll(
        '.ratib-nav__brand-profile, .ratib-nav__link--about, .ratib-nav__go-profile, [data-ratib-profile-nav], .ratib-footer-link--about'
      )
      .forEach(function (a) {
        a.setAttribute('href', profile);
        a.classList.add('is-current');
        a.setAttribute('aria-current', 'page');
      });
    document.querySelectorAll('.ratib-nav__platform-links .ratib-nav__link').forEach(function (a) {
      if (!a.classList.contains('ratib-nav__link--about')) {
        a.classList.remove('is-current');
        a.removeAttribute('aria-current');
      }
    });
  }

  function initMetricJitter() {
    if (prefersReduced) return;
    var values = document.querySelectorAll('.ratib-about-metric__value[data-ratib-count]');
    values.forEach(function (el) {
      var base = parseFloat(el.getAttribute('data-ratib-count') || '0');
      if (!base) return;
      var text = (el.textContent || '').trim();
      var suffix = text.replace(/[\d.]+/, '');
      var isPct = text.indexOf('%') !== -1;

      window.setInterval(function () {
        if (!document.hidden) {
          var jitter = (Math.random() - 0.5) * (isPct ? 0.4 : base * 0.02);
          var next = base + jitter;
          el.textContent = (isPct ? next.toFixed(1) : Math.round(next)) + suffix;
        }
      }, 4200 + Math.random() * 2000);
    });
  }

  function initEventStream() {
    if (prefersReduced) return;
    var stream = document.querySelector('.ratib-about-event-stream');
    if (!stream) return;

    var events = [
      { cls: 'ok', text: 'WORKER_LOCATION_UPDATE · geofence match' },
      { cls: 'warn', text: 'WORKER_IDLE_ALERT · SLA watch active' },
      { cls: 'info', text: 'WORKER_OFFLINE · batch queued' },
      { cls: 'ok', text: 'STAGE_COMMIT · Medical · corr ae7f9c2' },
    ];
    var idx = 0;

    window.setInterval(function () {
      if (document.hidden) return;
      var row = stream.querySelector('.ratib-about-event');
      if (!row) return;
      var e = events[idx % events.length];
      idx += 1;
      var clone = row.cloneNode(true);
      clone.className = 'ratib-about-event ratib-about-event--' + e.cls;
      clone.innerHTML = '<span class="ratib-mono">' + e.text + '</span>';
      stream.insertBefore(clone, stream.firstChild);
      while (stream.children.length > 4) {
        stream.removeChild(stream.lastChild);
      }
    }, 5500);
  }

  function initJumpNavActive() {
    var links = document.querySelectorAll('.ratib-about-jump a[href^="#"]');
    var sections = [];
    links.forEach(function (a) {
      var id = a.getAttribute('href').slice(1);
      var sec = document.getElementById(id);
      if (sec) sections.push({ link: a, sec: sec });
    });
    if (!sections.length || typeof IntersectionObserver === 'undefined') return;

    var io = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          sections.forEach(function (s) {
            s.link.style.color =
              s.sec === entry.target ? '#3b82f6' : '';
          });
        });
      },
      { rootMargin: '-40% 0px -50% 0px', threshold: 0 }
    );

    sections.forEach(function (s) {
      io.observe(s.sec);
    });
  }

  function initGalleryLightbox() {
    if (window.RatibGalleryLightbox && typeof window.RatibGalleryLightbox.init === 'function') {
      window.RatibGalleryLightbox.init();
    }
  }

  function boot() {
    initProfileNavHighlight();
    initScrollToCompanyProfile();
    initReveal();
    initArchSync();
    initMetricJitter();
    initEventStream();
    initJumpNavActive();
    initGalleryLightbox();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
