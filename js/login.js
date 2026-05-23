/**
 * Login Page JavaScript — password / phone-only barcode camera login
 */

document.addEventListener('DOMContentLoaded', function () {
    const loginMethodSelect = document.getElementById('login-method');
    const passwordForm = document.getElementById('password-form');
    const barcodeForm = document.getElementById('barcode-form');
    const barcodeMobilePanel = document.getElementById('barcode-mobile-panel');
    const barcodeDesktopPanel = document.getElementById('barcode-desktop-panel');
    const barcodeInput = document.getElementById('barcode-input');
    const barcodeLoginForm = document.getElementById('barcode-login-form');
    const barcodeReaderEl = document.getElementById('barcode-qr-reader');
    const barcodeCameraWrap = document.getElementById('barcode-camera-wrap');
    const barcodeStartBtn = document.getElementById('barcode-start-camera');
    const barcodeManualInput = document.getElementById('barcode-manual-input');
    const barcodeManualSubmit = document.getElementById('barcode-manual-submit');
    const barcodeManualInputDesktop = document.getElementById('barcode-manual-input-desktop');
    const barcodeManualSubmitDesktop = document.getElementById('barcode-manual-submit-desktop');
    const barcodeLoginUrlQr = document.getElementById('barcode-login-url-qr');
    const barcodeLoginUrlText = document.getElementById('barcode-login-url-text');
    const darkModeToggle = document.getElementById('dark-mode-toggle');
    const themeIcon = document.getElementById('theme-icon');
    const body = document.body;
    const animatedBackground = document.getElementById('animated-background');

    let barcodeScanner = null;
    let barcodeCameraStarting = false;
    let lastScanAt = 0;
    let desktopLoginQrRendered = false;

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

    function resetBarcodeUi() {
        stopBarcodeCamera();
        if (barcodeStartBtn) {
            barcodeStartBtn.classList.remove('d-none');
            barcodeStartBtn.disabled = false;
        }
    }

    function submitBarcodeValue(code) {
        if (!barcodeLoginForm || !barcodeInput) {
            return;
        }
        const value = String(code || '').trim();
        if (value.length < 2) {
            showBarcodeStatus('Barcode code is too short.', 'error');
            return;
        }
        const now = Date.now();
        if (now - lastScanAt < 2500) {
            return;
        }
        lastScanAt = now;
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
        const scanner = barcodeScanner;
        barcodeScanner = null;
        try {
            await scanner.stop();
        } catch (e) {
            /* ignore */
        }
        try {
            await scanner.clear();
        } catch (e2) {
            /* ignore */
        }
    }

    function pickCameraId(cameras) {
        if (!cameras || !cameras.length) {
            return null;
        }
        const back = cameras.find(function (c) {
            const label = (c.label || '').toLowerCase();
            return /back|rear|environment|wide|telephoto/.test(label);
        });
        if (back) {
            return back.id;
        }
        if (cameras.length > 1) {
            return cameras[cameras.length - 1].id;
        }
        return cameras[0].id;
    }

    function buildScanConfig() {
        return {
            fps: 12,
            qrbox: function (viewWidth, viewHeight) {
                const w = Math.min(viewWidth * 0.94, 400);
                const h = Math.min(viewHeight * 0.62, 280);
                return { width: Math.floor(w), height: Math.floor(h) };
            },
            aspectRatio: 1.333333
        };
    }

    async function tryStartWithCameraId(cameraId) {
        const config = buildScanConfig();
        await barcodeScanner.start(
            cameraId,
            config,
            function onDecoded(decodedText) {
                if (decodedText) {
                    submitBarcodeValue(decodedText);
                }
            },
            function () {
                /* frame noise */
            }
        );
    }

    async function tryStartWithFacingMode(facingMode) {
        const config = buildScanConfig();
        await barcodeScanner.start(
            { facingMode: facingMode },
            config,
            function onDecoded(decodedText) {
                if (decodedText) {
                    submitBarcodeValue(decodedText);
                }
            },
            function () {
                /* frame noise */
            }
        );
    }

    async function startBarcodeCamera() {
        if (!isPhoneDevice) {
            showBarcodeStatus('Camera scan works on phones only. Use the QR below to open login on your mobile.', 'info');
            return;
        }
        if (barcodeScanner || barcodeCameraStarting || !barcodeReaderEl) {
            return;
        }
        if (typeof Html5Qrcode === 'undefined') {
            showBarcodeStatus('Scanner library did not load. Refresh the page.', 'error');
            return;
        }

        barcodeCameraStarting = true;
        showBarcodeStatus('Starting phone camera…', 'info');

        if (barcodeCameraWrap) {
            barcodeCameraWrap.classList.remove('d-none');
        }
        if (barcodeStartBtn) {
            barcodeStartBtn.classList.add('d-none');
        }

        barcodeScanner = new Html5Qrcode('barcode-qr-reader');

        try {
            let cameras = [];
            try {
                cameras = await Html5Qrcode.getCameras();
            } catch (camErr) {
                cameras = [];
            }

            const cameraId = pickCameraId(cameras);
            if (cameraId) {
                await tryStartWithCameraId(cameraId);
            } else {
                await tryStartWithFacingMode('environment');
            }

            barcodeCameraStarting = false;
            showBarcodeStatus('Point at the QR code from Users settings.', 'info');
        } catch (err) {
            barcodeCameraStarting = false;
            await stopBarcodeCamera();
            if (barcodeStartBtn) {
                barcodeStartBtn.classList.remove('d-none');
            }
            console.error('Barcode camera error:', err);
            showBarcodeStatus(
                'Allow camera access, then tap Start phone camera again. Use Chrome or Safari on HTTPS.',
                'error'
            );
        }
    }

    function renderDesktopLoginQr() {
        if (!barcodeLoginUrlQr || desktopLoginQrRendered) {
            return;
        }
        const loginUrl = window.location.href.split('#')[0];
        if (barcodeLoginUrlText) {
            barcodeLoginUrlText.textContent = loginUrl;
        }
        const img = document.createElement('img');
        img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(loginUrl);
        img.alt = 'Open login on your phone';
        img.width = 220;
        img.height = 220;
        img.className = 'barcode-login-url-qr-img';
        barcodeLoginUrlQr.appendChild(img);
        desktopLoginQrRendered = true;
    }

    function showBarcodeLoginPanel() {
        if (barcodeMobilePanel) {
            barcodeMobilePanel.classList.toggle('d-none', !isPhoneDevice);
            barcodeMobilePanel.classList.toggle('d-block', isPhoneDevice);
        }
        if (barcodeDesktopPanel) {
            barcodeDesktopPanel.classList.toggle('d-none', isPhoneDevice);
            barcodeDesktopPanel.classList.toggle('d-block', !isPhoneDevice);
        }
        if (barcodeCameraWrap && isPhoneDevice) {
            barcodeCameraWrap.classList.remove('d-none');
        }
        if (!isPhoneDevice) {
            renderDesktopLoginQr();
            showBarcodeStatus('Open login on your phone, or type your barcode code below.', 'info');
        } else {
            showBarcodeStatus('Tap Start phone camera, allow access, then scan the QR from Users.', 'info');
        }
    }

    if (barcodeStartBtn) {
        barcodeStartBtn.addEventListener('click', function () {
            startBarcodeCamera();
        });
    }

    if (barcodeManualSubmit && barcodeManualInput) {
        barcodeManualSubmit.addEventListener('click', function () {
            submitBarcodeValue(barcodeManualInput.value);
        });
        barcodeManualInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitBarcodeValue(barcodeManualInput.value);
            }
        });
    }

    if (barcodeManualSubmitDesktop && barcodeManualInputDesktop) {
        barcodeManualSubmitDesktop.addEventListener('click', function () {
            submitBarcodeValue(barcodeManualInputDesktop.value);
        });
        barcodeManualInputDesktop.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitBarcodeValue(barcodeManualInputDesktop.value);
            }
        });
    }

    if (loginMethodSelect) {
        hideAllForms();
        showForm(passwordForm);

        loginMethodSelect.addEventListener('change', async function () {
            resetBarcodeUi();
            hideAllForms();
            if (this.value === 'barcode') {
                showForm(barcodeForm);
                showBarcodeLoginPanel();
            } else {
                showForm(passwordForm);
            }
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stopBarcodeCamera();
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
