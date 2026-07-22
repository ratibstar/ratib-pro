# RATEB ERP v1.0 — Final Risk Register

**Register date:** 2026-06-27  
**Environment:** Production `https://rateb.sa`  
**Version:** 1.0.0  
**Overall risk:** LOW

---

## Severity summary

| Severity | Count |
|----------|------:|
| **Critical** | **0** |
| **High** | **0** |
| **Medium** | **0** |
| **Low** | **3** |

---

## Open items

### LOW — Logout redirects to marketing site

| Field | Detail |
|-------|--------|
| **ID** | L-01 |
| **Severity** | LOW |
| **Module** | Company Portal |
| **Observation** | Portal logout redirects to `https://rateb.sa/` instead of `/rateb-erp/public/login` |
| **Session handling** | ✅ Session correctly destroyed |
| **User impact** | Cosmetic UX — user lands on marketing home after logout |
| **Production defect** | No — functional security behavior is correct |
| **Evidence** | Regression Issues 14–17, Test 14 (2026-06-27T02:38+03:00) |
| **Recommended action** | Address in v1.0.1 if UX polish desired |

---

### LOW — Backup verifier false negative on MariaDB preamble (>512 bytes)

| Field | Detail |
|-------|--------|
| **ID** | L-02 |
| **Severity** | LOW |
| **Module** | Backup / Restore tooling |
| **Observation** | `php bin/erp-restore.php --verify` returns `Backup invalid: not_sql_dump` on MariaDB 10.11 dumps |
| **Root cause** | `DeploymentReadinessService::verifyBackupFile()` reads only first 512 decompressed bytes; MariaDB sandbox preamble pushes `CREATE TABLE` beyond that window |
| **Operational impact** | None — manual verify and successful restore import confirm dump validity |
| **Evidence** | `go-live-backup-restore-evidence-20260627.json` — extended manual verify PASS, restore 143 tables, enterprise 31/31 |
| **Recommended action** | Increase read window or scan full stream in v1.0.1 |

---

### LOW — Build marker could be incremented on future release

| Field | Detail |
|-------|--------|
| **ID** | L-03 |
| **Severity** | LOW |
| **Module** | Deployment / Release process |
| **Observation** | Current build marker `rateb-erp-ga-security-20260626` reflects GA security bundle; future patches should increment marker |
| **Production impact** | None at v1.0 GA closeout |
| **Current marker** | `https://rateb.sa/rateb-erp/public/ratib-erp-build.txt` |
| **Recommended action** | Increment build marker on each production deploy post-GA |

---

## Closed / non-register items

The following were evaluated and **do not** appear in this register:

| Item | Disposition |
|------|-------------|
| Enterprise QA Test 91 (support ticket write) | BLOCKED by tenant scope — not a production defect; excluded from LOW register per GA closeout scope |
| Production reset | Process gate only — not executed by design; separate approval required |
| DB name hyphen vs underscore | Informational documentation note only |

---

## Risk acceptance

All three LOW items are **accepted for GA go-live**. None require immediate production action.

| Role | Acceptance | Date |
|------|------------|------|
| QA | ✅ Accepted | 2026-06-27 |
| Operations | ✅ Accepted | 2026-06-27 |
| Security | ✅ Accepted | 2026-06-27 |
| Product | _Pending signature_ | |

---

*RATEB ERP v1.0 Final Risk Register — documentation only.*
