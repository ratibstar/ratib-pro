/**
 * EN: Implements system administration/observability module behavior in `admin/dashboard/assets/dashboard.js`.
 * AR: ينفذ سلوك وحدة إدارة النظام والمراقبة في `admin/dashboard/assets/dashboard.js`.
 */
(function () {
    var liveKey = 'admin_dashboard_live_mode';
    var sectionKey = 'admin_dashboard_section';
    var liveCheckbox = document.getElementById('liveMode');
    var navLinks = document.querySelectorAll('.nav-link');
    var gatewaySearch = document.getElementById('gatewaySearch');
    var gatewayDecisionFilter = document.getElementById('gatewayDecisionFilter');
    var gatewayTable = document.getElementById('gatewayTable');
    var timer = null;

    function startLive() {
        if (timer !== null) return;
        timer = window.setInterval(function () {
            window.location.reload();
        }, 5000);
    }

    function stopLive() {
        if (timer === null) return;
        window.clearInterval(timer);
        timer = null;
    }

    function setActiveLink(targetId) {
        navLinks.forEach(function (link) {
            if (link.getAttribute('data-section') === targetId) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
    }

    navLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            var targetId = link.getAttribute('data-section');
            window.localStorage.setItem(sectionKey, targetId);
            setActiveLink(targetId);
        });
    });

    if (liveCheckbox) {
        var liveSaved = window.localStorage.getItem(liveKey);
        if (liveSaved === '1') {
            liveCheckbox.checked = true;
            startLive();
        }

        liveCheckbox.addEventListener('change', function () {
            if (liveCheckbox.checked) {
                window.localStorage.setItem(liveKey, '1');
                startLive();
            } else {
                window.localStorage.setItem(liveKey, '0');
                stopLive();
            }
        });
    }

    var savedSection = window.localStorage.getItem(sectionKey);
    if (savedSection) {
        setActiveLink(savedSection);
    }

    function applyGatewayFilters() {
        if (!gatewayTable) return;
        var search = gatewaySearch ? gatewaySearch.value.toLowerCase().trim() : '';
        var decision = gatewayDecisionFilter ? gatewayDecisionFilter.value : 'all';
        var rows = gatewayTable.querySelectorAll('tbody tr');

        rows.forEach(function (row) {
            if (row.children.length === 1 && row.classList.contains('muted')) {
                return;
            }

            var rowText = row.textContent.toLowerCase();
            var rowDecision = row.getAttribute('data-decision') || '';
            var searchOk = search === '' || rowText.indexOf(search) !== -1;
            var decisionOk = decision === 'all' || rowDecision === decision;

            row.style.display = (searchOk && decisionOk) ? '' : 'none';
        });
    }

    if (gatewaySearch) {
        gatewaySearch.addEventListener('input', applyGatewayFilters);
    }
    if (gatewayDecisionFilter) {
        gatewayDecisionFilter.addEventListener('change', applyGatewayFilters);
    }
})();

