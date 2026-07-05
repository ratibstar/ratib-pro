(function () {
    'use strict';

    function initCompanyPlanForm() {
        var planSelect = document.getElementById('rateb-company-plan');
        var presetsEl = document.getElementById('rateb-company-plan-presets');
        if (!planSelect || !presetsEl) {
            return;
        }

        var presets = {};
        try {
            presets = JSON.parse(presetsEl.textContent || '{}');
        } catch (e) {
            return;
        }

        var syncInput = document.getElementById('rateb-sync-from-plan');
        var moduleInputs = document.querySelectorAll('#rateb-company-form input[name="modules[]"]');
        var userLimit = document.querySelector('#rateb-company-form input[name="user_limit"]');
        var storageLimit = document.querySelector('#rateb-company-form input[name="storage_limit_mb"]');

        function clearCustomSync() {
            if (syncInput) {
                syncInput.value = '0';
            }
        }

        function applyPlan(planId, fromUser) {
            var preset = presets[String(planId)] || presets[planId];
            if (!preset) {
                return;
            }
            var mods = preset.modules || [];
            moduleInputs.forEach(function (cb) {
                cb.checked = mods.indexOf(cb.value) !== -1;
            });
            if (userLimit && preset.max_users) {
                userLimit.value = String(preset.max_users);
            }
            if (storageLimit && preset.max_storage_mb) {
                storageLimit.value = String(preset.max_storage_mb);
            }
            if (syncInput && fromUser) {
                syncInput.value = '1';
            }
        }

        planSelect.addEventListener('change', function () {
            if (planSelect.value) {
                applyPlan(planSelect.value, true);
            }
        });

        moduleInputs.forEach(function (cb) {
            cb.addEventListener('change', clearCustomSync);
        });
        if (userLimit) {
            userLimit.addEventListener('input', clearCustomSync);
        }
        if (storageLimit) {
            storageLimit.addEventListener('input', clearCustomSync);
        }

        if (planSelect.value) {
            applyPlan(planSelect.value, false);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCompanyPlanForm);
    } else {
        initCompanyPlanForm();
    }
})();
