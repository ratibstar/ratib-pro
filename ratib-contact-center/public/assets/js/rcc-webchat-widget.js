/**
 * RATEB Contact Center — Web Chat Widget (customer-facing)
 */
(function (global) {
    'use strict';

    function RccWebChatWidget(options) {
        this.apiUrl = options.apiUrl || '/ratib-contact-center/public/api/v1/inbox.php';
        this.tenantId = options.tenantId || 1;
        this.sessionId = options.sessionId || ('chat-' + Date.now());
        this.locale = options.locale || 'ar';
        this.onMessage = options.onMessage || function () {};
    }

    RccWebChatWidget.prototype.send = function (message, email, name) {
        var self = this;
        var url = self.apiUrl + '?action=webhook_chat&tenant_id=' + encodeURIComponent(self.tenantId);
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-RCC-Signature': self._sign(JSON.stringify({
                    message: message,
                    email: email || '',
                    name: name || '',
                    session_id: self.sessionId
                }))
            },
            body: JSON.stringify({
                message: message,
                email: email || '',
                name: name || '',
                session_id: self.sessionId
            })
        }).then(function (r) { return r.json(); }).then(function (json) {
            if (!json.ok) {
                throw new Error(json.error || 'Send failed');
            }
            self.onMessage(json.conversation);
            return json;
        });
    };

    RccWebChatWidget.prototype._sign = function (body) {
        // Web chat cannot hold HMAC secrets in the browser — server accepts when
        // RCC_WEBHOOK_SECRET is unset or RCC_WEBHOOK_ALLOW_UNSIGNED=1.
        return body;
    };

    global.RccWebChatWidget = RccWebChatWidget;
})(typeof window !== 'undefined' ? window : globalThis);
