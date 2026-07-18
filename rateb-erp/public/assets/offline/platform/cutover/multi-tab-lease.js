/*!
 * RATEB Platform Cutover Prep — Multi-tab Lease (B1-Prep G6)
 * navigator.locks + BroadcastChannel. Single writer guarantee.
 */
(function (root) {
    'use strict';

    if (root.RatebPlatformMultiTabLease && root.RatebPlatformMultiTabLease.__locked) {
        return;
    }

    var LOCK_NAME = 'rateb-admin-offline-engine';
    var CHANNEL = 'rateb-admin-offline-engine';
    var ownerId = 'tab-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
    var held = false;
    var channel = null;
    var fallbackOwner = null;
    var abortCtrl = null;

    function getChannel() {
        if (channel || typeof root.BroadcastChannel !== 'function') {
            return channel;
        }
        try {
            channel = new root.BroadcastChannel(CHANNEL);
            channel.onmessage = function (ev) {
                var data = ev && ev.data || {};
                if (data.type === 'lease-claim' && data.ownerId && data.ownerId !== ownerId) {
                    if (!held) {
                        fallbackOwner = data.ownerId;
                    }
                }
                if (data.type === 'lease-release' && data.ownerId === fallbackOwner) {
                    fallbackOwner = null;
                }
                if (data.type === 'lease-query') {
                    if (held) {
                        channel.postMessage({ type: 'lease-claim', ownerId: ownerId, at: Date.now() });
                    }
                }
            };
        } catch (e) {
            channel = null;
        }
        return channel;
    }

    function broadcast(msg) {
        var ch = getChannel();
        if (ch) {
            try { ch.postMessage(msg); } catch (e2) { /* ignore */ }
        }
    }

    function acquireWithLocks(timeoutMs) {
        if (!root.navigator || !root.navigator.locks || typeof root.navigator.locks.request !== 'function') {
            return Promise.resolve({ ok: false, reason: 'locks_unavailable' });
        }
        abortCtrl = typeof root.AbortController === 'function' ? new root.AbortController() : null;
        var settled = false;
        return new Promise(function (resolve) {
            var timer = root.setTimeout(function () {
                if (!settled) {
                    settled = true;
                    if (abortCtrl) {
                        try { abortCtrl.abort(); } catch (e3) { /* ignore */ }
                    }
                    resolve({ ok: false, reason: 'lease_timeout' });
                }
            }, timeoutMs || 3000);

            root.navigator.locks.request(LOCK_NAME, {
                mode: 'exclusive',
                signal: abortCtrl ? abortCtrl.signal : undefined
            }, function () {
                held = true;
                broadcast({ type: 'lease-claim', ownerId: ownerId, at: Date.now() });
                if (!settled) {
                    settled = true;
                    root.clearTimeout(timer);
                    resolve({ ok: true, ownerId: ownerId, method: 'navigator.locks' });
                }
                /* Hold until release() aborts / page unload. */
                return new Promise(function (holdResolve) {
                    root.__ratebCutoverLeaseHoldResolve = holdResolve;
                });
            }).catch(function (err) {
                if (!settled) {
                    settled = true;
                    root.clearTimeout(timer);
                    resolve({
                        ok: false,
                        reason: 'lease_rejected',
                        error: String(err && err.message ? err.message : err)
                    });
                }
            });
        });
    }

    function acquireFallback() {
        getChannel();
        broadcast({ type: 'lease-query', ownerId: ownerId });
        return new Promise(function (resolve) {
            root.setTimeout(function () {
                if (fallbackOwner && fallbackOwner !== ownerId) {
                    resolve({ ok: false, reason: 'lease_held_elsewhere', ownerId: fallbackOwner, method: 'broadcast' });
                    return;
                }
                held = true;
                fallbackOwner = ownerId;
                broadcast({ type: 'lease-claim', ownerId: ownerId, at: Date.now() });
                resolve({ ok: true, ownerId: ownerId, method: 'broadcast' });
            }, 120);
        });
    }

    function acquire(opts) {
        opts = opts || {};
        if (held) {
            return Promise.resolve({ ok: true, ownerId: ownerId, already: true });
        }
        return acquireWithLocks(opts.timeoutMs).then(function (res) {
            if (res.ok) {
                return res;
            }
            /* Fail closed when locks exist but are held/timed out — never dual-write via BroadcastChannel. */
            if (res.reason === 'locks_unavailable') {
                return acquireFallback();
            }
            return res;
        });
    }

    function release() {
        var wasHeld = held;
        held = false;
        broadcast({ type: 'lease-release', ownerId: ownerId, at: Date.now() });
        if (root.__ratebCutoverLeaseHoldResolve) {
            try { root.__ratebCutoverLeaseHoldResolve(); } catch (e4) { /* ignore */ }
            root.__ratebCutoverLeaseHoldResolve = null;
        }
        if (abortCtrl) {
            try { abortCtrl.abort(); } catch (e5) { /* ignore */ }
            abortCtrl = null;
        }
        if (fallbackOwner === ownerId) {
            fallbackOwner = null;
        }
        return { ok: true, released: wasHeld, ownerId: ownerId };
    }

    function isHeld() {
        return !!held;
    }

    root.addEventListener('pagehide', function () {
        if (held) {
            release();
        }
    });

    root.RatebPlatformMultiTabLease = {
        __locked: true,
        version: '1.0.0-b1-prep',
        LOCK_NAME: LOCK_NAME,
        CHANNEL: CHANNEL,
        getOwnerId: function () { return ownerId; },
        acquire: acquire,
        release: release,
        isHeld: isHeld
    };
})(typeof window !== 'undefined' ? window : this);
