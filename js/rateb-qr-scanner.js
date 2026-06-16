/**
 * RATEB enterprise QR scanner (html5-qrcode) — lifecycle, throttle, rear camera.
 */
(function (global) {
    'use strict';

    function pickRearCamera(cameras) {
        if (!cameras || !cameras.length) {
            return null;
        }
        var back = cameras.find(function (c) {
            return /back|rear|environment|wide|telephoto/i.test(c.label || '');
        });
        return back ? back.id : cameras[cameras.length - 1].id;
    }

    /**
     * @param {object} options
     * @param {string} options.elementId
     * @param {function(string):void} options.onScan
     * @param {function(string,string):void} [options.onStatus]
     */
    function RATEBQrScanner(options) {
        this.elementId = options.elementId;
        this.onScan = options.onScan;
        this.onStatus = options.onStatus || function () {};
        this.scanner = null;
        this.starting = false;
        this.lastScan = 0;
        this.throttleMs = options.throttleMs || 2500;
        this.submitted = false;
    }

    RATEBQrScanner.prototype.setStatus = function (message, type) {
        if (this.submitted) {
            return;
        }
        this.onStatus(message, type || 'info');
    };

    RATEBQrScanner.prototype.stop = async function () {
        this.starting = false;
        if (!this.scanner) {
            return;
        }
        var s = this.scanner;
        this.scanner = null;
        try {
            await s.stop();
        } catch (e) {
            /* ignore */
        }
        try {
            await s.clear();
        } catch (e2) {
            /* ignore */
        }
    };

    RATEBQrScanner.prototype.handleDecode = function (text) {
        var value = String(text || '').trim();
        if (value.length < 4) {
            return;
        }
        var now = Date.now();
        if (this.submitted) {
            return;
        }
        if (now - this.lastScan < this.throttleMs) {
            return;
        }
        this.lastScan = now;
        this.onScan(value);
    };

    RATEBQrScanner.prototype.lock = function () {
        this.submitted = true;
    };

    RATEBQrScanner.prototype.start = async function () {
        if (this.scanner || this.starting) {
            return;
        }
        if (typeof Html5Qrcode === 'undefined') {
            this.setStatus('Scanner library failed to load.', 'error');
            return;
        }
        this.starting = true;
        this.setStatus('Requesting camera…', 'loading');
        this.scanner = new Html5Qrcode(this.elementId);
        var self = this;
        var config = {
            fps: 20,
            disableFlip: false,
            qrbox: function (vw, vh) {
                var side = Math.floor(Math.min(vw, vh) * 0.78);
                return { width: side, height: side };
            },
            experimentalFeatures: {
                useBarCodeDetectorIfSupported: true
            },
            videoConstraints: {
                facingMode: 'environment',
                width: { ideal: 1280 },
                height: { ideal: 720 }
            }
        };
        try {
            var cameras = [];
            try {
                cameras = await Html5Qrcode.getCameras();
            } catch (e) {
                cameras = [];
            }
            var camId = pickRearCamera(cameras);
            var onDecoded = function (t) {
                self.handleDecode(t);
            };
            if (camId) {
                await this.scanner.start(camId, config, onDecoded, function () {});
            } else {
                await this.scanner.start({ facingMode: 'environment' }, config, onDecoded, function () {});
            }
            this.starting = false;
            this.setStatus('Align the QR code inside the frame.', 'info');
        } catch (err) {
            this.starting = false;
            await this.stop();
            var msg = 'Camera blocked. Allow permission and try again.';
            if (err && err.message) {
                msg += ' (' + String(err.message).substring(0, 80) + ')';
            }
            this.setStatus(msg, 'error');
            throw err;
        }
    };

    RATEBQrScanner.prototype.resetSubmit = function () {
        this.submitted = false;
    };

    global.RATEBQrScanner = RATEBQrScanner;
})(typeof window !== 'undefined' ? window : this);
