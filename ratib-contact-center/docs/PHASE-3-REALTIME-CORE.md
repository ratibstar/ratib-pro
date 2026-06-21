# RATIB Contact Center — Phase 3: Real-Time Core Layer

**Status:** Implemented  
**Transport:** WebSocket only (no polling)  
**Entry point:** `EventBus::emit()` — mandatory for all live updates

---

## Architecture

```
Asterisk AMI
     │
     ▼
IvrSessionManager / IvrEngine / QueueEngineGateway
     │
     ▼
EventBus (normalize → enrich → persist → broadcast)
     │
     ├── WebSocketGateway → RealtimeHub (TCP :9701 → WS :9702)
     ├── AgentStateService
     ├── QueueRealtimeService
     ├── IvrStateStreamer
     └── ErpActivityLogger
     │
     ▼
Frontend (RccRealtimeClient — WebSocket subscribe)
```

---

## Core components

| File | Role |
|------|------|
| `app/Core/Events/EventBus.php` | Central dispatcher |
| `app/Core/Events/EventType.php` | Standard event constants |
| `app/Core/Events/RealtimeEvent.php` | Immutable event envelope |
| `app/Infrastructure/Realtime/WebSocketGateway.php` | Room-based broadcast |
| `app/Infrastructure/Realtime/RealtimeHubClient.php` | TCP push to hub |
| `app/Domain/Agents/AgentStateService.php` | Agent presence engine |
| `app/Domain/Queue/QueueRealtimeService.php` | Live queue aggregator + SLA |
| `app/Application/Services/IvrStateStreamer.php` | IVR live path events |
| `app/Application/Services/ErpActivityLogger.php` | ERP activity stream |
| `app/Application/Services/RealtimeOrchestrator.php` | Subscriber wiring |
| `bin/rcc-realtime-hub.php` | WebSocket + TCP hub daemon |
| `public/assets/js/rcc-realtime-client.js` | Frontend client (auto-reconnect) |

---

## EventBus API

```php
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;

EventBus::instance()->emit([
    'type' => EventType::CALL_CONNECTED,
    'tenant_id' => 1,
    'call_id' => 9912,
    'agent_id' => 12,
    'queue_id' => 3,
    'payload' => [
        'channel_id' => 'SIP/trunk-00001234',
    ],
]);
```

### Pipeline (every emit)

1. **Normalize** — validate `type`, require `tenant_id`
2. **Enrich** — `event_uuid`, ISO timestamp, tenant/ERP/locale in payload
3. **Persist** — `rcc_realtime_events` (immutable audit)
4. **Broadcast** — WebSocket rooms via hub
5. **Subscribers** — agent state, queue snapshots, ERP log

---

## Standard event types

### Call
| Type | Trigger |
|------|---------|
| `CALL_INCOMING` | New inbound call (IvrSessionManager) |
| `CALL_CONNECTED` | AMI BridgeEnter / AgentConnect |
| `CALL_ENDED` | Hangup |
| `CALL_TRANSFERRED` | Blind/Attended transfer |

### IVR
| Type | Trigger |
|------|---------|
| `IVR_STARTED` | IvrEngine startSession |
| `IVR_NODE_ENTERED` | Each node execution |
| `IVR_WAITING_INPUT` | collect_input node |
| `IVR_COMPLETED` | Session finalize |

### Agent
| Type | Trigger |
|------|---------|
| `AGENT_LOGIN` | AgentStateService::login |
| `AGENT_READY` | setReady |
| `AGENT_BUSY` | setBusy / CALL_CONNECTED |
| `AGENT_WRAPUP` | setWrapup / CALL_ENDED |
| `AGENT_OFFLINE` | setOffline |
| `AGENT_STATE_UPDATED` | Any state change |

### Queue
| Type | Trigger |
|------|---------|
| `QUEUE_JOINED` | QueueEngineGateway enqueue |
| `QUEUE_ASSIGNED` | Agent assigned to call |
| `QUEUE_WAIT_TIME_UPDATED` | Snapshot recompute |
| `QUEUE_SNAPSHOT` | Full live metrics |
| `SLA_ALERT` | red/yellow SLA breach risk |

---

## WebSocket channel design

### Room naming (tenant isolated)

| Room | Subscribers | Events |
|------|-------------|--------|
| `tenant:{id}` | Supervisors, wallboards | All tenant events |
| `dashboard:{id}` | Live dashboard | Aggregates + calls + SLA |
| `agent:{id}` | Agent softphone UI | Agent + assigned calls |
| `queue:{id}` | Queue monitor | Queue + SLA |
| `ivr:{sessionId}` | IVR debugger | IVR path events |

### Subscribe protocol

Client sends after connect:

```json
{
  "action": "subscribe",
  "rooms": ["tenant:1", "dashboard:1", "queue:3", "agent:12"]
}
```

Server confirms:

```json
{ "type": "SUBSCRIBED", "rooms": ["tenant:1", "..."] }
```

### Event envelope (immutable)

```json
{
  "event_uuid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "type": "QUEUE_SNAPSHOT",
  "tenant_id": 1,
  "queue_id": 3,
  "timestamp": "2026-06-21T14:30:08.520Z",
  "payload": {
    "waiting_count": 4,
    "longest_wait_seconds": 127,
    "sla_risk": "yellow"
  }
}
```

Full example stream: `docs/examples/realtime-event-stream.json`

---

## Database

### `rcc_realtime_events`

| Column | Type |
|--------|------|
| id | BIGINT |
| event_uuid | CHAR(36) UNIQUE |
| tenant_id | INT |
| event_type | VARCHAR(80) |
| payload | JSON |
| created_at | TIMESTAMP(3) |

### `rcc_agent_live_state`

Live agent presence (updated by AgentStateService only).

### `rcc_erp_activity_log`

ERP-linked activity stream from critical events.

**Migration:** `migrations/005_realtime_core.sql`

---

## Running the realtime hub

```bash
php ratib-contact-center/bin/rcc-realtime-hub.php
```

| Service | Default | Env override |
|---------|---------|--------------|
| TCP ingest | 127.0.0.1:9701 | `RCC_REALTIME_HUB_HOST`, `RCC_REALTIME_HUB_PORT` |
| WebSocket | 0.0.0.0:9702 | `RCC_WEBSOCKET_HOST`, `RCC_WEBSOCKET_PORT` |

---

## Frontend integration

```html
<script src="/ratib-contact-center/public/assets/js/rcc-realtime-client.js"></script>
<script>
var client = new RccRealtimeClient({
  url: 'ws://your-host:9702',
  tenantId: 1,
  rooms: ['queue:3', 'agent:12'],
  onEvent: function (event) {
    if (event.type === 'CALL_INCOMING') { /* update live calls table */ }
    if (event.type === 'QUEUE_SNAPSHOT') { /* heat map */ }
    if (event.type === 'AGENT_STATE_UPDATED') { /* agent grid */ }
    if (event.type === 'IVR_NODE_ENTERED') { /* IVR path viz */ }
    if (event.type === 'SLA_ALERT') { /* red/yellow/green alert */ }
  }
});
client.connect();
</script>
```

**Rules enforced:**
- No polling
- No direct DB reads for live data on frontend
- All updates from WebSocket events only

---

## Agent state model

```json
{
  "agent_id": 12,
  "tenant_id": 1,
  "status": "busy",
  "current_call_id": 9912,
  "queue_id": 3,
  "pause_reason": null,
  "session_started_at": "2026-06-21 14:00:00",
  "last_update": "2026-06-21 14:32:05.010"
}
```

```php
$agentState = new AgentStateService();
$agentState->login(1, 12, 100);
$agentState->setReady(1, 12);
$agentState->setBusy(1, 12, 9912, 3);
```

---

## Queue live metrics

`QueueRealtimeService::computeSnapshot()` returns:

- `waiting_count`
- `longest_wait_seconds`
- `avg_wait_seconds`
- `available_agents` / `busy_agents`
- `sla_risk` — `green` | `yellow` | `red`
- `distribution_load` — waiting / available agents

SLA alert thresholds:
- **red** — wait ≥ SLA target OR waiting > 0 with 0 available agents
- **yellow** — wait ≥ 70% of SLA target
- **green** — otherwise

---

## Integration hooks (already patched)

| Source | Events emitted |
|--------|----------------|
| `IvrSessionManager` | CALL_INCOMING, CALL_ENDED |
| `IvrEngine` + `IvrStateStreamer` | IVR_* |
| `AsteriskAmiAdapter` | CALL_CONNECTED, CALL_TRANSFERRED |
| `QueueEngineGateway` | QUEUE_JOINED → snapshot |

---

## Critical rules

1. **Never bypass EventBus** for live UI updates
2. **Never poll** — WebSocket only
3. **Never couple UI to DB** for realtime state
4. **Tenant isolation** at room subscription level
5. **Events are immutable** — new state = new event_uuid

---

## Success criteria

| Scenario | Event chain |
|----------|-------------|
| Live incoming call | CALL_INCOMING → dashboard instantly |
| Agent status change | AGENT_* → AGENT_STATE_UPDATED |
| Queue update | QUEUE_JOINED → QUEUE_SNAPSHOT |
| IVR progress | IVR_NODE_ENTERED → path visualization |
| SLA alert | SLA_ALERT with level red/yellow |

---

*Phase 3 — Real-Time Core Layer for RATIB RCC.*
