(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var chip = document.getElementById('rcp-mfa-chip');
        if (chip) {
            chip.textContent =
                'MFA policy: enforce for privileged roles (adapter-ready).';
        }
        var btn = document.getElementById('rcp-revoke-sessions');
        if (btn && window.RATEBClientActions && RATEBClientActions.dispatch) {
            btn.addEventListener('click', function () {
                RATEBClientActions.dispatch('suspend', {
                    targetId: 'other_sessions',
                });
            });
        }
    });
})();
