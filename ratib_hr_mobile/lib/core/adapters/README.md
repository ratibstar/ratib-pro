# Adapters

Thin adapters that implement `lib/core/contracts/*` by calling **existing** RATIB ERP endpoints/services.

## Rules

- No business logic
- No new validation
- No new queues
- No feature imports
- Map UI payloads ↔ ERP fields only

## Implemented

| Adapter | ERP |
|---------|-----|
| `ErpAuthAdapter` | `POST /api/v1/auth/token` |
| `ErpMeAdapter` | `GET /api/v1/hr/me` |
| `ErpAttendanceAdapter` | `GET /api/v1/hr/attendance/today` |
| `ErpLeaveAdapter` | `GET /api/v1/hr/leave/balances` |
| `ErpNotificationAdapter` | `GET /api/v1/hr/notifications` |
| `ErpMobileConfigAdapter` | `GET /api/mobile/config` |
| `SharedPreferencesCacheStore` | local presentation cache |
