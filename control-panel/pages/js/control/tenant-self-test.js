/**
 * Compatibility shim for tenant self-test button wiring.
 * Some deployments may still reference this legacy script path.
 */
(function() {
    var runBtn = document.getElementById('runTenantSelfTestBtn');
    var resultEl = document.getElementById('tenantSelfTestResult');
    var config = document.getElementById('control-config');
    if (!runBtn || !resultEl || !config) return;

    // If the main dashboard script already attached handler, avoid duplicate binding.
    if (runBtn.getAttribute('data-tenant-self-test-bound') === '1') return;
    runBtn.setAttribute('data-tenant-self-test-bound', '1');

    var apiBase = (config.getAttribute('data-api-base') || '').replace(/\/$/, '');
    var explicitUrl = config.getAttribute('data-tenant-self-test-url') || '';

    function setResult(status, message) {
        resultEl.classList.remove('tenant-self-test-idle', 'tenant-self-test-running', 'tenant-self-test-pass', 'tenant-self-test-fail');
        resultEl.classList.add('tenant-self-test-' + status);
        resultEl.innerHTML = '<span class="tenant-self-test-badge">' + status.toUpperCase() + '</span>' +
            '<span class="tenant-self-test-text">' + message + '</span>';
    }

    function resolveUrl() {
        if (explicitUrl) return explicitUrl;
        if (apiBase) return apiBase.replace(/\/?api\/control$/i, '') + '/api/diagnostics/tenant-isolation-self-test.php';
        return '/api/diagnostics/tenant-isolation-self-test.php';
    }

    runBtn.addEventListener('click', function() {
        runBtn.disabled = true;
        setResult('running', 'Running isolation checks...');
        fetch(resolveUrl(), { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data && data.success === true && data.isolation_ok === true) {
                    var dbName = (data.runtime_context && data.runtime_context.db_name_active) ? data.runtime_context.db_name_active : 'N/A';
                    setResult('pass', 'PASS - isolation is healthy. Active DB: ' + dbName);
                } else if (data && data.success === true) {
                    setResult('fail', 'FAIL - one or more checks failed.');
                } else {
                    setResult('fail', 'Test failed to run.');
                }
            })
            .catch(function() {
                setResult('fail', 'Request error while running test.');
            })
            .finally(function() {
                runBtn.disabled = false;
            });
    });
})();

