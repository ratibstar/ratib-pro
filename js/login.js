/**
 * Login Page JavaScript — password / cross-device barcode (phone scans, PC opens)
 */

document.addEventListener('DOMContentLoaded', function () {
    const loginMethodSelect = document.getElementById('login-method');
    const passwordForm = document.getElementById('password-form');
    const barcodeForm = document.getElementById('barcode-form');
    const barcodeDesktopPanel = document.getElementById('barcode-desktop-panel');
    const barcodeMobileHint = document.getElementById('barcode-mobile-hint');
    const barcodePairQr = document.getElementById('barcode-pair-qr');
    const barcodePairWaiting = document.getElementById('barcode-pair-waiting');
    const darkModeToggle = document.getElementById('dark-mode-toggle');
    const themeIcon = document.getElementById('theme-icon');
    const body = document.body;
    const animatedBackground = document.getElementById('animated-background');

    let pairPollTimer = null;
    let pairToken = null;

    const isPhoneDevice = (function () {
        const ua = navigator.userAgent || '';
        const mobileUa = /Android|iPhone|iPod|Mobile|webOS|BlackBerry|IEMobile|Opera Mini/i.test(ua);
        const narrow = window.matchMedia('(max-width: 820px)').matches;
        const touch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        return mobileUa || (narrow && touch);
    })();

    if (animatedBackground) {
        const adPhrases = ['RATEB', 'Manage Your Business', 'Streamline Operations', 'Smart Solutions'];
        const professionalSymbols = ['fa-briefcase', 'fa-chart-line', 'fa-cog', 'fa-lightbulb', 'fa-rocket'];
        for (let i = 0; i < 4; i++) {
            const el = document.createElement('div');
            el.className = 'animated-text';
            el.textContent = adPhrases[Math.floor(Math.random() * adPhrases.length)];
            animatedBackground.appendChild(el);
        }
        for (let i = 0; i < 6; i++) {
            const el = document.createElement('div');
            el.className = 'animated-symbol';
            el.innerHTML = '<i class="fas ' + professionalSymbols[Math.floor(Math.random() * professionalSymbols.length)] + '"></i>';
            animatedBackground.appendChild(el);
        }
    }

    if (darkModeToggle) {
        const savedTheme = localStorage.getItem('theme') || 'dark';
        if (savedTheme === 'dark') {
            body.classList.add('dark-mode');
            if (themeIcon) {
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
            }
        }
        darkModeToggle.addEventListener('click', function () {
            body.classList.toggle('dark-mode');
            if (themeIcon) {
                if (body.classList.contains('dark-mode')) {
                    themeIcon.classList.remove('fa-moon');
                    themeIcon.classList.add('fa-sun');
                    localStorage.setItem('theme', 'dark');
                } else {
                    themeIcon.classList.remove('fa-sun');
                    themeIcon.classList.add('fa-moon');
                    localStorage.setItem('theme', 'light');
                }
            }
        });
    }

    function hideAllForms() {
        [passwordForm, barcodeForm].forEach(function (form) {
            if (form) {
                form.classList.add('d-none');
                form.classList.remove('d-block');
            }
        });
        clearPairSession();
    }

    function showForm(form) {
        if (form) {
            form.classList.remove('d-none');
            form.classList.add('d-block');
        }
    }

    function showBarcodeStatus(message, type) {
        const statusDiv = document.getElementById('barcode-status');
        if (!statusDiv) {
            return;
        }
        statusDiv.classList.remove('d-none');
        statusDiv.className = 'barcode-status ' + (type || 'info') + '-message d-block mt-2';
        statusDiv.textContent = message;
    }

    function stopPairPolling() {
        if (pairPollTimer) {
            clearInterval(pairPollTimer);
            pairPollTimer = null;
        }
    }

    function clearPairSession() {
        stopPairPolling();
        pairToken = null;
    }

    function renderPairQr(scanUrl) {
        if (!barcodePairQr) {
            return;
        }
        barcodePairQr.innerHTML = '';
        const img = document.createElement('img');
        img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=' + encodeURIComponent(scanUrl);
        img.alt = 'Open phone scanner';
        img.width = 240;
        img.height = 240;
        img.className = 'barcode-login-url-qr-img';
        barcodePairQr.appendChild(img);
    }

    function completePairOnDesktop(token) {
        stopPairPolling();
        if (barcodePairWaiting) {
            barcodePairWaiting.innerHTML = '<i class="fas fa-check-circle text-success"></i> Signed in — opening RATEB…';
        }
        showBarcodeStatus('Login successful. Opening your workspace…', 'info');
        const u = new URL(window.location.href);
        u.searchParams.set('barcode_pair', token);
        u.searchParams.delete('message');
        window.location.href = u.toString();
    }

    async function pollPairToken(token) {
        const cfg = window.RATIB_LOGIN_PAIR || {};
        const apiPair = cfg.apiPair || '/api/login-barcode-pair.php';
        try {
            const res = await fetch(apiPair, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'poll', token: token })
            });
            const json = await res.json();
            if (json.success && json.status === 'approved') {
                completePairOnDesktop(token);
            } else if (json.status === 'expired') {
                stopPairPolling();
                showBarcodeStatus('Session expired. Choose Barcode again.', 'error');
            }
        } catch (e) {
            /* keep polling */
        }
    }

    async function startDesktopBarcodePair() {
        const cfg = window.RATIB_LOGIN_PAIR || {};
        const apiPair = cfg.apiPair || '/api/login-barcode-pair.php';
        let scanBase = cfg.scanPage || 'login-scan.php';
        if (scanBase.indexOf('http') !== 0) {
            if (scanBase.indexOf('/') === 0) {
                scanBase = window.location.origin + scanBase;
            } else {
                const pathDir = window.location.pathname.replace(/\/[^/]*$/, '/');
                scanBase = window.location.origin + pathDir + scanBase.replace(/^\.\.\//, '').replace(/^\.\//, '');
            }
        }

        showBarcodeStatus('Preparing phone scanner…', 'info');
        if (barcodePairWaiting) {
            barcodePairWaiting.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Waiting for phone scan…';
        }

        try {
            const res = await fetch(apiPair, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'create',
                    country_id: cfg.countryId || 0,
                    agency_id: cfg.agencyId || 0,
                    country_slug: cfg.countrySlug || '',
                    country_name: cfg.countryName || '',
                    agency_name: cfg.agencyName || '',
                    control: cfg.control ? 1 : 0
                })
            });
            const json = await res.json();
            if (!json.success || !json.token) {
                showBarcodeStatus(json.message || 'Could not start barcode login.', 'error');
                return;
            }
            pairToken = json.token;
            const scanUrl = scanBase + (scanBase.indexOf('?') >= 0 ? '&' : '?') + 'token=' + encodeURIComponent(pairToken);
            renderPairQr(scanUrl);
            showBarcodeStatus('Scan the QR with your phone, then scan your user badge.', 'info');
            stopPairPolling();
            const activeToken = pairToken;
            pairPollTimer = setInterval(function () {
                pollPairToken(activeToken);
            }, 1500);
            pollPairToken(activeToken);
        } catch (e) {
            showBarcodeStatus('Network error. Refresh and try again.', 'error');
        }
    }

    function showBarcodeLoginPanel() {
        if (isPhoneDevice) {
            if (barcodeDesktopPanel) {
                barcodeDesktopPanel.classList.add('d-none');
            }
            if (barcodeMobileHint) {
                barcodeMobileHint.classList.remove('d-none');
                barcodeMobileHint.classList.add('d-block');
            }
            showBarcodeStatus('Open login on your computer and scan the QR shown there.', 'info');
            clearPairSession();
            return;
        }
        if (barcodeMobileHint) {
            barcodeMobileHint.classList.add('d-none');
        }
        if (barcodeDesktopPanel) {
            barcodeDesktopPanel.classList.remove('d-none');
            barcodeDesktopPanel.classList.add('d-block');
        }
        startDesktopBarcodePair();
    }

    if (loginMethodSelect) {
        hideAllForms();
        showForm(passwordForm);

        loginMethodSelect.addEventListener('change', function () {
            hideAllForms();
            if (this.value === 'barcode') {
                showForm(barcodeForm);
                showBarcodeLoginPanel();
            } else {
                showForm(passwordForm);
            }
        });
    }

    const successMessage = document.querySelector('.success-message');
    if (successMessage) {
        setTimeout(function () {
            successMessage.classList.add('fade-out');
            setTimeout(function () {
                successMessage.classList.add('d-none');
                successMessage.classList.remove('fade-out');
            }, 500);
        }, 5000);
    }
});
