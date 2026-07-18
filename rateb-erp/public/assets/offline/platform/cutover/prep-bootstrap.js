/*!
 * RATEB Platform Cutover Prep — Bootstrap facade (B1-Prep)
 * Loads gate stack for certification harness. Does not cut over Admin.
 */
(function (root) {
    'use strict';

    if (root.RatebPlatformCutoverPrep && root.RatebPlatformCutoverPrep.__locked) {
        return;
    }

    function required() {
        return {
            flags: !!root.RatebPlatformCutoverFlags,
            probe: !!root.RatebPlatformCapabilityProbe,
            lease: !!root.RatebPlatformMultiTabLease,
            gate: !!root.RatebPlatformCompatGate,
            emergency: !!root.RatebPlatformEmergencyRollback,
            queue: !!root.RatebPlatformQueueStrategy,
            identity: !!root.RatebPlatformIdentityBridge
        };
    }

    function bootTimeline(opts) {
        opts = opts || {};
        var timeline = [];
        function mark(step, detail) {
            timeline.push({ step: step, at: Date.now(), detail: detail || null });
        }
        mark('BOOT');
        var Flags = root.RatebPlatformCutoverFlags;
        var Probe = root.RatebPlatformCapabilityProbe;
        var Lease = root.RatebPlatformMultiTabLease;
        var Gate = root.RatebPlatformCompatGate;
        var Emergency = root.RatebPlatformEmergencyRollback;

        if (!Flags || !Probe || !Gate) {
            return Promise.resolve({
                ok: false,
                reason: 'cutover_prep_incomplete',
                required: required(),
                timeline: timeline
            });
        }

        mark('FLAGS');
        var vendorUrl = opts.vendorIndexUrl || null;
        return Probe.run({ vendorIndexUrl: vendorUrl }).then(function (capability) {
            mark('CAPABILITY', capability);
            var leasePromise = opts.skipLease
                ? Promise.resolve({ ok: true, skipped: true })
                : Lease.acquire({ timeoutMs: opts.leaseTimeoutMs || 3000 });
            return leasePromise.then(function (lease) {
                mark('LEASE', lease);
                return Gate.evaluate({
                    capability: capability,
                    lease: lease,
                    v1Pending: opts.v1Pending || 0,
                    vendorIndexUrl: vendorUrl
                }).then(function (decision) {
                    mark('COMPATGATE', decision);
                    mark('VALIDATION', { mode: decision.mode });
                    if (decision.mode === 'rollback' || (Flags.isEmergencyRollback && Flags.isEmergencyRollback())) {
                        mark('EMERGENCY_ROLLBACK');
                        return Emergency.apply({
                            fetcher: opts.emergencyFetcher,
                            remoteFlags: opts.emergencyRemoteFlags,
                            v1Enable: opts.v1Enable,
                            platformDisableWriters: opts.platformDisableWriters
                        }).then(function (er) {
                            mark('RECOVERY', er);
                            return {
                                ok: true,
                                timeline: timeline,
                                capability: capability,
                                lease: lease,
                                decision: decision,
                                emergency: er,
                                required: required()
                            };
                        });
                    }
                    mark('PLATFORM_BOOTSTRAP', {
                        status: 'prep_only_no_admin_cutover',
                        mode: decision.mode
                    });
                    mark('ROLLBACK_PATH_READY', {
                        emergency: !!Emergency,
                        flagKill: true,
                        commitRevert: 'git_revert_prep_modules'
                    });
                    mark('FAILURE_RECOVERY_READY', {
                        forceOfflineV1: !!(capability && capability.forceOfflineV1),
                        dualEngineReject: true
                    });
                    return {
                        ok: true,
                        timeline: timeline,
                        capability: capability,
                        lease: lease,
                        decision: decision,
                        required: required()
                    };
                });
            });
        });
    }

    root.RatebPlatformCutoverPrep = {
        __locked: true,
        version: '1.0.0-b1-prep',
        required: required,
        bootTimeline: bootTimeline
    };
})(typeof window !== 'undefined' ? window : this);
