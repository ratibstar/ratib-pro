(function () {
    'use strict';

    var MANUAL = '__manual__';

    function b64DecodeUtf8(b64) {
        if (!b64) {
            return '';
        }
        try {
            var bin = atob(String(b64));
            if (typeof TextDecoder !== 'undefined') {
                var bytes = new Uint8Array(bin.length);
                for (var i = 0; i < bin.length; i++) {
                    bytes[i] = bin.charCodeAt(i);
                }
                return new TextDecoder('utf-8').decode(bytes);
            }
            return decodeURIComponent(escape(bin));
        } catch (eDec) {
            return '';
        }
    }

    function selectedOptionLabel(sel) {
        var opt = sel.options[sel.selectedIndex];
        if (!opt) {
            return '';
        }
        var fromData = opt.getAttribute('data-label');
        if (fromData != null && String(fromData) !== '') {
            return String(fromData);
        }
        return String(opt.textContent || '').trim();
    }

    function syncHybrid(wrap, forceFill) {
        var sel = wrap.querySelector('.rateb-hybrid-select');
        var manual = wrap.querySelector('.rateb-hybrid-manual');
        var hidden = wrap.querySelector('.rateb-hybrid-value');
        if (!sel || !manual || !hidden) {
            return;
        }
        var detailsOnPick = wrap.getAttribute('data-details-on-pick') === '1';

        if (sel.value === MANUAL) {
            manual.style.display = '';
            manual.disabled = false;
            manual.required = true;
            if (forceFill && !manual.dataset.touched) {
                manual.value = '';
            }
            hidden.value = manual.value;
            return;
        }

        if (detailsOnPick) {
            if (!sel.value) {
                manual.style.display = 'none';
                manual.disabled = true;
                manual.required = false;
                if (forceFill) {
                    manual.value = '';
                    delete manual.dataset.touched;
                }
                hidden.value = '';
                return;
            }
            // Always show details box under the message options.
            manual.style.display = '';
            manual.disabled = false;
            manual.required = true;
            if (forceFill || !manual.dataset.touched) {
                manual.value = selectedOptionLabel(sel);
                delete manual.dataset.touched;
            }
            hidden.value = manual.value;
            try { manual.focus(); } catch (eF) { /* ignore */ }
            return;
        }

        manual.style.display = 'none';
        manual.disabled = true;
        manual.required = false;
        hidden.value = sel.value;
    }

    /**
     * Fill "ردك" from ready-reply pick. Lives here because form-hybrid.js is a
     * critical layout script (always present) — soft-nav must not depend on
     * page-only support-ticket-ui.js being reinjected.
     */
    function fillSupportReplyFromPick(sel, forceFill) {
        if (!sel) {
            return;
        }
        var box = sel.closest ? sel.closest('[data-support-reply-picker="1"]') : null;
        if (!box) {
            return false;
        }
        var body = box.querySelector('[data-reply-body="1"]');
        var wrap = box.querySelector('[data-reply-body-wrap="1"]');
        var submit = box.querySelector('[data-reply-submit="1"]');
        if (!body) {
            return false;
        }
        if (wrap) {
            wrap.hidden = false;
            wrap.style.display = '';
        }
        body.removeAttribute('hidden');
        body.style.display = '';
        body.setAttribute('required', 'required');

        var val = String(sel.value || '');
        var opt = sel.options[sel.selectedIndex];
        var templateBody = '';
        if (opt) {
            var b64 = opt.getAttribute('data-body-b64');
            if (b64) {
                templateBody = b64DecodeUtf8(b64);
            } else {
                templateBody = String(opt.getAttribute('data-body') || '');
            }
        }

        if (forceFill === undefined) {
            forceFill = true;
        }

        if (val === '') {
            if (forceFill) {
                body.value = '';
                delete body.dataset.manualTouched;
            }
        } else if (val === MANUAL) {
            if (forceFill && !body.dataset.manualTouched) {
                body.value = '';
            }
            try { body.focus(); } catch (eFocus) { /* ignore */ }
        } else if (forceFill || !body.dataset.manualTouched) {
            body.value = templateBody;
            delete body.dataset.manualTouched;
            try { body.focus(); } catch (eFocus2) { /* ignore */ }
        }

        if (submit) {
            submit.disabled = String(body.value || '').trim() === '';
        }
        return true;
    }

    window.ratebFillSupportReplyFromPick = fillSupportReplyFromPick;

    function initHybrid(root) {
        (root || document).querySelectorAll('.rateb-hybrid-field').forEach(function (wrap) {
            var sel = wrap.querySelector('.rateb-hybrid-select');
            if (!sel) {
                return;
            }
            // Initial paint sync (e.g. edit with existing value).
            syncHybrid(wrap, false);
        });
        (root || document).querySelectorAll('[data-support-reply-picker="1"] [data-reply-pick="1"]').forEach(function (sel) {
            if (sel.value && sel.value !== MANUAL) {
                fillSupportReplyFromPick(sel, true);
            }
        });
    }

    function syncAllHybrids(form) {
        if (!form || !form.querySelectorAll) {
            return;
        }
        form.querySelectorAll('.rateb-hybrid-field').forEach(function (wrap) {
            syncHybrid(wrap, false);
        });
    }

    // Document-level delegation survives soft-nav DOM swaps.
    document.addEventListener('change', function (e) {
        var t = e.target;
        if (!t || !t.classList) {
            return;
        }
        if (t.getAttribute && t.getAttribute('data-reply-pick') === '1') {
            if (t.closest && t.closest('[data-support-reply-picker="1"]')) {
                var replyBody = document.getElementById('support_ticket_reply_body');
                if (replyBody) {
                    delete replyBody.dataset.manualTouched;
                }
                fillSupportReplyFromPick(t, true);
            }
            return;
        }
        if (t.classList.contains('rateb-hybrid-select')) {
            var wrap = t.closest('.rateb-hybrid-field');
            if (!wrap) {
                return;
            }
            var manual = wrap.querySelector('.rateb-hybrid-manual');
            if (manual) {
                delete manual.dataset.touched;
            }
            syncHybrid(wrap, true);
        }
    }, true);

    document.addEventListener('input', function (e) {
        var t = e.target;
        if (!t) {
            return;
        }
        if (t.getAttribute && t.getAttribute('data-reply-body') === '1') {
            t.dataset.manualTouched = '1';
            var box = t.closest ? t.closest('[data-support-reply-picker="1"]') : null;
            var submit = box ? box.querySelector('[data-reply-submit="1"]') : null;
            if (submit) {
                submit.disabled = String(t.value || '').trim() === '';
            }
            return;
        }
        if (t.classList && t.classList.contains('rateb-hybrid-manual')) {
            t.dataset.touched = '1';
            var wrap = t.closest('.rateb-hybrid-field');
            var hidden = wrap ? wrap.querySelector('.rateb-hybrid-value') : null;
            if (hidden) {
                hidden.value = t.value;
            }
        }
    }, true);

    document.addEventListener('submit', function (e) {
        syncAllHybrids(e.target);
        var form = e.target;
        if (!form || !form.querySelector) {
            return;
        }
        var pick = form.querySelector('[data-reply-pick="1"]');
        var body = form.querySelector('[data-reply-body="1"]');
        if (pick && body && pick.value && pick.value !== MANUAL && !body.dataset.manualTouched) {
            fillSupportReplyFromPick(pick, true);
        }
    }, true);

    function boot() {
        initHybrid(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    document.addEventListener('rateb:nav:afterEnter', boot);
    document.addEventListener('rateb:soft-nav:afterEnter', boot);

    window.ratebInitHybridFields = initHybrid;
})();
