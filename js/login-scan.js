/**
 * Phone scanner for cross-device barcode login (pairs with PC session).
 */
document.addEventListener('DOMContentLoaded', function () {
    const cfg = window.RATIB_LOGIN_SCAN || {};
    const token = cfg.token || '';
    const apiPair = cfg.apiPair || '../api/login-barcode-pair.php';
    const barcodeReaderEl = document.getElementById('barcode-qr-reader');
    const barcodeStartBtn = document.getElementById('barcode-start-camera');
    const statusDiv = document.getElementById('barcode-status');

    let barcodeScanner = null;
    let barcodeCameraStarting = false;
    let submitted = false;

    function setStatus(message, type) {
        if (!statusDiv) {
            return;
        }
        statusDiv.className = 'barcode-status ' + (type || 'info') + '-message d-block mt-3';
        statusDiv.textContent = message;
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
        return back ? back.id : cameras[cameras.length - 1].id;
    }

    async function submitBarcode(code) {
        if (submitted || !token) {
            return;
        }
        const value = String(code || '').trim();
        if (value.length < 2) {
            return;
        }
        submitted = true;
        setStatus('Signing in on your computer…', 'info');
        await stopBarcodeCamera();
        try {
            const res = await fetch(apiPair, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'submit', token: token, barcode: value })
            });
            const json = await res.json();
            if (json.success) {
                setStatus('Success! You can close this page. RATEB is opening on your computer.', 'info');
                if (statusDiv) {
                    statusDiv.classList.add('scan-done');
                }
                if (barcodeStartBtn) {
                    barcodeStartBtn.classList.add('d-none');
                }
            } else {
                submitted = false;
                setStatus(json.message || 'Barcode not recognized. Try again.', 'error');
                if (barcodeStartBtn) {
                    barcodeStartBtn.classList.remove('d-none');
                }
            }
        } catch (e) {
            submitted = false;
            setStatus('Network error. Try again.', 'error');
            if (barcodeStartBtn) {
                barcodeStartBtn.classList.remove('d-none');
            }
        }
    }

    async function startBarcodeCamera() {
        if (barcodeScanner || barcodeCameraStarting || !barcodeReaderEl) {
            return;
        }
        if (typeof Html5Qrcode === 'undefined') {
            setStatus('Scanner failed to load. Refresh the page.', 'error');
            return;
        }
        barcodeCameraStarting = true;
        setStatus('Starting camera…', 'info');
        if (barcodeStartBtn) {
            barcodeStartBtn.classList.add('d-none');
        }
        barcodeScanner = new Html5Qrcode('barcode-qr-reader');
        try {
            let cameras = [];
            try {
                cameras = await Html5Qrcode.getCameras();
            } catch (e) {
                cameras = [];
            }
            const cameraId = pickCameraId(cameras);
            const config = {
                fps: 12,
                qrbox: function (vw, vh) {
                    return {
                        width: Math.floor(Math.min(vw * 0.94, 400)),
                        height: Math.floor(Math.min(vh * 0.62, 280))
                    };
                }
            };
            const onScan = function (text) {
                if (text) {
                    submitBarcode(text);
                }
            };
            if (cameraId) {
                await barcodeScanner.start(cameraId, config, onScan, function () {});
            } else {
                await barcodeScanner.start({ facingMode: 'environment' }, config, onScan, function () {});
            }
            barcodeCameraStarting = false;
            setStatus('Scan the QR from Users settings.', 'info');
        } catch (err) {
            barcodeCameraStarting = false;
            await stopBarcodeCamera();
            if (barcodeStartBtn) {
                barcodeStartBtn.classList.remove('d-none');
            }
            setStatus('Allow camera access, then tap Start camera again.', 'error');
        }
    }

    if (barcodeStartBtn) {
        barcodeStartBtn.addEventListener('click', startBarcodeCamera);
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stopBarcodeCamera();
        }
    });
});
