/**
 * Login Page JavaScript — password / camera barcode login, dark mode
 */

document.addEventListener('DOMContentLoaded', function () {
    const loginMethodSelect = document.getElementById('login-method');
    const passwordForm = document.getElementById('password-form');
    const barcodeForm = document.getElementById('barcode-form');
    const barcodeInput = document.getElementById('barcode-input');
    const barcodeLoginForm = document.getElementById('barcode-login-form');
    const barcodeReaderEl = document.getElementById('barcode-qr-reader');
    const darkModeToggle = document.getElementById('dark-mode-toggle');
    const themeIcon = document.getElementById('theme-icon');
    const body = document.body;
    const animatedBackground = document.getElementById('animated-background');

    let barcodeScanner = null;
    let barcodeCameraStarting = false;

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

    function showBarcodeStatus(message, type) {
        const statusDiv = document.getElementById('barcode-status');
        if (!statusDiv) {
            return;
        }
        statusDiv.classList.remove('d-none');
        statusDiv.className = 'barcode-status ' + (type || 'info') + '-message d-block mt-2';
        statusDiv.textContent = message;
    }

    function submitBarcodeValue(code) {
        if (!barcodeLoginForm || !barcodeInput) {
            return;
        }
        const value = String(code || '').trim();
        if (value.length < 2) {
            return;
        }
        barcodeInput.value = value;
        showBarcodeStatus('Signing in…', 'info');
        stopBarcodeCamera();
        barcodeLoginForm.requestSubmit();
    }

    async function stopBarcodeCamera() {
        barcodeCameraStarting = false;
        if (!barcodeScanner) {
            return;
        }
        try {
            await barcodeScanner.stop();
        } catch (e) {
            /* ignore */
        }
        try {
            await barcodeScanner.clear();
        } catch (e2) {
            /* ignore */
        }
        barcodeScanner = null;
    }

    function getBarcodeFormats() {
        if (typeof Html5QrcodeSupportedFormats === 'undefined') {
            return undefined;
        }
        return [
            Html5QrcodeSupportedFormats.QR_CODE,
            Html5QrcodeSupportedFormats.CODE_128,
            Html5QrcodeSupportedFormats.CODE_39,
            Html5QrcodeSupportedFormats.EAN_13,
            Html5QrcodeSupportedFormats.EAN_8,
            Html5QrcodeSupportedFormats.UPC_A,
            Html5QrcodeSupportedFormats.UPC_E
        ];
    }

    async function startBarcodeCamera() {
        if (barcodeScanner || barcodeCameraStarting || !barcodeReaderEl) {
            return;
        }
        if (typeof Html5Qrcode === 'undefined') {
            showBarcodeStatus('Camera scanner failed to load. Refresh the page and try again.', 'error');
            return;
        }

        barcodeCameraStarting = true;
        showBarcodeStatus('Starting camera…', 'info');

        barcodeScanner = new Html5Qrcode('barcode-qr-reader');
        const config = {
            fps: 12,
            qrbox: function (viewWidth, viewHeight) {
                const w = Math.min(viewWidth * 0.88, 320);
                const h = Math.min(viewHeight * 0.45, 160);
                return { width: Math.floor(w), height: Math.floor(h) };
            },
            aspectRatio: 1.5
        };
        const formats = getBarcodeFormats();
        if (formats) {
            config.formatsToSupport = formats;
        }

        try {
            await barcodeScanner.start(
                { facingMode: 'environment' },
                config,
                function onDecoded(decodedText) {
                    if (!decodedText) {
                        return;
                    }
                    submitBarcodeValue(decodedText);
                },
                function () {
                    /* frame noise */
                }
            );
            barcodeCameraStarting = false;
            showBarcodeStatus('Hold your badge barcode inside the frame.', 'info');
        } catch (err) {
            barcodeCameraStarting = false;
            await stopBarcodeCamera();
            showBarcodeStatus('Could not open camera. Allow camera permission in the browser, then choose Barcode again.', 'error');
        }
    }

    if (loginMethodSelect) {
        hideAllForms();
        showForm(passwordForm);

        loginMethodSelect.addEventListener('change', async function () {
            await stopBarcodeCamera();
            hideAllForms();
            if (this.value === 'barcode') {
                showForm(barcodeForm);
                startBarcodeCamera();
            } else {
                showForm(passwordForm);
            }
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stopBarcodeCamera();
        } else if (loginMethodSelect && loginMethodSelect.value === 'barcode' && barcodeForm && !barcodeForm.classList.contains('d-none')) {
            startBarcodeCamera();
        }
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
