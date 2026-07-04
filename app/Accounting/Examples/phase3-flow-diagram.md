# Accounting Gateway — Phase 3 Event Flow

## When ACCOUNTING_EVENT_STORE_ENABLED = false (default)

```
postAccountingEvent()
    └── AccountingGateway::post()
            ├── AccountingEventValidator::validate()
            └── Adapter::post()  →  rateb_* | financial_* | control_* | ledger_*
```

## When ACCOUNTING_EVENT_STORE_ENABLED = true

```
postAccountingEvent()
    └── AccountingEventPipeline::post()     ← NEW (does NOT modify AccountingGateway)
            │
            ├── 1. Resolve event_uuid
            ├── 2. AccountingIdempotency::wasProcessed()  → skip if duplicate
            ├── 3. AccountingEventStore::persistPending()   → accounting_events (immutable payload)
            ├── 4. AccountingAuditService::log('event_created')
            ├── 5. AccountingAuditService::log('gateway_route')
            │
            ├── 6. AccountingGateway::post()                ← UNCHANGED core gateway
            │       ├── validate
            │       └── Adapter::post()
            │
            ├── 7. AccountingEventStore::markProcessed() | markFailed()
            ├── 8. AccountingIdempotency::markProcessed()
            └── 9. AccountingAuditService::log('adapter_executed')
```

## Replay (admin, ACCOUNTING_REPLAY_ENABLED)

```
AccountingReplayEngine::replay(filters)
    └── FOR EACH accounting_events row:
            ├── optional: Idempotency::clear() if force=true
            ├── payload.metadata.replay = true
            └── AccountingEventPipeline::post(payload)
```

## Unified Reporting (read-only)

```
AccountingReportService::trialBalance()
    └── AccountingNormalizer::normalizeAll()
            ├── fromRatebErp()      → rateb_journal_lines
            ├── fromMainSite()      → journal_entry_lines
            ├── fromControlPanel()  → control_journal_entry_lines
            └── fromLedger()        → ledger_entries
    └── aggregate → AccountingReportRow DTO
```
