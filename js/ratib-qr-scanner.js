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
    function RatibQrScanner(options) {
        this.elementId = options.elementId;
        this.onScan = options.onScan;
        this.onStatus = options.onStatus || function () {};
        this.scanner = null;
        this.starting = false;
        this.lastScan = 0;
        this.throttleMs = options.throttleMs || 2500;
        this.submitted = false;
    }

    RatibQrScanner.prototype.setStatus = function (message, type) {
        this.onStatus(message, type || 'info');
    };

    RatibQrScanner.prototype.stop = async function () {
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

    RatibQrScanner.prototype.handleDecode = function (text) {
        if (this.submitted) {
            return;
        }
        var value = String(text || '').trim();
        if (value.length < 4) {
            return;
        }
        var now = Date.now();
        if (now - this.lastScan < this.throttleMs) {
            return;
        }
        this.lastScan = now;
        this.submitted = true;
        this.onScan(value);
    };

    RatibQrScanner.prototype.start = async function () {
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
            fps: 12,
            qrbox: function (vw, vh) {
                return {
                    width: Math.floor(Math.min(vw * 0.92, 380)),
                    height: Math.floor(Math.min(vh * 0.58, 260))
                };
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
            this.setStatus('Camera blocked. Allow permission and try again.', 'error');
        }
    };

    RatibQrScanner.prototype.resetSubmit = function () {
        this.submitted = false;
    };

    global.RatibQrScanner = RatibQrScanner;
})(typeof window !== 'undefined' ? window : this);
