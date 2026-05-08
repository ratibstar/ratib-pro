(function () {
    // Remove legacy ?err= from URL after load so refresh does not imply a new error.
    try {
        const u = new URL(window.location.href);
        if (u.searchParams.has('err')) {
            u.searchParams.delete('err');
            const q = u.searchParams.toString();
            const path = u.pathname + (q ? '?' + q : '') + u.hash;
            window.history.replaceState({}, '', path);
        }
    } catch (e) {
        /* ignore */
    }

    const form = document.getElementById('ppLoginForm');
    const msg = document.getElementById('ppLoginMsg');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (msg) {
            msg.hidden = true;
            msg.textContent = '';
        }
        const username = (document.getElementById('ppUsername')?.value || '').trim();
        const passwordField = document.getElementById('ppPassword')?.value || '';

        const body = {};
        if (username && passwordField) {
            body.username = username;
            body.password = passwordField;
        }

        if (Object.keys(body).length === 0) {
            if (msg) {
                msg.textContent = 'Enter username and password.';
                msg.hidden = false;
            }
            return;
        }

        try {
            const res = await fetch('../api/partnerships/partner-portal-auth.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
            const json = await res.json().catch(() => ({}));
            if (res.ok && json.success) {
                window.location.href = 'partner-portal.php';
                return;
            }
            if (msg) {
                msg.textContent = json.message || 'Could not sign in.';
                msg.hidden = false;
            }
        } catch (err) {
            if (msg) {
                msg.textContent = err && err.message ? err.message : 'Network error.';
                msg.hidden = false;
            }
        }
    });
})();
