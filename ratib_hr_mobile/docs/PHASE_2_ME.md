# Phase 2 — User → Employee Resolver

## ERP source

Existing linkage: `rateb_employees.user_id` → `rateb_users.id`

Thin read endpoint (no new tables / no write rules):

`GET /api/v1/hr/me` (Bearer token)

| Result | HTTP | code |
|--------|------|------|
| Exactly one employee | 200 | success + employee |
| None | 404 | `employee_unbound` |
| More than one | 409 | `employee_ambiguous` |

## Mobile

1. After ERP auth → `MePort.currentEmployee()`
2. Bind `EmployeeContext` (session-only)
3. Auth fails closed if unbound/ambiguous (token cleared)
4. Router requires `EmployeeContext.isResolved`
5. Future HR features must call `EmployeeContext.requireResolved()`

## Stop

Phase 2 ends here. Do not implement Home Dashboard (Phase 3).
