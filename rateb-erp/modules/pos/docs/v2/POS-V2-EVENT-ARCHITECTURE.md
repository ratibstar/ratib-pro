# RATEB POS V2 — Event Architecture

**Version:** 1.0.0  
**Pattern:** Domain Events (in-process) + Integration Events (async queue)

---

## 1. Event Taxonomy

| Type | Scope | Transport | Example |
|------|-------|-----------|---------|
| Domain Event | Within POS BC | Sync dispatcher after DB commit | `OrderCompleted` |
| Integration Event | Cross-module / external | Queue job | `InventoryStockPosted` |
| System Event | Platform lifecycle | Queue | `PosV2FeatureEnabled` |
| Hardware Event | Device layer | Sync + optional queue | `PrintJobFailed` |
| Audit Event | Compliance | Dedicated audit queue | `AuditActionLogged` |

---

## 2. Event Envelope (standard)

```json
{
  "event_id": "uuid-v7",
  "event_type": "pos.v2.order.completed",
  "event_version": 1,
  "occurred_at": "2026-07-05T12:00:00Z",
  "company_id": 1,
  "branch_id": 2,
  "terminal_id": 5,
  "session_id": 100,
  "correlation_id": "uuid",
  "causation_id": "uuid",
  "actor": { "user_id": 10, "role": "pos_cashier" },
  "payload": {}
}
```

---

## 3. Domain Events

### 3.1 Register / Shift

| Event | Producer | Consumers | Payload | Queue | Priority | Retry | Failure |
|-------|----------|-----------|---------|-------|----------|-------|---------|
| `ShiftOpened` | OpenShiftUseCase | AuditListener, NotificationListener | shift_id, opening_cash | audit | normal | 3×30s | DLQ + alert |
| `ShiftClosed` | CloseShiftUseCase | AccountingBridge, AuditListener | shift_id, variance | accounting, audit | high | 5×60s | DLQ + manager notify |
| `ChargeInitiated` | InitiateChargeUseCase | AnalyticsListener (optional) | session_id, cart_total | — | — | — | log only |
| `SessionRecovered` | RecoverSessionUseCase | AuditListener | session_id, snapshot_version | audit | low | 3×30s | DLQ |

### 3.2 Cart

| Event | Producer | Consumers | Payload | Queue | Priority | Retry | Failure |
|-------|----------|-----------|---------|-------|----------|-------|---------|
| `LineAdded` | AddLineUseCase | CartSnapshotListener | line_id, product_id, qty | — | — | — | — |
| `CartSnapshotSaved` | CartSnapshotService | — | snapshot_id, version | offline_sync | low | 5×60s | DLQ |
| `CartCleared` | ClearCartUseCase | AuditListener | session_id | audit | low | 3×30s | DLQ |

### 3.3 Orders / Payment

| Event | Producer | Consumers | Payload | Queue | Priority | Retry | Failure |
|-------|----------|-----------|---------|-------|----------|-------|---------|
| `OrderCreated` | CompleteSaleUseCase | InventoryBridgeListener | order_id, lines[] | inventory | high | 5×60s | DLQ + block retry UI |
| `OrderCompleted` | CompleteSaleUseCase | ReceiptListener, AccountingListener, CRMListener | order_id, totals, payments[] | print, accounting | high | 5×60s | compensating alert |
| `PaymentRecorded` | RecordPaymentUseCase | AuditListener | payment_id, method, amount | audit | normal | 3×30s | DLQ |
| `TerminalPaymentFailed` | TerminalPaymentService | UI websocket/poll, AuditListener | error_code, order_id | audit | high | 0 | user retry |

### 3.4 Returns

| Event | Producer | Consumers | Payload | Queue | Priority | Retry | Failure |
|-------|----------|-----------|---------|-------|----------|-------|---------|
| `ReturnCompleted` | ProcessReturnUseCase | InventoryBridge, AccountingBridge, AuditListener | return_id, refund_amount | inventory, accounting, audit | high | 5×60s | DLQ |
| `RefundIssued` | ProcessReturnUseCase | ReceiptListener | refund_id | print | normal | 3×30s | manual reprint |

### 3.5 Approvals

| Event | Producer | Consumers | Payload | Queue | Priority | Retry | Failure |
|-------|----------|-----------|---------|-------|----------|-------|---------|
| `ApprovalRequested` | *Restricted use cases* | NotificationListener (manager) | request_id, action | notifications | high | 3×30s | in-app fallback |
| `ApprovalGranted` | ApproveActionUseCase | AuditListener, original action retry | token_id, action | audit | high | 3×30s | DLQ |
| `ApprovalDenied` | DenyActionUseCase | AuditListener | request_id, reason | audit | normal | 3×30s | DLQ |

### 3.6 Discounts

| Event | Producer | Consumers | Payload | Queue | Priority | Retry | Failure |
|-------|----------|-----------|---------|-------|----------|-------|---------|
| `DiscountApplied` | ApplyDiscountUseCase | AuditListener | discount_id, scope, value | audit | normal | 3×30s | DLQ |
| `DiscountApprovalRequired` | ApplyDiscountUseCase | ApprovalWorkflow | cart_id, requested_discount | notifications | high | 3×30s | block action |

---

## 4. Integration Events

| Event | Producer | Consumers | Payload | Queue | Priority | Retry | Failure |
|-------|----------|-----------|---------|-------|----------|-------|---------|
| `InventoryStockReserved` | InventoryBridgeAdapter | ERP inventory worker | reservations[] | inventory | high | 5×60s | release + user msg |
| `InventoryStockPosted` | InventoryBridgeAdapter | ERP inventory | movements[] | inventory | high | 5×60s | DLQ + ops alert |
| `AccountingEntryPosted` | AccountingBridgeAdapter | ERP GL | entry_id, lines[] | accounting | high | 5×60s | DLQ + finance alert |
| `ZatcaInvoiceSubmitted` | ZatcaBridgeAdapter | ZATCA gateway | invoice_uuid | accounting | high | 10×120s | manual resubmit |
| `CustomerLoyaltyUpdated` | CRMBridgeAdapter | CRM module | customer_id, points | — | low | 3×60s | skip |

---

## 5. System Events

| Event | Producer | Consumers | Payload | Queue | Priority | Retry | Failure |
|-------|----------|-----------|---------|-------|----------|-------|---------|
| `PosV2Enabled` | ConfigService | CacheInvalidationListener | company_id | — | — | — | — |
| `OfflineModeEntered` | ConnectivityMonitor | AuditListener, SyncWorker | terminal_id, policy | audit, offline_sync | high | 3×30s | log |
| `OfflineSyncCompleted` | SyncWorker | AuditListener | batch_id, count | audit | normal | 3×60s | DLQ |
| `SyncConflictDetected` | SyncWorker | UI notification | conflict_id | notifications | high | 0 | user resolution |
| `LicenseValidationFailed` | LicenseJob | RegisterGateListener | terminal_id | license | critical | 3×300s | read-only mode |

---

## 6. Hardware Events

| Event | Producer | Consumers | Payload | Queue | Priority | Retry | Failure |
|-------|----------|-----------|---------|-------|----------|-------|---------|
| `PrintJobQueued` | PrintReceiptUseCase | PrintWorker | job_id, template | printing | high | 0 | — |
| `PrintJobCompleted` | PrintWorker | AuditListener | job_id | audit | low | 3×30s | — |
| `PrintJobFailed` | PrintWorker | UI, AuditListener, RetryListener | job_id, error | printing | high | 3×30s | PrintFailureSheet |
| `DrawerOpened` | OpenDrawerUseCase | AuditListener | shift_id, reason | audit | normal | 3×30s | DLQ |
| `ScaleReadingCaptured` | ScaleDriver | AddWeighedProductUseCase | weight, unit | — | — | — | user retry |
| `ScannerDataReceived` | ScannerDriver | BarcodeRouter | barcode, symbology | — | — | — | — |
| `TerminalReady` | PaymentTerminalDriver | PaymentSheet UI | terminal_id | — | — | — | — |
| `DeviceHealthChanged` | HealthCheckJob | DiagnosticsDashboard | device_id, status | hardware_diag | low | 3×60s | alert |

---

## 7. Audit Events

All audit events write to `rateb_pos_audit_events` (proposed table).

| Event | Producer | Payload | Queue | Retention |
|-------|----------|---------|-------|-----------|
| `AuditActionLogged` | AuditMiddleware / Listeners | action, entity, before, after, ip | audit | 7 years (configurable) |
| `PermissionDenied` | PolicyGate | permission, resource | audit | 7 years |
| `PriceOverrideApplied` | OverridePriceUseCase | line_id, old, new, approver | audit | 7 years |
| `EmergencySaleRecorded` | EmergencyCompleteUseCase | order_id, limits_applied | audit | 7 years |

**Audit queue:** `pos-audit` — never drop; DLQ requires manual replay.

---

## 8. Event Dispatcher Architecture

```
UseCase::execute()
  → DB::transaction()
      → Repository persist
  → commit
  → EventDispatcher::dispatch(DomainEvent[])
      → Sync listeners (in-process)
      → IntegrationEventPublisher → Queue::push()
```

### Rules
1. No events inside uncommitted transactions.
2. Listeners must be idempotent (`event_id` dedup table).
3. Integration events versioned (`event_version`).
4. V1 services may emit V2 integration events via adapter only.

---

## 9. Subscription Registry

| Listener | Subscribes to |
|----------|---------------|
| `InventoryBridgeListener` | OrderCreated, ReturnCompleted |
| `AccountingBridgeListener` | OrderCompleted, ShiftClosed, ReturnCompleted |
| `ReceiptPrintListener` | OrderCompleted, RefundIssued |
| `AuditListener` | * (wildcard with filter) |
| `CartSnapshotListener` | LineAdded, LineUpdated, LineRemoved |
| `NotificationListener` | ApprovalRequested, SyncConflictDetected |
| `ExtensionEventBus` | Configurable by extensions |

---

## 10. Failure Handling Matrix

| Severity | Behavior |
|----------|----------|
| Critical (sale post) | Rollback transaction; user sees error; no partial order |
| High (print) | Sale committed; PrintFailureSheet; retry queue |
| Medium (accounting) | Sale committed; DLQ; finance dashboard |
| Low (analytics) | Log and drop after retries |

---

*End of POS-V2-EVENT-ARCHITECTURE.md*
