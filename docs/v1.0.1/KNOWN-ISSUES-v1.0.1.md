# RATIB ERP v1.0.1 — Known Issues

**Last updated:** 2026-06-27  
**Release:** 1.0.1 (maintenance)

---

## Open — Low severity

| ID | Issue | Impact | Workaround |
|----|-------|--------|------------|
| L-ARCH-01 | Obsolete GA docs with BLOCKED status remain in tree | Operator confusion | Use `FINAL-GA-CERTIFICATE.md` as canonical; archive plan ready |
| L-ARCH-02 | Tracked `scripts/__pycache__/*.pyc` | Repo noise | `.gitignore` updated; manual removal when approved |
| SEC-L01 | Legacy passwords in archive markdown | Informational if rotated | Redact during archive execution |
| SEC-L02–05 | Default passwords in recovery/setup scripts | Dev/recovery only | Do not expose URLs; rotate after use |
| CI-01 | Auto-migrations on every `main` deploy | Ops process | Review migrations before merge |
| CI-02 | No active PR validation workflow | Process | Draft in `.github/workflow-drafts/` |

---

## Closed in v1.0.1

| ID | Issue | Resolution |
|----|-------|------------|
| L-01 | Portal logout → marketing home | Redirect to ERP login |
| L-02 | Backup verifier false negative | 256KB scan + header detection |
| L-03 | Build marker not incremented | v1.0.1 build marker |
| SEC-M01 | Hardcoded password in test-control-db | Env vars + CLI-only |

---

## Not defects

| Item | Note |
|------|------|
| Test 91 support ticket QA write BLOCKED | Tenant-scoped model — by design |
| Production reset not executed | Awaiting explicit approval |

---

## Risk summary

**Overall:** LOW  
**Blocking go-live:** None  
**Blocking v1.0.1 merge:** None (pending approval and deploy checklist)

---

*Known issues for maintenance release v1.0.1.*
