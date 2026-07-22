# RATEB Contact Center — Phase 2: IVR Runtime Engine

**Status:** Implemented  
**Database:** `ratib_contact_center`  
**Core class:** `app/Domain/IVR/IvrEngine.php`

---

## Overview

The IVR Runtime Engine is a **data-driven state machine** that executes call flows from the database at runtime. No IVR paths are hardcoded. All business logic flows through `IvrEngine` — controllers and AMI adapters are thin delegates only.

### Design goals (met)

| Requirement | Implementation |
|-------------|----------------|
| Data-driven (DB only) | `rcc_ivr_flows`, `rcc_ivr_nodes`, `rcc_ivr_sessions` |
| State machine | `IvrEngine::runUntilWaitOrComplete()` node loop |
| Session per call | `IvrSessionManager::onIncomingCall()` |
| Tenant isolated | `tenant_id` on all tables + `TenantContext` |
| Real-time (AMI) | `AsteriskAmiAdapter::dispatch()` |
| Strategy pattern | `NodeExecutorRegistry` + 4 executors |
| Retry / fallback / timeout | `handleTimeout()`, `max_retries`, `fallback_node_id` |
| AR/EN language | `payload.message_ar` / `message_en`, ERP locale bridge |

---

## Architecture

```
AMI Event
   │
   ▼
AsteriskAmiAdapter          ← NO business logic
   │
   ▼
IvrSessionManager           ← call record + lifecycle facade
   │
   ▼
IvrEngine                   ← ALL IVR business logic
   │
   ├── IvrFlowRepository
   ├── IvrNodeRepository
   ├── IvrSessionRepository
   └── NodeExecutorRegistry
         ├── PlayMessageExecutor
         ├── CollectInputExecutor
         ├── RouteCallExecutor
         └── HangupExecutor
   │
   ▼
AsteriskPbxCommandGateway   → AMI actions (Playback, Read, Queue, Hangup)
QueueEngineGateway          → enqueue on route_call
TicketGateway               → create ticket on Press 2
```

---

## Database schema

### `rcc_ivr_flows`

| Column | Type | Notes |
|--------|------|-------|
| id | INT | PK |
| tenant_id | INT | FK → `rcc_tenants` |
| name | VARCHAR(120) | Flow label |
| is_active | TINYINT(1) | One active flow per tenant (latest wins) |
| entry_node_id | INT | First node |
| default_locale | ENUM('en','ar') | Fallback locale |

### `rcc_ivr_nodes`

| Column | Type | Notes |
|--------|------|-------|
| id | INT | PK |
| flow_id | INT | FK |
| type | ENUM | `play_message`, `collect_input`, `route_call`, `hangup` |
| payload | JSON | Messages, routes, actions |
| next_node_id | INT | Default next node |
| fallback_node_id | INT | Used on timeout / invalid input |
| max_retries | TINYINT | Default 3 |
| timeout_seconds | TINYINT | Default 10 (5–60 enforced on PBX) |

### `rcc_ivr_sessions`

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT | PK |
| call_id | BIGINT | FK → `rcc_calls` |
| tenant_id | INT | Tenant isolation |
| flow_id | INT | Active flow |
| current_node_id | INT | State machine position |
| state | JSON | `last_input`, `inputs[]`, `locale`, etc. |
| status | ENUM | `active`, `waiting_input`, `completed`, `failed`, `timeout` |
| channel_id | VARCHAR(120) | Asterisk channel |
| locale | ENUM('en','ar') | Session language |
| retry_count | TINYINT | Collect retry counter |

**Migrations:**

- `migrations/001_core_tenancy.sql`
- `migrations/002_ivr_runtime_engine.sql`
- `migrations/003_queue_ticket_stub.sql`
- `migrations/004_ivr_example_flow.sql`

---

## Core engine API

### `IvrEngine`

```php
// Start IVR for inbound call
$session = $engine->startSession(
    callId: $callId,
    tenantId: $tenantId,
    callUuid: $uuid,
    channelId: $channelId,
    erpCompanyId: $erpCompanyId
);

// Execute current node (usually called internally)
$session = $engine->executeNode($session);

// AMI DTMF hook
$session = $engine->pushDtmfInput($sessionId, $tenantId, '1');

// Timeout hook (Read expired)
$session = $engine->handleTimeout($sessionId, $tenantId);

// Hangup hook
$engine->finalizeSession($sessionId, $tenantId);
```

### Execution loop

1. `startSession()` → `loadFlow(tenantId)` → create `rcc_ivr_sessions`
2. `runUntilWaitOrComplete()` executes nodes until:
   - `waiting_input` (collect_input)
   - `completed` / `failed` / `timeout` (terminal)
3. Every step **persists** `current_node_id`, `state`, `status` to DB

---

## Node types

### `play_message`

Sends TTS or audio URL to PBX.

**Payload:**

```json
{
  "message": "Default message",
  "message_en": "Welcome.",
  "message_ar": "مرحباً.",
  "audio_url": "optional/path.wav",
  "audio_url_ar": "optional/ar/welcome.wav"
}
```

**Behavior:** `Playback` or AGI TTS → advance to `next_node_id`

---

### `collect_input`

Plays prompt and waits for DTMF.

**Payload:**

```json
{
  "message_en": "Press 1 for sales, 2 for support, 0 for operator.",
  "message_ar": "اضغط 1 للمبيعات، 2 للدعم، 0 للموظف.",
  "max_digits": 1
}
```

**Behavior:**

- Sets status → `waiting_input`
- On DTMF → stores `state.last_input` → advances to `next_node_id`
- On timeout → retry up to `max_retries` → then `fallback_node_id`

---

### `route_call`

Dynamic routing based on DTMF, tenant rules, ERP data.

**Payload example:**

```json
{
  "routes": [
    {
      "dtmf": "1",
      "label": "Sales",
      "action": "queue",
      "queue_code": "sales",
      "next_node_id": 4
    },
    {
      "dtmf": "2",
      "label": "Support",
      "action": "create_ticket",
      "ticket_subject": "IVR Support Request",
      "ticket_description": "Customer selected support.",
      "next_node_id": 4
    },
    {
      "dtmf": "0",
      "label": "Operator",
      "action": "extension",
      "extension": "100"
    }
  ],
  "default": {
    "action": "next_node",
    "next_node_id": 5
  }
}
```

**Actions:**

| action | Effect |
|--------|--------|
| `queue` | AMI `Queue()` + `QueueEngineGateway::enqueueCaller()` |
| `extension` / `operator` | AMI `Redirect` to tenant context |
| `create_ticket` | `TicketGateway::createFromIvr()` |
| `language` | Sets `state.locale` → AR/EN |
| `next_node` | Jump to `next_node_id` |

---

### `hangup`

**Payload:** `{ "reason": "normal" }`  
**Behavior:** AMI `Hangup` → session `completed`

---

## AMI integration

### `AsteriskAmiAdapter`

Wire to your AMI listener:

```php
require 'ratib-contact-center/bootstrap.php';

use Ratib\ContactCenter\App\Infrastructure\Voice\AsteriskAmiAdapter;

$adapter = new AsteriskAmiAdapter();

// In AMI event loop:
$adapter->dispatch($amiEventArray);
```

### Event mapping

| AMI Event | Handler | IVR action |
|-----------|---------|------------|
| `Newchannel` / `RCCIncomingCall` | `onIncomingCall()` | `startSession()` |
| `DTMF` / `RCCDTMF` | `onDtmf()` | `pushDtmfInput()` |
| `RCCDTMFTimeout` | `onDtmfTimeout()` | `handleTimeout()` |
| `Hangup` | `onHangup()` | `finalizeSession()` |

### Required dialplan variables

Set on inbound channel before IVR:

```
Set(RCC_TENANT_ID=1)
Set(RCC_ERP_COMPANY_ID=1)
Set(RCC_IVR=1)
```

Context should match `rcc-ivr-*` or include `RCC_IVR` flag.

---

## Example flow (seeded)

Migration `004_ivr_example_flow.sql` creates tenant `demo-company`:

```
Node 1  play_message     → Welcome (AR/EN)
Node 2  collect_input    → Press 1 / 2 / 0
Node 3  route_call       → Route by digit
Node 4  play_message     → "Please hold..."
Node 5  play_message     → Invalid → back to menu (fallback)
Node 6  hangup           → End after routing
```

**Success scenarios:**

- Press **1** → Sales queue (`sales`)
- Press **2** → Support ticket created + hold message
- Press **0** → Extension `100` (operator)
- No input (3 retries) → fallback node 5 → menu

---

## File reference

| File | Role |
|------|------|
| `app/Domain/IVR/IvrEngine.php` | Core state machine |
| `app/Application/Services/IvrSessionManager.php` | Session lifecycle + call records |
| `app/Domain/IVR/NodeExecutors/*` | Strategy executors |
| `app/Infrastructure/Voice/AsteriskAmiAdapter.php` | AMI hooks |
| `app/Infrastructure/Voice/AsteriskPbxCommandGateway.php` | PBX commands |
| `app/Infrastructure/Queue/QueueEngineGateway.php` | Queue integration |
| `app/Infrastructure/Ticket/TicketGateway.php` | Ticket on Press 2 |
| `app/Core/TenantContext.php` | Tenant isolation |
| `app/Core/ErpBridge.php` | ERP locale (read-only) |

---

## Bootstrap usage

```php
require __DIR__ . '/ratib-contact-center/bootstrap.php';

use Ratib\ContactCenter\App\Application\Services\IvrSessionManager;

$manager = new IvrSessionManager();
$session = $manager->onIncomingCall(
    tenantId: 1,
    channelId: 'SIP/trunk-000001',
    callerNumber: '0500000000',
    calleeNumber: '920000000',
    erpCompanyId: 1
);
```

---

## Rules enforced

- No hardcoded IVR paths
- No business logic in controllers / AMI adapter
- Session persisted after every node step
- Multi-tenant isolation via `tenant_id` + `TenantContext`
- Retry, fallback, timeout on `collect_input`
- Tenant language from ERP company settings + node payload

---

## Next steps (Phase 3)

- Drag-drop IVR builder UI (writes `rcc_ivr_nodes`)
- ARI/WebRTC softphone integration
- SLA-aware queue routing from ERP
- Visual flow simulator API
- Cron worker for orphaned `waiting_input` sessions

---

*Generated for RATEB Contact Center — IVR Runtime Engine Phase 2.*
