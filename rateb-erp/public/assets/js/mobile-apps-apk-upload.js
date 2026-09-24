/**
 * Mobile Apps — chunked HR APK upload (hosts often cap upload_max_filesize below APK size).
 * Delegated handler so it survives soft navigation.
 */
(function (root) {
    'use strict';

    if (root.__ratebApkUploadBound) {
        return;
    }
    root.__ratebApkUploadBound = true;

    function randomId() {
        var bytes = new Uint8Array(16);
        root.crypto.getRandomValues(bytes);
        return Array.prototype.map.call(bytes, function (b) {
            return ('0' + b.toString(16)).slice(-2);
        }).join('');
    }

    function setProgress(box, pct) {
        var bar = box.querySelector('[data-rateb-apk-progress]');
        if (bar) {
            bar.style.width = pct + '%';
            bar.textContent = pct + '%';
        }
    }

    function showError(box, msg) {
        var el = box.querySelector('[data-rateb-apk-error]');
        if (el) {
            el.textContent = msg;
            el.classList.remove('d-none');
        }
    }

    function sendChunk(box, file, uploadId, index, total, chunkSize) {
        var fd = new FormData();
        fd.append('_csrf', box.getAttribute('data-csrf') || '');
        fd.append('upload_id', uploadId);
        fd.append('index', String(index));
        fd.append('total', String(total));
        fd.append('original_name', file.name);
        fd.append('chunk', file.slice(index * chunkSize, Math.min(file.size, (index + 1) * chunkSize)), 'chunk.bin');
        return root.fetch(box.getAttribute('data-url'), {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            return res.json().catch(function () {
                return { ok: false, message: res.status === 413
                    ? box.getAttribute('data-msg-too-large')
                    : box.getAttribute('data-msg-failed') };
            });
        });
    }

    function start(box) {
        var input = box.querySelector('[data-rateb-apk-file]');
        var btn = box.querySelector('[data-rateb-apk-start]');
        var file = input && input.files ? input.files[0] : null;
        var errEl = box.querySelector('[data-rateb-apk-error]');
        if (errEl) {
            errEl.classList.add('d-none');
        }
        if (!file) {
            return;
        }
        if (!/\.apk$/i.test(file.name)) {
            showError(box, box.getAttribute('data-msg-not-apk'));
            return;
        }
        var max = parseInt(box.getAttribute('data-max') || '0', 10);
        if (max > 0 && file.size > max) {
            showError(box, box.getAttribute('data-msg-too-large'));
            return;
        }
        var chunkSize = parseInt(box.getAttribute('data-chunk') || '2097152', 10);
        var total = Math.max(1, Math.ceil(file.size / chunkSize));
        var uploadId = randomId();
        var wrap = box.querySelector('[data-rateb-apk-progress-wrap]');
        if (wrap) {
            wrap.classList.remove('d-none');
        }
        btn.disabled = true;
        input.disabled = true;
        setProgress(box, 0);

        var index = 0;
        var next = function () {
            sendChunk(box, file, uploadId, index, total, chunkSize).then(function (data) {
                if (!data || !data.ok) {
                    throw new Error((data && data.message) || box.getAttribute('data-msg-failed'));
                }
                index += 1;
                setProgress(box, Math.round((index / total) * 100));
                if (data.done) {
                    root.location.reload();
                    return;
                }
                next();
            }).catch(function (err) {
                btn.disabled = false;
                input.disabled = false;
                showError(box, (err && err.message) || box.getAttribute('data-msg-failed'));
            });
        };
        next();
    }

    root.document.addEventListener('click', function (ev) {
        var btn = ev.target && ev.target.closest ? ev.target.closest('[data-rateb-apk-start]') : null;
        if (!btn) {
            return;
        }
        var box = btn.closest('[data-rateb-apk-upload]');
        if (box) {
            ev.preventDefault();
            start(box);
        }
    });
})(window);
