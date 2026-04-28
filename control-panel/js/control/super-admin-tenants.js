/**
 * EN: Implements control-panel module behavior and admin-country operations in `control-panel/js/control/super-admin-tenants.js`.
 * AR: ينفذ سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/js/control/super-admin-tenants.js`.
 */
(function() {
    var config = document.getElementById('control-config');
    var pageConfig = document.getElementById('page-config');
    var apiBase = (config && config.getAttribute('data-api-base')) || '';
    apiBase = apiBase.replace(/\/$/, '');
    var agenciesUrl = (pageConfig && pageConfig.getAttribute('data-agencies-url')) || '';
    var grid = document.getElementById('countriesLoginGrid');
    if (!grid || !apiBase) return;

    fetch(apiBase + '/get-countries-with-login.php', { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && Array.isArray(data.countries) && data.countries.length > 0) {
                grid.innerHTML = data.countries.map(function(c) {
                    var agenciesLink = agenciesUrl ? (agenciesUrl + '&country_id=' + c.id) : ('#');
                    var card = '<div class="country-login-card">' +
                        '<div class="country-name">' + (c.name || 'Unknown') + '</div>' +
                        '<div class="users-count">' + (c.users_count || 0) + '</div>' +
                        '<div class="users-label">Users</div>';
                    if (c.login_url) {
                        card += '<a href="' + c.login_url + '" target="_blank" class="btn-login"><i class="fas fa-sign-in-alt"></i> Login</a>';
                    }
                    card += '<a href="' + agenciesLink + '" class="btn-agencies">View Agencies &rarr;</a></div>';
                    return card;
                }).join('');
            } else {
                grid.innerHTML = '<div class="text-muted control-empty-state">No countries configured.</div>';
            }
        })
        .catch(function() {
            grid.innerHTML = '<div class="text-muted control-empty-state">Failed to load countries.</div>';
        });
})();
