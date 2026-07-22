# RATEB Contact Center — Production Readiness Audit

**Date:** 2026-06-22  
**Scope:** `ratib-contact-center/` + Control Panel bridge (`control-panel/includes/control/contact-center-bridge.php`, hub/migrate/app pages)  
**Method:** Code-only findings. Nothing marked working unless executable integration exists in the repo.

**Implemented phases audited:** Phase 1 Core Foundation · Phase 2 IVR Runtime · Phase 3 Realtime Core · Phase 4 WebRTC Softphone · Phase 5 AI Routing · Phase 6 Omnichannel Inbox · Phase 7 AI Copilot · Phase 8 Production Operations

> **Update (post-hardening):** Migrations `001`–`011`, AMI voice worker, Asterisk dialplan package, API auth, and Phase 8 Ops Center are now in the repo. Items below reflect the **original** audit baseline; see `PRODUCTION_READINESS_REPORT.md` and `PHASE-8-PRODUCTION-OPS.md` for current status.

---

## Executive Summary

The project has **substantial PHP/JS domain logic** across Phases 1–7 (IVR engine, EventBus, softphone SDK, routing, inbox, AI copilot). It is **not production-ready for real inbound phone calls** because:

1. **`ratib-contact-center/migrations/` contains zero SQL files** — schema cannot be created from the repo.
2. **No AMI/ARI runtime worker** — telephony events never reach `AsteriskAmiAdapter`.
3. **No Asterisk dialplan/AGI assets** (`rcc-ivr`, `rcc-tts.php`) in the repo.
4. **AMI outbound defaults to `error_log()`** when no sender is wired.
5. **Public APIs have no authentication** — `tenant_id` / `agent_id` are client-supplied.
6. **Default realtime mode is polling**; softphone has **no polling fallback** for call events.
7. **WhatsApp/Email outbound is DB-only** — no provider send integration.

---

## Module Audit

### 1. Database Layer

| Field | Value |
|-------|-------|
| **STATUS** | **BROKEN** |
| **FILES** | `app/Core/Database.php`, `config/database.php`, 12 repositories under `app/Infrastructure/Persistence/Repositories/`, `control-panel/includes/control/contact-center-bridge.php` (`control_contact_center_run_migrations`), `control-panel/pages/control/contact-center-migrate.php` |
| **DEPENDENCIES** | MySQL DB `admin_call-center` (configurable), `RATIB_CC_DB_*` in `.env`, migration SQL files |
| **ISSUES** | `ratib-contact-center/migrations/` is **empty** (0 `.sql` files). Migrate UI references `001`–`009` but nothing to apply. All repositories assume `rcc_*` tables. `.gitignore` allows `!ratib-contact-center/migrations/*.sql` but no files exist. |
| **RISK** | **CRITICAL** |

---

### 2. Multi-Tenant Layer

| Field | Value |
|-------|-------|
| **STATUS** | **PARTIAL** |
| **FILES** | `app/Core/TenantContext.php`, tenant-scoped queries in all repositories, `config/env.php` |
| **DEPENDENCIES** | `rcc_tenants` table (missing), tenant rows in DB |
| **ISSUES** | Runtime isolation is request-scoped via `TenantContext::set()`. Agent Desktop hardcodes `$tenantId = 1; $agentId = 1` in `control-panel/pages/control/contact-center-app.php`. No tenant resolution from auth/session. |
| **RISK** | **HIGH** |

---

### 3. IVR Runtime Engine

| Field | Value |
|-------|-------|
| **STATUS** | **PARTIAL** |
| **FILES** | `app/Domain/IVR/IvrEngine.php`, `IvrSession.php`, `IvrFlow.php`, `IvrNode.php`, `NodeExecutors/*`, `app/Application/Services/IvrSessionManager.php`, `IvrStateStreamer.php`, `docs/PHASE-2-IVR-RUNTIME.md` |
| **DEPENDENCIES** | DB (`rcc_ivr_flows`, `rcc_ivr_nodes`, `rcc_ivr_sessions`, `rcc_calls`), AMI events, `AsteriskPbxCommandGateway`, active IVR flows per tenant |
| **ISSUES** | State machine logic is complete in PHP. **No inbound trigger** without AMI worker. PBX commands log-only by default. No seed/example flow SQL in repo (docs reference `004_ivr_example_flow.sql` — missing). |
| **RISK** | **CRITICAL** |

---

### 4. Asterisk Integration

| Field | Value |
|-------|-------|
| **STATUS** | **NOT CONNECTED** |
| **FILES** | `app/Infrastructure/Voice/AsteriskPbxCommandGateway.php`, `AsteriskAmiAdapter.php`, `app/Application/Contracts/PbxCommandGatewayInterface.php` |
| **DEPENDENCIES** | Asterisk server, dialplan contexts `rcc-ivr-*`, `rcc-tenant-{id}`, AGI `rcc-tts.php`, AMI credentials |
| **ISSUES** | No dialplan, extensions, or AGI scripts in repo. `AsteriskPbxCommandGateway::send()` falls back to `error_log('[RCC AMI] ...')` when `$amiSender` is null (default). Docs reference `bin/rcc-voice-worker.php` — **file does not exist**. |
| **RISK** | **CRITICAL** |

---

### 5. AMI Integration

| Field | Value |
|-------|-------|
| **STATUS** | **PARTIAL / NOT CONNECTED** |
| **FILES** | `AsteriskAmiAdapter.php`, `AsteriskPbxCommandGateway.php` |
| **DEPENDENCIES** | Long-lived AMI TCP client, event loop calling `AsteriskAmiAdapter::dispatch()` |
| **ISSUES** | Adapter handles `Newchannel`, `DTMF`, `Hangup`, `BridgeEnter`, transfers — but **nothing in repo connects to AMI**. No AMI host/port/user config usage in runtime code. |
| **RISK** | **CRITICAL** |

---

### 6. ARI Integration

| Field | Value |
|-------|-------|
| **STATUS** | **NOT CONNECTED** |
| **FILES** | `StasisStart` case in `AsteriskAmiAdapter.php` only; comment in `PbxCommandGatewayInterface.php` |
| **DEPENDENCIES** | Asterisk ARI HTTP/WebSocket, Stasis apps |
| **ISSUES** | **No ARI client, no Stasis application, no channel control via ARI.** Docs list ARI as future work. |
| **RISK** | **HIGH** |

---

### 7. Queue Engine

| Field | Value |
|-------|-------|
| **STATUS** | **PARTIAL** |
| **FILES** | `app/Infrastructure/Queue/QueueEngineGateway.php`, `app/Application/Contracts/TicketGatewayInterface.php` (contains `QueueGatewayInterface`), `app/Domain/Queue/QueueRealtimeService.php`, `app/Domain/IVR/NodeExecutors/RouteCallExecutor.php` |
| **DEPENDENCIES** | `rcc_queues`, `rcc_calls`, `RoutingEngine`, Asterisk `Queue()` app |
| **ISSUES** | PHP enqueues in DB + runs AI routing + emits `QUEUE_ASSIGNED`. Asterisk queue join via AMI `Exec Queue` only if AMI sender works. No PHP `Originate` to ring agent. `RCC_PREFERRED_AGENT_ID` set as channel var — requires dialplan support not in repo. |
| **RISK** | **CRITICAL** |

---

### 8. Routing Engine (AI)

| Field | Value |
|-------|-------|
| **STATUS** | **PARTIAL** |
| **FILES** | `app/Domain/Routing/AI/RoutingEngine.php`, `RoutingContext.php`, `RoutingDecision.php`, `AgentScoringEngine.php`, `QueueScoringEngine.php`, `SkillMatcher.php`, `SlaPredictor.php`, `ErpContextAnalyzer.php`, `RoutingLogRepository.php`, `config/routing.php` |
| **DEPENDENCIES** | `rcc_agent_skills`, `rcc_queues`, `rcc_agent_live_state`, `rcc_calls`, ERP contact data |
| **ISSUES** | Rule-based scoring (not ML). Works in-process **if DB populated**. Does not itself connect calls to agents on PBX. Logs to `rcc_routing_log` (table missing without migrations). |
| **RISK** | **MEDIUM** (logic) / **CRITICAL** (telephony binding) |

---

### 9. Realtime Hub

| Field | Value |
|-------|-------|
| **STATUS** | **PARTIAL** |
| **FILES** | `bin/rcc-realtime-hub.php`, `bin/start-realtime-hub.sh`, `bin/rcc-realtime-hub.service`, `app/Infrastructure/Realtime/RealtimeHubClient.php`, `public/run-realtime-hub.php`, `control-panel/api/control/rcc-realtime-hub-run.php`, `scripts/run-rcc-realtime-hub.py` |
| **DEPENDENCIES** | CLI PHP, TCP :9701, WebSocket :9702, persistent process on server |
| **ISSUES** | Hub implementation exists. Default `control_contact_center_realtime_mode()` returns **`polling`**. Shared hosting may block `proc_open`/`shell_exec` for auto-start. Events persist to DB even if hub down, but live WS delivery fails silently (`error_log` in `RealtimeHubClient`). |
| **RISK** | **HIGH** |

---

### 10. EventBus

| Field | Value |
|-------|-------|
| **STATUS** | **PARTIAL** |
| **FILES** | `app/Core/Events/EventBus.php`, `EventType.php`, `RealtimeEvent.php`, `EventSubscriberInterface.php`, `app/Application/Services/RealtimeOrchestrator.php` |
| **DEPENDENCIES** | `rcc_realtime_events` table, WebSocket hub |
| **ISSUES** | Core pipeline works: normalize → persist → broadcast → subscribers. Subscribers wired at boot: `AgentStateService`, `QueueRealtimeService`, `ConversationEventBridge`, `ErpActivityLogger`, `AiAssistantEngine`. Fails if DB schema missing. |
| **RISK** | **MEDIUM** |

---

### 11. WebSocket Layer

| Field | Value |
|-------|-------|
| **STATUS** | **PARTIAL** |
| **FILES** | `app/Infrastructure/Realtime/WebSocketGateway.php`, `WebSocketProtocol.php`, `public/assets/js/rcc-realtime-client.js` |
| **DEPENDENCIES** | `rcc-realtime-hub.php` running, `RCC_WEBSOCKET_PORT=9702`, browser WSS access |
| **ISSUES** | Custom PHP WebSocket server (not Ratchet/Swoole). Client skips connect when URL is `polling`. No HTTP long-poll endpoint for softphone events. |
| **RISK** | **HIGH** |

---

### 12. SIP Registration

| Field | Value |
|-------|-------|
| **STATUS** | **PARTIAL** |
| **FILES** | `app/Infrastructure/WebRTC/SipGateway.php`, `config/sip.php`, `app/Controllers/Api/SoftphoneApiController.php` (`register`), `public/assets/js/rcc-softphone.js` (`SIP.Registerer`) |
| **DEPENDENCIES** | `rcc_sip_extensions`, `rcc_agents`, Asterisk PJSIP/WSS, `RCC_SIP_*` env vars |
| **ISSUES** | Backend returns WebRTC config; browser registers via SIP.js. **Fallback credentials** generated if no DB row (`tenant{N}-agent{M}`). Requires real PBX + WSS URI. No server-side SIP REGISTER — browser-only. |
| **RISK** | **HIGH** |

---

### 13. WebRTC Softphone

| Field | Value |
|-------|-------|
| **STATUS** | **PARTIAL** |
| **FILES** | `app/Domain/Softphone/CallControlEngine.php`, `MediaSessionManager.php`, `TransferEngine.php`, `SoftphoneCallRepository.php`, `AgentSipSessionRepository.php`, `public/assets/js/rcc-softphone.js`, `rcc-softphone-ui.js`, `views/components/softphone-panel.php`, `agent-desktop-embed.php` |
| **DEPENDENCIES** | SIP.js CDN, WSS to Asterisk, softphone API, optional realtime hub |
| **ISSUES** | INVITE/ANSWER/HOLD/RESUME/HANGUP implemented in JS + API state tracking. **Cannot receive queue calls** without PBX delivering SIP INVITE + `QUEUE_ASSIGNED` realtime event. Hold uses WebRTC SDP hold modifier, not AMI hold. |
| **RISK** | **HIGH** |

---

### 14. Agent Desktop

| Field | Value |
|-------|-------|
| **STATUS** | **PARTIAL** |
| **FILES** | `control-panel/pages/control/contact-center-app.php`, `views/components/agent-desktop-embed.php`, `public/assets/js/rcc-agent-desktop-ui.js`, `rcc-agent-inbox.js`, `rcc-ai-copilot.js` |
| **DEPENDENCIES** | Control Panel session, DB schema, APIs, SIP.js, optional WS hub |
| **ISSUES** | UI shell exists. Hardcoded tenant/agent IDs. Protected by CP login + `view_control_system_settings` — **not** agent RBAC. Demo chat button wired to `start_demo` API. |
| **RISK** | **HIGH** |

---

### 15. Omnichannel Inbox

| Field | Value |
|-------|-------|
| **STATUS** | **PARTIAL** |
| **FILES** | `app/Domain/Conversation/ConversationEngine.php`, `ChannelNormalizer.php`, `IdentityResolver.php`, `ConversationPriorityEngine.php`, repositories, `app/Controllers/Api/InboxApiController.php`, channel adapters, `public/assets/js/rcc-agent-inbox.js` |
| **DEPENDENCIES** | `rcc_conversations`, `rcc_conversation_messages`, realtime or 8s polling fallback |
| **ISSUES** | Ingest/thread/send/close work against DB. **`start_demo`** creates demo conversations. Inbox polling fallback exists; softphone does not. Outbound `send` writes DB only — no external delivery. |
| **RISK** | **MEDIUM** |

---

### 16. WhatsApp Integration

| Field | Value |
|-------|-------|
| **STATUS** | **PARTIAL / NOT CONNECTED** |
| **FILES** | `app/Infrastructure/Channels/WhatsAppChannelAdapter.php`, `InboxApiController` (`webhook_whatsapp`) |
| **DEPENDENCIES** | Meta WhatsApp Business API webhook + send API |
| **ISSUES** | Ingest normalizes webhook payload into conversation. **No outbound WhatsApp send.** No webhook signature verification. No Meta API credentials in config. |
| **RISK** | **HIGH** |

---

### 17. Email Integration

| Field | Value |
|-------|-------|
| **STATUS** | **PARTIAL / NOT CONNECTED** |
| **FILES** | `app/Infrastructure/Channels/EmailChannelAdapter.php`, `InboxApiController` (`webhook_email`) |
| **DEPENDENCIES** | IMAP/polling or inbound webhook, SMTP send |
| **ISSUES** | Ingest only. **No SMTP/IMAP worker.** Agent replies stored in DB, not emailed. |
| **RISK** | **HIGH** |

---

### 18. Web Chat Integration

| Field | Value |
|-------|-------|
| **STATUS** | **PARTIAL** |
| **FILES** | `app/Infrastructure/Channels/WebChatChannelAdapter.php`, `InboxApiController` (`webhook_chat`, `start_demo`) |
| **DEPENDENCIES** | Public chat widget posting to API |
| **ISSUES** | Adapter + API endpoint exist. **No customer-facing chat widget** in repo. Demo path is the only tested flow in UI. |
| **RISK** | **MEDIUM** |

---

### 19. ERP Integration

| Field | Value |
|-------|-------|
| **STATUS** | **PARTIAL** |
| **FILES** | `app/Core/ErpBridge.php`, `SoftphoneErpService.php`, `ErpActivityLogger.php`, `ErpContextAnalyzer.php`, `IdentityResolver.php` |
| **DEPENDENCIES** | ERP MySQL (`rateb_companies`), RCC tables (`rcc_contacts`, `rcc_tickets`, `rcc_erp_activity_log`) |
| **ISSUES** | `ErpBridge` read-only to `rateb_companies`. Customer lookup uses **`rcc_contacts`** (RCC DB), not live ERP CRM tables. Activity log INSERT to `rcc_erp_activity_log`. No write-back to ERP. |
| **RISK** | **MEDIUM** |

---

### 20. AI Copilot

| Field | Value |
|-------|-------|
| **STATUS** | **PARTIAL** |
| **FILES** | `app/Domain/AI/Assistant/AiAssistantEngine.php`, `IntentDetector.php`, `SentimentAnalyzer.php`, `ReplySuggestionEngine.php`, `ConversationSummarizer.php`, `NextBestActionEngine.php`, `AiContextRepository.php`, `app/Controllers/Api/AiAssistantApiController.php`, `config/assistant.php`, `public/assets/js/rcc-ai-copilot.js` |
| **DEPENDENCIES** | `rcc_ai_context`, `rcc_tickets`, EventBus triggers |
| **ISSUES** | **Heuristic/keyword-based** — no OpenAI/LLM calls in code (`RCC_AI_PROVIDER` documented as future). Auto-ticket creation rules in engine. Works on demo conversations if DB exists. |
| **RISK** | **LOW** (advisory) / **MEDIUM** (if marketed as AI) |

---

### 21. Ticket System

| Field | Value |
|-------|-------|
| **STATUS** | **PARTIAL** |
| **FILES** | `app/Infrastructure/Ticket/TicketGateway.php`, `TicketGatewayInterface.php`, IVR `create_ticket` route, AI `create_ticket` API |
| **DEPENDENCIES** | `rcc_tickets` table |
| **ISSUES** | INSERT logic exists. No ticket UI, workflow, assignment engine, or ERP sync. No SLA fields enforcement beyond INSERT columns. |
| **RISK** | **MEDIUM** |

---

### 22. SLA Engine

| Field | Value |
|-------|-------|
| **STATUS** | **PARTIAL** |
| **FILES** | `SlaPredictor.php`, `QueueRealtimeService.php` (sla_risk), `ConversationPriorityEngine.php`, `EventType::SLA_ALERT`, `SLA_ESCALATED_CALL` |
| **DEPENDENCIES** | Queue snapshots, `rcc_queues.sla_target_seconds`, routing log |
| **ISSUES** | Rule-based prediction and alerts via EventBus. **No SLA timer enforcement**, breach escalation to supervisors, or reporting. Conversation `sla_status` is computed, not monitored by cron. |
| **RISK** | **MEDIUM** |

---

### 23. Reporting

| Field | Value |
|-------|-------|
| **STATUS** | **NOT CONNECTED** |
| **FILES** | None (only doc references in `PHASE-1-ARCHITECTURE.md`) |
| **DEPENDENCIES** | Analytics tables, export APIs, dashboards |
| **ISSUES** | **No reporting module, controllers, views, or queries found.** |
| **RISK** | **HIGH** |

---

### 24. Security

| Field | Value |
|-------|-------|
| **STATUS** | **BROKEN** |
| **FILES** | CP auth in `contact-center-app.php`, `contact-center.php`; APIs: `public/api/bootstrap-api.php`, `softphone.php`, `inbox.php`, `assistant.php` |
| **DEPENDENCIES** | Session auth, API tokens, webhook signing |
| **ISSUES** | Public JSON APIs accept **`tenant_id` / `agent_id` without verification**. No CSRF on API. No rate limiting. `EXTERNAL_API_TOKEN` / `WEBHOOK_SIGNING_SECRET` in env example but **not checked in RCC API code**. Docs reference `002_security_rbac.sql` — missing. |
| **RISK** | **CRITICAL** |

---

### 25. Authentication

| Field | Value |
|-------|-------|
| **STATUS** | **PARTIAL** |
| **FILES** | Control Panel `$_SESSION['control_logged_in']`, `requireControlPermission(..., 'view_control_system_settings')` |
| **DEPENDENCIES** | CP session |
| **ISSUES** | Hub/migrate/desktop gated by CP admin permission — **not agent login**. APIs fully open. No agent SSO mapping. |
| **RISK** | **CRITICAL** |

---

### 26. Permissions

| Field | Value |
|-------|-------|
| **STATUS** | **NOT CONNECTED** |
| **FILES** | CP permission check only; no RCC RBAC classes |
| **DEPENDENCIES** | `rcc_roles`, `rcc_permissions` (documented, not shipped) |
| **ISSUES** | Single CP permission gates entire Contact Center. No per-agent/per-tenant RBAC in RCC. |
| **RISK** | **HIGH** |

---

## Real Integration Verification

| Integration | Code Exists | Runtime Connected | Evidence |
|-------------|-------------|-------------------|----------|
| **Asterisk** | Partial | **No** | PBX gateway + adapter only; no worker, dialplan, or AMI socket |
| **SIP** | Yes (browser) | **Unverified** | SIP.js REGISTER in `rcc-softphone.js`; needs live WSS + credentials |
| **WebRTC** | Yes | **Unverified** | SDP audio in browser; depends on Asterisk WSS |
| **Queues** | Partial | **No** | DB + AMI `Queue()` command; AMI not connected |
| **IVR** | Yes (PHP) | **No** | Engine complete; no inbound AMI trigger |
| **Realtime Hub** | Yes | **Conditional** | Requires CLI daemon; default mode `polling` |
| **WebSocket** | Yes | **Conditional** | Hub WS :9702; blocked without process + firewall |
| **MySQL** | Yes | **Conditional** | PDO works; **schema SQL missing from repo** |
| **ERP** | Partial | **Partial** | Read `rateb_companies`; contacts from `rcc_contacts` |

---

## Telephony Capability Matrix (Code Proof)

| Capability | Status | Location |
|------------|--------|----------|
| **SIP REGISTER** | **PARTIAL** | Browser: `rcc-softphone.js` → `SIP.Registerer.register()`. Server returns creds via `CallControlEngine::registerAgentSession()` |
| **SIP INVITE** | **PARTIAL** | Browser: `onInvite` handler. Outbound: `SIP.Inviter.invite()`. Requires live PBX |
| **ANSWER** | **PARTIAL** | `session.accept()` + API `accept` action |
| **HANGUP** | **PARTIAL** | `session.bye()` + API `hangup`. AMI hangup only if `channel_id` passed to backend |
| **HOLD** | **PARTIAL** | WebRTC SDP hold + API `hold` — not AMI hold |
| **RESUME** | **PARTIAL** | WebRTC unhold + API `resume` |
| **BLIND TRANSFER** | **PARTIAL** | API + `TransferEngine::blindTransfer()` — AMI `Redirect` only if `channel_id` provided |
| **ATTENDED TRANSFER** | **PARTIAL** | Events only in `attendedTransferInit/Complete` — **no PBX bridge logic** in init |
| **Queue Assignment** | **PARTIAL** | `QueueEngineGateway` + `QUEUE_ASSIGNED` event — **no proven agent ring** |
| **IVR DTMF Processing** | **PARTIAL** | `AsteriskAmiAdapter::onDtmf` → `IvrEngine::pushDtmfInput` — **no AMI listener** |
| **WebSocket Broadcast** | **PARTIAL** | `EventBus` → `WebSocketGateway` → TCP hub → WS clients |
| **AI Routing Decisions** | **YES (in-process)** | `RoutingEngine::decide()` with DB + rules |
| **Conversation Creation** | **YES (in-process)** | `ConversationEngine` + EventBus bridge |
| **Ticket Creation** | **YES (in-process)** | `TicketGateway::createFromIvr/createFromAssistant` — needs `rcc_tickets` table |

---

## Final Scores

| Area | Score | Basis |
|------|-------|-------|
| **Database** | **20%** | PDO + repos complete; **zero migration SQL in repo** |
| **Backend** | **55%** | Domain services/controllers largely implemented |
| **Realtime** | **50%** | EventBus + hub code; default polling; hub often not running |
| **Telephony** | **12%** | AMI adapter/gateway without worker, dialplan, or live AMI |
| **WebRTC** | **45%** | Full browser SDK + API; needs PBX + DB SIP rows |
| **AI** | **40%** | Heuristic copilot/routing; no external LLM |
| **ERP** | **35%** | Read bridge + RCC-local contacts; limited ERP CRM tie-in |
| **Security** | **15%** | CP gate only; open public APIs |
| **Production Readiness** | **22%** | Cannot complete real inbound call path end-to-end |

---

## Production Gap Report

Must be completed before handling **real customer calls**:

1. **Ship all `migrations/*.sql`** (`001`–`009` per docs) and verify migrate page creates all `rcc_*` tables.
2. **Implement AMI event worker** (`rcc-voice-worker.php` or equivalent) — persistent AMI connection calling `AsteriskAmiAdapter::dispatch()`.
3. **Wire AMI action sender** into `AsteriskPbxCommandGateway` (replace `error_log` default).
4. **Deploy Asterisk dialplan** — contexts `rcc-ivr-*`, `rcc-tenant-{id}`, AGI `rcc-tts.php`, queue integration with `RCC_TENANT_ID` / `RCC_PREFERRED_AGENT_ID`.
5. **Configure PJSIP/WebRTC** on Asterisk — WSS URI, extensions in `rcc_sip_extensions`, env passwords.
6. **Run Realtime Hub** as persistent service; set `RCC_REALTIME_MODE=websocket` and expose WSS (or add softphone polling fallback).
7. **Secure public APIs** — session/JWT binding agent to tenant; webhook HMAC; remove unauthenticated `tenant_id` trust.
8. **Map real agents/tenants** — replace hardcoded `$tenantId=1, $agentId=1` with authenticated agent context.
9. **Seed production IVR flows + queues + agents/skills** — no example flow SQL in repo.
10. **Validate queue→agent ring path** on PBX (Asterisk must deliver INVITE to agent WebRTC endpoint).
11. **Remove or gate demo paths** (`start_demo`, "New demo chat" UI) for production.
12. **Reporting** — not implemented; required for operations.

### Optional omnichannel gaps (not voice-blocking)

- WhatsApp Business API send + verified webhooks
- Email IMAP ingest + SMTP outbound
- Public web chat widget

---

## End-to-End Test Checklist

| Step | Result | Code Basis |
|------|--------|------------|
| **Inbound Call** | **NOT IMPLEMENTED** | No AMI worker; no dialplan |
| **IVR** | **NOT IMPLEMENTED** | Engine exists; no live trigger + no flows in DB |
| **Queue** | **NOT IMPLEMENTED** | Logic exists; AMI `Queue()` not connected |
| **Agent Ring** | **NOT IMPLEMENTED** | No Originate/queue-member connect in PHP |
| **Answer** | **FAIL** | SIP answer code exists; cannot be reached without INVITE |
| **Transfer** | **FAIL** | Partial; AMI redirect needs `channel_id` + live AMI |
| **Hangup** | **FAIL** | Browser hangup works; end-to-end with PSTN not wired |
| **Conversation** | **FAIL** | Works via `start_demo` / webhooks only; not from live call path |
| **Ticket** | **FAIL** | INSERT code exists; tables missing; not triggered on live call |
| **Realtime Updates** | **FAIL** | Requires hub + websocket mode; softphone has no polling fallback |

**Demo-only path that can PASS partially (if DB migrated manually):** Inbox `start_demo` → conversation → heuristic AI → manual ticket via assistant API — **not a real call flow**.

---

## Dead / Disconnected Code (Notable)

| Item | Finding |
|------|---------|
| `AsteriskAmiAdapter` | Implemented but **never invoked** by any runtime entry point |
| `bin/rcc-voice-worker.php` | Documented in Phase 1 — **missing** |
| `AsteriskPbxCommandGateway` default | **Dead AMI path** (logs only) |
| `AttendedTransferInit` | Emits event only — **no PBX action** |
| `rcc-sp.js` / `rcc-sp-ui.js` | Parallel to `rcc-softphone*.js` — possible legacy duplicates |
| `docs/examples/*.json` | Example payloads only — not runtime |

---

## File Inventory Summary

**Root:** `ratib-contact-center/` (~128 tracked files), `control-panel/` bridge pages, deploy in `scripts/github-cpanel-fileman-deploy-core.py`.

**Key directories:**

| Path | Contents |
|------|----------|
| `app/` | 83 PHP domain/application files |
| `public/api/v1/` | `softphone.php`, `inbox.php`, `assistant.php` |
| `public/assets/js/` | softphone, inbox, realtime, copilot |
| `bin/` | realtime hub only (no voice worker) |
| `migrations/` | **empty** |
| `docs/` | PHASE 1–7 architecture docs + this audit |

---

## Disclaimer

This audit reflects **repository state only**. A production server may have manually uploaded migrations or Asterisk config not present in git — that cannot be verified from code in this repo.
