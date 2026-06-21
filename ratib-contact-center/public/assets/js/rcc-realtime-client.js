/**
 * RATIB Contact Center - WebSocket realtime client (auto-reconnect).
 * Consumes EventBus stream only - no polling.
 */
(function (global) {
    'use strict';

    function RccRealtimeClient(options) {
        this.url = (options.url != null && String(options.url).trim() !== '')
            ? String(options.url).trim()
            : 'polling';
        this.rooms = options.rooms || [];
        this.tenantId = options.tenantId || 0;
        this.onEvent = typeof options.onEvent === 'function' ? options.onEvent : function () {};
        this.onStatus = typeof options.onStatus === 'function' ? options.onStatus : function () {};
        this.reconnectMs = options.reconnectMs || 3000;
        this.maxReconnectMs = options.maxReconnectMs || 30000;
        this.maxRetries = options.maxRetries != null ? options.maxRetries : 8;
        this._ws = null;
        this._retry = 0;
        this._stopped = false;
    }

    RccRealtimeClient.prototype.connect = function () {
        var self = this;
        if (self._stopped) {
            return;
        }
        var url = (self.url || '').trim();
        if (!url || url === 'polling' || url.indexOf('ws://') !== 0 && url.indexOf('wss://') !== 0) {
            self.onStatus('offline', 'polling');
            return;
        }
        self.onStatus('connecting', url);
        try {
            self._ws = new WebSocket(url);
        } catch (e) {
            self._scheduleReconnect();
            return;
        }
        self._ws.onopen = function () {
            self._retry = 0;
            self.onStatus('connected', self.url);
            var rooms = self.rooms.slice();
            if (self.tenantId > 0) {
                rooms.push('tenant:' + self.tenantId, 'dashboard:' + self.tenantId);
            }
            self._ws.send(JSON.stringify({ action: 'subscribe', rooms: rooms }));
        };
        self._ws.onmessage = function (msg) {
            try {
                var event = JSON.parse(msg.data);
                self.onEvent(event);
            } catch (err) {
                /* ignore malformed */
            }
        };
        self._ws.onclose = function () {
            self.onStatus('disconnected');
            self._scheduleReconnect();
        };
        self._ws.onerror = function () {
            self.onStatus('error');
        };
    };

    RccRealtimeClient.prototype._scheduleReconnect = function () {
        var self = this;
        if (self._stopped) {
            return;
        }
        if (self._retry >= self.maxRetries) {
            self.onStatus('offline', 'Realtime hub unavailable');
            return;
        }
        var delay = Math.min(self.reconnectMs * Math.pow(2, self._retry), self.maxReconnectMs);
        self._retry += 1;
        setTimeout(function () {
            self.connect();
        }, delay);
    };

    RccRealtimeClient.prototype.disconnect = function () {
        this._stopped = true;
        if (this._ws) {
            this._ws.close();
        }
    };

    global.RccRealtimeClient = RccRealtimeClient;
})(typeof window !== 'undefined' ? window : globalThis);
