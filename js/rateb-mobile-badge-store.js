/**
 * Save workforce badge on the phone (localStorage) for repeat PC barcode login.
 */
(function (global) {
    'use strict';

    var PREFIX = 'rateb_mobile_badge_v1';

    function storageKey(agencyId, countryId) {
        return PREFIX + '_' + (parseInt(countryId, 10) || 0) + '_' + (parseInt(agencyId, 10) || 0);
    }

    function buildBadgeUrl(payload, agencyId, countryId, countrySlug) {
        if (!payload || !global.location || !global.location.origin) {
            return '';
        }
        if (/^https?:\/\//i.test(payload)) {
            return payload;
        }
        if (!/^RATEBLOGIN:/i.test(payload)) {
            return '';
        }
        var q = new URLSearchParams();
        q.set('d', payload);
        if (agencyId > 0) {
            q.set('agency_id', String(agencyId));
        }
        if (countryId > 0) {
            q.set('country_id', String(countryId));
        }
        if (countrySlug) {
            q.set('country_slug', countrySlug);
        }
        return global.location.origin + '/login/badge?' + q.toString();
    }

    function save(ctx, payload, scanValue, extra) {
        if (!payload || !/^RATEBLOGIN:/i.test(String(payload))) {
            return false;
        }
        var agencyId = parseInt(ctx.agencyId, 10) || 0;
        var countryId = parseInt(ctx.countryId, 10) || 0;
        var entry = {
            payload: String(payload),
            scanValue: String(scanValue || payload),
            badgeUrl: buildBadgeUrl(payload, agencyId, countryId, (ctx.countrySlug || '').trim()),
            agencyId: agencyId,
            countryId: countryId,
            countrySlug: (ctx.countrySlug || '').trim(),
            username: extra && extra.username ? String(extra.username) : '',
            savedAt: Date.now()
        };
        try {
            global.localStorage.setItem(storageKey(agencyId, countryId), JSON.stringify(entry));
            return true;
        } catch (e) {
            return false;
        }
    }

    function load(ctx) {
        var agencyId = parseInt(ctx.agencyId, 10) || 0;
        var countryId = parseInt(ctx.countryId, 10) || 0;
        try {
            var raw = global.localStorage.getItem(storageKey(agencyId, countryId));
            if (!raw) {
                return null;
            }
            var data = JSON.parse(raw);
            if (!data || !data.payload) {
                return null;
            }
            return data;
        } catch (e) {
            return null;
        }
    }

    function clear(ctx) {
        var agencyId = parseInt(ctx.agencyId, 10) || 0;
        var countryId = parseInt(ctx.countryId, 10) || 0;
        try {
            global.localStorage.removeItem(storageKey(agencyId, countryId));
        } catch (e) {
            /* ignore */
        }
    }

    global.RATEBMobileBadgeStore = {
        save: save,
        load: load,
        clear: clear,
        buildBadgeUrl: buildBadgeUrl
    };
})(typeof window !== 'undefined' ? window : this);
