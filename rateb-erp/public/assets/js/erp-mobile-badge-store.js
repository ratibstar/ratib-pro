/**
 * Save ERP login badge on the phone for repeat desktop barcode login.
 */
(function (global) {
    'use strict';

    var PREFIX = 'rateb_erp_mobile_badge_v1';

    function storageKey() {
        var host = (global.location && global.location.hostname) ? global.location.hostname : 'default';
        return PREFIX + '_' + host;
    }

    function isBadgePayload(payload) {
        var p = String(payload || '').trim();
        return /^RATEBERP:/i.test(p) || /^ERP\d{5,}/i.test(p);
    }

    function save(payload, scanValue, extra) {
        if (!isBadgePayload(payload)) {
            return false;
        }
        var entry = {
            payload: String(payload),
            scanValue: String(scanValue || payload),
            username: extra && extra.username ? String(extra.username) : '',
            savedAt: Date.now()
        };
        try {
            global.localStorage.setItem(storageKey(), JSON.stringify(entry));
            return true;
        } catch (e) {
            return false;
        }
    }

    function load() {
        try {
            var raw = global.localStorage.getItem(storageKey());
            if (!raw) return null;
            var data = JSON.parse(raw);
            if (!data || !data.payload || !isBadgePayload(data.payload)) return null;
            return data;
        } catch (e) {
            return null;
        }
    }

    function clear() {
        try {
            global.localStorage.removeItem(storageKey());
        } catch (e) { /* ignore */ }
    }

    global.RatebErpMobileBadgeStore = {
        save: save,
        load: load,
        clear: clear
    };
})(typeof window !== 'undefined' ? window : this);
