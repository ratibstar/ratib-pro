# Phase 3 — Reduced MVP Home

## ERP thin adapters

| Method | Path | Service |
|--------|------|---------|
| GET | `/api/v1/hr/me` | `HrEssEmployeeResolverService` (existing) |
| GET | `/api/v1/hr/attendance/today` | `HrService::findAttendanceByEmployeeDate` |
| GET | `/api/v1/hr/leave/balances` | `HrService::leaveBalancesForEmployee` |
| GET | `/api/v1/hr/notifications` | `NotificationService::listForUser` |

Query params (pass-through only): `employee_id`, `date`, `year` where required.

## Home widgets

Employee name · Today's attendance · Leave balance · Recent notifications · Quick actions

## Out of MVP

Company name · Pending leave · Photo · KPIs · Charts · Payroll · Approvals · Histories

## Stop

Phase 3 ends here. Do not implement Attendance / Leave / Notifications modules.
