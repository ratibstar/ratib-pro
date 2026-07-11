# Phase 4 — HR Offline (Tier 1)

**Date:** 2026-07-11  
**Scope:** HR offline only — attendance, attendance bulk, leave drafts, employee directory delta  
**Out of scope:** Payroll, payroll posting/calculation, leave approvals, financial posting, Procurement, Designed/

---

## Repository audit

### Additive / offline-local changes

| Area | Change |
|------|--------|
| Replay adapter | `HrOfflineReplayService` — mirrors HR controller write paths via `AttendanceRecord` / `LeaveRequest` + `HrService::bootstrapTenant()` |
| Tenant/branch guard | `HrOfflineTenantGuard` |
| Employee directory delta | `HrOfflineEmployeeDirectoryService` (excludes salary fields) |
| Queue / engine | Accepts `module=hr` when `offline.hr.attendance` on |
| Conflict resolver | `resolveHr()` — LWW + `status_changed` / `attendance_conflict` |
| Client adapter | `hr-adapter.js` — attendance / bulk / leave draft / directory pull |
| SDK | `rateb-offline.js` v4.0.0 |
| Tests | `HrOfflinePhase4Test` |

### Explicit non-changes

- No edits to `HrService` payroll/approve methods, HR controllers, or schema
- No leave approvals (`approveLeave` / `rejectLeave` never called)
- No payroll / financial posting
- Flag `offline.hr.attendance` defaults **false**

### Architecture compliance

| Rule | Status |
|------|--------|
| HR only (Tier 1) | Pass |
| Replay via existing HR domain | Pass |
| No business logic duplication | Pass |
| Idempotent replay (`[offline:key]`) | Pass |
| Branch + tenant isolation | Pass |
| Zero data loss (ack / selective clear) | Pass |
| Flag default OFF | Pass |

---

## Security audit

| Control | Status | Notes |
|---------|--------|-------|
| Master + HR flags required | Pass | |
| Push ack + selective clear | Pass | |
| Payload sanitizer | Pass | |
| Tenant/branch on employee | Pass | |
| Authz allows `hr` ability | Pass | |
| Directory excludes `salary_base` | Pass | |
| No approvals / payroll offline | Pass | |
| Procurement still rejected | Pass | |

**Residual (Medium):** staging soak for bulk attendance multi-branch; leave drafts remain `pending` until online approval.

---

## Test report

```bash
php offline/tests/run-offline-foundation-tests.php
php offline/tests/run-inventory-offline-tests.php
php offline/tests/run-hr-offline-tests.php
```

| Suite | Result |
|-------|--------|
| Foundation | **26/26 PASS** |
| Inventory Phase 3 | **33/33 PASS** |
| HR Phase 4 | **30/30 PASS** |

---

## Performance report

| Benchmark | Iterations |
|-----------|------------|
| Ack contract | 5,000 |
| HR conflict resolver | 2,000 |
| Sanitizer | 1,000 |

---

## Production readiness score

| Dimension | Score | Weight |
|-----------|-------|--------|
| Functional completeness | 8.0 | 25% |
| Architecture | 9.0 | 20% |
| Sync integrity | 9.0 | 20% |
| Conflict / multi-branch | 8.5 | 15% |
| Test depth | 8.0 | 10% |
| Security | 8.5 | 10% |

**Weighted: 8.5 / 10 — CONDITIONAL GO** for staging

```bash
RATEB_OFFLINE_ENABLED=1
RATEB_OFFLINE_HR_ATTENDANCE=1
```
