# RATIB ERP v1.0 — FINAL GO-LIVE OPERATIONAL CERTIFICATION REPORT

**Report date:** 2026-06-27  
**Environment:** Production — `https://rateb.sa`  
**ERP database:** `admin_rateb-erp`  
**Application version:** `1.0.0`  
**Build marker:** `rateb-erp-ga-security-20260626`  
**Certification mode:** READ-ONLY (no code, migrations, schema, or feature changes)  
**Certification run ID:** `go-live-operational-cert-20260627-023758`  
**Evidence JSON:** `rateb-erp/docs/GA/go-live-operational-cert-20260627-023758.json`

---

## Final recommendation

# ⚠ PRODUCTION READY WITH OPERATIONAL ACTIONS REQUIRED

Application quality, security, and production readiness probes **pass**. The **Operational Go-Live Certification cannot be fully signed off** until **Step 1 (backup)**, **Step 2 (restore verification)**, and **Step 3 (backup integrity verify)** are executed **on the production server** by an operator with SSH and/or `RATEB_ERP_MIGRATE_TOKEN`.

**Do not** run `reset-production.php --confirm=RESET-PRODUCTION` until Steps 1–3 pass and explicit written approval is recorded.

---

## Certification checkpoint status

| Step | Requirement | Status | Notes |
|------|-------------|--------|-------|
| **1** | Production backup | **⛔ STOP — BLOCKED** | See forensic report below |
| **2** | Restore verification | **⏸ NOT EXECUTED** | Blocked by Step 1 |
| **3** | Backup validation (`erp-restore.php --verify`) | **⏸ NOT EXECUTED** | Blocked by Step 1 |
| **4** | Infrastructure validation | ✅ PASS (HTTP probes) | Partial — disk/cron/queue not remotely observable |
| **5** | Production readiness checklist | ✅ PASS | 18/18 admin routes HTTP 200 |
| **6** | Reset readiness (dry-run only) | ✅ PASS | 94 tables; super-admins preserved |
| **7** | Risk assessment | ✅ COMPLETE | See section below |
| **8** | Executive report | ✅ THIS DOCUMENT | |

Per certification rules: **certification stopped at Step 1** because the mandatory backup checkpoint could not be completed. Steps 4–6 were collected in read-only mode for context; they do **not** substitute for backup/restore proof.

---

## STEP 1 — Forensic report (backup gate)

### What failed

The official production backup procedure **was not executed** in this certification session. No SQL dump was created, verified, or recorded.

### Evidence

| Item | Value |
|------|-------|
| Attempted procedure | `POST /rateb-erp/public/enterprise-cert-run.php` with `action=backup` (requires `X-Rateb-Migrate-Token`) |
| Alternative | SSH: `php bin/erp-backup.php` on production host |
| Agent result | `status: BLOCKED` |
| Block reason | `RATEB_ERP_MIGRATE_TOKEN not available in agent environment` |
| Local token file | `storage/deploy-migrate-token` — **not present** in certification workstation |
| Timestamp | 2026-06-27T02:37:44+03:00 |
| Exit code | N/A (not run) |
| Backup filename | N/A |
| Backup size | N/A |
| Backup checksum | N/A |
| Duration | N/A |

### Root cause

Backup is **intentionally token-gated** for production safety. The certification agent runs from a developer workstation without:

1. Production SSH shell access, and  
2. The deploy migrate token (`RATEB_ERP_MIGRATE_TOKEN`) required by `enterprise-cert-run.php`.

This is an **operational access gap**, not an application defect. Backup scripts exist and are validated by enterprise infrastructure tests (31/31 PASS includes `erp-backup.php` and `erp-restore.php` presence checks).

### Recovery steps (operator — required before full sign-off)

```bash
# On production server
cd /home/admin/domains/rateb.sa/public_html/rateb-erp

# Option A — CLI (preferred)
php bin/erp-backup.php
# Expect exit code 0 and output path under storage/backups/

# Option B — HTTP (with token from server secrets)
curl -X POST "https://rateb.sa/rateb-erp/public/enterprise-cert-run.php" \
  -H "X-Rateb-Migrate-Token: $RATEB_ERP_MIGRATE_TOKEN" \
  -d "action=backup"

# Step 3 — verify latest dump
php bin/erp-restore.php --verify storage/backups/erp-admin_rateb-erp-YYYYMMDD-HHMMSS.sql.gz

# Step 2 — restore to temp DB (example)
# Create DB admin_rateb_erp_restore_test, set RATEB_ERP_DB_NAME, then:
php bin/erp-restore.php storage/backups/erp-admin_rateb-erp-YYYYMMDD-HHMMSS.sql.gz
php bin/enterprise-test/run.php --json
# Drop temp DB after verification
```

Record: filename, timestamp, size (bytes), duration (seconds), SHA-256 checksum.

**Expected backup location:**

```
/home/admin/domains/rateb.sa/public_html/rateb-erp/storage/backups/
```

**Expected artifacts:**

| Pattern | Contents |
|---------|----------|
| `erp-admin_rateb-erp-{Ymd-His}.sql.gz` | Full MySQL dump (gzip) |
| `erp-files-{Ymd-His}.tar.gz` | `storage/uploads/` archive (if uploads exist) |

**Gate:** If backup exit code ≠ 0 or `--verify` fails → **STOP**, produce new forensic report, do **not** proceed to production reset.

---

## Executive summary

RATIB ERP v1.0 on `https://rateb.sa` has completed:

- **Enterprise QA Tests 1–100** — 76 PASS, 1 BLOCKED (tenant-scoped support ticket write), 0 FAIL  
- **Safe QA v2** — zero orphan QA objects  
- **Security certification** — 0 Critical, 0 High  
- **Enterprise probe suite** — **31/31 PASS** on live production database  
- **Production readiness** — super-admin login and 18 module routes HTTP 200  
- **Reset dry-run** — 94 business tables scheduled; RBAC, CMS, migrations, super-admins preserved  

**Outstanding:** Disaster-recovery proof (backup → verify → restore → enterprise tests on temp DB) must be completed on the server before executive **✅ PRODUCTION READY FOR GO-LIVE** sign-off.

Prior QA certification: `rateb-erp/docs/QA/enterprise-qa-certification-final.md`  
Prior go-live checklist: `rateb-erp/docs/GA/go-live-final-report.md`

---

## Infrastructure summary

Evidence: HTTP probes 2026-06-27T02:37:44+03:00

| Check | Result | Evidence |
|-------|--------|----------|
| PHP version | ✅ **8.3.31** | `GET /rateb-erp/public/ping.php` → `RATEB ERP OK — PHP 8.3.31` |
| Health endpoint | ✅ OK | `GET /erp-health.php` → `{"status":"ok"}` HTTP 200 |
| Enterprise endpoint | ✅ **31/31 PASS** | `GET /erp-security-cert.php?enterprise=1` |
| Build marker | ✅ | `rateb-erp-ga-security-20260626` |
| SSL / HTTPS | ✅ | All probes over HTTPS |
| HSTS | ✅ | `Strict-Transport-Security` present on login |
| CSP | ✅ | `Content-Security-Policy` present on login |
| X-Frame-Options | ✅ | `SAMEORIGIN` |
| X-Content-Type-Options | ✅ | `nosniff` |
| Robots | ✅ | `GET /site/robots.txt` HTTP 200 |
| Database connectivity | ✅ (indirect) | Enterprise suite 31/31 against live DB |
| Disk space | ⏳ **Not remotely verified** | Requires server `df` |
| Storage permissions | ⏳ **Not remotely verified** | Requires server `ls -la storage/` |
| Upload directory | ⏳ **Not remotely verified** | Backup includes uploads when present |
| Cache / session / log dirs | ⏳ **Not remotely verified** | Requires server inspection |
| Cron status | ⏳ **Not remotely verified** | `rateb_cron_health` table exists (enterprise test) |
| Queue workers | ⏳ **Not remotely verified** | Queue monitor route HTTP 200 (admin UI) |
| OPcache / memory / upload limits | ⏳ **Not remotely verified** | Requires `php -i` on server |
| Timezone | ⏳ **Not remotely verified** | Requires server config |

---

## Backup summary

| Field | Value |
|-------|-------|
| **Status** | **NOT EXECUTED** |
| Filename | — |
| Timestamp | — |
| Size | — |
| Duration | — |
| Checksum | — |
| Exit code | — |
| Location (documented) | `/home/admin/domains/rateb.sa/public_html/rateb-erp/storage/backups/` |

---

## Restore summary

| Field | Value |
|-------|-------|
| **Status** | **NOT EXECUTED** (blocked by Step 1) |
| Temporary database | Planned: `admin_rateb_erp_restore_test` |
| Restore command | `php bin/erp-restore.php storage/backups/erp-admin_rateb-erp-*.sql.gz` |
| Post-restore tests | `php bin/enterprise-test/run.php --json` |
| Cleanup | Drop temp DB after verification |

---

## Security summary

| Area | Status | Evidence |
|------|--------|----------|
| Security cert (production) | ✅ PASS | `critical: 0`, `high: 0` |
| Health probe hardening | ✅ PASS | No anonymous privilege escalation |
| API rate limiting | ✅ PASS | GA-SEC-H04 |
| Security headers | ✅ PASS | CSP, HSTS, XFO, XCTO on login |
| Enterprise api_security suite | ✅ PASS | 4/4 |
| Super-admin preservation (reset preview) | ✅ PASS | 2 accounts preserved |

Full report: `rateb-erp/docs/GA/enterprise-final-pass-report.md`

---

## Performance summary

| Metric | Value | Source |
|--------|------:|--------|
| Enterprise QA avg response | ~250 ms | QA cert 2026-06-27 |
| Enterprise QA max response | 1458 ms | Marketing site page |
| Enterprise probe execution | ~1 s | Security cert JSON |
| k6 load test | ⏳ Not run in this session | Requires dedicated run |

No Critical/High/Medium performance defects recorded at code freeze.

---

## Operational summary

### Step 5 — Production readiness (authenticated, read-only)

Super-admin login: ✅ `admin@rateb.sa` → `/rateb-erp/public/admin`

| Module | HTTP | Status |
|--------|-----:|--------|
| Dashboard | 200 | ✅ |
| Settings | 200 | ✅ |
| Roles | 200 | ✅ |
| Permissions | 200 | ✅ |
| Companies | 200 | ✅ |
| Branches | 200 | ✅ |
| Billing (invoices) | 200 | ✅ |
| HR | 200 | ✅ |
| CRM (customers) | 200 | ✅ |
| Reports | 200 | ✅ |
| Notifications | 200 | ✅ |
| Automation health | 200 | ✅ |
| Queue monitor | 200 | ✅ |
| Audit logs | 200 | ✅ |
| Login activity | 200 | ✅ |
| Portal | 200 | ✅ |
| API v1 | 200 | ✅ |
| Monitoring (login-activity) | 200 | ✅ |

### Step 6 — Reset readiness (dry-run only — NOT executed)

| Item | Value |
|------|-------|
| Script | `bin/reset-production.php` |
| Dry-run probe | ✅ `erp-security-cert.php?enterprise=1&reset_dry_run=1` |
| Database | `admin_rateb-erp` |
| Tables to truncate | **94** |
| Non-super-admin users to delete | **2** (operational cert) / **0** (earlier dry-run snapshot — recount on server before reset) |
| Super-admins preserved | `admin@rateb.sa`, `ahmedashrafabdalmonem77@gmail.com` |
| Migrations preserved | ✅ `rateb_migrations` |
| RBAC preserved | ✅ permissions, roles, role_permissions, plans |
| CMS preserved | ✅ all `rateb_cms_*` |
| Settings preserved | ✅ `rateb_system_settings`, email/SMS templates |

**Production reset was NOT executed.** Await explicit approval phrase `RESET-PRODUCTION` after backup + restore proof.

Detail: `rateb-erp/docs/GA/reset-dry-run-report.md`

---

## Remaining risks

| ID | Severity | Issue | Mitigation |
|----|----------|-------|------------|
| **GL-C01** | **High** (operational) | No verified production backup in this certification | Operator runs `erp-backup.php` + `--verify` on server |
| **GL-C02** | **High** (operational) | Restore to temp DB not proven | Complete Step 2 before reset or GA sign-off |
| **GL-L01** | Low | Portal logout redirects to marketing home (`/`) not `/login` | Session cleared; UX observation only |
| **GL-L02** | Low | Test 91 support ticket QA write BLOCKED | Tenant-scoped model; not a production defect |
| **GL-L03** | Low | Admin login rate limiting under heavy QA | Wait ~15 min between burst login attempts |
| **GL-I01** | Informational | DB name hyphen (`admin_rateb-erp`) vs underscore in docs | Same ERP database on server |
| **GL-I02** | Informational | Infrastructure disk/cron/queue not remotely audited | Server-side ops checklist recommended |
| **GL-I03** | Informational | k6/AB load tests not re-run in this session | Schedule post-go-live if SLA required |

**Defect counts at certification:** Critical **0**, High **0** (application), Medium **0**, Low **2**, Informational **3**, Operational blockers **2**.

---

## Go-live checklist

| # | Item | Status |
|---|------|--------|
| 1 | Enterprise QA 1–100 | ✅ Complete |
| 2 | Safe QA v2 + zero orphans | ✅ Complete |
| 3 | Security certification | ✅ Complete |
| 4 | Monitoring controllers | ✅ Fixed & verified |
| 5 | Subscription auto-provision | ✅ Fixed & verified |
| 6 | Enterprise probe 31/31 | ✅ Complete |
| 7 | Production backup | ⛔ **PENDING — operator** |
| 8 | Backup verify (`--verify`) | ⛔ **PENDING — operator** |
| 9 | Restore to temp DB | ⛔ **PENDING — operator** |
| 10 | Infrastructure (full server audit) | ⚠ Partial (HTTP only) |
| 11 | Admin module readiness | ✅ Complete |
| 12 | Reset dry-run reviewed | ✅ Complete |
| 13 | Production reset | ❌ **NOT APPROVED / NOT RUN** |
| 14 | Executive sign-off | ⚠ **Pending Steps 7–9** |

---

## Approval record

| Role | Name | Date | Decision |
|------|------|------|----------|
| Enterprise Release Director | _Automated certification_ | 2026-06-27 | ⚠ Operational actions required |
| Product owner | | | _Pending backup/restore proof_ |
| Technical lead | | | _Pending backup/restore proof_ |
| DBA / Ops | | | _Execute Steps 1–3 on server_ |

---

## Next actions to reach ✅ PRODUCTION READY FOR GO-LIVE

1. Operator executes **Step 1** backup on production server; records filename, size, checksum, duration.  
2. Operator executes **Step 3** `erp-restore.php --verify` on the new dump.  
3. Operator executes **Step 2** restore to temp DB; runs enterprise tests; destroys temp DB.  
4. Update this report (or append operator addendum) with backup/restore evidence.  
5. Obtain written approval for production reset if pre-GA data wipe is still required.  
6. Change final recommendation to **✅ PRODUCTION READY FOR GO-LIVE** only when Steps 1–3 pass.

---

*RATIB ERP v1.0 — Final Go-Live Operational Certification Report. Generated under read-only code freeze. No production data was modified during this certification run.*
