/*!
 * RATEB Platform Cutover Prep — Identity Bridge (B1-Prep G5)
 * Offline V1 → Online Enrollment → module.identity.* only.
 * Never transfers credentials. AF-2.1 preserved.
 */
(function (root) {
    'use strict';

    if (root.RatebPlatformIdentityBridge && root.RatebPlatformIdentityBridge.__locked) {
        return;
    }

    var FORBIDDEN = [
        'password', 'password_hash', 'passwd', 'pwd',
        'token', 'access_token', 'refresh_token', 'bearer', 'api_token',
        'jwt', 'id_token', 'oauth', 'totp', 'totp_secret', 'session_cookie'
    ];

    function containsForbidden(value, path) {
        path = path || '';
        if (value == null) {
            return null;
        }
        if (typeof value === 'string') {
            if (String(value).toLowerCase().indexOf('bearer ') === 0) {
                return path || 'string';
            }
            return null;
        }
        if (typeof value !== 'object') {
            return null;
        }
        if (Array.isArray(value)) {
            for (var i = 0; i < value.length; i++) {
                var hitA = containsForbidden(value[i], path + '[' + i + ']');
                if (hitA) {
                    return hitA;
                }
            }
            return null;
        }
        var keys = Object.keys(value);
        for (var k = 0; k < keys.length; k++) {
            var key = keys[k];
            var lower = String(key).toLowerCase();
            if (FORBIDDEN.indexOf(lower) !== -1) {
                return path ? path + '.' + key : key;
            }
            var hit = containsForbidden(value[key], path ? path + '.' + key : key);
            if (hit) {
                return hit;
            }
        }
        return null;
    }

    function refuseCredentials(payload) {
        var hit = containsForbidden(payload);
        if (hit) {
            return { ok: false, reason: 'credential_forbidden', path: hit };
        }
        return { ok: true };
    }

    /**
     * Bridge flow:
     * 1) Optional read of V1 non-secret metadata (claims summary) — never vault secrets
     * 2) Fetch enrollment package from Online ERP (cookie session) — dryRun allowed
     * 3) applyEnrollment via Identity module / published API
     */
    function bridge(opts) {
        opts = opts || {};
        var scan = refuseCredentials(opts.v1Package || opts.enrollment || {});
        if (!scan.ok) {
            return Promise.resolve(scan);
        }
        if (opts.password || opts.token || opts.credentials) {
            return Promise.resolve({
                ok: false,
                reason: 'credentials_not_accepted',
                authority: 'online_erp'
            });
        }

        var fetchEnrollment = opts.fetchEnrollment;
        if (typeof fetchEnrollment !== 'function') {
            /* Default: Identity module online bridge if present. */
            fetchEnrollment = function () {
                var Id = root.RatebOfflineV2Identity;
                if (Id && typeof Id.create === 'function') {
                    var mod = Id.create();
                    if (mod && typeof mod.fetchEnrollmentFromOnline === 'function') {
                        return mod.fetchEnrollmentFromOnline({ dryRun: !!opts.dryRun });
                    }
                }
                if (typeof opts.enrollment === 'object' && opts.enrollment) {
                    return Promise.resolve({ ok: true, package: opts.enrollment, source: 'provided' });
                }
                return Promise.resolve({ ok: false, reason: 'enrollment_source_missing' });
            };
        }

        return Promise.resolve(fetchEnrollment()).then(function (enroll) {
            if (!enroll || !enroll.ok) {
                return {
                    ok: false,
                    reason: (enroll && enroll.reason) || 'enrollment_failed',
                    authority: 'online_erp',
                    enroll: enroll || null
                };
            }
            var pkg = enroll.package || enroll.pkg || opts.enrollment;
            var credScan = refuseCredentials(pkg);
            if (!credScan.ok) {
                return credScan;
            }

            if (opts.dryRun) {
                return {
                    ok: true,
                    dryRun: true,
                    authority: 'online_erp',
                    stores_credentials: false,
                    bridge: 'v1_meta_to_online_enrollment',
                    next: 'module.identity.applyEnrollment'
                };
            }

            var apply = opts.applyEnrollment;
            if (typeof apply !== 'function') {
                apply = function (packageToApply) {
                    var Id = root.RatebOfflineV2Identity;
                    if (Id && typeof Id.create === 'function') {
                        var mod = Id.create();
                        if (mod && typeof mod.applyEnrollmentPackage === 'function') {
                            return mod.applyEnrollmentPackage(packageToApply);
                        }
                    }
                    if (root.RatebOfflineV2Business && typeof root.RatebOfflineV2Business.invokePublished === 'function') {
                        return root.RatebOfflineV2Business.invokePublished('identity', 'applyEnrollment', packageToApply);
                    }
                    return Promise.resolve({ ok: false, reason: 'identity_apply_missing' });
                };
            }

            return Promise.resolve(apply(pkg)).then(function (applied) {
                return {
                    ok: !!(applied && applied.ok !== false),
                    authority: 'online_erp',
                    stores_credentials: false,
                    applied: applied || null,
                    publishedApi: 'module.identity.*',
                    credentialsTransferred: false
                };
            });
        });
    }

    root.RatebPlatformIdentityBridge = {
        __locked: true,
        version: '1.0.0-b1-prep',
        refuseCredentials: refuseCredentials,
        containsForbidden: containsForbidden,
        bridge: bridge,
        FORBIDDEN_KEYS: FORBIDDEN.slice()
    };
})(typeof window !== 'undefined' ? window : this);
