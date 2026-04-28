/**
 * EN: Implements control-panel module behavior and admin-country operations in `control-panel/js/control/dashboard.js`.
 * AR: ينفذ سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/js/control/dashboard.js`.
 */
// EN: Dashboard widget bootstrap for users-per-country cards and shortcuts.
// AR: تهيئة ويدجت لوحة التحكم لبطاقات المستخدمين حسب الدولة والروابط المختصرة.
(function() {
    var config = document.getElementById('control-config');
    var apiBase = (config && config.getAttribute('data-api-base')) || '';
    var agenciesUrlBase = (config && config.getAttribute('data-agencies-url-base')) || '';
    var countryUsersUrlBase = (config && config.getAttribute('data-country-users-url-base')) || '';
    var ratibBase = (config && config.getAttribute('data-ratib-base')) || '';
    var grid = document.getElementById('usersPerCountryGrid');
    if (!grid || !apiBase) return;
    apiBase = apiBase.replace(/\/$/, '');
    ratibBase = ratibBase.replace(/\/$/, '') || (window.location.origin || '');
    /** If PHP pageUrl() base disagrees with this deployment, derive panel root from api path (same as dashboard.php $baseUrl). */
    if (!agenciesUrlBase && apiBase) {
        agenciesUrlBase = apiBase.replace(/\/?api\/control$/i, '') + '/pages/control/agencies.php?control=1';
    }
    if (!countryUsersUrlBase && apiBase) {
        countryUsersUrlBase = apiBase.replace(/\/?api\/control$/i, '') + '/pages/control/country-users.php?control=1';
    }

    // EN: Load country/user summary from control API using same-origin session cookies.
    // AR: جلب ملخص الدول/المستخدمين من API لوحة التحكم باستخدام كوكيز الجلسة.
    fetch(apiBase + '/get-users-per-country.php', { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && Array.isArray(data.countries) && data.countries.length > 0) {
                grid.innerHTML = data.countries.map(function(c) {
                    var cid = encodeURIComponent(String(c.id != null ? c.id : ''));
                    var agenciesUrl = agenciesUrlBase ? (agenciesUrlBase + (agenciesUrlBase.indexOf('?') >= 0 ? '&' : '?') + 'country_id=' + cid) : '#';
                    var usersUrl = c.agency_id && countryUsersUrlBase ? (countryUsersUrlBase + '&agency_id=' + encodeURIComponent(String(c.agency_id))) : null;
                    var slug = (c.slug || '').trim();
                    var loginUrl = (ratibBase && slug) ? (ratibBase + '/' + slug + '/login') : null;
                    var linksHtml = '<div class="users-per-country-links">' +
                        (usersUrl ? '<a href="' + usersUrl + '" target="_blank" rel="noopener noreferrer">View Users &rarr;</a>' : '') +
                        '<a href="' + agenciesUrl + '">View Agencies &rarr;</a>' +
                        '</div>';
                    var cardClass = 'users-per-country-card' + (loginUrl ? ' users-per-country-card-clickable' : '');
                    var loginAttr = loginUrl ? (' data-login-url="' + loginUrl.replace(/"/g, '&quot;') + '"') : '';
                    return '<div class="' + cardClass + '"' + loginAttr + '>' +
                        '<div class="country-name">' + (c.name || 'Unknown') + '</div>' +
                        '<div class="users-count">' + (c.users_count || 0) + '</div>' +
                        '<div class="users-label">Users</div>' +
                        linksHtml +
                        '</div>';
                }).join('');
                // EN: Make card body open tenant login in new tab while preserving inner action links.
                // AR: جعل البطاقة تفتح تسجيل دخول المستأجر في تبويب جديد مع إبقاء الروابط الداخلية مستقلة.
                grid.querySelectorAll('.users-per-country-card-clickable').forEach(function(card) {
                    card.addEventListener('click', function(evt) {
                        if (evt.target && evt.target.closest('.users-per-country-links')) return;
                        var loginUrl = card.getAttribute('data-login-url') || '';
                        if (!loginUrl) return;
                        window.open(loginUrl, '_blank', 'noopener,noreferrer');
                    });
                });
            } else {
                grid.innerHTML = '<div class="text-muted control-empty-state">No countries configured.</div>';
            }
        })
        .catch(function() {
            grid.innerHTML = '<div class="text-muted control-empty-state">Failed to load users per country.</div>';
        });
})();
