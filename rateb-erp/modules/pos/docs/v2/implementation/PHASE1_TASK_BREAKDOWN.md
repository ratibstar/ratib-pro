# RATEB POS V2 — Phase 1 Task Breakdown

**Version:** 1.0.0  
**Date:** 2026-07-06  
**Scope:** Retail MVP only (per FINAL ARCHITECTURE AUDIT §11)  
**Rule:** Each task is atomic, independently reviewable, and rollback-safe

---

## Task Index

| ID | Task | Complexity | Sprint |
|----|------|------------|--------|
| T01 | Feature flag service | S | 1 |
| T02 | V2 route files (empty scaffold) | S | 1 |
| T03 | Route registration (index.php, api.php) | S | 1 |
| T04 | V2 autoload verification | S | 1 |
| T05 | Migration 169 — V2 tables | M | 1 |
| T06 | V2 models (audit, approval, print, snapshot) | M | 1 |
| T07 | V2 permissions migration | S | 1 |
| T08 | Settings validator + v2 defaults | M | 1 |
| T09 | Base V2 controller + error envelope | S | 1 |
| T10 | DTO foundation (Money, Error, Pagination) | M | 1 |
| T11 | Domain exceptions + error codes | S | 1 |
| T12 | Event dispatcher scaffold | M | 1 |
| T13 | Audit listener + WriteAuditEventJob | M | 1 |
| T14 | Cart repository adapter | M | 1 |
| T15 | Shift repository adapter | S | 1 |
| T16 | Session repository adapter | S | 1 |
| T17 | Order/Payment repository adapter | M | 1 |
| T18 | Approval repository | M | 1 |
| T19 | Print job repository | S | 1 |
| T20 | AccessRegisterUseCase | M | 1 |
| T21 | OpenShiftUseCase | M | 1 |
| T22 | SearchCatalogUseCase | M | 1 |
| T23 | AddLineUseCase | M | 1 |
| T24 | UpdateLineUseCase | S | 1 |
| T25 | RemoveLineUseCase | S | 1 |
| T26 | ClearCartUseCase | S | 1 |
| T27 | SearchCustomerUseCase | S | 1 |
| T28 | AttachCustomerUseCase | S | 1 |
| T29 | DetachCustomerUseCase | S | 1 |
| T30 | ApplyDiscountUseCase | M | 2 |
| T31 | RemoveDiscountUseCase | S | 2 |
| T32 | InitiateChargeUseCase | M | 2 |
| T33 | RecordPaymentUseCase | M | 2 |
| T34 | CompleteSaleUseCase | L | 2 |
| T35 | RequestApprovalUseCase | M | 2 |
| T36 | ApproveActionUseCase | M | 2 |
| T37 | DenyActionUseCase | S | 2 |
| T38 | OverridePriceUseCase | M | 2 |
| T39 | PrintReceiptUseCase | M | 2 |
| T40 | RegisterApiController — bootstrap | M | 2 |
| T41 | CatalogApiController | S | 2 |
| T42 | CartApiController | M | 2 |
| T43 | CustomerApiController | S | 2 |
| T44 | DiscountApiController | S | 2 |
| T45 | PaymentApiController | L | 2 |
| T46 | ApprovalApiController | M | 2 |
| T47 | ReceiptApiController | S | 2 |
| T48 | ShiftApiController | S | 2 |
| T49 | SettingsApiController | S | 2 |
| T50 | V2 policies (Register, Cart, Payment, Approval) | M | 2 |
| T51 | CSS tokens + base layout | M | 3 |
| T52 | V2 register shell view | M | 3 |
| T53 | Shift gate partial | M | 3 |
| T54 | Ticket partial | M | 3 |
| T55 | Catalog partial | L | 3 |
| T56 | Payment sheet partial | L | 3 |
| T57 | Customer sheet partial | M | 3 |
| T58 | Approval sheet partial | M | 3 |
| T59 | JS api client module | M | 3 |
| T60 | JS state store | M | 3 |
| T61 | JS cart module | M | 3 |
| T62 | JS catalog module | M | 3 |
| T63 | JS payment module | L | 3 |
| T64 | JS approval module | M | 3 |
| T65 | JS app bootstrap | M | 3 |
| T66 | RegisterV2Controller (web) | S | 3 |
| T67 | PrintReceiptJob + queue config | M | 4 |
| T68 | PDF/browser print fallback driver | M | 4 |
| T69 | Unit tests — UseCases | L | 4 |
| T70 | Feature tests — V2 API | L | 4 |
| T71 | OpenAPI contract validation CI | M | 4 |
| T72 | Pilot terminal checklist + docs | S | 4 |

**Complexity:** S = ≤4h, M = 4–8h, L = 1–2 days

---

## Detailed Tasks

### T01 — Feature Flag Service

| Field | Value |
|-------|-------|
| **Objective** | Resolve `POS_V2_ENABLED` from env, company settings, terminal override |
| **Dependencies** | None |
| **Files** | `app/Services/V2/PosV2FeatureService.php`, `app/DTO/V2/PosV2FeatureStatus.php` |
| **Complexity** | S |
| **Acceptance Criteria** | Returns false when unset; true when env=1 or `settings_json.v2.enabled=true`; terminal `device_meta.v2` overrides; unit tests pass |
| **Rollback** | Delete new files; no runtime effect |
| **Risk** | Low — read-only until routes gated |

---

### T02 — V2 Route Files Scaffold

| Field | Value |
|-------|-------|
| **Objective** | Create empty route files with middleware placeholders |
| **Dependencies** | T01 |
| **Files** | `routes/pos-v2.php`, `routes/pos-api-v2.php` |
| **Complexity** | S |
| **Acceptance Criteria** | Files exist; no routes registered until controllers ready; PHP lint clean |
| **Rollback** | Delete files |
| **Risk** | None |

---

### T03 — Route Registration

| Field | Value |
|-------|-------|
| **Objective** | Conditionally load V2 routes when feature enabled |
| **Dependencies** | T01, T02 |
| **Files** | `public/index.php` (additive lines), `routes/api.php` (additive lines) |
| **Complexity** | S |
| **Acceptance Criteria** | V1 routes unchanged; V2 routes load only when flag true; V1 works with flag false |
| **Rollback** | Remove added require lines |
| **Risk** | Medium — touches bootstrap; minimal diff only |

---

### T04 — V2 Autoload Verification

| Field | Value |
|-------|-------|
| **Objective** | Confirm `Rateb\App\Pos\Controllers\V2\*` resolves via existing autoload |
| **Dependencies** | None |
| **Files** | `app/Controllers/V2/_AutoloadProbe.php` (temporary, delete after verify) |
| **Complexity** | S |
| **Acceptance Criteria** | Class loads without PosModule changes OR document if PosModule extension needed |
| **Rollback** | Delete probe |
| **Risk** | Low |

---

### T05 — Migration 169 V2 Tables

| Field | Value |
|-------|-------|
| **Objective** | Create audit, approval, token, cart_snapshot, print_jobs tables |
| **Dependencies** | None |
| **Files** | `migrations/169_pos_v2_phase1.sql` |
| **Complexity** | M |
| **Acceptance Criteria** | All CREATE TABLE IF NOT EXISTS; no ALTER on V1 tables; rollback SQL in comment; applies cleanly after 168 |
| **Rollback** | DROP new tables migration 169_down (comment block) |
| **Risk** | Medium — DB change; staging first |

---

### T06 — V2 Models

| Field | Value |
|-------|-------|
| **Objective** | Eloquent-style models for new tables |
| **Dependencies** | T05 |
| **Files** | `app/Models/V2/PosV2Models.php` or extend barrel additively |
| **Complexity** | M |
| **Acceptance Criteria** | Models map to new tables; tenant/branch scoped; no V1 model edits |
| **Rollback** | Delete V2 model file |
| **Risk** | Low |

---

### T07 — V2 Permissions Migration

| Field | Value |
|-------|-------|
| **Objective** | Seed additive permission slugs for V2 policies |
| **Dependencies** | None |
| **Files** | `migrations/170_pos_v2_permissions.sql` |
| **Complexity** | S |
| **Acceptance Criteria** | New slugs inserted; existing roles unchanged unless opt-in mapping added to pos_manager |
| **Rollback** | DELETE FROM permissions WHERE slug LIKE 'pos.v2.%' |
| **Risk** | Low |

---

### T08 — Settings Validator

| Field | Value |
|-------|-------|
| **Objective** | Validate `settings_json.v2` against CONFIGURATION-SCHEMA |
| **Dependencies** | None |
| **Files** | `app/Services/V2/PosV2SettingsValidator.php`, `config/v2/settings-schema.json` |
| **Complexity** | M |
| **Acceptance Criteria** | Invalid config rejected on save; defaults merged on read; schema_version handled |
| **Rollback** | Delete validator; settings remain JSON |
| **Risk** | Low |

---

### T09 — Base V2 API Controller

| Field | Value |
|-------|-------|
| **Objective** | Shared JSON responses, header parsing (terminal, session, approval token, idempotency) |
| **Dependencies** | T10, T11 |
| **Files** | `app/Controllers/V2/PosV2ApiController.php` |
| **Complexity** | S |
| **Acceptance Criteria** | Extends PosBaseController; no business logic; error envelope matches OpenAPI |
| **Rollback** | Delete file |
| **Risk** | Low |

---

### T10 — DTO Foundation

| Field | Value |
|-------|-------|
| **Objective** | Shared DTOs: MoneyDto, ErrorDto, PaginationDto, RegisterContextDto |
| **Dependencies** | None |
| **Files** | `app/DTO/V2/Shared/*.php` |
| **Complexity** | M |
| **Acceptance Criteria** | Immutable readonly classes; jsonSerialize; validation factories |
| **Rollback** | Delete directory |
| **Risk** | Low |

---

### T11 — Domain Exceptions

| Field | Value |
|-------|-------|
| **Objective** | Typed exceptions mapped to HTTP status |
| **Dependencies** | None |
| **Files** | `app/Domain/V2/Exceptions/*.php`, `app/Domain/V2/Enums/PosErrorCode.php` |
| **Complexity** | S |
| **Acceptance Criteria** | Codes match OpenAPI error codes; handler maps to JSON |
| **Rollback** | Delete files |
| **Risk** | Low |

---

### T12 — Event Dispatcher Scaffold

| Field | Value |
|-------|-------|
| **Objective** | Post-commit event dispatch per ADR-005 |
| **Dependencies** | T11 |
| **Files** | `app/Events/V2/EventDispatcher.php`, `app/Events/V2/DomainEventInterface.php` |
| **Complexity** | M |
| **Acceptance Criteria** | Events fire after DB commit; listener registry; no events in V1 path |
| **Rollback** | Delete files |
| **Risk** | Medium — must not affect V1 |

---

### T13 — Audit Pipeline

| Field | Value |
|-------|-------|
| **Objective** | AuditListener + WriteAuditEventJob → rateb_pos_audit_events |
| **Dependencies** | T05, T06, T12 |
| **Files** | `app/Events/V2/Listeners/AuditListener.php`, `app/Jobs/V2/WriteAuditEventJob.php` |
| **Complexity** | M |
| **Acceptance Criteria** | Sensitive actions logged; append-only; queue pos-audit |
| **Rollback** | Disable listener registration |
| **Risk** | Low |

---

### T14–T19 — Repository Adapters

| Task | Wraps | Files |
|------|-------|-------|
| T14 Cart | `PosRegisterCartService`, `PosSessionService` | `Repositories/V2/CartRepository.php`, interface |
| T15 Shift | `PosShiftService` | `Repositories/V2/ShiftRepository.php` |
| T16 Session | `PosSessionService` | `Repositories/V2/SessionRepository.php` |
| T17 Order/Payment | `PosCheckoutService`, models | `Repositories/V2/OrderRepository.php`, `PaymentRepository.php` |
| T18 Approval | New tables | `Repositories/V2/ApprovalRepository.php` |
| T19 Print | `rateb_pos_print_jobs` | `Repositories/V2/PrintJobRepository.php` |

**Acceptance (all):** No raw SQL duplicating V1; interfaces injected into UseCases; unit tests with mocked V1 services.

---

### T20 — AccessRegisterUseCase

| Field | Value |
|-------|-------|
| **Objective** | Bootstrap context + cart + settings for V2 register |
| **Dependencies** | T14, T16, T08, T10 |
| **Files** | `UseCases/V2/Register/AccessRegisterUseCase.php`, `DTO/V2/Register/RegisterBootstrapResponse.php` |
| **Complexity** | M |
| **Acceptance Criteria** | Matches OpenAPI `RegisterBootstrapResponse`; shift required; permissions included |
| **Rollback** | Delete use case |
| **Risk** | Medium — core entry point |

---

### T21 — OpenShiftUseCase

| Field | Value |
|-------|-------|
| **Objective** | Open shift from V2 gate API |
| **Dependencies** | T15, T16, T12, T13 |
| **Files** | `UseCases/V2/Shift/OpenShiftUseCase.php`, request/response DTOs |
| **Complexity** | M |
| **Acceptance Criteria** | Delegates to PosShiftService; emits ShiftOpened; audit logged |
| **Rollback** | Delete; V1 shift form still works |
| **Risk** | Low |

---

### T22–T26 — Cart UseCases

| UseCase | V1 delegation |
|---------|---------------|
| SearchCatalogUseCase | PosInventoryBridgeService |
| AddLineUseCase | CartRepository + reservation service |
| UpdateLineUseCase | CartRepository |
| RemoveLineUseCase | CartRepository |
| ClearCartUseCase | CartRepository |

**Acceptance:** CartResponse DTO matches OpenAPI; no array passing; events LineAdded/Updated/Removed/Cleared.

---

### T27–T29 — Customer UseCases

Delegate to `PosCustomerBridgeService` + session attach/detach.

---

### T30–T34 — Payment Flow UseCases

| UseCase | Notes |
|---------|-------|
| ApplyDiscountUseCase | PosPricingService + PosDiscountGuardService; approval threshold |
| RemoveDiscountUseCase | Pre-Charge only |
| InitiateChargeUseCase | Validates non-empty cart; PaymentSheetResponse |
| RecordPaymentUseCase | In-memory payment lines until complete |
| CompleteSaleUseCase | **Critical** — PosCheckoutService; idempotency_key; transaction boundary |

**T34 Acceptance:** Duplicate idempotency returns 409 with same order; inventory posted; OrderCompleted event; audit full snapshot.

---

### T35–T38 — Approval UseCases

Implement token workflow per ADR-009. OverridePriceUseCase requires approval token when configured.

---

### T39 — PrintReceiptUseCase

Queue PrintReceiptJob; pdf.fallback driver for Phase 1.

---

### T40–T49 — API Controllers

One controller per OpenAPI tag group. Each action ≤15 lines delegating to UseCase.

| Controller | Endpoints |
|------------|-----------|
| RegisterApiController | bootstrap |
| ShiftApiController | open |
| CatalogApiController | search, product detail |
| CartApiController | lines CRUD, clear, price-override |
| CustomerApiController | search, attach, detach |
| DiscountApiController | apply, remove |
| PaymentApiController | initiate, record, complete |
| ApprovalApiController | request, grant, deny |
| ReceiptApiController | print |
| SettingsApiController | get v2 settings |

---

### T50 — Policies

`RegisterPolicy`, `CartPolicy`, `PaymentPolicy`, `ApprovalPolicy` — map to existing + new slugs.

---

### T51–T58 — V2 Views (no inline CSS/JS)

| Task | Output |
|------|--------|
| T51 | `pos-v2-tokens.css`, `pos-v2-base.css` |
| T52 | `views/v2/layouts/pos-v2-shell.php` |
| T53 | `views/v2/partials/shift-gate.php` |
| T54 | `views/v2/partials/ticket.php` |
| T55 | `views/v2/partials/catalog.php` |
| T56 | `views/v2/partials/payment-sheet.php` |
| T57 | `views/v2/partials/customer-sheet.php` |
| T58 | `views/v2/partials/approval-sheet.php` |

**Acceptance:** All mandatory `data-pos-*` hooks present; RTL/LTR; touch targets ≥48px.

---

### T59–T65 — JavaScript Modules

ES modules under `public/assets/pos/v2/js/`:

- `api/client.js` — fetch wrapper, CSRF, headers
- `state/store.js` — single store
- `modules/cart.js`, `catalog.js`, `payment.js`, `approval.js`, `customer.js`
- `app.js` — bootstrap

**Acceptance:** No globals except `window.RatebPosV2`; no inline scripts in views.

---

### T66 — RegisterV2Controller

Web controller serves `views/v2/register/index.php` at `pos/v2/register`.

---

### T67–T68 — Print Queue

PrintReceiptJob on `pos-printing` queue; `PdfFallbackPrinterDriver` or browser print trigger.

---

### T69–T71 — Tests & CI

| Task | Coverage target |
|------|-----------------|
| T69 | UseCases 80%+ |
| T70 | All Phase 1 API endpoints |
| T71 | OpenAPI yaml vs route list diff |

---

### T72 — Pilot Checklist

Document: enable flag on one terminal, test sale E2E, rollback steps.

---

## Dependency Graph (critical path)

```
T01 → T03 → T40–T49 (API)
T05 → T06 → T13, T18
T10 → T09 → T20–T39
T14 → T23–T26 → T34
T20 → T66 → T51–T65 (UI)
T34 → T39 → T67 (print)
T69–T71 (tests) → T72 (pilot)
```

---

*End of PHASE1_TASK_BREAKDOWN.md*
