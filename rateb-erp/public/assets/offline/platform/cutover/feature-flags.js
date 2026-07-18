/*!
 * RATEB Platform Cutover Prep — Feature Flags + EmergencyRollback (B1-Prep G3)
 * Remote config only for kill switch. Defaults OFF. No Admin cutover.
 */
(function (root) {
    'use strict';

    if (root.RatebPlatformCutoverFlags && root.RatebPlatformCutoverFlags.__locked) {
        return;
    }

    var STORAGE_KEY = 'rateb_platform_cutover_flags_v1';
    var KILL_STICKY = 'rateb_platform_emergency_rollback_sticky';
    var DEFAULTS = {
        CompatGateEnabled: false,
        PlatformEnabled: false,
        PlatformShadow: false,
        PlatformCutover: false,
        EmergencyRollback: false,
        PlatformQueueMigrate: false,
        PlatformIdentityBridge: false,
        PlatformAdminSW: false
    };

    var state = {
        flags: Object.assign({}, DEFAULTS),
        source: 'defaults',
        remoteUrl: null,
        lastFetchedAt: null,
        listeners: []
    };

    function clone(obj) {
        return JSON.parse(JSON.stringify(obj || {}));
    }

    function emit() {
        var snap = getFlags();
        state.listeners.slice().forEach(function (fn) {
            try { fn(snap); } catch (e) { /* isolate */ }
        });
    }

    function readStickyKill() {
        try {
            return root.localStorage && root.localStorage.getItem(KILL_STICKY) === '1';
        } catch (e) {
            return false;
        }
    }

    function writeStickyKill(on) {
        try {
            if (!root.localStorage) {
                return;
            }
            if (on) {
                root.localStorage.setItem(KILL_STICKY, '1');
            } else {
                root.localStorage.removeItem(KILL_STICKY);
            }
        } catch (e2) { /* ignore */ }
    }

    function mergeFlags(partial, source) {
        var next = Object.assign({}, DEFAULTS, state.flags, partial || {});
        Object.keys(DEFAULTS).forEach(function (k) {
            next[k] = !!next[k];
        });
        /* EmergencyRollback sticky wins until remote clears and sticky cleared by ops. */
        if (readStickyKill()) {
            next.EmergencyRollback = true;
        }
        if (next.EmergencyRollback) {
            writeStickyKill(true);
            next.PlatformCutover = false;
            next.PlatformShadow = false;
            /* PlatformEnabled may stay true for diagnostics, but writers are blocked by gate. */
        }
        state.flags = next;
        state.source = source || state.source;
        try {
            if (root.localStorage) {
                root.localStorage.setItem(STORAGE_KEY, JSON.stringify({
                    flags: next,
                    source: state.source,
                    at: new Date().toISOString()
                }));
            }
        } catch (e3) { /* ignore */ }
        emit();
        return getFlags();
    }

    function getFlags() {
        var f = clone(state.flags);
        if (readStickyKill()) {
            f.EmergencyRollback = true;
        }
        return f;
    }

    function setRemoteUrl(url) {
        state.remoteUrl = url || null;
        return state.remoteUrl;
    }

    function defaultRemoteUrl() {
        try {
            var path = String(root.location.pathname || '');
            var m = path.match(/^(.*\/public\/)/i);
            if (m && m[1]) {
                return m[1] + 'platform-cutover-flags.php';
            }
            /* Local PHP built-in server rooted at public/ */
            return new URL('/platform-cutover-flags.php', root.location.origin).pathname;
        } catch (e) {
            return '/rateb-erp/public/platform-cutover-flags.php';
        }
    }

    function fetchRemote(opts) {
        opts = opts || {};
        var url = opts.url || state.remoteUrl || defaultRemoteUrl();
        state.remoteUrl = url;
        if (typeof opts.fetcher === 'function') {
            return Promise.resolve(opts.fetcher()).then(function (data) {
                state.lastFetchedAt = new Date().toISOString();
                return mergeFlags(data && data.flags ? data.flags : data, 'remote-mock');
            });
        }
        return root.fetch(url + (url.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now(), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json', 'X-Rateb-Cutover-Flags': '1' }
        }).then(function (res) {
            if (!res || !res.ok) {
                throw new Error('cutover_flags_http_' + (res ? res.status : 0));
            }
            return res.json();
        }).then(function (data) {
            state.lastFetchedAt = new Date().toISOString();
            return mergeFlags(data && data.flags ? data.flags : data, 'remote');
        }).catch(function (err) {
            /* Keep sticky kill if set; otherwise retain last known. */
            if (readStickyKill()) {
                mergeFlags({ EmergencyRollback: true }, 'sticky-kill');
            }
            return Promise.reject(err);
        });
    }

    function clearEmergencySticky() {
        writeStickyKill(false);
        return mergeFlags({ EmergencyRollback: false }, 'ops-clear-sticky');
    }

    function onChange(fn) {
        if (typeof fn !== 'function') {
            return function () {};
        }
        state.listeners.push(fn);
        return function () {
            state.listeners = state.listeners.filter(function (f) { return f !== fn; });
        };
    }

    root.RatebPlatformCutoverFlags = {
        __locked: true,
        version: '1.0.0-b1-prep',
        DEFAULTS: clone(DEFAULTS),
        getFlags: getFlags,
        mergeFlags: mergeFlags,
        setRemoteUrl: setRemoteUrl,
        defaultRemoteUrl: defaultRemoteUrl,
        fetchRemote: fetchRemote,
        clearEmergencySticky: clearEmergencySticky,
        isEmergencyRollback: function () {
            return !!getFlags().EmergencyRollback || readStickyKill();
        },
        onChange: onChange,
        getStatus: function () {
            return {
                flags: getFlags(),
                source: state.source,
                remoteUrl: state.remoteUrl,
                lastFetchedAt: state.lastFetchedAt,
                stickyKill: readStickyKill()
            };
        }
    };
})(typeof window !== 'undefined' ? window : this);
