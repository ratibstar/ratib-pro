/**
 * Login Page JavaScript — method switching, USB barcode scan-only login, dark mode
 */

document.addEventListener('DOMContentLoaded', function () {
    const loginMethodSelect = document.getElementById('login-method');
    const passwordForm = document.getElementById('password-form');
    const barcodeForm = document.getElementById('barcode-form');
    const barcodeInput = document.getElementById('barcode-input');
    const barcodeLoginForm = document.getElementById('barcode-login-form');
    const darkModeToggle = document.getElementById('dark-mode-toggle');
    const themeIcon = document.getElementById('theme-icon');
    const body = document.body;
    const animatedBackground = document.getElementById('animated-background');

    let scanSubmitTimer = null;

    const adPhrases = [
        'RATEB', 'Manage Your Business', 'Streamline Operations', 'Boost Productivity',
        'Smart Solutions', 'Efficient Management', 'Digital Transformation', 'Work Smarter'
    ];
    const professionalSymbols = [
        'fa-briefcase', 'fa-chart-line', 'fa-cog', 'fa-lightbulb', 'fa-rocket', 'fa-shield-alt',
        'fa-star', 'fa-bullseye', 'fa-trophy', 'fa-network-wired'
    ];

    if (animatedBackground) {
        for (let i = 0; i < 6; i++) {
            const el = document.createElement('div');
            el.className = 'animated-text';
            el.textContent = adPhrases[Math.floor(Math.random() * adPhrases.length)];
            animatedBackground.appendChild(el);
        }
        for (let i = 0; i < 8; i++) {
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
    }

    function showForm(form) {
        if (form) {
            form.classList.remove('d-none');
            form.classList.add('d-block');
        }
    }

    function focusBarcodeCapture() {
        if (!barcodeInput) {
            return;
        }
        barcodeInput.value = '';
        setTimeout(function () {
            barcodeInput.focus();
        }, 60);
    }

    function showBarcodeStatus(message) {
        const statusDiv = document.getElementById('barcode-status');
        if (!statusDiv) {
            return;
        }
        statusDiv.classList.remove('d-none');
        statusDiv.className = 'barcode-status info-message d-block mt-2';
        statusDiv.textContent = message;
    }

    function submitBarcodeScan() {
        if (!barcodeLoginForm || !barcodeInput) {
            return;
        }
        const code = String(barcodeInput.value || '').trim();
        if (code.length < 2) {
            return;
        }
        showBarcodeStatus('Signing in…');
        barcodeLoginForm.requestSubmit();
    }

    function scheduleScanSubmit() {
        if (scanSubmitTimer) {
            clearTimeout(scanSubmitTimer);
        }
        scanSubmitTimer = setTimeout(submitBarcodeScan, 180);
    }

    if (loginMethodSelect) {
        hideAllForms();
        showForm(passwordForm);

        loginMethodSelect.addEventListener('change', function () {
            hideAllForms();
            if (this.value === 'barcode') {
                showForm(barcodeForm);
                focusBarcodeCapture();
            } else {
                showForm(passwordForm);
            }
        });
    }

    if (barcodeInput && barcodeLoginForm) {
        barcodeInput.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                submitBarcodeScan();
            }
        });
        barcodeInput.addEventListener('input', scheduleScanSubmit);
    }

    document.addEventListener('click', function (ev) {
        if (!barcodeForm || barcodeForm.classList.contains('d-none')) {
            return;
        }
        if (ev.target.closest('#password-form')) {
            return;
        }
        if (ev.target.closest('#login-method')) {
            return;
        }
        focusBarcodeCapture();
    });

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
