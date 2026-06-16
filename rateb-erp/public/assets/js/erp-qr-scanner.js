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
            var scanConfig = {
                fps: 12,
                disableFlip: false,
                qrbox: function (vw, vh) {
                    var side = Math.floor(Math.min(vw, vh) * 0.78);
                    return { width: Math.max(180, side), height: Math.max(180, side) };
                },
                aspectRatio: 1.0
            };
            if (camId) {
                await this.scanner.start(camId, scanConfig, onDecoded, function () {});
            } else {
                await this.scanner.start({ facingMode: 'environment' }, scanConfig, onDecoded, function () {});
            }
            this.starting = false;
            this.setStatus('Align the QR code inside the frame.', 'info');
        } catch (err) {
            this.starting = false;
            try {
                var onDecoded2 = function (t) { self.handleDecode(t); };
                await this.scanner.start({ facingMode: 'user' }, {
                    fps: 12,
                    qrbox: { width: 220, height: 220 }
                }, onDecoded2, function () {});
                this.setStatus('Align the QR code inside the frame.', 'info');
                return;
            } catch (err2) {
                /* fall through */
            }
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
