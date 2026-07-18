/*!
 * RATEB Platform Cutover Prep — CompatGate (B1-Prep G2)
 * Exactly one Runtime / EventBus / ServiceLocator / SQLite / Queue / Sync / Identity.
 * Rejects dual-engine startup immediately. Does not perform Admin cutover.
 */
(function (root) {
    'use strict';

    if (root.RatebPlatformCompatGate && root.RatebPlatformCompatGate.__locked) {
        return;
    }

    var registry = {
        runtime: null,
        eventBus: null,
        serviceLocator: null,
        sqlite: null,
        queue: null,
        sync: null,
        identity: null,
        offlineV1Writer: null
    };

    var lastDecision = null;

    function flagsApi() {
        return root.RatebPlatformCutoverFlags || null;
    }

    function probeApi() {
        return root.RatebPlatformCapabilityProbe || null;
    }

    function leaseApi() {
        return root.RatebPlatformMultiTabLease || null;
    }

    function claim(kind, ownerId, meta) {
        if (!registry.hasOwnProperty(kind)) {
            throw new Error('compat_unknown_kind:' + kind);
        }
        var existing = registry[kind];
        if (existing && existing.ownerId && existing.ownerId !== ownerId) {
            var err = new Error('compat_dual_engine:' + kind + ':' + existing.ownerId + '|' + ownerId);
            err.code = 'DUAL_ENGINE';
            err.kind = kind;
            err.existing = existing;
            throw err;
        }
        registry[kind] = {
            ownerId: ownerId,
            meta: meta || null,
            at: new Date().toISOString()
        };
        return registry[kind];
    }

    function release(kind, ownerId, force) {
        var existing = registry[kind];
        if (!existing) {
            return { ok: true, released: false };
        }
        if (!force && ownerId && existing.ownerId !== ownerId) {
            throw new Error('compat_release_owner_mismatch:' + kind);
        }
        registry[kind] = null;
        return { ok: true, released: true, previous: existing };
    }

    function clearWriters(force) {
        ['sync', 'queue', 'sqlite', 'runtime', 'eventBus', 'serviceLocator', 'identity'].forEach(function (k) {
            release(k, null, !!force);
        });
        return snapshot();
    }

    function snapshot() {
        var out = {};
        Object.keys(registry).forEach(function (k) {
            out[k] = registry[k] ? Object.assign({}, registry[k]) : null;
        });
        return out;
    }

    function assertSingleEngine() {
        var kinds = ['runtime', 'eventBus', 'serviceLocator', 'sqlite', 'queue', 'sync', 'identity'];
        var dual = [];
        /* Dual means two distinct owners across V1 writer + Platform writer for write kinds. */
        if (registry.offlineV1Writer && registry.queue && registry.queue.ownerId
            && registry.offlineV1Writer.ownerId !== registry.queue.ownerId
            && String(registry.queue.ownerId).indexOf('platform') === 0) {
            dual.push('queue');
        }
        if (registry.offlineV1Writer && registry.sqlite && registry.sqlite.ownerId
            && String(registry.sqlite.ownerId).indexOf('platform') === 0) {
            /* V1 IDB + Platform SQLite both as writers is forbidden. */
            if (registry.offlineV1Writer.meta && registry.offlineV1Writer.meta.writes) {
                dual.push('sqlite');
            }
        }
        kinds.forEach(function (k) {
            /* registry itself allows one slot; dual is detected at claim time.
               Additional scan: platform sync + v1 writer */
        });
        if (registry.offlineV1Writer && registry.offlineV1Writer.meta && registry.offlineV1Writer.meta.writes
            && registry.sync && String(registry.sync.ownerId).indexOf('platform') === 0) {
            dual.push('sync');
        }
        if (dual.length) {
            var err = new Error('compat_dual_engine:' + dual.join(','));
            err.code = 'DUAL_ENGINE';
            err.kinds = dual;
            throw err;
        }
        return { ok: true, registry: snapshot() };
    }

    function decide(opts) {
        opts = opts || {};
        var Flags = flagsApi();
        var flags = Flags ? Flags.getFlags() : {
            CompatGateEnabled: false,
            PlatformEnabled: false,
            PlatformShadow: false,
            PlatformCutover: false,
            EmergencyRollback: false
        };
        var capability = opts.capability || null;
        var lease = opts.lease || null;
        var v1Pending = Number(opts.v1Pending || 0);
        var decision = {
            mode: 'v1',
            reason: 'default',
            flags: flags,
            at: new Date().toISOString()
        };

        if (!flags.CompatGateEnabled) {
            decision.reason = 'compat_gate_disabled';
            lastDecision = decision;
            return decision;
        }
        if (flags.EmergencyRollback || (Flags && Flags.isEmergencyRollback && Flags.isEmergencyRollback())) {
            decision.mode = 'rollback';
            decision.reason = 'emergency_rollback';
            lastDecision = decision;
            return decision;
        }
        if (capability && capability.forceOfflineV1) {
            decision.mode = 'v1';
            decision.reason = 'capability_force_v1';
            lastDecision = decision;
            return decision;
        }
        if (!flags.PlatformEnabled) {
            decision.reason = 'platform_disabled';
            lastDecision = decision;
            return decision;
        }
        if (flags.PlatformCutover) {
            if (lease && lease.ok === false) {
                decision.mode = 'v1';
                decision.reason = 'lease_required_for_cutover';
                lastDecision = decision;
                return decision;
            }
            if (v1Pending > 0 && !flags.PlatformQueueMigrate) {
                decision.mode = 'v1';
                decision.reason = 'v1_queue_pending';
                lastDecision = decision;
                return decision;
            }
            decision.mode = 'cutover';
            decision.reason = 'platform_cutover';
            lastDecision = decision;
            return decision;
        }
        if (flags.PlatformShadow) {
            decision.mode = 'shadow';
            decision.reason = 'platform_shadow';
            lastDecision = decision;
            return decision;
        }
        decision.reason = 'platform_enabled_idle';
        lastDecision = decision;
        return decision;
    }

    function evaluate(opts) {
        opts = opts || {};
        var Probe = probeApi();
        var Lease = leaseApi();
        var chain = Promise.resolve(opts.capability || null);
        if (!opts.capability && Probe) {
            chain = Probe.run({
                vendorIndexUrl: opts.vendorIndexUrl || null
            });
        }
        return chain.then(function (capability) {
            var leasePromise = Promise.resolve(opts.lease || null);
            if (!opts.lease && Lease && opts.requireLease) {
                leasePromise = Lease.acquire({ timeoutMs: opts.leaseTimeoutMs || 3000 });
            }
            return leasePromise.then(function (lease) {
                var decision = decide({
                    capability: capability,
                    lease: lease,
                    v1Pending: opts.v1Pending
                });
                try {
                    assertSingleEngine();
                } catch (err) {
                    decision.mode = 'rollback';
                    decision.reason = 'dual_engine_rejected';
                    decision.error = String(err && err.message ? err.message : err);
                    lastDecision = decision;
                    return decision;
                }
                decision.capability = capability;
                decision.lease = lease;
                decision.registry = snapshot();
                lastDecision = decision;
                return decision;
            });
        });
    }

    function rejectIfDual(kind, ownerId, meta) {
        try {
            claim(kind, ownerId, meta);
            assertSingleEngine();
            return { ok: true };
        } catch (err) {
            try {
                if (registry[kind] && registry[kind].ownerId === ownerId) {
                    registry[kind] = null;
                }
            } catch (eClear) { /* ignore */ }
            return {
                ok: false,
                code: err.code || 'DUAL_ENGINE',
                error: String(err && err.message ? err.message : err),
                kind: err.kind || kind
            };
        }
    }

    root.RatebPlatformCompatGate = {
        __locked: true,
        version: '1.0.0-b1-prep',
        claim: claim,
        release: release,
        clearWriters: clearWriters,
        snapshot: snapshot,
        assertSingleEngine: assertSingleEngine,
        decide: decide,
        evaluate: evaluate,
        rejectIfDual: rejectIfDual,
        getLastDecision: function () { return lastDecision ? Object.assign({}, lastDecision) : null; },
        resetRegistryForTests: function () {
            Object.keys(registry).forEach(function (k) { registry[k] = null; });
            lastDecision = null;
        }
    };
})(typeof window !== 'undefined' ? window : this);
