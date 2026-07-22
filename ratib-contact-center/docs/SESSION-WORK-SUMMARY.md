# RATIB Contact Center — Complete Work Summary

This document records everything done across the production readiness audit, hardening pass (Phases A–L), deep verification, and the follow-up fix batch. It is a factual code-and-ops log — not a marketing readiness claim.

**Repository:** `ratib-contact-center/` + Control Panel bridge  
**Date span:** June 2026 session  
**Scope:** Phases 1–7 preserved; no new product phases; no demo/mock data in production paths

---

## Table of contents

1. [Phase 0 — Read-only audit](#phase-0--read-only-audit)
2. [Phase A–L — Production hardening](#phase-al--production-hardening)
3. [Deep verification pass](#deep-verification-pass)
4. [Critical issues found](#critical-issues-found)
5. [Fix batch — recommended order (completed)](#fix-batch--recommended-order-completed)
6. [Deploy observation (GitHub Actions)](#deploy-observation-github-actions)
7. [Files created](#files-created)
8. [Files modified](#files-modified)
9. [Files deleted / archived](#files-deleted--archived)
10. [What still requires server-side work](#what-still-requires-server-side-work)
11. [Related documentation](#related-documentation)

---

## Phase 0 — Read-only audit

A full code-only audit of `ratib-contact-center/` (~128 files) and the Control Panel bridge was performed before any hardening.

### Key findings (pre-hardening)

| Area | Finding |
|------|---------|
| **Migrations** | `migrations/` was empty — no schema in repo |
| **AMI / voice** | No persistent AMI worker; PBX gateway logged only |
| **API security** | Endpoints unauthenticated; tenant/agent IDs trusted from request body |
| **Realtime** | Default mode was polling, not WebSocket |
| **Dialplan** | No Asterisk config in repository |
| **Assets** | Duplicate `rcc-sp.*` vs `rcc-softphone.*` files |
| **Reporting** | No production report service or API |
| **Omnichannel** | Inbound webhooks not secured; outbound not wired |

**Initial production readiness score (code-only):** ~22%

**Deliverable:** `docs/PRODUCTION-READINESS-AUDIT.md`

---

## Phase A–L — Production hardening

A full hardening pass was implemented to close gaps from the audit. Rules: preserve Phases 1–7, no placeholder/mock/demo logic, real integrations only.

### Phase A — Database migrations

Created **9 canonical SQL migrations** (`001`–`009`):

| File | Purpose |
|------|---------|
| `001_core_schema.sql` | Tenants, contacts, calls, settings, migration log |
| `002_security_rbac.sql` | Users, roles, permissions, sessions, API tokens, audit |
| `003_realtime_core.sql` | Realtime events, agent live state |
| `004_ivr_runtime.sql` | IVR flows, nodes, sessions |
| `005_agents_queues.sql` | Agents, queues, queue members |
| `006_softphone_webrtc.sql` | SIP extensions, agent SIP sessions, softphone calls |
| `007_ai_routing_engine.sql` | Routing logs, agent skills |
| `008_omnichannel_conversations.sql` | Conversations, messages, customer identity |
| `009_ai_assistant.sql` | AI context, tickets, report exports |

Added Control Panel migration runner integration via `control_contact_center_run_migrations()` and `contact-center-migrate.php`.

### Phase B — Security

| Component | Path |
|-----------|------|
| Auth context | `app/Core/Security/AuthContext.php` |
| Session / API token auth | `app/Core/Security/SessionAuthService.php` |
| API middleware | `app/Core/Security/ApiAuthMiddleware.php` |
| Webhook HMAC | `app/Core/Security/WebhookSignatureValidator.php` |
| API bootstrap | `public/api/bootstrap-api.php` |

**Behavior:**

- Non-public API actions require authentication (`rcc.access` minimum).
- Webhooks require HMAC (when secret configured) + `tenant_id` query param.
- Public actions: `health`, `webhook_*`, `chat_widget_config`.
- Controllers updated to use `AuthContext::tenantId()` / `agentId()` instead of trusting request body.
- `start_demo` gated behind `rcc.admin.settings`.

### Phase C–D — AMI / PBX

| Component | Path |
|-----------|------|
| AMI config | `config/asterisk.php` |
| AMI client | `app/Infrastructure/Voice/AmiClient.php` |
| AMI command gateway | `app/Infrastructure/Voice/AmiPbxCommandGateway.php` |
| Connection pool | `app/Infrastructure/Voice/AmiConnectionPool.php` |
| AMI adapter | `app/Infrastructure/Voice/AsteriskAmiAdapter.php` |
| Voice worker | `bin/rcc-voice-worker.php` |

`AsteriskPbxCommandGateway` rewired to real AMI (removed error_log-only stub default).

### Phase E — Asterisk dialplan deploy package

`deploy/asterisk/`:

- `extensions_rcc.conf`
- `queues_rcc.conf`
- `pjsip_rcc.conf`
- `rtp_rcc.conf`
- `INSTALL.md`

### Phase F–G — WebRTC transfer + queue delivery

| Change | Detail |
|--------|--------|
| `TransferEngine.php` | AMI blind/attended transfer |
| `QueueDeliveryService.php` | AMI Originate on `CALL_ASSIGNED` |
| `RoutingEngine.php` | Includes `channel_id` in assignment payload |
| `RealtimeOrchestrator.php` | Registers `QueueDeliveryService` subscriber |

### Phase H — Realtime

| Change | Detail |
|--------|--------|
| Default mode | `control_contact_center_realtime_mode()` default → `websocket` |
| Health API | `public/api/v1/health.php` |

### Phase I — Omnichannel

| Component | Path |
|-----------|------|
| Config | `config/omnichannel.php` |
| WhatsApp outbound | `app/Infrastructure/Omnichannel/Channels/WhatsAppOutboundService.php` |
| SMTP outbound | `app/Infrastructure/Omnichannel/Channels/EmailOutboundService.php` |
| IMAP sync | `app/Infrastructure/Omnichannel/Channels/EmailImapSyncService.php` |
| Dispatcher | `app/Infrastructure/Omnichannel/OutboundDispatcher.php` |
| Email sync CLI | `bin/rcc-email-sync.php` |
| Web chat widget | `public/assets/js/rcc-webchat-widget.js` |

### Phase J — Reporting

| Component | Path |
|-----------|------|
| Report service | `app/Application/Services/ReportService.php` |
| Reports API | `public/api/v1/reports.php` (JSON + CSV export metadata) |

Reports: agents, queues, SLA, calls, conversations, AI. CSV export writes to `storage/exports/`.

**Not implemented in this pass:** Control Panel report pages, PDF/Excel export, HTTP download route for exports.

### Phase K — Production audit CLI

`tools/production-audit.php` — scores DB, migrations, AMI, WS hub, security classes, dialplan, outbound, reports.

### Phase L — Cleanup + final report

| Action | Detail |
|--------|--------|
| Deleted duplicates | `rcc-sp.js`, `rcc-sp-ui.js`, `rcc-sp.css` |
| Manifest updated | `config/assets-manifest.php` → canonical `rcc-softphone.*` |
| Schema verify | `control_contact_center_verify_schema()` in CP bridge |
| Final report | `docs/PRODUCTION_READINESS_REPORT.md` (claimed ~82% code readiness) |

**Deliverable:** `docs/PRODUCTION_READINESS_REPORT.md`

---

## Deep verification pass

After hardening, a second pass was requested: “check if there are any missings and errors deeply.”

### What was verified

- **PHP syntax:** All 112 RCC `.php` files pass `php -l` — no parse errors.
- **Production audit CLI:** Ran locally; 64% (DB/AMI/WS not running on dev machine — expected).
- **Auth flow:** Traced bootstrap → controllers → `AuthContext`.
- **Queue events:** Traced `RoutingEngine` → `QueueEngineGateway` → `QueueDeliveryService`.
- **Assets:** Cross-checked manifest, views, `asset.php`, deleted `rcc-sp.*`.
- **Migrations:** Found **16 SQL files** — duplicate legacy set conflicting with canonical `001`–`009`.
- **Deploy logs:** GitHub Actions deploy succeeded; realtime hub step warned (403/500).

### Revised readiness estimate

After deep verification (before fix batch): **~65–70%** code readiness — lower than the 82% report due to logic bugs and migration conflicts.

---

## Critical issues found

### 1. Duplicate conflicting migrations

Two migration sets in `migrations/`:

- **Canonical:** `001_core_schema.sql` … `009_ai_assistant.sql`
- **Legacy:** `001_core_tenancy.sql`, `002_ivr_runtime_engine.sql`, `003_queue_ticket_stub.sql`, etc.

Runner applied **all** `*.sql` alphabetically → schema conflicts and unpredictable FK state.

### 2. Omnichannel webhooks broken

`InboxApiController` called `AuthContext::requirePermission('rcc.inbox.manage')` **before** webhook cases → 403 for all inbound channel traffic despite public bootstrap.

### 3. Webhook HMAC

- Validator returned `false` when secret unset.
- Web chat widget could not sign (no secret in browser).

### 4. Deleted softphone assets still referenced

`softphone-panel.php` and `asset.php` still pointed at deleted `rcc-sp.*` → 404 on standalone desktop and asset fallback.

### 5. Double `QUEUE_ASSIGNED` events

`QueueEngineGateway::onAgentAssigned()` and `QueueDeliveryService` both emitted `QUEUE_ASSIGNED` → duplicate UI events / possible double AMI originate.

### 6. Agent ID fallback to `1`

`ApiAuthMiddleware::resolveAgentForControlUser()` returned `1` when email unmapped → wrong agent context.

### 7. Realtime hub deploy failures

| Symptom | Cause |
|---------|--------|
| HTTP 403 on CP hub endpoint | Migrate token only uploaded to `rateb-erp/storage/`; RCC looked only in `ratib-contact-center/storage/` |
| HTTP 500 on `run-realtime-hub.php` | Wrong path to `control-panel/includes/config.php` |

### 8. Other gaps (documented, not all fixed in first batch)

| Gap | Status |
|-----|--------|
| No seed data | Fixed in fix batch (`010_seed_production.sql`) |
| No CP report pages | Still open |
| Hold/resume DB-only (no AMI Hold) | Still open |
| `close` used body `agent_id` | Fixed in fix batch |
| ERP Arabic repair class missing on deploy | ERP-side warning, unrelated to RCC |

---

## Fix batch — recommended order (completed)

All seven recommended fixes plus realtime hub deploy fixes were applied in code.

### 1. Archive legacy migrations

**Moved to `migrations/archive/`:**

- `001_core_tenancy.sql`
- `002_ivr_runtime_engine.sql`
- `003_queue_ticket_stub.sql`
- `004_ivr_example_flow.sql`
- `005_realtime_core.sql`
- `006_softphone.sql`
- `010_rcc_tickets_ai_columns.sql`

**Active migrations (10 files):** `001`–`009` + new `010_seed_production.sql`

**Runner:** `control_contact_center_run_migrations()` filters to top-level `migrations/*.sql` only (excludes `archive/`).

### 2. Fix `InboxApiController`

- `health` and `chat_widget_config` remain public (no auth).
- `webhook_whatsapp`, `webhook_email`, `webhook_chat` handled **before** `AuthContext::requirePermission`.
- Webhooks use `TenantContext` set by `bootstrap-api.php`.
- `close` uses authenticated `AuthContext::agentId()` (not request body).

### 3. Fix webhook signing

`WebhookSignatureValidator.php`:

- Allows unsigned webhooks when secret is unset.
- Treats `CHANGE_ME` / `CHANGE_ME_WEBHOOK_SECRET` as unset.
- Production should set real `WEBHOOK_SIGNING_SECRET` or `RCC_WEBHOOK_SECRET` for provider webhooks.

`rcc-webchat-widget.js`: documented that browser cannot hold HMAC secret; server accepts when secret not configured.

### 4. Fix asset references

| File | Change |
|------|--------|
| `views/components/softphone-panel.php` | `rcc-softphone.css`, `rcc-softphone.js`, `rcc-softphone-ui.js` |
| `public/asset.php` | RTL/bidi aliases map to `rcc-softphone.*` (not deleted `rcc-sp.*`) |

### 5. Deduplicate queue delivery

Removed second `QUEUE_ASSIGNED` emit from `QueueDeliveryService.php`. AMI originate runs on `CALL_ASSIGNED`; UI notification stays on `QueueRealtimeService::onAgentAssigned()`.

### 6. Remove agent-id-1 fallback

`ApiAuthMiddleware::resolveAgentForControlUser()`:

- Checks `$_SESSION['rcc_agent_id']` first.
- Resolves by CP user email → `rcc_agents`.
- Returns `0` if unmapped → auth fails (no default agent #1).

### 7. Seed migration

**New:** `migrations/010_seed_production.sql` (idempotent `INSERT IGNORE`):

| Entity | Values |
|--------|--------|
| Tenant | `id=1`, code `rateb` |
| User | `agent@rateb.sa` (placeholder bcrypt hash — change on first login) |
| Role | Agent role for user |
| Agent | `id=1`, extension `1001` |
| Queue | `support`, SLA 300s |
| Queue member | Agent 1 on queue 1 |
| SIP extension | `1001` placeholders (`RCC_SIP_EXT_1001`, `pbx.rateb.sa`) |
| Settings | softphone auto-answer off, realtime websocket |

### 8. Realtime hub deploy fixes (bonus)

| File | Change |
|------|--------|
| `contact-center-bridge.php` | Added `control_contact_center_migrate_token_expected()` and `control_contact_center_verify_migrate_token()` |
| Token paths | RCC storage **and** `rateb-erp/storage/deploy-migrate-token` (matches deploy upload) |
| `rcc-realtime-hub-run.php` | Uses shared verify helper |
| `run-realtime-hub.php` | Fixed config path (`dirname(__DIR__, 2)/control-panel/...`); uses shared verify helper |

---

## Deploy observation (GitHub Actions)

**Run:** `update-20260622-211349` (#1493) — **Succeeded** in ~35s.

| Step | Result |
|------|--------|
| ERP migrations | OK — no new migrations |
| RCC realtime hub | Warning — 403 on CP endpoint, 500 on public endpoint (fixed in code for next deploy) |
| LiteSpeed purge | 200 |

**Note:** Hub warning does not fail the deploy job. Inbox works with polling fallback until hub listens on port 9702.

**After next push:** Hub HTTP auth should pass; port may still need cPanel cron if `proc_open`/`shell_exec` disabled on shared hosting.

---

## Files created

### Migrations
- `migrations/001_core_schema.sql` … `009_ai_assistant.sql`
- `migrations/010_seed_production.sql`
- `migrations/archive/README.md`

### Security
- `app/Core/Security/AuthContext.php`
- `app/Core/Security/SessionAuthService.php`
- `app/Core/Security/ApiAuthMiddleware.php`
- `app/Core/Security/WebhookSignatureValidator.php`
- `public/api/bootstrap-api.php`

### Voice / AMI
- `config/asterisk.php`
- `app/Infrastructure/Voice/AmiClient.php`
- `app/Infrastructure/Voice/AmiPbxCommandGateway.php`
- `app/Infrastructure/Voice/AmiConnectionPool.php`
- `bin/rcc-voice-worker.php`

### Deploy / Asterisk
- `deploy/asterisk/extensions_rcc.conf`
- `deploy/asterisk/queues_rcc.conf`
- `deploy/asterisk/pjsip_rcc.conf`
- `deploy/asterisk/rtp_rcc.conf`
- `deploy/asterisk/INSTALL.md`

### Services / APIs
- `app/Application/Services/QueueDeliveryService.php`
- `app/Application/Services/ReportService.php`
- `public/api/v1/health.php`
- `public/api/v1/reports.php`
- `config/omnichannel.php`
- Omnichannel outbound services + `OutboundDispatcher.php`
- `bin/rcc-email-sync.php`
- `public/assets/js/rcc-webchat-widget.js`
- `tools/production-audit.php`

### Documentation
- `docs/PRODUCTION-READINESS-AUDIT.md`
- `docs/PRODUCTION_READINESS_REPORT.md`
- `docs/SESSION-WORK-SUMMARY.md` (this file)

---

## Files modified

### Controllers
- `app/Controllers/Api/InboxApiController.php` — webhooks, close agent, auth order
- `app/Controllers/Api/SoftphoneApiController.php` — AuthContext usage
- `app/Controllers/Api/AiAssistantApiController.php` — AuthContext usage

### Core / orchestration
- `app/Application/Services/RealtimeOrchestrator.php` — QueueDeliveryService registration
- `app/Application/Services/QueueDeliveryService.php` — removed duplicate emit
- `app/Domain/Routing/AI/RoutingEngine.php` — channel_id in payload
- `app/Domain/Softphone/TransferEngine.php` — AMI transfers
- `app/Infrastructure/Voice/AsteriskPbxCommandGateway.php` — real AMI

### Control Panel bridge
- `control-panel/includes/control/contact-center-bridge.php` — realtime mode default, schema verify, migrate token helpers, migration filter
- `control-panel/pages/control/contact-center-migrate.php`
- `control-panel/pages/control/contact-center-app.php`
- `control-panel/api/control/rcc-realtime-hub-run.php`

### Views / assets
- `views/components/softphone-panel.php`
- `public/asset.php`
- `config/assets-manifest.php`
- `public/assets/js/rcc-webchat-widget.js`
- `public/run-realtime-hub.php`

### Environment
- `env.ratib-contact-center.example` — AMI, omnichannel, realtime vars

---

## Files deleted / archived

### Deleted (duplicate assets)
- `public/assets/js/rcc-sp.js`
- `public/assets/js/rcc-sp-ui.js`
- `public/assets/css/rcc-sp.css`

### Archived (legacy migrations → `migrations/archive/`)
- `001_core_tenancy.sql`
- `002_ivr_runtime_engine.sql`
- `003_queue_ticket_stub.sql`
- `004_ivr_example_flow.sql`
- `005_realtime_core.sql`
- `006_softphone.sql`
- `010_rcc_tickets_ai_columns.sql`

---

## What still requires server-side work

These are **not** code gaps alone — they need production configuration and daemons.

| Task | Action |
|------|--------|
| **Run RCC migrations** | Control Panel → Contact Center → Database setup (applies `010_seed_production.sql` on fresh DB) |
| **MySQL** | `RATIB_CC_DB_*` in server `.env` |
| **AMI worker** | `php bin/rcc-voice-worker.php` as persistent process / systemd |
| **Realtime hub** | `php bin/rcc-realtime-hub.php` or cron per `bin/REALTIME-HUB-RUN.txt` |
| **Asterisk** | Install `deploy/asterisk/*`, AMI credentials, PJSIP/WebRTC |
| **SIP extensions** | Configure `rcc_sip_extensions` + env password refs |
| **Webhooks** | Set `WEBHOOK_SIGNING_SECRET` for WhatsApp/email providers |
| **WhatsApp / SMTP / IMAP** | Env vars in `env.ratib-contact-center.example` |
| **CP report UI** | Optional — API exists at `public/api/v1/reports.php` |
| **Supervisor dashboard** | Permission exists; no CP page yet |

### Suggested cPanel cron (realtime hub)

```bash
*/5 * * * * pgrep -f rcc-realtime-hub.php || bash /home/.../public_html/ratib-contact-center/bin/start-realtime-hub.sh
```

---

## Related documentation

| Document | Purpose |
|----------|---------|
| `docs/PRODUCTION-READINESS-AUDIT.md` | Original read-only audit (pre-hardening) |
| `docs/PRODUCTION_READINESS_REPORT.md` | Post-hardening report (~82% claim) |
| `docs/SESSION-WORK-SUMMARY.md` | This file — full session log |
| `deploy/asterisk/INSTALL.md` | PBX install steps |
| `env.ratib-contact-center.example` | Server environment template |

---

## Quick timeline

```
1. Audit (read-only)           → PRODUCTION-READINESS-AUDIT.md, score ~22%
2. Hardening A–L               → migrations, security, AMI, dialplan, omnichannel, reports
3. Deep verification         → found logic bugs, migration conflicts, asset 404s
4. Fix batch (7 + hub)       → webhooks, assets, queue dedup, seed, token path, hub 500
5. Deploy #1493 (Jun 22)     → succeeded; hub warning (addressed in code for next push)
```

---

*Generated from the RCC production readiness and hardening session. Update this file when new RCC production changes land.*
