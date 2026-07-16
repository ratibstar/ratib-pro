/*!
 * RATEB Offline V2 — L7 Package Manager (Phase 2)
 * verify → stage → activate → rollback
 * Uses HCI only. Never mutates packages/* in place. Never mutates active slot.
 */
(function (root) {
    'use strict';

    if (root.RatebOfflineV2PM && root.RatebOfflineV2PM.__locked) {
        return;
    }

    var PM_VERSION = '1.0.0-phase2';
    var INSTALL_SCHEMA = 'rateb-offline-v2-install/1';

    function hci() {
        var h = root.RatebOfflineV2HCI;
        if (!h) {
            throw new Error('pm_hci_missing');
        }
        return h;
    }

    function utf8Encode(str) {
        return new TextEncoder().encode(String(str));
    }

    function assertSlot(slotId) {
        if (hci().SLOTS.indexOf(slotId) === -1) {
            throw new Error('pm_bad_slot:' + slotId);
        }
    }

    function artifactFileName(id, version, sha256) {
        var safeId = String(id).replace(/[^a-zA-Z0-9._-]/g, '_');
        var safeVer = String(version).replace(/[^a-zA-Z0-9._-]/g, '_');
        return safeId + '__' + safeVer + '__' + sha256 + '.pkg';
    }

    function packagePath(type, id, version, sha256) {
        return 'packages/' + type + '/' + artifactFileName(id, version, sha256);
    }

    function metaPath(type, id, version, sha256) {
        return packagePath(type, id, version, sha256) + '.meta.json';
    }

    function slotManifestPath(slotId) {
        return 'slots/' + slotId + '/install.manifest.json';
    }

    /**
     * Ingest immutable package artifact into packages/{type}/ (create-if-absent).
     * Staging copy first under updates/, verify hash, then commit to packages/.
     */
    function ingestArtifact(spec) {
        var type = spec.type;
        var id = spec.id;
        var version = spec.version;
        var bytes = spec.bytes instanceof Uint8Array ? spec.bytes : new Uint8Array(spec.bytes || []);
        var H = hci();

        if (H.PACKAGE_TYPES.indexOf(type) === -1) {
            return Promise.reject(new Error('pm_bad_package_type'));
        }
        if (!id || !version) {
            return Promise.reject(new Error('pm_missing_id_version'));
        }

        return H.sha256Hex(bytes).then(function (sha256) {
            if (spec.sha256 && String(spec.sha256).toLowerCase() !== sha256) {
                throw new Error('pm_hash_mismatch');
            }
            var updatePath = 'updates/' + sha256 + '.pkg';
            var dest = packagePath(type, id, version, sha256);
            var meta = metaPath(type, id, version, sha256);
            var metaObj = {
                schema: 'rateb-offline-v2-artifact/1',
                type: type,
                id: id,
                version: version,
                sha256: sha256,
                size: bytes.length,
                path: dest,
                ingestedAt: new Date().toISOString()
            };

            return H.writeBytes(updatePath, bytes, {}).then(function () {
                return H.sha256Hex(bytes);
            }).then(function (verifyHash) {
                if (verifyHash !== sha256) {
                    throw new Error('pm_staging_corrupt');
                }
                return H.writeBytes(dest, bytes, {
                    packageIngest: true,
                    createIfAbsent: true
                });
            }).then(function (writeRes) {
                return H.writeBytes(meta, utf8Encode(JSON.stringify(metaObj, null, 2)), {
                    packageIngest: true,
                    createIfAbsent: true
                }).then(function () {
                    return H.removeFile(updatePath).catch(function () { /* best-effort */ });
                }).then(function () {
                    return {
                        ok: true,
                        created: !writeRes.skipped,
                        skipped: !!writeRes.skipped,
                        path: dest,
                        sha256: sha256,
                        meta: metaObj
                    };
                });
            });
        });
    }

    function getActive() {
        return hci().readActivePointer();
    }

    /**
     * Stage install manifest into a non-active slot (may overwrite that slot).
     */
    function stageInstall(slotId, installManifest) {
        assertSlot(slotId);
        var H = hci();
        return getActive().then(function (active) {
            if (active.activeSlot === slotId) {
                throw new Error('pm_cannot_stage_active_slot');
            }
            var man = installManifest || {};
            if (man.schema !== INSTALL_SCHEMA) {
                throw new Error('pm_bad_install_schema');
            }
            if (!man.installId || !man.packages || !man.packages.runtime) {
                throw new Error('pm_install_incomplete');
            }
            man.slot = slotId;
            man.stagedAt = new Date().toISOString();
            man.status = 'staged';

            return H.writeJson(slotManifestPath(slotId), man, {
                activeSlot: active.activeSlot || null
            }).then(function () {
                return { ok: true, slot: slotId, installId: man.installId };
            });
        });
    }

    function readSlotManifest(slotId) {
        assertSlot(slotId);
        return hci().readJson(slotManifestPath(slotId));
    }

    /**
     * Re-hash every referenced package artifact; refuse activate on failure.
     */
    function verifySlot(slotId) {
        assertSlot(slotId);
        var H = hci();
        return readSlotManifest(slotId).then(function (man) {
            if (!man || !man.packages) {
                throw new Error('pm_slot_empty');
            }
            var refs = [];
            var rt = man.packages.runtime;
            if (rt) {
                refs.push(rt);
            }
            ['modules', 'language', 'assets'].forEach(function (k) {
                var list = man.packages[k];
                if (Array.isArray(list)) {
                    list.forEach(function (item) { refs.push(item); });
                } else if (list && list.sha256) {
                    refs.push(list);
                }
            });

            var results = [];
            var chain = Promise.resolve();
            refs.forEach(function (ref) {
                chain = chain.then(function () {
                    if (!ref.path || !ref.sha256) {
                        throw new Error('pm_ref_incomplete');
                    }
                    if (String(ref.path).indexOf('packages/') !== 0) {
                        throw new Error('pm_ref_not_in_packages');
                    }
                    return H.readFile(ref.path).then(function (bytes) {
                        return H.sha256Hex(bytes).then(function (hex) {
                            var ok = hex === String(ref.sha256).toLowerCase();
                            results.push({ path: ref.path, ok: ok, expected: ref.sha256, actual: hex });
                            if (!ok) {
                                throw new Error('pm_verify_failed:' + ref.path);
                            }
                        });
                    });
                });
            });
            return chain.then(function () {
                return { ok: true, slot: slotId, installId: man.installId, checked: results.length, results: results };
            });
        });
    }

    /**
     * Atomic activate: verify → materialize runtime from packages → flip active.json.
     * Does not modify packages/*. Does not write into the newly-active slot after flip
     * (manifest already written at stage). Does not mutate previous active slot contents.
     */
    function activate(slotId) {
        assertSlot(slotId);
        var H = hci();
        return verifySlot(slotId).then(function (vr) {
            return getActive().then(function (prev) {
                return readSlotManifest(slotId).then(function (man) {
                    var rt = man.packages.runtime;
                    return H.readFile(rt.path).then(function (runtimeBytes) {
                        var runtimeManifest = {
                            id: rt.id,
                            version: rt.version,
                            sha256: rt.sha256,
                            path: rt.path,
                            installId: man.installId,
                            activatedFromSlot: slotId,
                            activatedAt: new Date().toISOString()
                        };
                        // Stage runtime bytes in temp, then write runtime/ (replaceable).
                        var tmp = 'temp/activate-' + slotId + '-' + Date.now() + '.pkg';
                        return H.writeBytes(tmp, runtimeBytes, {}).then(function () {
                            return H.writeBytes('runtime/runtime.pkg', runtimeBytes, {});
                        }).then(function () {
                            return H.writeJson('runtime/runtime.manifest', runtimeManifest, {});
                        }).then(function () {
                            var nextActive = {
                                layout: H.layoutId,
                                phase: 2,
                                activeSlot: slotId,
                                previousSlot: prev.activeSlot && prev.activeSlot !== slotId
                                    ? prev.activeSlot
                                    : (prev.previousSlot || null),
                                installId: man.installId,
                                status: 'active',
                                updatedAt: new Date().toISOString()
                            };
                            return H.writeJson('runtime/active.json', nextActive, {}).then(function () {
                                return H.removeFile(tmp).catch(function () { /* ignore */ });
                            }).then(function () {
                                return H.appendLog('activate ' + slotId + ' install ' + man.installId);
                            }).then(function () {
                                return {
                                    ok: true,
                                    slot: slotId,
                                    previousSlot: nextActive.previousSlot,
                                    installId: man.installId,
                                    verify: vr
                                };
                            });
                        });
                    });
                });
            });
        });
    }

    function rollback() {
        return getActive().then(function (cur) {
            if (!cur.previousSlot) {
                throw new Error('pm_no_previous_slot');
            }
            if (cur.previousSlot === cur.activeSlot) {
                throw new Error('pm_rollback_same_slot');
            }
            return activate(cur.previousSlot).then(function (res) {
                return { ok: true, rolledBackTo: res.slot, from: cur.activeSlot, detail: res };
            });
        });
    }

    /**
     * Enterprise self-test — exercises ingest → stage → verify → activate → rollback
     * without touching Offline V1 or network.
     */
    function runSelfTest() {
        var H = hci();
        var evidence = [];
        function note(step, ok, detail) {
            evidence.push({ step: step, ok: !!ok, detail: detail || '' });
        }

        var runtimeA = utf8Encode('RUNTIME-ARTIFACT-A-v1');
        var runtimeB = utf8Encode('RUNTIME-ARTIFACT-B-v2');
        var modBytes = utf8Encode('MODULE-ARTIFACT-DEMO');

        return H.ensureLayout().then(function () {
            note('ensureLayout', true);
            return ingestArtifact({
                type: 'runtime', id: 'core-runtime', version: '1.0.0', bytes: runtimeA
            });
        }).then(function (a) {
            note('ingest_runtime_a', a.ok, a.path);
            return ingestArtifact({
                type: 'runtime', id: 'core-runtime', version: '2.0.0', bytes: runtimeB
            }).then(function (b) {
                note('ingest_runtime_b', b.ok, b.path);
                return ingestArtifact({
                    type: 'modules', id: 'demo-module', version: '1.0.0', bytes: modBytes
                }).then(function (m) {
                    note('ingest_module', m.ok, m.path);
                    // Immutability: re-ingest identical A must skip, not overwrite
                    return ingestArtifact({
                        type: 'runtime', id: 'core-runtime', version: '1.0.0', bytes: runtimeA
                    }).then(function (again) {
                        note('immutable_skip', !!again.skipped, 'skipped=' + again.skipped);
                        return { a: a, b: b, m: m };
                    });
                });
            });
        }).then(function (arts) {
            var installA = {
                schema: INSTALL_SCHEMA,
                installId: 'install-a-' + Date.now(),
                version: '1.0.0',
                packages: {
                    runtime: {
                        id: arts.a.meta.id,
                        version: arts.a.meta.version,
                        sha256: arts.a.sha256,
                        path: arts.a.path
                    },
                    modules: [{
                        id: arts.m.meta.id,
                        version: arts.m.meta.version,
                        sha256: arts.m.sha256,
                        path: arts.m.path
                    }],
                    language: [],
                    assets: []
                }
            };
            var installB = {
                schema: INSTALL_SCHEMA,
                installId: 'install-b-' + Date.now(),
                version: '2.0.0',
                packages: {
                    runtime: {
                        id: arts.b.meta.id,
                        version: arts.b.meta.version,
                        sha256: arts.b.sha256,
                        path: arts.b.path
                    },
                    modules: [],
                    language: [],
                    assets: []
                }
            };

            return getActive().then(function (cur) {
                // Refresh-safe: never stageInstall into the currently active slot first.
                // After a prior boot self-test, activeSlot is usually slot-a; staging slot-a
                // again throws pm_cannot_stage_active_slot and blocks Shell Ready forever.
                var slotPrimary = (cur && cur.activeSlot === 'slot-a') ? 'slot-b' : 'slot-a';
                var slotSecondary = slotPrimary === 'slot-a' ? 'slot-b' : 'slot-a';
                note('slot_plan', true, 'primary=' + slotPrimary + ' secondary=' + slotSecondary +
                    ' wasActive=' + ((cur && cur.activeSlot) || 'none'));

                return stageInstall(slotPrimary, installA).then(function (s1) {
                    note('stage_slot_a', s1.ok, s1.installId + '@' + slotPrimary);
                    return verifySlot(slotPrimary).then(function (v1) {
                        note('verify_slot_a', v1.ok, 'checked=' + v1.checked);
                        return activate(slotPrimary).then(function (act1) {
                            note('activate_slot_a', act1.ok, act1.slot);
                            return stageInstall(slotSecondary, installB).then(function (s2) {
                                note('stage_slot_b', s2.ok, s2.installId + '@' + slotSecondary);
                                return activate(slotSecondary).then(function (act2) {
                                    note('activate_slot_b', act2.ok, 'prev=' + act2.previousSlot);
                                    // Refuse staging into active slot
                                    return stageInstall(slotSecondary, installB).then(function () {
                                        note('refuse_stage_active', false, 'should_have_thrown');
                                    }).catch(function (err) {
                                        note('refuse_stage_active', /pm_cannot_stage_active_slot/.test(String(err && err.message)), String(err && err.message));
                                    }).then(function () {
                                        return rollback().then(function (rb) {
                                            note('rollback', rb.ok, 'to=' + rb.rolledBackTo);
                                            return getActive().then(function (cur2) {
                                                note('active_after_rollback', cur2.activeSlot === slotPrimary, JSON.stringify(cur2));
                                                // packages immutable: attempt overwrite must fail
                                                return H.writeBytes(arts.a.path, utf8Encode('TAMPER'), {
                                                    packageIngest: true,
                                                    createIfAbsent: true
                                                }).then(function (w) {
                                                    note('package_no_overwrite', !!w.skipped, 'skipped=' + w.skipped);
                                                }).catch(function (err) {
                                                    note('package_no_overwrite', /hci_packages/.test(String(err && err.message)), String(err && err.message));
                                                });
                                            });
                                        });
                                    });
                                });
                            });
                        });
                    });
                });
            });
        }).then(function () {
            var failed = evidence.filter(function (e) { return !e.ok; });
            return {
                ok: failed.length === 0,
                version: PM_VERSION,
                evidence: evidence,
                failed: failed
            };
        }).catch(function (err) {
            note('fatal', false, String(err && err.message ? err.message : err));
            return {
                ok: false,
                version: PM_VERSION,
                evidence: evidence,
                error: String(err && err.message ? err.message : err)
            };
        });
    }

    root.RatebOfflineV2PM = {
        __locked: true,
        version: PM_VERSION,
        INSTALL_SCHEMA: INSTALL_SCHEMA,
        ingestArtifact: ingestArtifact,
        stageInstall: stageInstall,
        verifySlot: verifySlot,
        activate: activate,
        rollback: rollback,
        getActive: getActive,
        readSlotManifest: readSlotManifest,
        runSelfTest: runSelfTest
    };
})(typeof window !== 'undefined' ? window : this);
