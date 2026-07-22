# RATEB ERP v1.0 — GA Certification (Operational)

**Auditor role:** Lead Release Engineer + Enterprise QA  
**Date:** 2026-06-26  
**Production:** `https://rateb.sa`  
**Commit deployed:** `3eec9ce1f34aa672b0c78fa91e61981829f59417`  
**Build:** `rateb-erp-ga-security-20260626`

---

## 1. Deployment verification

| Check | Result |
|-------|--------|
| Build marker matches GA security release | ✅ |
| Git HEAD = `origin/main` | ✅ |
| Anonymous health `{"status":"ok"}` | ✅ |
| `erp-security-cert.php` critical=0 high=0 | ✅ |

Details: [deployment-verification.md](./deployment-verification.md)

---

## 2. Security verification (operational)

| Check | Production evidence |
|-------|---------------------|
| Health probes blocked | ✅ 404/403, no impersonation |
| No schema disclosure without token | ✅ |
| Document scan unauthenticated | ✅ HTTP 302 → login |
| Security headers (6) | ✅ on health + login |
| API rate limit 429 | ✅ 11/130 requests |
| Cross-tenant barcode (authenticated) | ⏸ Not tested (no creds) |
| CMS XSS/SVG live test | ⏸ Not tested (no creds) |

Details: [health-endpoint-validation.md](./health-endpoint-validation.md), [security-operational-validation.md](./security-operational-validation.md)

---

## 3. Enterprise validation

| Metric | Result |
|--------|--------|
| Staging + enterprise seed | ❌ Not run |
| Enterprise suite 100% | ❌ 15/28 (local, no DB) |

Details: [enterprise-validation-final.md](./enterprise-validation-final.md)

---

## 4. Accounting validation

❌ **Not executed** — no seeded Staging, no numerical TB/BS/PL/CF.

Details: [accounting-final.md](./accounting-final.md)

---

## 5. Performance metrics

| Metric | Value |
|--------|------:|
| Health avg (n=30) | 120 ms |
| Health P95 | 120 ms |
| k6 / AB | Not run |
| CPU / RAM / MySQL | Not measured |

Details: [performance-final.md](./performance-final.md)

---

## 6. Disaster recovery

| Metric | Value |
|--------|-------|
| Backup/restore scripts | Present (structural PASS) |
| RPO / RTO | **Not measured** |
| Restore drill | **Not executed** |

Details: [dr-final.md](./dr-final.md)

---

## 7. Remaining risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| Enterprise suite not 100% on DB-connected host | High | Run on Staging post-seed |
| Accounting reconciliation not proven | High | TB/BS/PL/CF compare after seed |
| DR RTO/RPO unknown | Medium | Scheduled restore drill on Staging |
| Cross-tenant barcode not live-tested | Medium | QA with two company test accounts |
| CMS admin XSS not live-pen-tested | Low | Code sanitization deployed; admin spot-check |
| Predictable barcode format | Low | Mitigated by auth + tenant gate |

---

## 8. Evidence log (reproducible commands)

```powershell
# Build marker
Invoke-WebRequest https://rateb.sa/rateb-erp/public/ratib-erp-build.txt

# Public health
Invoke-WebRequest https://rateb.sa/rateb-erp/public/erp-health.php
# → {"status":"ok"}

# Blocked probes
Invoke-WebRequest "https://rateb.sa/rateb-erp/public/erp-health.php?probe=branch-ops" -MaximumRedirection 0
# → 404 {"status":"forbidden"}

# Document scan (unauthenticated)
Invoke-WebRequest "https://rateb.sa/rateb-erp/public/scan/doc/PO00030000000123" -MaximumRedirection 0
# → 302 → /login

# Security cert
Invoke-WebRequest https://rateb.sa/rateb-erp/public/erp-security-cert.php
# → ok:true critical:0 high:0

# API rate limit burst (130 requests) → 11× HTTP 429
```

---

## FINAL DECISION

# ❌ GENERAL AVAILABILITY BLOCKED

### Approved with operational evidence (Production)

- GA security build **deployed and verified**
- Health endpoint **hardened and working**
- Dangerous probes **blocked**
- Security headers **present**
- API rate limiting **returns 429**
- Unauthenticated document scan **blocked**

### Blocking GA (missing operational evidence)

1. Enterprise Test Suite **not 100%** on DB-connected Staging (15/28 local only).
2. Enterprise seed + E2E branch transfers **not executed**.
3. Accounting TB/BS/PL/CF reconciliation **not executed**.
4. k6 / Apache Bench **not executed**.
5. Backup/restore drill with **measured RTO/RPO** **not executed**.
6. Authenticated cross-tenant security tests **not executed** (credential gap).

**Security deployment verification: ✅ PASS**  
**Full GA operational certification: ❌ BLOCKED**

### Minimum path to ✅ APPROVED FOR GA

1. Provision Staging (`RATEB_ENV=staging`) + run enterprise seed.
2. `php bin/enterprise-test/run.php` → **28/28**.
3. Accounting numerical reconciliation documented.
4. k6 run with P95/P99 logged.
5. Restore drill with RTO/RPO on Staging.
6. Authenticated IDOR spot-check (two tenants).
