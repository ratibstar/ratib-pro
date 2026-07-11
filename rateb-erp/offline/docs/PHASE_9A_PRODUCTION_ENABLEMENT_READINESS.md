# Phase 9A — Production Enablement Readiness

**Date:** 2026-07-11  
**Mode:** Readiness only — **no flag enablement**, no runtime/schema changes  
**Target:** `https://rateb.sa/rateb-erp/public/admin/`  
**Filesystem:** `/home/admin/domains/rateb.sa/public_html/rateb-erp`  
**Database:** `admin_rateb-erp`

## Decision

**READY FOR PILOT** (dormant posture verified; do not enable flags until Phase 9 pilot start is explicitly approved).

## Evidence summary

| Check | Result |
|-------|--------|
| Serving DocumentRoot | `/home/admin/domains/rateb.sa/public_html` |
| Dual path A vs B | **Identical** (same inode `520997:2049`) |
| Build | `20260710-pos-approval-schema-v7` |
| SDK | Phase **5.0.0** + 4.5.1 `removeMany` |
| Phase 7.1 hardening | Present (device gate, replay scope, auto_process) |
| Monitoring | Service + routes + ops view present; flag OFF |
| Migrations 175–179 | Applied in `rateb_migrations` + tables PRESENT |
| Flags | No `RATEB_OFFLINE_*` in `.env`; runtime master/monitoring OFF |
| POS | Present |
| Rollback SQL on server | **Missing** (present in certified repo) — sync before R3 |
| Phase 8 doc on server | **Missing** (present in certified repo) |

## Hash note

Raw Windows vs Linux SHA256 may differ due to CRLF; LF-normalized hashes for key 7.1/flag/SDK/migration files **match** production.
