(function () {
    const form = document.getElementById('ppLoginForm');
    const msg = document.getElementById('ppLoginMsg');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (msg) {
            msg.hidden = true;
            msg.textContent = '';
        }
        const email = (document.getElementById('ppEmail')?.value || '').trim();
        const passwordField = document.getElementById('ppPassword')?.value || '';
        const token = (document.getElementById('ppToken')?.value || '').trim();
        const agencyId = parseInt(String(document.getElementById('ppAgencyId')?.value || '0'), 10);

        const body = {};
        if (email && passwordField) {
            body.email = email;
            body.password = passwordField;
        } else if (token) {
            body.token = token;
        } else if (Number.isFinite(agencyId) && agencyId > 0 && passwordField) {
            body.agency_id = agencyId;
            body.password = passwordField;
        }

        if (Object.keys(body).length === 0) {
            if (msg) {
                msg.textContent = 'Enter email and password, or a token, or agency ID with password.';
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
