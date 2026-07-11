# Enterprise Baseline v1.2 — Extension Guide

How to add future ERP modules **above** Baseline v1.2 without breaking frozen contracts.

---

## 1. Golden Path (mandatory shape)

```
Online Domain Services (business rules)
        ↑
{Module}OfflineReplayService (thin delegate)
        ↑
OfflineReplayEngine (additive if branch)
        ↑
OfflineQueue (module = …, frozen fields)
        ↑
{module}-adapter.js → RatebOffline.{module}()
```

Nothing else is an acceptable offline write path.

---

## 2. Step-by-step for a new module (e.g. Accounting drafts)

### A. Online first
1. Ensure domain services exist and enforce all validation.
2. Additive migration if needed.
3. Online tests GREEN.

### B. Offline additive
1. Flags in `offline/config/feature-flags.php` (default **false** + env map).
2. Helpers on `OfflineFeatureFlagService` (require master).
3. `{Module}OfflineTenantGuard`.
4. `{Module}OfflineReplayService` — `deferredActions()`, `replayFromQueueRow()`, match → domain only.
5. Additive branches in `OfflineReplayEngine` + `OfflineQueueService` (enqueue normalize + processPending).
6. `resolve{Module}()` on `OfflineConflictResolverService` if status/qty conflicts apply.
7. Register in `modules.php` + `entity-manifest.php`.
8. Client `offline/client/adapters/{module}-adapter.js`.
9. Expose via `RatebOffline.{module}()` in `sdk.js` (additive).
10. Rebuild bundle: `php offline/scripts/build-rateb-offline-bundle.php`.
11. Optional: ops allowlist paths + ops-forms hooks (narrow).
12. Optional: read-only directory under master-data / Tier-1 pull.
13. Tests: `offline/tests/{Module}Offline…Test.php` + runner; keep prior gates GREEN.
14. Doc: `offline/docs/PHASE_XX_….md`.

### C. Never
- Business validation in the adapter  
- Direct DB writes from replay  
- Approvals / payments / posting unless online services already define a **draft-safe** API and product explicitly enables it  

---

## 3. Module templates by domain

| Future module | Online prerequisite | Offline Tier-1 candidates | Explicitly later |
|---------------|---------------------|---------------------------|------------------|
| Accounting | Journal / voucher services | Draft voucher enqueue | Auto-post, payments |
| CRM | Customer / lead services | Lead/note drafts | Billing |
| Projects | Project/task services | Task status drafts | Budget posting |
| Assets | Asset register services | Inspection drafts | Depreciation runs |
| Maintenance | Work-order services | WO create/update drafts | Vendor payments |
| Recruitment expansion | Extend 15A services first | Agency draft, passport update API | Gov submission |

---

## 4. Feature flag naming

```
offline.{module}
offline.{module}.{capability}
```

Env: `RATEB_OFFLINE_{MODULE}` / `RATEB_OFFLINE_{MODULE}_{CAPABILITY}`

Always default **OFF**. Document in phase report.

---

## 5. Test minimums

- Flag default OFF + requires master  
- Replay skips when OFF  
- Replay delegates to domain (source inspection)  
- Queue rejects when OFF / subflag OFF  
- Conflict resolver cases  
- Tenant guard markers  
- SDK bundle contains adapter  
- Foundation markers: IDB v2, queue fields, SDK 14.2.0  

---

## 6. Compatibility promise

Existing clients on SDK **14.2.0** must keep working when your module lands with flags OFF. Do not change default flag values in `feature-flags.php` for already-shipped keys.
