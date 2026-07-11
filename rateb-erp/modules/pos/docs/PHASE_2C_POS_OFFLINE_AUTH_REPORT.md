# Phase 2C — POS Offline Auth + Device Governance

**Date:** 2026-07-11  
**Scope:** POS module only — WebAuthn harden, device admin, local lock vault, offline shell wiring, tests  
**Out of scope:** Full offline ERP login, real face SDK, Inventory/HR/Accounting, Designed/

---

## What shipped (verified)

| Stream | Artifacts |
|--------|-----------|
| WebAuthn harden | `BiometricAuthService` challenge binding via `clientDataJSON`; `migrations/178_pos_webauthn_company_scope.sql`; face disabled in `pos-biometric-gate.js` + `verifyFace()` always fails |
| Device admin | `migrations/179_offline_device_activation.sql` + `offline/migrations/004_*`; `PosOfflineDeviceService`; register/heartbeat APIs; `views/devices/index.php`; `pos.devices.manage` |
| Lock vault + shell | `pos-auth-lock.js` IndexedDB (PBKDF2 PIN, no raw password); wired in `pos-shell.php`, biometric gate enroll, register header; SW biometric → register; sync gated on session reauth |

---

## Migration run order

Two files were originally both numbered **178**. Collision resolved additively:

| Order | File | Purpose |
|-------|------|---------|
| 1 | `migrations/177_offline_device_registry.sql` | Base `rateb_offline_devices` |
| 2 | `migrations/178_pos_webauthn_company_scope.sql` | `company_id` / `branch_id` on `rateb_webauthn_credentials` |
| 3 | `migrations/179_offline_device_activation.sql` | `pending` status + activate/approve columns + `pos.devices.manage` |
| Mirror | `offline/migrations/004_offline_device_activation.sql` | Same device activation DDL for offline tree (no permission inserts) |

**Note:** If a staging DB already applied a copy of device activation under the old `178_offline_device_activation.sql` name, skip re-running content (DDL is idempotent via `INFORMATION_SCHEMA` guards) and ensure the file on disk is now `179_*`.

---

## Test report

**Command:** `php modules/pos/tests/run-offline-sync-tests.php`  
**Result:** **42/42 PASS**

| Suite | Cases | Focus |
|-------|-------|--------|
| `PosOfflineSyncTest` | 4 | Version conflict resolver |
| `PosOfflinePhase2BTest` | 21 | Ack, replay, client wiring, stress |
| `PosOfflinePhase2CTest` | 17 | PIN client-only, device status gate, company scope, face deny, lock/SW/sync audits, migration numbering |

Coverage mapped to plan §6:

1. **PIN** — client-only PBKDF2 in IndexedDB; no server PIN verify (documented + audited)  
2. **Device status gate** — pending/revoked blocked; active allowed; `publicDevice.is_active` mirrors status  
3. **Credential company scope** — service queries + migration 178  
4. **Client audits** — lock overlay path; no face stub success; SW biometric → register; sync session renew gate  

---

## Security notes

| Control | Status | Notes |
|---------|--------|-------|
| No password offline | Pass | Vault stores salted PIN hash (PBKDF2) + WebAuthn credential id only |
| Revoke kills unlock | Pass | Cached `status !== active` → unlock rejects; re-register returns to `pending` |
| Tenant isolation on device APIs | Pass | `company_id` from session; `findByDeviceId(company, device)`; activate/revoke reject cross-company rows; `OfflineDevice` tenant-scoped |
| Face never authenticates | Pass | Client button disabled; server `verifyFace` always `ok: false` |
| WebAuthn challenge binding | Pass | `clientDataJSON` type + challenge (+ origin) checked on finish |
| Credential tenancy | Pass | Queries filter `company_id` when column present (legacy NULL allowed until backfill) |
| Sync without renewed session | Pass | `sessionNeedsReauth` blocks push; sell can still queue locally |

**Residual risks (Medium):**

1. **Unknown device status allows unlock** until first successful register/heartbeat caches status (`isDeviceAllowedOffline` returns true when cache empty). Mitigate: require online device register before first offline open in ops runbooks.  
2. **Full COSE signature verify** still deferred (challenge + presence of authenticatorData/signature only). Prefer a WebAuthn library before high-assurance production.  
3. **Offline WebAuthn unlock** trusts platform assertion + local credential id match (no server round-trip) — acceptable for cashier lock after prior online enroll; revoke relies on cached status refresh when online.

---

## Success criteria checklist

| Criterion | Status |
|-----------|--------|
| Opening register offline (after one online visit) shows POS shell + cashier lock, not Chrome error / login HTML only | Pass (SW v7 shell + lock overlay wiring; staging soak recommended) |
| Unlock with enrolled fingerprint or PIN for that company/branch user | Pass (vault enroll + unlock paths) |
| New device cannot sell offline until admin activates it | Conditional — blocked when status cached as pending; unknown cache still allows until heartbeat |
| Admin can list/activate/revoke devices per company and branch | Pass (devices UI + service + permission) |
| Face does not falsely authenticate | Pass |

---

## Production readiness score

| Dimension | Score (0–10) | Weight |
|-----------|--------------|--------|
| Functional completeness (lock + devices + WebAuthn) | 8.0 | 25% |
| Security posture (no password, revoke, tenancy, face) | 8.0 | 25% |
| Offline shell reliability (SW + lock + sync gate) | 8.5 | 20% |
| Test depth (unit + source audits) | 8.0 | 15% |
| Migration / ops clarity | 8.5 | 15% |

**Weighted score: 8.2 / 10**

### Gate recommendation

**CONDITIONAL GO** for staging enablement of POS offline cashier lock + device governance.

Before production:

1. Run migrations **178** then **179** (and confirm `177` already applied)  
2. Staging soak: enroll → go offline → lock → PIN/WebAuthn unlock; pending device blocked after heartbeat; revoke then confirm unlock fails after status refresh  
3. Tighten unknown-status allow if ops require hard deny before first cache (small client change)  
4. Keep enterprise Offline foundation flag separate; this phase is POS lock + `rateb_offline_devices` admin only  
5. Plan WebAuthn library for full assertion signature verify before high-risk tenants
