/**
 * Login Page JavaScript — password / mobile camera barcode login, dark mode
 */

document.addEventListener('DOMContentLoaded', function () {
    const loginMethodSelect = document.getElementById('login-method');
    const passwordForm = document.getElementById('password-form');
    const barcodeForm = document.getElementById('barcode-form');
    const barcodeInput = document.getElementById('barcode-input');
    const barcodeLoginForm = document.getElementById('barcode-login-form');
    const barcodeReaderEl = document.getElementById('barcode-qr-reader');
    const barcodeCameraWrap = document.getElementById('barcode-camera-wrap');
    const barcodeStartBtn = document.getElementById('barcode-start-camera');
    const darkModeToggle = document.getElementById('dark-mode-toggle');
    const themeIcon = document.getElementById('theme-icon');
    const body = document.body;
    const animatedBackground = document.getElementById('animated-background');

    let barcodeScanner = null;
    let barcodeCameraStarting = false;
    let lastScanAt = 0;

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
        if (barcodeCameraWrap) {
            barcodeCameraWrap.classList.add('d-none');
        }
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
            return /back|rear|environment|wide/.test(label);
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
            fps: 10,
            qrbox: function (viewWidth, viewHeight) {
                const w = Math.min(viewWidth * 0.92, 360);
                const h = Math.min(viewHeight * 0.55, 220);
                return { width: Math.floor(w), height: Math.floor(h) };
            },
            aspectRatio: 1.777778
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
        if (barcodeScanner || barcodeCameraStarting || !barcodeReaderEl) {
            return;
        }
        if (typeof Html5Qrcode === 'undefined') {
            showBarcodeStatus('Scanner library did not load. Check your connection and refresh.', 'error');
            return;
        }

        barcodeCameraStarting = true;
        showBarcodeStatus('Requesting camera…', 'info');

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
                try {
                    await tryStartWithFacingMode('environment');
                } catch (envErr) {
                    await tryStartWithFacingMode('user');
                }
            }

            barcodeCameraStarting = false;
            showBarcodeStatus('Align the barcode inside the frame.', 'info');
        } catch (err) {
            barcodeCameraStarting = false;
            await stopBarcodeCamera();
            if (barcodeStartBtn) {
                barcodeStartBtn.classList.remove('d-none');
            }
            console.error('Barcode camera error:', err);
            showBarcodeStatus(
                'Camera blocked or unavailable. On mobile: tap Open camera, allow permission, use Chrome/Safari on HTTPS.',
                'error'
            );
        }
    }

    if (barcodeStartBtn) {
        barcodeStartBtn.addEventListener('click', function () {
            startBarcodeCamera();
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
                showBarcodeStatus('Tap Open camera, then allow access when asked.', 'info');
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
