/**
 * Login Page JavaScript
 * Method switching, barcode scanner (USB wedge + optional camera), dark mode
 */

document.addEventListener('DOMContentLoaded', function () {
    const loginMethodSelect = document.getElementById('login-method');
    const passwordForm = document.getElementById('password-form');
    const barcodeForm = document.getElementById('barcode-form');
    const barcodeInput = document.getElementById('barcode-input');
    const barcodeLoginForm = document.getElementById('barcode-login-form');
    const barcodeCameraToggle = document.getElementById('barcode-camera-toggle');
    const barcodeQrReader = document.getElementById('barcode-qr-reader');
    const darkModeToggle = document.getElementById('dark-mode-toggle');
    const themeIcon = document.getElementById('theme-icon');
    const body = document.body;
    const animatedBackground = document.getElementById('animated-background');

    let barcodeScanner = null;

    const adPhrases = [
        'RATEB', 'Manage Your Business', 'Streamline Operations', 'Boost Productivity',
        'Smart Solutions', 'Efficient Management', 'Digital Transformation', 'Work Smarter',
        'Innovation First', 'Your Success Partner', 'Simplify & Grow', 'Future Ready',
        'Excellence Delivered', 'Trusted Platform', 'Secure & Reliable', 'Powerful Tools',
        'Seamless Experience', 'Next Generation', 'Professional Grade', 'Transform Your Workflow'
    ];

    const professionalSymbols = [
        'fa-briefcase', 'fa-chart-line', 'fa-cog', 'fa-lightbulb', 'fa-rocket', 'fa-shield-alt',
        'fa-star', 'fa-bullseye', 'fa-trophy', 'fa-network-wired', 'fa-database', 'fa-cloud',
        'fa-lock', 'fa-chart-bar', 'fa-gem', 'fa-certificate', 'fa-award', 'fa-handshake',
        'fa-users-cog', 'fa-microchip'
    ];

    if (animatedBackground) {
        for (let i = 0; i < 6; i++) {
            const textElement = document.createElement('div');
            textElement.className = 'animated-text';
            textElement.textContent = adPhrases[Math.floor(Math.random() * adPhrases.length)];
            animatedBackground.appendChild(textElement);
        }
        for (let i = 0; i < 10; i++) {
            const symbolElement = document.createElement('div');
            symbolElement.className = 'animated-symbol';
            const randomSymbol = professionalSymbols[Math.floor(Math.random() * professionalSymbols.length)];
            symbolElement.innerHTML = '<i class="fas ' + randomSymbol + '"></i>';
            animatedBackground.appendChild(symbolElement);
        }
        setInterval(function () {
            animatedBackground.querySelectorAll('.animated-text').forEach(function (element) {
                element.textContent = adPhrases[Math.floor(Math.random() * adPhrases.length)];
            });
        }, 30000);
        setInterval(function () {
            animatedBackground.querySelectorAll('.animated-symbol').forEach(function (element) {
                const randomSymbol = professionalSymbols[Math.floor(Math.random() * professionalSymbols.length)];
                element.innerHTML = '<i class="fas ' + randomSymbol + '"></i>';
            });
        }, 45000);
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

    function focusBarcodeInput() {
        if (barcodeInput) {
            setTimeout(function () {
                barcodeInput.focus();
                barcodeInput.select();
            }, 80);
        }
    }

    async function stopBarcodeCamera() {
        if (!barcodeScanner) {
            return;
        }
        try {
            await barcodeScanner.stop();
            await barcodeScanner.clear();
        } catch (e) {
            /* ignore */
        }
        barcodeScanner = null;
        if (barcodeQrReader) {
            barcodeQrReader.classList.add('d-none');
            barcodeQrReader.setAttribute('aria-hidden', 'true');
        }
        if (barcodeCameraToggle) {
            barcodeCameraToggle.setAttribute('aria-expanded', 'false');
            barcodeCameraToggle.innerHTML = '<i class="fas fa-camera" aria-hidden="true"></i> Use camera';
        }
    }

    async function startBarcodeCamera() {
        if (typeof Html5Qrcode === 'undefined') {
            showBarcodeStatus('Camera scanner is not available. Use a USB barcode scanner or type the code.', 'error');
            return;
        }
        if (barcodeScanner) {
            await stopBarcodeCamera();
            return;
        }
        if (!barcodeQrReader) {
            return;
        }
        barcodeQrReader.classList.remove('d-none');
        barcodeQrReader.setAttribute('aria-hidden', 'false');
        barcodeScanner = new Html5Qrcode('barcode-qr-reader');
        const config = { fps: 10, qrbox: { width: 240, height: 120 }, aspectRatio: 1.5 };
        try {
            await barcodeScanner.start(
                { facingMode: 'environment' },
                config,
                function (decoded) {
                    if (!decoded) {
                        return;
                    }
                    if (barcodeInput) {
                        barcodeInput.value = String(decoded).trim();
                    }
                    stopBarcodeCamera();
                    if (barcodeLoginForm) {
                        barcodeLoginForm.requestSubmit();
                    }
                },
                function () {
                    /* scan errors — ignore frame noise */
                }
            );
            if (barcodeCameraToggle) {
                barcodeCameraToggle.setAttribute('aria-expanded', 'true');
                barcodeCameraToggle.innerHTML = '<i class="fas fa-times" aria-hidden="true"></i> Stop camera';
            }
            showBarcodeStatus('Point the camera at your barcode.', 'info');
        } catch (err) {
            showBarcodeStatus('Could not start camera. Use a USB scanner or enter the code manually.', 'error');
            await stopBarcodeCamera();
        }
    }

    function showBarcodeStatus(message, type) {
        const statusDiv = document.getElementById('barcode-status');
        if (!statusDiv) {
            return;
        }
        statusDiv.classList.remove('d-none');
        statusDiv.className = 'barcode-status ' + (type || 'info') + '-message d-block mt-3';
        statusDiv.textContent = message;
    }

    if (loginMethodSelect) {
        hideAllForms();
        showForm(passwordForm);

        loginMethodSelect.addEventListener('change', async function () {
            const method = this.value;
            await stopBarcodeCamera();
            hideAllForms();
            if (method === 'barcode') {
                showForm(barcodeForm);
                focusBarcodeInput();
            } else {
                showForm(passwordForm);
            }
        });
    }

    if (barcodeCameraToggle) {
        barcodeCameraToggle.addEventListener('click', function () {
            if (barcodeScanner) {
                stopBarcodeCamera();
            } else {
                startBarcodeCamera();
            }
        });
    }

    if (barcodeLoginForm && barcodeInput) {
        barcodeInput.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                barcodeLoginForm.requestSubmit();
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
