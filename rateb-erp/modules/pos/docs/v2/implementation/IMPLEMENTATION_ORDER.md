# RATEB POS V2 — Phase 1 Implementation Order

**Version:** 1.0.0  
**Date:** 2026-07-06  
**Duration:** 4 sprints × 2 weeks = **8 weeks** (Retail MVP)  
**Prerequisite:** Architecture docs approved; this plan approved; no code until sign-off

---

## Sprint Overview

| Sprint | Theme | Exit milestone |
|--------|-------|----------------|
| **Sprint 1** | Foundation & domain core | Flag + migrations + repositories + bootstrap API |
| **Sprint 2** | Business logic & API surface | Full sale flow via API (no UI) |
| **Sprint 3** | V2 UI shell | Touch register usable end-to-end in browser |
| **Sprint 4** | Print, approval hardening, tests, pilot | One terminal production pilot |

---

## Sprint 1 — Foundation (Weeks 1–2)

### Deliverables

| # | Deliverable | Tasks |
|---|-------------|-------|
| D1.1 | Feature flag `POS_V2_ENABLED` + settings resolver | T01 |
| D1.2 | V2 route scaffold + conditional loading | T02, T03, T04 |
| D1.3 | Migration 169 (audit, approval, tokens, snapshots, print_jobs) | T05 |
| D1.4 | Migration 170 (permissions) | T07 |
| D1.5 | V2 models for new tables | T06 |
| D1.6 | Settings validator + defaults | T08 |
| D1.7 | DTO foundation + domain exceptions | T10, T11 |
| D1.8 | Event dispatcher + audit pipeline | T12, T13 |
| D1.9 | Repository adapters (cart, session, shift, order, approval, print) | T14–T19 |
| D1.10 | `AccessRegisterUseCase` + `OpenShiftUseCase` | T20, T21 |
| D1.11 | `RegisterApiController` bootstrap endpoint | T40 (partial) |
| D1.12 | `ShiftApiController` open endpoint | T48 |
| D1.13 | Base V2 API controller + error envelope | T09 |

### Required Tests

| Test | Type |
|------|------|
| `PosV2FeatureServiceTest` | Unit — flag resolution |
| `MoneyDtoTest` | Unit — serialization |
| `AccessRegisterUseCaseTest` | Unit — mock repositories |
| `OpenShiftUseCaseTest` | Unit — mock PosShiftService adapter |
| `RegisterBootstrapTest` | Feature — GET `/pos/api/v2/register/bootstrap` returns 403 without shift |

### Definition of Done

- [ ] Feature flag off → zero V2 routes registered; V1 smoke test passes
- [ ] Feature flag on → bootstrap API returns JSON matching OpenAPI schema
- [ ] Migrations 169–170 apply on clean DB after 168
- [ ] Audit event written on shift open
- [ ] No V1 files modified except `index.php` / `api.php` additive lines
- [ ] PSR-12 lint clean on all new PHP files
- [ ] Code review: no business logic in controllers

### Rollback Strategy

1. Set `POS_V2_ENABLED=false`
2. Remove V2 require lines from `index.php` / `api.php` (optional)
3. V2 tables remain but unused (no data loss)
4. Drop tables only via explicit down migration if needed

### Sprint 1 Risks

| Risk | Mitigation |
|------|------------|
| Autoload fails for V2 | T04 probe first |
| Migration failure on prod | Staging apply + backup |

---

## Sprint 2 — API Complete Sale Flow (Weeks 3–4)

### Deliverables

| # | Deliverable | Tasks |
|---|-------------|-------|
| D2.1 | Catalog UseCases + API | T22, T41 |
| D2.2 | Cart UseCases + API | T23–T26, T42 |
| D2.3 | Customer UseCases + API | T27–T29, T43 |
| D2.4 | Discount UseCases + API | T30–T31, T44 |
| D2.5 | Payment flow UseCases + API | T32–T34, T45 |
| D2.6 | Approval workflow + API | T35–T38, T46 |
| D2.7 | V2 Policies | T50 |
| D2.8 | Settings API | T49 |
| D2.9 | Override price with approval | T38 |

### Required Tests

| Test | Type |
|------|------|
| `AddLineUseCaseTest` | Unit |
| `CompleteSaleUseCaseTest` | Unit — idempotency, rollback |
| `ApproveActionUseCaseTest` | Unit — token TTL, single use |
| `ApplyDiscountUseCaseTest` | Unit — threshold → approval required |
| `CartFlowTest` | Feature — add/update/remove/clear |
| `PaymentCompleteTest` | Feature — full cash sale E2E via API |
| `ApprovalFlowTest` | Feature — override price with manager token |
| `IdempotencyTest` | Feature — duplicate Complete returns 409 |

### Definition of Done

- [ ] Complete retail sale achievable via API only (Postman/curl)
- [ ] OpenAPI endpoints implemented for Phase 1 scope (catalog, cart, customer, discount, payment, approval, shift, bootstrap, settings)
- [ ] All UseCases use DTOs — zero array passing in signatures
- [ ] All mutations authorized via Policy
- [ ] Domain events emitted after commit
- [ ] V1 `pos/api/register/checkout` still works independently
- [ ] Returns/suspend/exchange endpoints **not** exposed in V2

### Rollback Strategy

- Disable V2 flag; V1 register + API unchanged
- V2 orders written to same tables — readable by V1 admin

### Sprint 2 Risks

| Risk | Mitigation |
|------|------------|
| CompleteSale regression | Adapter tests against PosCheckoutService |
| Idempotency collision | Scope key to terminal+session |

---

## Sprint 3 — V2 Register UI (Weeks 5–6)

### Deliverables

| # | Deliverable | Tasks |
|---|-------------|-------|
| D3.1 | CSS design tokens + component styles | T51 |
| D3.2 | V2 shell layout + register page | T52, T66 |
| D3.3 | Shift gate UI | T53 |
| D3.4 | Ticket panel | T54 |
| D3.5 | Catalog grid + search | T55 |
| D3.6 | Payment sheet + numpad | T56 |
| D3.7 | Customer sheet | T57 |
| D3.8 | Approval sheet | T58 |
| D3.9 | JS modules (api, state, cart, catalog, payment, approval) | T59–T65 |
| D3.10 | Connectivity banner (read-only, Phase 1) | partial T51 |
| D3.11 | All mandatory `data-pos-*` hooks | T52–T58 |

### Required Tests

| Test | Type |
|------|------|
| Manual RTL/LTR check | QA — Arabic + English |
| Touch target audit | QA — ≥48px |
| Hook inventory check | Automated — grep `data-pos-` in v2 views |
| JS module load | Browser — no console errors |
| E2E smoke (Playwright optional) | Add product → charge → cash → complete |

### Definition of Done

- [ ] Cashier completes sale on `pos/v2/register` without V1 page
- [ ] No inline CSS or JavaScript in any V2 view
- [ ] Charge button is sole primary CTA; secondaries in More sheet
- [ ] Start/End layout works RTL and LTR
- [ ] V1 register at `pos/register` unchanged and functional
- [ ] Offline banner shows when connectivity lost; Complete blocked (Phase 1 policy)

### Rollback Strategy

- Redirect pilot terminal away from `pos/v2/register`
- V1 URL remains available

### Sprint 3 Risks

| Risk | Mitigation |
|------|------------|
| UX not validated | Internal cashier UAT before pilot |
| Performance on large catalog | Virtual scroll from V1 pattern in catalog module |

---

## Sprint 4 — Print, Hardening, Pilot (Weeks 7–8)

### Deliverables

| # | Deliverable | Tasks |
|---|-------------|-------|
| D4.1 | PrintReceiptUseCase + job | T39, T67 |
| D4.2 | PDF/browser print fallback | T68, T47 |
| D4.3 | Receipt preview partial | views partial |
| D4.4 | Unit test suite completion | T69 |
| D4.5 | Feature test suite completion | T70 |
| D4.6 | OpenAPI CI validation | T71 |
| D4.7 | Pilot terminal runbook | T72 |
| D4.8 | Security: rate limit on approval grant | hardening |
| D4.9 | Deploy verification for v2 assets | ops |

### Required Tests

| Test | Type |
|------|------|
| `PrintReceiptUseCaseTest` | Unit |
| `PrintReceiptJobTest` | Integration |
| Full regression V1 | Manual — 10-step smoke script |
| OpenAPI diff | CI — all Phase 1 paths documented |
| Load test bootstrap | Optional — 50 concurrent requests |

### Definition of Done

- [ ] Receipt prints or PDF fallback after sale
- [ ] Print failure shows user message; sale remains committed
- [ ] 80%+ UseCase unit coverage
- [ ] All Phase 1 API endpoints have feature tests
- [ ] OpenAPI yaml matches implemented routes
- [ ] One production terminal pilot completed (min 50 transactions)
- [ ] Rollback tested on pilot terminal
- [ ] No P1 bugs open

### Rollback Strategy

**Pilot rollback (instant):**
1. Set company `settings_json.v2.enabled = false`
2. Set env `POS_V2_ENABLED=false`
3. Cashiers use `pos/register` (V1)

**Full rollback (if needed):**
1. Remove route requires
2. Tables remain for audit data retention

### Sprint 4 Risks

| Risk | Mitigation |
|------|------------|
| No Redis workers | Sync print fallback in dev; document prod requirement |
| Print hardware absent | PDF fallback default |

---

## Cross-Sprint Dependency Graph

```
Sprint 1: Foundation
    ↓
Sprint 2: API (blocked until repositories + bootstrap done)
    ↓
Sprint 3: UI (blocked until payment API complete)
    ↓
Sprint 4: Print + Tests + Pilot (blocked until UI E2E works)
```

---

## Phase 1 Endpoint Checklist (OpenAPI subset)

| Endpoint | Sprint |
|----------|--------|
| GET `/register/bootstrap` | 1 |
| POST `/shift/open` | 1 |
| GET `/catalog/search` | 2 |
| GET `/catalog/product/{id}` | 2 |
| POST `/cart/lines` | 2 |
| PATCH/DELETE `/cart/lines/{id}` | 2 |
| POST `/cart/clear` | 2 |
| POST `/cart/price-override` | 2 |
| GET `/customer/search` | 2 |
| POST `/customer/attach`, `/detach` | 2 |
| POST `/discount/apply`, `/remove` | 2 |
| POST `/charge/initiate` | 2 |
| POST `/payment/record` | 2 |
| POST `/payment/complete` | 2 |
| POST `/approval/*` | 2 |
| POST `/receipt/print` | 4 |
| GET `/settings` | 2 |

**Explicitly deferred:** returns, suspend, terminal payment, sync, restaurant, pharmacy, hardware (except print job).

---

## Team Roles (recommended)

| Role | Sprint focus |
|------|--------------|
| Backend lead | S1–S2 UseCases, repositories, migrations |
| Frontend lead | S3 assets, hooks, RTL |
| QA | S2 API tests, S3 UAT, S4 pilot |
| DevOps | S1 migrations staging, S4 deploy + workers |

---

## Approval Gate

**No production code until:**

1. ✅ PHASE1_IMPLEMENTATION_AUDIT.md reviewed
2. ✅ PHASE1_TASK_BREAKDOWN.md reviewed
3. ✅ PHASE1_FOLDER_STRUCTURE.md reviewed
4. ✅ COMPATIBILITY_REPORT.md reviewed
5. ✅ IMPLEMENTATION_ORDER.md reviewed
6. ⬜ Product owner signs retail MVP scope
7. ⬜ Engineering lead approves sprint plan

---

*End of IMPLEMENTATION_ORDER.md*
