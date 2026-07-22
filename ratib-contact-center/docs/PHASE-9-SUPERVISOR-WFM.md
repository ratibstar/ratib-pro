# Phase 9 — Supervisor & Workforce Management

Production supervisor operations suite for RATEB Contact Center.

## Entry points

| Layer | URL |
|-------|-----|
| Control Panel | `/control-panel/pages/control/contact-center-supervisor.php?control=1&route=dashboard` |
| API | `/ratib-contact-center/public/api/v1/supervisor.php?action=...` |

**RBAC:** Requires `rcc.supervisor.view` or legacy `rcc.supervisor.dashboard`. CP admins receive full `rcc.supervisor.*` permissions.

## Modules (12)

| Route | Module | API action |
|-------|--------|------------|
| `dashboard` | Supervisor Dashboard | `dashboard_summary` |
| `wallboard` | Live Wallboard | `wallboard` |
| `queues` | Queue Monitor | `queue_monitor` |
| `agents` | Agent Monitor | `agent_monitor` |
| `sla` | SLA Dashboard | `sla_dashboard` |
| `wfm` | Workforce Management | `wfm_overview` |
| `shifts` | Shift Planner | `shift_list`, `shift_save`, `shift_assign`, `shift_assignments` |
| `attendance` | Attendance Tracking | `attendance_list`, `attendance_clock_in`, `attendance_clock_out` |
| `breaks` | Break Management | `break_list`, `break_start`, `break_end` |
| `occupancy` | Occupancy Monitoring | `occupancy` |
| `adherence` | Schedule Adherence | `adherence` |
| `alerts` | Supervisor Alerts | `alert_list`, `alert_acknowledge`, `alert_rules_*` |

Reports: `report` action (types: agents, queues, sla, calls, conversations, ai) — requires `rcc.supervisor.reports`.

## Database (migration 012)

- `rcc_wfm_shifts` — shift templates
- `rcc_wfm_shift_assignments` — agent ↔ shift per date
- `rcc_wfm_attendance` — clock in/out, adherence status
- `rcc_wfm_breaks` — break sessions
- `rcc_supervisor_alerts` — persisted alerts
- `rcc_supervisor_alert_rules` — auto-alert rules (SLA red, no agents, long break)

Permissions: `rcc.supervisor.*` (11 granular + view). Supervisor role (id=2) seeded in migration.

## Architecture

```
Control Panel (contact-center-supervisor.php)
    └── supervisor-center-embed.php + rcc-supervisor-center.js
            └── POST supervisor.php (bootstrap-api auth)
                    └── SupervisorApiController
                            ├── SupervisorDashboardService
                            ├── SupervisorMonitorService
                            ├── SupervisorSlaService
                            ├── SupervisorWfmService
                            ├── SupervisorAlertService
                            └── ReportService (reports)
                                    └── EventBus → WebSocket (SUPERVISOR_*, AGENT_*, QUEUE_*, SLA_*)
```

`SupervisorAlertBridge` subscribes to `SLA_ALERT` and `QUEUE_SNAPSHOT` at boot (`RealtimeOrchestrator`) and persists supervisor alerts.

## Realtime

- `rcc-supervisor-center.js` uses `RccRealtimeClient` when WebSocket URL is set (no polling for live updates).
- Rooms: `tenant:{id}`, `dashboard:{id}`, `supervisor:{id}`.
- Listens for `SUPERVISOR_*`, `AGENT_*`, `QUEUE_*`, `SLA_*` events.

## Event types (Phase 9)

- `SUPERVISOR_DASHBOARD_UPDATED`
- `SUPERVISOR_WALLBOARD_UPDATED`
- `SUPERVISOR_SLA_UPDATED`
- `SUPERVISOR_SHIFT_UPDATED`
- `SUPERVISOR_ATTENDANCE_UPDATED`
- `SUPERVISOR_BREAK_STARTED` / `SUPERVISOR_BREAK_ENDED`
- `SUPERVISOR_OCCUPANCY_UPDATED`
- `SUPERVISOR_ADHERENCE_UPDATED`
- `SUPERVISOR_ALERT_RAISED` / `SUPERVISOR_ALERT_ACKNOWLEDGED`
- `SUPERVISOR_AUDIT_LOGGED`

## Multi-tenant

- `control_contact_center_resolve_tenant_id()` in CP shell.
- API `tenants_list` + tenant switcher for CP admins (`rcc.tenants.manage`).

## Deploy

- `control-panel/pages/control/contact-center-supervisor.php` in `FAST_FILES` / `CRITICAL_FILES`.
- Full `ratib-contact-center/` tree deploys on RCC changes.
- Run migration **012** via CP Database setup after deploy.

## Production readiness target

Code layer ~90%+ after migration 012 applied. Live score depends on AMI, realtime hub, and agent presence data on server.

## Key files

- `migrations/012_supervisor_workforce.sql`
- `app/Controllers/Api/SupervisorApiController.php`
- `app/Application/Services/Supervisor/*`
- `app/Infrastructure/Persistence/Repositories/Supervisor/*`
- `public/api/v1/supervisor.php`
- `control-panel/pages/control/contact-center-supervisor.php`
- `views/components/supervisor-center-embed.php`
- `public/assets/js/rcc-supervisor-center.js`
- `public/assets/css/rcc-supervisor-center.css`
