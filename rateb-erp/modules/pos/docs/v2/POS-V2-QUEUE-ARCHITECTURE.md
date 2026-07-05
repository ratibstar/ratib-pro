# RATEB POS V2 — Queue Architecture

**Version:** 1.0.0  
**Backend:** Laravel Queue (Redis recommended; database fallback)

---

## 1. Queue Topology

```
                    ┌─────────────────┐
                    │  pos-critical   │  ← Complete sale post-failure recovery
                    └────────┬────────┘
                             │
    ┌────────────────────────┼────────────────────────┐
    │                        │                        │
┌───▼───┐  ┌────────┐  ┌─────▼─────┐  ┌──────────┐  ┌────────────┐
│ print │  │kitchen │  │accounting │  │inventory │  │notifications│
└───────┘  └────────┘  └───────────┘  └──────────┘  └────────────┘
    │          │              │              │              │
┌───▼───┐  ┌───▼───┐  ┌──────▼──────┐  ┌────▼────┐  ┌─────▼─────┐
│ email │  │  sms  │  │   audit     │  │ license │  │offline_sync│
└───────┘  └───────┘  └─────────────┘  └─────────┘  └───────────┘
                             │
                    ┌────────▼────────┐
                    │  pos-dlq        │  ← Dead letter (all queues)
                    └─────────────────┘
```

---

## 2. Queue Definitions

### 2.1 `pos-printing`

| Property | Value |
|----------|-------|
| Purpose | Receipt, kitchen copy, refund receipt |
| Jobs | `PrintReceiptJob`, `ReprintReceiptJob` |
| Priority | high |
| Workers | 2+ per store cluster |
| Timeout | 30s |
| Retry | 3 attempts, backoff [5, 15, 30]s |
| DLQ | `pos-dlq` after exhaustion |
| Idempotency | `print_job_id` unique |

### 2.2 `pos-kitchen`

| Property | Value |
|----------|-------|
| Purpose | Kitchen ticket routing (restaurant profile) |
| Jobs | `SendKitchenTicketJob`, `VoidKitchenTicketJob` |
| Priority | critical (time-sensitive) |
| Workers | 1 per location minimum |
| Timeout | 15s |
| Retry | 5 attempts, backoff [2, 5, 10, 20, 30]s |
| Failure | In-app kitchen alert + DLQ |

### 2.3 `pos-email`

| Property | Value |
|----------|-------|
| Purpose | E-receipt, shift reports |
| Jobs | `SendEmailReceiptJob`, `SendShiftReportJob` |
| Priority | low |
| Retry | 3×60s |
| Rate limit | 100/min per company |

### 2.4 `pos-sms`

| Property | Value |
|----------|-------|
| Purpose | Receipt link, OTP (approval) |
| Jobs | `SendSmsReceiptJob`, `SendApprovalOtpJob` |
| Priority | normal |
| Retry | 3×120s |
| Provider | ERP notification bridge |

### 2.5 `pos-whatsapp`

| Property | Value |
|----------|-------|
| Purpose | Receipt delivery (MENA markets) |
| Jobs | `SendWhatsAppReceiptJob` |
| Priority | low |
| Retry | 3×180s |
| Feature flag | `notifications.whatsapp_enabled` |

### 2.6 `pos-accounting`

| Property | Value |
|----------|-------|
| Purpose | GL entries, ZATCA submission |
| Jobs | `PostSaleAccountingJob`, `PostRefundAccountingJob`, `PostShiftVarianceJob`, `SubmitZatcaInvoiceJob` |
| Priority | high |
| Retry | 5×60s exponential |
| DLQ | Manual finance replay required |
| Ordering | Per `order_id` serial (same queue worker key) |

### 2.7 `pos-inventory`

| Property | Value |
|----------|-------|
| Purpose | Reserve, commit, return stock |
| Jobs | `ReserveStockJob`, `CommitStockJob`, `ReturnStockJob` |
| Priority | high |
| Retry | 5×60s |
| Compensating | Release reservation on permanent failure |

### 2.8 `pos-audit`

| Property | Value |
|----------|-------|
| Purpose | Immutable audit log writes |
| Jobs | `WriteAuditEventJob` |
| Priority | normal |
| Retry | 10×30s (never drop) |
| DLQ | Alert ops immediately |

### 2.9 `pos-notifications`

| Property | Value |
|----------|-------|
| Purpose | Manager approval push, conflict alerts |
| Jobs | `NotifyManagerApprovalJob`, `NotifySyncConflictJob` |
| Priority | high |
| Retry | 3×30s |

### 2.10 `pos-license`

| Property | Value |
|----------|-------|
| Purpose | Terminal license validation |
| Jobs | `ValidateTerminalLicenseJob` |
| Priority | critical |
| Schedule | Every 6 hours + on boot |
| Retry | 3×300s |
| Failure | Degrade to read-only register |

### 2.11 `pos-hardware-diag`

| Property | Value |
|----------|-------|
| Purpose | Scheduled device health checks |
| Jobs | `DeviceHealthCheckJob`, `PrinterTestJob` |
| Priority | low |
| Schedule | Every 15 min when register open |
| Retry | 2×60s |

### 2.12 `pos-offline-sync`

| Property | Value |
|----------|-------|
| Purpose | Process `rateb_pos_sync_queue` batches |
| Jobs | `ProcessSyncBatchJob`, `ReplayFailedSyncJob` |
| Priority | high on reconnect |
| Retry | 5×120s |
| Ordering | FIFO per terminal_id |
| Conflict | Emit `SyncConflictDetected`; no auto-merge |

### 2.13 `pos-critical`

| Property | Value |
|----------|-------|
| Purpose | Recovery jobs for failed sale post-commit steps |
| Jobs | `RecoverIncompleteOrderJob` |
| Priority | critical |
| Retry | 1 (manual investigation after) |

---

## 3. Priority Queues

Laravel queue priorities via multiple queues consumed in order:

```
Worker consume order: pos-critical → pos-kitchen → pos-printing → pos-accounting → pos-inventory → pos-notifications → pos-offline-sync → pos-audit → pos-email/sms/whatsapp → pos-license → pos-hardware-diag
```

Within queue: `high` jobs use dedicated worker pool in enterprise tier.

---

## 4. Retry Strategies

| Strategy | Applies to | Config |
|----------|------------|--------|
| Fixed backoff | print, notifications | `[5, 15, 30]` |
| Exponential | accounting, inventory | `60 * 2^attempt` max 900s |
| No retry | terminal payment user-cancel | 0 |
| Infinite retry | audit | until success or DLQ manual |

### Idempotency keys
All jobs accept `idempotency_key` stored in `rateb_pos_job_dedup` (proposed).

---

## 5. Dead Letter Queue (`pos-dlq`)

| Property | Value |
|----------|-------|
| Storage | Same Redis + `rateb_pos_dlq_log` table |
| Payload | Original job class, payload, exception, attempts |
| Alert | Webhook to ops + control panel widget |
| Replay | Admin action `ReplayDlqJobUseCase` |
| Retention | 90 days |

---

## 6. Offline Synchronization Pipeline

```
Terminal action (offline allowed)
  → LocalIndexedDB queue (client)
  → On reconnect: POST /pos/api/v2/sync/batch
  → ProcessSyncBatchJob (pos-offline-sync)
      → For each item: dispatch appropriate UseCase
      → Conflict: store in rateb_pos_sync_conflicts
      → Success: mark sync_queue row processed
```

**Rules:**
- Server is source of truth for inventory and pricing at sync time.
- Cashier resolves conflicts via Screen 44.

---

## 7. Monitoring

| Metric | Alert threshold |
|--------|-----------------|
| Queue depth `pos-printing` | > 50 for 5 min |
| DLQ insert rate | > 10/hour |
| `pos-audit` failure | any |
| Job latency p95 `pos-accounting` | > 120s |
| Offline sync backlog | > 500 per terminal |

---

## 8. Worker Deployment

| Environment | Workers |
|-------------|---------|
| Single store | 1 worker, all queues |
| Multi-branch | 2 workers, split print/kitchen |
| Enterprise | Dedicated accounting + inventory workers |

Horizon recommended for visibility (optional Phase 2).

---

*End of POS-V2-QUEUE-ARCHITECTURE.md*
