# Phase 8 — Live Production Deployment (Operations Center)

Production Operations layer for multi-tenant go-live: PBX, SIP, queues, IVR, agents, diagnostics, realtime hub, and checklist — integrated with Control Panel, RBAC, EventBus, and WebSocket.

## Access

| Surface | URL |
|---------|-----|
| Control Panel | `/control-panel/pages/control/contact-center-ops.php?control=1&route=health` |
| API | `/ratib-contact-center/public/api/v1/ops.php?action=...` |
| Sidebar | **Contact Center → Operations Center** |

**RBAC:** Requires `rcc.ops.view` (CP admins receive full ops permissions via `ApiAuthMiddleware` bridge).

## Migration

Run after 001–010:

```
migrations/011_production_ops.sql
```

Creates:

- `rcc_pbx_servers` — per-tenant PBX/AMI config (secrets via env ref, not plain text)
- `rcc_ops_checklist_steps` — system go-live steps
- `rcc_ops_checklist_status` — per-tenant progress
- New permissions: `rcc.ops.*`, `rcc.tenants.manage`

## Modules (10)

| # | Module | Route | API actions |
|---|--------|-------|-------------|
| 1 | PBX deployment wizard | `pbx` | `pbx_list`, `pbx_save`, `pbx_test`, `pbx_activate` |
| 2 | SIP extension manager | `sip` | `sip_list`, `sip_save`, `sip_delete` |
| 3 | Queue manager | `queues` | `queue_list`, `queue_save`, `queue_members_save` |
| 4 | IVR production builder | `ivr` | `ivr_list`, `ivr_save`, `ivr_publish` |
| 5 | Agent provisioning wizard | `agents` | `agent_list`, `agent_provision` |
| 6 | WebRTC diagnostic panel | `webrtc` | `diag_webrtc` |
| 7 | AMI diagnostic panel | `ami` | `diag_ami` |
| 8 | Realtime hub monitor | `hub` | `hub_status`, `hub_start` |
| 9 | Health center dashboard | `health` | `health_center` |
| 10 | Go-live checklist manager | `golive` | `checklist_list`, `checklist_update`, `checklist_auto_verify` |

## Architecture

```
Control Panel (contact-center-ops.php)
    └── ops-center-embed.php + rcc-ops-center.js
            └── POST ops.php (bootstrap-api auth)
                    └── OpsApiController
                            ├── OpsPbxService
                            ├── OpsProvisioningService
                            ├── OpsDiagnosticService
                            └── OpsChecklistService
                                    └── OpsAuditService → rcc_audit_logs
                                    └── EventBus → WebSocket (OPS_* events)
```

## EventBus types (Phase 8)

- `OPS_PBX_UPDATED`, `OPS_PBX_ACTIVATED`
- `OPS_SIP_UPDATED`, `OPS_QUEUE_UPDATED`
- `OPS_IVR_UPDATED`, `OPS_IVR_PUBLISHED`
- `OPS_AGENT_PROVISIONED`
- `OPS_DIAGNOSTIC_RUN`
- `OPS_CHECKLIST_UPDATED`, `OPS_CHECKLIST_AUTO_VERIFY`
- `OPS_AUDIT_LOGGED`, `OPS_HEALTH_UPDATED`

## Multi-tenant

- Tenant from `AuthContext::tenantId()` (session / CP bridge).
- Admins with `rcc.tenants.manage` may pass `tenant_id` in API body.
- `control_contact_center_resolve_tenant_id()` — session first, else first active tenant (no hardcoded ID).

## Realtime

- `rcc-ops-center.js` subscribes to `RccRealtimeClient` when `data-ws` is a WebSocket URL.
- Listens for `OPS_*` events; refreshes health/hub/golive panels live.
- No polling when WebSocket is available (`RCC_REALTIME_MODE=websocket`).

## Security

- All ops actions require authenticated CP session (cookie bridge) or API token.
- PBX AMI secrets stored as **env reference** (`ami_secret_ref`), not in DB plaintext.
- Every mutating action writes `rcc_audit_logs` via `OpsAuditService`.
- Permission checks per action (`rcc.ops.pbx`, `rcc.ops.sip`, etc.).

## Files added

### Migration
- `migrations/011_production_ops.sql`

### Backend
- `app/Application/Contracts/QueueGatewayInterface.php` (autoload fix)
- `app/Application/Services/Ops/*`
- `app/Infrastructure/Persistence/Repositories/Ops/*`
- `app/Controllers/Api/OpsApiController.php`
- `public/api/v1/ops.php`

### Control Panel
- `control-panel/pages/control/contact-center-ops.php`
- Bridge: `control_contact_center_ops_page_url()`, `ops_api_url()`, `resolve_tenant_id()`
- Sidebar + i18n (en/ar)

### Frontend
- `views/components/ops-center-embed.php`
- `public/assets/js/rcc-ops-center.js`
- `public/assets/css/rcc-ops-center.css`
- `config/assets-manifest.php` keys: `ops-css`, `ops-js`

## Go-live workflow

1. Run migrations through **Database setup** (includes 011).
2. Open **Operations Center → Health Center** — target ≥ 70% before telephony.
3. **PBX Wizard** — save server, test AMI, activate.
4. **Agent Provisioning** — create agents (+ optional SIP).
5. **Queues** + **IVR Builder** — publish flow.
6. **Go-Live Checklist** — run **Auto-verify**, resolve failures.
7. Start **Realtime Hub** when using WebSocket mode.

## Production readiness note

Phase 8 delivers the **operations UI and APIs**. Live telephony still requires:

- Asterisk + AMI on server
- Env secrets (`RCC_AMI_PASS`, SIP password refs)
- Voice worker + realtime hub processes
- Running migration 011 on production DB

After server configuration + checklist pass, re-run `tools/production-audit.php` and `tools/verify-production-evidence.php`.
