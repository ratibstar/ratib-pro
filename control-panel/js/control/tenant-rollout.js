(function () {
    var copyBtn = document.getElementById('copyRolloutLinkBtn');
    var flash = document.getElementById('tenantRolloutFlash');
    var urlEl = document.getElementById('tenantRolloutDirectUrl');
    if (!copyBtn || !urlEl) return;

    function showFlash(text, ok) {
        if (!flash) return;
        flash.classList.remove('d-none', 'is-ok', 'is-fail');
        flash.classList.add(ok ? 'is-ok' : 'is-fail');
        flash.textContent = text;
        window.setTimeout(function () {
            flash.classList.add('d-none');
        }, 1800);
    }

    copyBtn.addEventListener('click', function () {
        var url = urlEl.textContent ? urlEl.textContent.trim() : '';
        if (!url) {
            showFlash('No link found.', false);
            return;
        }

        if (!navigator.clipboard || !navigator.clipboard.writeText) {
            showFlash('Clipboard is not supported in this browser.', false);
            return;
        }

        navigator.clipboard.writeText(url)
            .then(function () {
                showFlash('Link copied to clipboard.', true);
            })
            .catch(function () {
                showFlash('Failed to copy link.', false);
            });
    });
})();
