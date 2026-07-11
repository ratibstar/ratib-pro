# Phase 5 — Procurement Offline (Tier 1)

**Date:** 2026-07-11  
**Scope:** Procurement offline drafts only — PR / RFQ / PO + supplier directory delta  
**Out of scope:** Approvals, financial posting, supplier payments, accounting entries, schema/API/business-logic changes  
**Flag:** `offline.procurement` default **false** (`RATEB_OFFLINE_PROCUREMENT`)

---

## Repository audit

### Additive / offline-local

| Area | Change |
|------|--------|
| Feature flag | `offline/config/feature-flags.php` — `offline.procurement=false` |
| Replay | `ProcurementOfflineReplayService` → `PurchaseRequest` / `Rfq` / `PurchaseOrder` + `LineItems` + `DocumentCodeService` |
| Tenant/branch guard | `ProcurementOfflineTenantGuard` |
| Supplier delta | `ProcurementOfflineSupplierDirectoryService` (no payment/banking fields) |
| Conflict | `OfflineConflictResolverService::resolveProcurement()` (LWW + `status_changed`) |
| Queue / engine / background | Flag-gated `module=procurement` (replaces hard-reject) |
| Cursor | `supplier_directory` via `OfflineCursorService` |
| Authz | API token ability `procurement` allowed for sync manage |
| Client | `procurement-adapter.js` + SDK **v5.0.0** |
| Config | `modules.php` / `entity-manifest.php` activate procurement ops |
| Compat | Uses existing `OfflineSchema` (no Core ERP change) |

### Explicitly unchanged

- Existing ERP controllers / `ProcurementService` business methods (not modified)
- Existing public APIs
- Database schema (no new migrations)
- Approvals / payments / journals

### Safe offline actions

- `purchase_request.draft`
- `rfq.draft`
- `purchase_order.draft`
- Delta: `supplier_directory`

All drafts force `status=draft`. Idempotency via `[offline:key]` in notes/description.

---

## Security audit

| Check | Result |
|-------|--------|
| Master + procurement flags default OFF | **PASS** |
| Queue rejects procurement when flag OFF | **PASS** |
| Replay skipped when flag OFF | **PASS** |
| Tenant mismatch / branch mismatch guards | **PASS** (source + unit) |
| Supplier assert on PO draft | **PASS** |
| Authz: procurement ability allowed; accounting denied | **PASS** |
| Supplier directory excludes payment fields | **PASS** (select list) |
| No approve/submit/payment/journal in replay | **PASS** |
| Existing APIs unmodified | **PASS** |

---

## Test report

| Suite | Result |
|-------|--------|
| Procurement Phase 5 | **31/31 PASS** |
| Phase 4.5 integration gate | **19/19 PASS** (Critical 0, High 0) |
| Foundation | **26/26 PASS** |
| Inventory Offline | **33/33 PASS** |
| HR Offline | **30/30 PASS** |
| Queue durability 4.5.1 | **15/15 PASS** (prior run in session) |

Runners:
- `php offline/tests/run-procurement-offline-tests.php`
- `php offline/tests/run-phase45-integration-validation.php`

---

## Performance report (unit stress)

| Stress | Result |
|--------|--------|
| Ack evaluate ×3000 | ~4.3 ms |
| Procurement conflict resolve ×2000 | ~1.3 ms |
| Sanitizer ×1000 procurement payloads | ~0.8 ms |

All under thresholds (<3s / <2s).

---

## Production readiness score

| Dimension | Score /10 | Notes |
|-----------|-----------|-------|
| Architecture lock / additive | 9.5 | Offline-local only |
| Security / flags OFF | 9.0 | Default safe |
| Draft-only scope | 9.0 | No approvals/posting |
| Multi-branch guards | 8.5 | Source + unit; live soak optional |
| Tests | 9.0 | 31 dedicated + regression green |
| Staging soak (procurement) | 7.0 | Inv/HR soaked; procurement live soak pending |
| **Weighted readiness** | **8.7 / 10** | **CONDITIONAL GO** for staging enablement |

### Residuals (Medium)

- M-PROC-SOAK-001 — Live staging soak for PR/RFQ/PO drafts still recommended before production flag ON
- Prior: M-DEVICE-001, M-WEBAUTHN-001, M-IDEM-001, M-DUAL-001, M-TRANSPORT-001

### Enablement

```
RATEB_OFFLINE_ENABLED=1
RATEB_OFFLINE_PROCUREMENT=1
```

Keep **OFF** in production until staging soak sign-off.
