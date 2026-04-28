/**
 * EN: Implements system administration/observability module behavior in `admin/assets/js/create-tenant.js`.
 * AR: ينفذ سلوك وحدة إدارة النظام والمراقبة في `admin/assets/js/create-tenant.js`.
 */
(function () {
    var form = document.getElementById('tenantForm');
    var submitBtn = document.getElementById('submitBtn');
    var message = document.getElementById('message');
    var result = document.getElementById('result');
    var outTenantId = document.getElementById('outTenantId');
    var outDomain = document.getElementById('outDomain');
    var outStatus = document.getElementById('outStatus');

    if (!form || !submitBtn || !message || !result || !outTenantId || !outDomain || !outStatus) {
        return;
    }

    function setMessage(type, text) {
        message.className = 'msg ' + type;
        message.textContent = text;
    }

    function clearOutput() {
        message.className = 'msg';
        message.textContent = '';
        result.style.display = 'none';
        outTenantId.textContent = '-';
        outDomain.textContent = '-';
        outStatus.textContent = '-';
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        clearOutput();

        var agencyNameInput = document.getElementById('agency_name');
        var domainInput = document.getElementById('domain');
        var agencyName = agencyNameInput ? agencyNameInput.value.trim() : '';
        var domain = domainInput ? domainInput.value.trim().toLowerCase() : '';

        if (!agencyName || !domain) {
            setMessage('err', 'Agency name and domain are required.');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Creating...';

        try {
            var response = await fetch('/api/tenants/create-full.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    agency_name: agencyName,
                    domain: domain
                })
            });

            var payload = null;
            try {
                payload = await response.json();
            } catch (jsonErr) {
                throw new Error('Invalid JSON response from server');
            }

            if (!response.ok || !payload || payload.success !== true) {
                var msg = payload && payload.message ? payload.message : 'Tenant creation failed';
                throw new Error(msg);
            }

            var data = payload.data || {};
            outTenantId.textContent = String(data.tenant_id || '-');
            outDomain.textContent = domain;
            outStatus.textContent = String(data.status || 'active');
            result.style.display = 'block';
            setMessage('ok', payload.message || 'Tenant created successfully.');
        } catch (err) {
            setMessage('err', err.message || 'Request failed');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create Tenant';
        }
    });
})();
