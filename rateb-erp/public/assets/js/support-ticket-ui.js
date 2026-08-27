/**
 * Support ticket UI: searchable canned replies + thread show-more (last 3).
 * Selecting a ready reply always fills "Your reply" (editable afterward).
 */
(function () {
    'use strict';

    var VISIBLE_LIMIT = 3;

    function normalize(s) {
        try {
            return String(s || '').toLowerCase();
        } catch (e) {
            return String(s || '');
        }
    }

    function loadTemplateBodies(box) {
        if (box._replyBodyMap) {
            return box._replyBodyMap;
        }
        var map = {};
        var el = box.querySelector('[data-reply-templates-json="1"]');
        if (el) {
            try {
                var list = JSON.parse(String(el.textContent || '[]'));
                if (Array.isArray(list)) {
                    list.forEach(function (tpl) {
                        if (!tpl || tpl.id == null) {
                            return;
                        }
                        map[String(tpl.id)] = String(tpl.body || '');
                    });
                }
            } catch (eParse) {
                map = {};
            }
        }
        box._replyBodyMap = map;
        return map;
    }

    function applyThreadCollapse(root, expanded) {
        if (!root) {
            return;
        }
        var body = root.querySelector('[data-rateb-ticket-thread-body="1"]');
        if (!body) {
            return;
        }
        var limit = parseInt(root.getAttribute('data-thread-visible-limit') || String(VISIBLE_LIMIT), 10) || VISIBLE_LIMIT;
        var msgs = Array.prototype.slice.call(body.querySelectorAll('[data-thread-msg="1"]'));
        if (msgs.length === 0) {
            Array.prototype.forEach.call(body.children, function (el) {
                if (el.classList && el.classList.contains('support-ticket-thread__msg')) {
                    el.setAttribute('data-thread-msg', '1');
                    msgs.push(el);
                }
            });
        }
        var total = msgs.length;
        var hiddenCount = Math.max(0, total - limit);
        var startVisible = expanded ? 0 : hiddenCount;

        var moreWrap = body.querySelector('[data-thread-more-wrap="1"]');
        if (hiddenCount > 0) {
            var moreTpl = root.getAttribute('data-label-more-tpl')
                || (window.__RATEB_ST_THREAD_MORE__ || 'Show more (:count older messages)');
            var lessLbl = root.getAttribute('data-label-less')
                || (window.__RATEB_ST_THREAD_LESS__ || 'Show less');
            var moreLabel = String(moreTpl).split(':count').join(String(hiddenCount));
            if (!moreWrap) {
                moreWrap = document.createElement('div');
                moreWrap.className = 'support-ticket-thread__more-wrap';
                moreWrap.setAttribute('data-thread-more-wrap', '1');
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm btn-outline-secondary support-ticket-thread__more-btn';
                btn.setAttribute('data-thread-more-btn', '1');
                btn.innerHTML = '<i class="fas fa-chevron-up ms-1"></i><span data-thread-more-label="1"></span>';
                moreWrap.appendChild(btn);
                body.insertBefore(moreWrap, body.firstChild);
            }
            var moreBtn = moreWrap.querySelector('[data-thread-more-btn="1"]');
            var labelEl = moreWrap.querySelector('[data-thread-more-label="1"]');
            if (moreBtn) {
                moreBtn.setAttribute('data-label-more', moreLabel);
                moreBtn.setAttribute('data-label-less', lessLbl);
                moreBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                var icon = moreBtn.querySelector('i');
                if (icon) {
                    icon.className = expanded ? 'fas fa-chevron-down ms-1' : 'fas fa-chevron-up ms-1';
                }
            }
            if (labelEl) {
                labelEl.textContent = expanded ? lessLbl : moreLabel;
            }
            moreWrap.hidden = false;
        } else if (moreWrap) {
            moreWrap.hidden = true;
        }

        msgs.forEach(function (el, idx) {
            var isOlder = idx < startVisible;
            if (expanded || !isOlder) {
                el.hidden = false;
                el.classList.remove('is-collapsed');
            } else {
                el.hidden = true;
                el.classList.add('is-collapsed');
            }
            el.classList.toggle('support-ticket-thread__msg--older', idx < hiddenCount);
        });

        var visiblePrevStaff = null;
        msgs.forEach(function (el) {
            if (el.hidden || el.classList.contains('is-collapsed')) {
                el.classList.remove('support-ticket-thread__msg--continued');
                return;
            }
            var isStaff = el.getAttribute('data-is-staff') === '1'
                || el.classList.contains('support-ticket-thread__msg--staff');
            var continued = visiblePrevStaff !== null && visiblePrevStaff === isStaff;
            el.classList.toggle('support-ticket-thread__msg--continued', continued);
            visiblePrevStaff = isStaff;
        });

        root.setAttribute('data-thread-expanded', expanded ? '1' : '0');
    }

    function initThread(root) {
        if (!root || root.dataset.threadBound === '1') {
            return;
        }
        root.dataset.threadBound = '1';
        var expanded = root.getAttribute('data-thread-expanded') === '1';
        applyThreadCollapse(root, expanded);

        root.addEventListener('click', function (e) {
            var btn = e.target && e.target.closest ? e.target.closest('[data-thread-more-btn="1"]') : null;
            if (!btn || !root.contains(btn)) {
                return;
            }
            e.preventDefault();
            var next = root.getAttribute('data-thread-expanded') !== '1';
            applyThreadCollapse(root, next);
        });
    }

    function syncReplyPicker(box, forceFill) {
        var pick = box.querySelector('[data-reply-pick="1"]');
        var body = box.querySelector('[data-reply-body="1"]');
        var wrap = box.querySelector('[data-reply-body-wrap="1"]');
        var submit = box.querySelector('[data-reply-submit="1"]');
        if (!body) {
            return;
        }
        if (wrap) {
            wrap.hidden = false;
        }
        body.setAttribute('required', 'required');
        if (!pick) {
            if (submit) {
                submit.disabled = String(body.value || '').trim() === '';
            }
            return;
        }
        var val = String(pick.value || '');
        var bodies = loadTemplateBodies(box);
        var templateBody = Object.prototype.hasOwnProperty.call(bodies, val) ? String(bodies[val] || '') : '';

        if (val === '') {
            if (forceFill) {
                body.value = '';
                delete body.dataset.manualTouched;
            }
        } else if (val === '__manual__') {
            if (forceFill || !body.dataset.manualTouched) {
                // Keep typed text if user already started manual entry; clear only on fresh pick.
                if (forceFill && !body.dataset.manualTouched) {
                    body.value = '';
                }
            }
            try { body.focus(); } catch (eFocus) { /* ignore */ }
        } else if (forceFill || !body.dataset.manualTouched) {
            body.value = templateBody;
            delete body.dataset.manualTouched;
        }

        if (submit) {
            submit.disabled = String(body.value || '').trim() === '';
        }
    }

    function filterReplyOptions(box, query) {
        var pick = box.querySelector('[data-reply-pick="1"]');
        if (!pick) {
            return;
        }
        var q = normalize(query);
        Array.prototype.forEach.call(pick.options, function (opt, idx) {
            if (idx === 0) {
                opt.hidden = false;
                return;
            }
            if (!q) {
                opt.hidden = false;
                return;
            }
            var hay = normalize(opt.getAttribute('data-search') || opt.textContent || '');
            opt.hidden = hay.indexOf(q) === -1;
        });
        var cur = pick.options[pick.selectedIndex];
        if (cur && cur.hidden && pick.value !== '') {
            pick.value = '';
            syncReplyPicker(box, true);
        }
    }

    function initReplyPicker(box) {
        if (!box || box.dataset.replyBound === '1') {
            return;
        }
        box.dataset.replyBound = '1';
        box._replyBodyMap = null;
        var search = box.querySelector('[data-reply-search="1"]');
        var pick = box.querySelector('[data-reply-pick="1"]');
        var body = box.querySelector('[data-reply-body="1"]');
        var form = box.querySelector('form');

        if (search) {
            search.addEventListener('input', function () {
                filterReplyOptions(box, search.value);
            });
        }
        if (pick) {
            pick.addEventListener('change', function () {
                if (body) {
                    delete body.dataset.manualTouched;
                }
                // Always inject the chosen template into "Your reply"; user may edit after.
                syncReplyPicker(box, true);
            });
        }
        if (body) {
            body.addEventListener('input', function () {
                body.dataset.manualTouched = '1';
                if (pick && pick.value && pick.value !== '__manual__') {
                    // Edited a template → treat as custom from now until another pick.
                }
                var submit = box.querySelector('[data-reply-submit="1"]');
                if (submit) {
                    submit.disabled = String(body.value || '').trim() === '';
                }
            });
        }
        if (form) {
            form.addEventListener('submit', function (e) {
                if (pick && pick.value && pick.value !== '__manual__' && body && !body.dataset.manualTouched) {
                    syncReplyPicker(box, true);
                }
                if (String((body && body.value) || '').trim() === '') {
                    e.preventDefault();
                    if (body) {
                        body.focus();
                    } else if (pick) {
                        pick.focus();
                    }
                }
            });
        }
        syncReplyPicker(box, false);
    }

    function boot() {
        document.querySelectorAll('[data-rateb-ticket-live="1"]').forEach(function (root) {
            initThread(root);
        });
        document.querySelectorAll('[data-support-reply-picker="1"]').forEach(function (box) {
            // Soft-nav may re-inject the form; allow re-bind on a fresh node.
            if (box.dataset.replyBound === '1' && !box.querySelector('[data-reply-pick="1"]')) {
                delete box.dataset.replyBound;
            }
            initReplyPicker(box);
        });
    }

    window.RatebSupportTicketUi = {
        applyThreadCollapse: applyThreadCollapse,
        refreshThread: function (root) {
            if (!root) {
                root = document.querySelector('[data-rateb-ticket-live="1"]');
            }
            if (!root) {
                return;
            }
            root.dataset.threadBound = '0';
            initThread(root);
            applyThreadCollapse(root, root.getAttribute('data-thread-expanded') === '1');
        },
        boot: boot
    };

    document.addEventListener('DOMContentLoaded', boot);
    document.addEventListener('rateb:soft-nav:afterEnter', function () {
        document.querySelectorAll('[data-support-reply-picker="1"]').forEach(function (box) {
            delete box.dataset.replyBound;
            box._replyBodyMap = null;
        });
        boot();
    });
    if (document.readyState !== 'loading') {
        boot();
    }
})();
