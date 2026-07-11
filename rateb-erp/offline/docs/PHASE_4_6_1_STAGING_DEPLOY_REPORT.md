# Phase 4.6.1 — Deploy Offline to Staging (Certification)

**Date:** 2026-07-11  
**Environment:** `https://dev.rateb.sa` / DB `admin_rateb_dev`  
**Scope:** Deployment + verification only — **no new features**, offline flags **not enabled**  
**Result:** **READY for Stage 2 (Soak Test)** — with caveats below

---

## Deployment report

| Item | Result |
|------|--------|
| Target | `/home/admin/domains/dev.rateb.sa/public_html/rateb-erp` |
| Method | SSH + tarball extract + additive patches |
| Backup | `/home/admin/domains/dev.rateb.sa/backups/offline-phase461-20260711-004541` |
| Offline tree | Deployed `rateb-erp/offline/` |
| SDK assets | Deployed `public/assets/offline/rateb-offline.js` (+ min) |
| Migrations files | `175`–`179` copied to `migrations/` |
| `index.php` | Additive `OfflineModule::init()` (guarded `is_file`) — **no PosModule** (staging has no `modules/pos`) |
| `routes/api.php` | Additive `offline-api.php` require |
| `.env` `RATEB_OFFLINE_*` | **None** (0 matches) |
| Build marker | `rateb-erp-v1.0.1-maintenance-20260627` + `rateb-erp-offline-phase-4.6.1-staging-20260711-034514` |
| Production `admin_rateb-erp` | **Not touched** |

### Deployed surface (additive)

- `offline/**` (foundation + Inv/HR server/client + docs/tests)
- `public/assets/offline/**`
- `migrations/175_offline_sync_meta.sql` … `179_offline_device_activation.sql`
- Wiring patches only (index + api routes)
- Helper scripts: `bin/apply-offline-175-179-staging.php`, `bin/verify-offline-staging.php`
- Rollback SQL: `offline/docs/rollback-offline-175-179.sql`

### Explicitly not deployed / not changed

- Full ERP code refresh (staging remains v1.0.1 base)
- `modules/pos` (absent on staging; not introduced)
- Enabling any `RATEB_OFFLINE_*` env flags
- Migrations 136–174 (intentionally skipped to avoid ERP behavior drift)

---

## Migration report

| Migration | Status |
|-----------|--------|
| `175_offline_sync_meta.sql` | **DONE** |
| `176_offline_entity_cursors.sql` | **DONE** |
| `177_offline_device_registry.sql` | **DONE** |
| `178_pos_webauthn_company_scope.sql` | **DONE** |
| `179_offline_device_activation.sql` | **DONE** |
| Tracker | All five recorded in `rateb_migrations` |
| Database | `admin_rateb_dev` only (refused any other name in applicator) |

### Tables / columns verified

```
TABLE_OK rateb_offline_sync_queue
TABLE_OK rateb_offline_sync_conflicts
TABLE_OK rateb_offline_entity_cursors
TABLE_OK rateb_offline_devices
COL_OK rateb_offline_devices.activated_by / activated_at / approved_by / status
STATUS_TYPE=enum('pending','active','inactive','revoked') DEFAULT pending
PERM=pos.devices.manage
```

---

## Verification report

### Repository / filesystem

| Check | Result |
|-------|--------|
| `offline/OfflineModule.php` | Present; `php -l` OK |
| Offline API controller | Autoload OK |
| SDK HTTP | **200** `…/assets/offline/rateb-offline.js` (Phase 4.5.1 header) |
| SDK min | **200** |
| Offline route file | Present |

### Environment

| Check | Result |
|-------|--------|
| `RATEB_ENV` | `staging` |
| ERP DB | `admin_rateb_dev` |
| `RATEB_OFFLINE_*` in `.env` | **Absent** |
| `offline.enabled` (effective master) | **OFF** |
| `offline.inventory.movements` effective | **OFF** |
| `offline.hr.attendance` effective | **OFF** |
| `offline.read_cache` | **OFF** |
| `offline.pos.complete` default bit | ON in config file, but **inert** while master OFF |

### Health / ERP unchanged smoke

| Check | Result |
|-------|--------|
| `erp-health.php` | **200** `{"status":"ok"}` |
| Login | **200** |
| Admin | **200** |
| `/api/v1/offline/status` (unauthenticated) | **401** (route live; auth required — expected) |
| Existing ERP routes | Unmodified except additive offline require |
| POS module | Still absent (pre-existing staging state) |

### Rollback procedure (verified artifacts)

1. Restore from backup dir:  
   `…/backups/offline-phase461-20260711-004541/{index.php,api.php,ratib-erp-build.txt}`
2. Remove deployed code: `rm -rf offline/ public/assets/offline/` and optional `migrations/175–179` files
3. On **`admin_rateb_dev` only**, apply `offline/docs/rollback-offline-175-179.sql`
4. Confirm health + login still OK

Artifacts present: backup files + rollback SQL on server.  
**Rollback was not executed** (deploy retained for Stage 2).

---

## Security / safety notes

- Migrations applicator refuses any DB ≠ `admin_rateb_dev`
- Feature flags remain default OFF; no soak enablement in this phase
- Offline APIs require `ApiAuthMiddleware` (401 without token)
- WebAuthn column adds (178) are additive/conditional; rollback SQL leaves them in place by design

---

## Readiness for Stage 2 (Soak Test)

| Gate | Status |
|------|--------|
| Offline code on staging | **PASS** |
| SDK accessible | **PASS** |
| Migrations 175–179 applied | **PASS** |
| Offline tables exist | **PASS** |
| Flags OFF by default | **PASS** |
| Health OK | **PASS** |
| Rollback path documented + backed up | **PASS** |
| ERP smoke (login/admin/health) | **PASS** |

### Caveats before enabling soak flags

1. **Do not enable** `RATEB_OFFLINE_*` until Stage 2 soak runbook starts.
2. Staging has **no `modules/pos`** — multi-terminal **POS** soak needs a separate POS module deploy (out of this package). Enterprise Inv/HR offline soak can proceed on this stack.
3. POS sync tables (`rateb_pos_sync_*`) were **not** in scope of 175–179 and remain absent.
4. Site root `https://dev.rateb.sa/` may still 500 (pre-existing); ERP public paths used above are healthy.

### Stage 2 verdict

**READY for Stage 2 Soak Test** (enterprise offline Inv/HR + foundation), flags still OFF until soak kickoff.

**Not a GO for Procurement Offline** — soak (Phase 4.6 scenarios) still required after controlled flag enablement.
