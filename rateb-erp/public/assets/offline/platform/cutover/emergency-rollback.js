/*!
 * RATEB Platform Cutover Prep — Emergency Rollback (B1-Prep G3)
 * Remote flag → instant V1 writers, Platform writers off, session preserved.
 */
(function (root) {
    'use strict';

    if (root.RatebPlatformEmergencyRollback && root.RatebPlatformEmergencyRollback.__locked) {
        return;
    }

    var lastResult = null;

    function flagsApi() {
        return root.RatebPlatformCutoverFlags;
    }

    function gateApi() {
        return root.RatebPlatformCompatGate;
    }

    /**
     * Apply emergency rollback using remote flags (no deploy).
     * opts.fetcher — inject remote payload for tests.
     * opts.v1Enable — callback to re-enable Offline V1 writers
     * opts.platformDisableWriters — callback to stop Platform sync/writers
     */
    function apply(opts) {
        opts = opts || {};
        var Flags = flagsApi();
        if (!Flags) {
            return Promise.reject(new Error('emergency_flags_missing'));
        }

        var fetchOpts = {};
        if (typeof opts.fetcher === 'function') {
            fetchOpts.fetcher = opts.fetcher;
        } else if (opts.remoteFlags) {
            fetchOpts.fetcher = function () {
                return { flags: opts.remoteFlags };
            };
        }

        return Flags.fetchRemote(fetchOpts).catch(function () {
            /* If remote fails but sticky kill exists, continue. */
            if (!Flags.isEmergencyRollback()) {
                throw new Error('emergency_remote_unavailable');
            }
            return Flags.getFlags();
        }).then(function () {
            if (!Flags.isEmergencyRollback()) {
                lastResult = {
                    ok: false,
                    applied: false,
                    reason: 'emergency_flag_off',
                    flags: Flags.getFlags()
                };
                return lastResult;
            }

            var Gate = gateApi();
            if (Gate) {
                try {
                    /* Drop all Platform write claims; V1 writer is re-claimed below. */
                    if (typeof Gate.clearWriters === 'function') {
                        Gate.clearWriters(true);
                    } else {
                        Gate.release('sync', null, true);
                        Gate.release('queue', null, true);
                        Gate.release('sqlite', null, true);
                        Gate.release('runtime', null, true);
                        Gate.release('eventBus', null, true);
                        Gate.release('serviceLocator', null, true);
                        Gate.release('identity', null, true);
                    }
                } catch (eRelease) { /* best effort */ }
            }

            var platformStopped = true;
            if (typeof opts.platformDisableWriters === 'function') {
                try {
                    platformStopped = opts.platformDisableWriters() !== false;
                } catch (eStop) {
                    platformStopped = false;
                }
            } else if (root.RatebOfflineV2ActiveSync && typeof root.RatebOfflineV2ActiveSync.stop === 'function') {
                try {
                    root.RatebOfflineV2ActiveSync.stop();
                } catch (eSync) {
                    platformStopped = false;
                }
            }

            var v1Enabled = true;
            if (typeof opts.v1Enable === 'function') {
                try {
                    v1Enabled = opts.v1Enable() !== false;
                } catch (eV1) {
                    v1Enabled = false;
                }
            }

            if (Gate) {
                Gate.claim('offlineV1Writer', 'offline-v1', { writes: true, source: 'emergency_rollback' });
            }

            lastResult = {
                ok: !!(platformStopped && v1Enabled),
                applied: true,
                reason: 'emergency_rollback',
                sessionPreserved: true,
                logout: false,
                platformWritersDisabled: !!platformStopped,
                offlineV1Enabled: !!v1Enabled,
                dualWrite: false,
                dataDeleted: false,
                flags: Flags.getFlags(),
                at: new Date().toISOString()
            };
            return lastResult;
        });
    }

    function armFromRemote(remoteFlags) {
        return apply({ remoteFlags: remoteFlags || { EmergencyRollback: true } });
    }

    root.RatebPlatformEmergencyRollback = {
        __locked: true,
        version: '1.0.0-b1-prep',
        apply: apply,
        armFromRemote: armFromRemote,
        getLastResult: function () { return lastResult ? Object.assign({}, lastResult) : null; }
    };
})(typeof window !== 'undefined' ? window : this);
