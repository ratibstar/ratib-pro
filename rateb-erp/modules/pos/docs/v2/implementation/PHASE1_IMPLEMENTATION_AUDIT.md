# RATEB POS V2 — Phase 1 Implementation Audit

**Version:** 1.0.0  
**Date:** 2026-07-06  
**Status:** Read-only audit — no code modified  
**Scope:** Existing `rateb-erp/modules/pos` module + related ERP integration points

---

## 1. Executive Summary

The RATEB POS V1 module is a **mature, production-deployed** subsystem under `rateb-erp/modules/pos/` with 14 controllers, ~50 services, 14 Eloquent-style models, 15 SQL migrations (154–168), dual route files (web + API v1), and 19 static assets. **No Repository layer exists in V1** — services talk to models and bridge services directly.

V2 Phase 1 must be **100% additive**: new `V2/` namespaces, new `views/v2/`, new `public/assets/pos/v2/`, new `routes/pos-v2.php` + `routes/pos-api-v2.php`, gated by `POS_V2_ENABLED` / `settings_json.v2.enabled`.

**V2 code does not exist yet** in the repository (confirmed: no `Controllers/V2/`, no `pos-v2.php`, no `assets/pos/v2/`).

---

## 2. Module Bootstrap

| Component | Path | Notes |
|-----------|------|-------|
| Module class | `modules/pos/PosModule.php` | Autoload, i18n, entity-permissions merge |
| Init hook | `public/index.php` L34–35 | `PosModule::init()` |
| Web routes | `public/index.php` L46 | `require routes/pos.php` |
| API routes | `routes/api.php` L39–40 | `require routes/pos-api.php` |
| Config | `modules/pos/config/module.php` | slug `pos`, route_prefix `admin/ops/pos` |
| Views helper | `app/Support/PosView.php` | Renders from `modules/pos/views/` |

**Safe extension point:** Add conditional `require routes/pos-v2.php` in `index.php` (new lines only). Do **not** edit existing route registrations in `pos.php`.

---

## 3. Existing Controllers (14)

| Controller | Purpose | V1 Route prefix |
|------------|---------|-----------------|
| `PosBaseController` | Abstract: auth, JSON, view guards | — |
| `PosRegisterController` | Register shell (main POS UI) | `pos/`, `pos/register` |
| `PosRegisterApiController` | Cart, catalog, checkout JSON | `pos/api/register/*` |
| `PosOrderOpsApiController` | Suspend, quotes, returns, exchange | `pos/api/register/*` |
| `PosApiController` | Context, sync, pricing preview | `pos/api/*` |
| `PosShiftsController` | Shift open/close/show | `pos/shifts/*` |
| `PosTerminalsController` | Terminal CRUD | `pos/terminals/*` |
| `PosOrdersController` | Order list/show (admin) | `pos/orders/*` |
| `PosReturnsController` | Returns admin page | `pos/returns` |
| `PosCashDrawersController` | Drawer events | `pos/cash-drawers/*` |
| `PosReportsController` | X/Z reports | `pos/reports/*` |
| `PosSettingsController` | POS settings UI | `pos/settings` |
| `PosSyncController` | Sync admin UI | `pos/sync` |
| `PosDashboardController` | POS dashboard | `pos/dashboard` |

**V2 Phase 1 new controllers (planned, not created):** `Controllers/V2/RegisterController`, `RegisterApiController`, `ShiftApiController`, `CatalogApiController`, `CartApiController`, `PaymentApiController`, `ApprovalApiController`, `ReceiptApiController`, `SettingsApiController`.

---

## 4. Existing Services (50 files)

### 4.1 Core domain services (V2 adapter targets)

| Service | Responsibility | V2 reuse |
|---------|----------------|----------|
| `PosContextService` | Tenant/branch/terminal context snapshot | **Adapter** — bootstrap, register context |
| `PosSessionService` | Session + cart lines in session | **Adapter** — cart repository backend |
| `PosRegisterCartService` | Cart lines, totals, normalize | **Adapter** — Cart UseCases MUST delegate |
| `PosShiftService` | Open/close shift | **Adapter** — OpenShiftUseCase |
| `PosCheckoutService` | Complete sale, payments, order | **Adapter** — CompleteSaleUseCase |
| `PosPricingService` | Line/cart pricing, tax | **Adapter** — InitiateCharge, discounts |
| `PosSellPriceService` | Sell price resolution | **Adapter** — catalog display |
| `PosDiscountGuardService` | Discount limits by role | **Adapter** — ApplyDiscountUseCase |
| `PosInventoryReservationService` | Reserve/release stock | **Adapter** — AddLineUseCase |
| `PosReceiptService` | Receipt generation | **Adapter** — PrintReceiptUseCase |
| `PosTerminalService` | Terminal lookup | **Adapter** — shift gate |
| `PosFormLookupService` | Terminal/shift form options | Read-only for V2 gate |
| `PosHardwareManager` | Device registry | Phase 1: read status only |
| `PosPaymentGatewayService` | Card gateway wrapper | Phase 1: cash-only path |

### 4.2 Operations services (Phase 2 — do not duplicate in Phase 1)

| Service | Phase |
|---------|-------|
| `PosReturnService` | 2 |
| `PosExchangeService` | 2 |
| `PosRefundService` | 2 |
| `PosSuspendService` | 2 |
| `PosQuoteService` | 2 |
| `PosOfflineSyncService` | 3 |
| `PosOfflineConflictResolverService` | 3 |
| `PosOrderQueryService` | 2 |
| `PosReportService` | 2+ |
| `PosCashDrawerService` | 2+ |
| `PosRewardService` | 2+ |

### 4.3 Bridge services (mandatory integration path)

| Bridge | ERP domain |
|--------|------------|
| `PosInventoryBridgeService` | Inventory catalog, stock, scope |
| `PosCustomerBridgeService` | CRM customer search/attach |
| `PosAccountingBridgeService` | GL posting |
| `PosZatcaBridgeService` | E-invoicing |
| `PosAuditBridgeService` | ERP audit log |
| `PosBarcodeLookupBridgeService` | Barcode → product |
| `PosBranchBridgeService` | Branch scope |
| `PosWarehouseBridgeService` | Warehouse scope |
| `PosPricingBridgeService` | ERP pricing rules |
| `PosPromotionBridgeService` | Promotions |
| `PosCouponBridgeService` | Coupons |
| `PosGiftCardBridgeService` | Gift cards |
| `PosLoyaltyBridgeService` | Loyalty |
| `PosStoreCreditBridgeService` | Store credit |
| `PosCogsBridgeService` | COGS |
| `PosNotificationBridgeService` | Notifications |
| `PosRewardPolicyBridgeService` | Reward policies |

**Phase 1 bridges required:** Inventory, Customer, Audit, Barcode (optional scan path). Accounting/ZATCA invoked via existing `PosCheckoutService` on complete.

### 4.4 Hardware drivers (Null pattern — Phase 1)

| Driver | Interface |
|--------|-----------|
| `NullPosPrinter` | `PosPrinterInterface` |
| `NullPosScanner` | `PosScannerInterface` |
| `NullPosScale` | `PosScaleInterface` |
| `NullPosCashDrawerHardware` | `PosCashDrawerHardwareInterface` |
| `NullPosCustomerDisplay` | `PosCustomerDisplayInterface` |
| `NullPosNfcReader` | `PosNfcInterface` |
| `NullPaymentGateway` | `PaymentGatewayInterface` |
| `NullPosOfflineSyncTransport` | `PosOfflineSyncTransportInterface` |

---

## 5. Repositories

**None exist in V1.**

V1 pattern: `Service` → `Model` (via `Rateb\App\Core\Model`).

V2 Phase 1 introduces repository **interfaces + implementations** that wrap V1 services (adapter pattern per ADR-002), not raw SQL duplication.

---

## 6. Existing APIs

### 6.1 Web-relative (`admin/ops/pos/api/...`)

| Method | Path | Controller |
|--------|------|------------|
| GET | `api/context` | PosApiController |
| GET/POST | `api/sync/*` | PosApiController |
| GET | `api/pricing/preview` | PosApiController |
| GET/POST | `api/register/session` | PosRegisterApiController |
| GET | `api/register/customers/search` | PosRegisterApiController |
| GET | `api/register/products/search` | PosRegisterApiController |
| GET | `api/register/barcode` | PosRegisterApiController |
| POST | `api/register/pricing` | PosRegisterApiController |
| POST | `api/register/checkout` | PosRegisterApiController |
| POST | `api/register/cart/add` | PosRegisterApiController |
| POST | `api/register/cart/update-line` | PosRegisterApiController |
| POST | `api/register/coupon/validate` | PosRegisterApiController |
| POST | `api/register/gift-card/validate` | PosRegisterApiController |
| GET | `api/register/loyalty/balance` | PosRegisterApiController |
| GET | `api/register/products/*` | PosRegisterApiController |
| POST | `api/register/suspend` | PosOrderOpsApiController |
| GET | `api/register/suspended` | PosOrderOpsApiController |
| POST | `api/register/suspended/{id}/resume` | PosOrderOpsApiController |
| POST/GET | `api/register/quote/*` | PosOrderOpsApiController |
| GET/POST | `api/register/orders/*`, `return`, `exchange` | PosOrderOpsApiController |
| GET | `api/reports/shifts/{id}/x` | PosReportsController |

### 6.2 REST API v1 (`/api/v1/pos/...`)

Mirror of register + ops + context endpoints in `routes/pos-api.php` (38 routes).

### 6.3 V2 API (planned — OpenAPI)

Prefix: **`/pos/api/v2/`** (web) and **`/api/v2/pos/`** (REST mirror).

**No overlap with V1** if prefix is strictly `v2`.

---

## 7. Existing Routes (Web UI)

| Path | Controller@action |
|------|-------------------|
| `pos/dashboard` | PosDashboardController@index |
| `pos/`, `pos/register` | PosRegisterController@index |
| `pos/terminals/*` | PosTerminalsController |
| `pos/shifts/*` | PosShiftsController |
| `pos/reports/*` | PosReportsController |
| `pos/cash-drawers/*` | PosCashDrawersController |
| `pos/orders/*` | PosOrdersController |
| `pos/returns` | PosReturnsController@index |
| `pos/settings` | PosSettingsController@index |
| `pos/sync` | PosSyncController@index |

**V2 planned web route:** `pos/v2/register` (new only). V1 `pos/register` unchanged.

---

## 8. Existing Assets

### 8.1 CSS (`public/assets/pos/css/`)

| File | Active |
|------|--------|
| `pos-register.css` | ✅ Primary register (~2400 lines) |
| `pos-register-checkout.css` | ✅ Payment sheet |
| `pos-register-ops.css` | ✅ Returns/ops overlays |
| `pos-register-motion.css` | ✅ Motion |
| `pos-module.css` | ✅ Admin module |
| `pos-touch.css` | ✅ Touch targets |
| `archive/*` | ❌ Deprecated, not loaded |

### 8.2 JavaScript (`public/assets/pos/js/`)

| File | Purpose |
|------|---------|
| `pos-register.js` | Main register controller |
| `pos-register-tiles.js` | Product grid |
| `pos-register-checkout.js` | Payment sheet |
| `pos-register-ops.js` | Returns, suspend, exchange |
| `pos-register-motion.js` | Animations |
| `pos-module.js` | Admin pages |
| `pos-offline-sync.js` | Offline queue client |
| `pos-keyboard.js` | Keyboard shortcuts |

**V2 assets path (planned):** `public/assets/pos/v2/css/`, `public/assets/pos/v2/js/` — no conflict with V1 paths.

---

## 9. Existing DOM Hooks (`data-pos-*`)

**Critical for ADR-015.** V2 views must preserve hook names.

| Source file | Hook count (approx) |
|-------------|---------------------|
| `views/register/index.php` | 57 |
| `views/partials/pos-register-modes.php` | 78 |
| `views/partials/pos-v3-header.php` | 9 |
| `views/partials/pos-register-header.php` | 4 |
| `views/partials/pos-commercial-header.php` | 9 |
| `views/partials/pos-premium-toolbar.php` | 9 |

**Phase 1 mandatory hooks (retail MVP):**

`data-pos-register`, `data-pos-shift-form`, `data-pos-shift-open`, `data-pos-cart-lines`, `data-pos-cart-empty`, `data-pos-subtotal`, `data-pos-tax`, `data-pos-total`, `data-pos-checkout-open`, `data-pos-pay-amount`, `data-pos-charge`, `data-pos-product-grid`, `data-pos-categories`, `data-pos-product-search`, `data-pos-barcode-input`, `data-pos-focus-customer`, `data-pos-toolbar-customer`, `data-pos-checkout-panel`, `data-pos-checkout-complete`, `data-pos-payment-methods`, `data-pos-keypad`, `data-pos-change-due`, `data-pos-connection-status`, `data-pos-new-sale`, `data-pos-clear-cart`.

Full inventory to be captured in V2 view checklist during Sprint 2.

---

## 10. Existing Permissions

### 10.1 Entity map (`config/entity-permissions.php`)

| Entity | View | Manage |
|--------|------|--------|
| `pos` | `pos.view` | `pos.manage` |
| `pos/register` | `pos.register` | `pos.register` |
| `pos/terminals` | `pos.view` | `pos.terminal.manage` |
| `pos/shifts` | `pos.view` | `pos.shift.close` / post: `pos.shift.open` |
| `pos/cash-drawers` | `pos.view` | `pos.cash_drawer.manage` |
| `pos/orders` | `pos.orders.view` | `pos.manage` |
| `pos/settings` | `pos.view` | `pos.settings.manage` |
| `pos/sync` | `pos.view` | `pos.sync.manage` |
| `pos/reports` | `pos.reports.view` | `pos.reports.z` |
| `pos/returns` | `pos.orders.view` | `pos.returns.manage` |

### 10.2 Role mapping (`config/role-mapping.php`)

Roles: `pos_cashier`, `pos_supervisor`, `pos_manager`, plus ERP roles with POS bundles.

### 10.3 V2 permissions to add (Phase 1 migration 169+)

Per use case catalog (new slugs, additive):

- `pos.v2.access`
- `pos.cart.modify`, `pos.cart.clear`
- `pos.catalog.view`
- `pos.sale.complete`
- `pos.payment.record`
- `pos.discount.apply`
- `pos.price.override`
- `pos.approval.grant`, `pos.approval.request`
- `pos.receipt.print`
- `pos.register.access` (alias or new)

**Rule:** Map V2 policies to existing slugs where equivalent (`pos.register` → register access) to avoid role migration pain.

---

## 11. Existing Migrations (154–168)

| # | File | Tables / changes |
|---|------|------------------|
| 154 | `154_pos_core.sql` | terminals, sessions, shifts, cash_drawers, cash_drawer_events, settings |
| 155 | `155_pos_sales.sql` | orders, order_lines, payments |
| 156 | `156_pos_returns_loyalty.sql` | returns, loyalty_accounts, gift_cards, coupons |
| 157 | `157_pos_sync_reports.sql` | sync_queue, inventory_reservations, reports_snapshots |
| 159 | `159_pos_role_permissions.sql` | RBAC seeds |
| 160 | `160_pos_checkout.sql` | checkout columns on orders |
| 161 | `161_pos_pricing.sql` | price_groups, branch_prices, group_prices, promotions |
| 162 | `162_pos_post_sale_ops.sql` | refunds, store_credit, suspend/quote columns |
| 163 | `163_pos_batch_serial_lifecycle.sql` | batch_ledger, serial_history |
| 164 | `164_pos_accounting.sql` | gl_postings |
| 165 | `165_pos_rewards.sql` | loyalty_ledger, gift_card_ledger, coupon_redemptions, promotion_applications |
| 166 | `166_pos_reward_reversal_reports.sql` | reward_reversals, report_snapshots |
| 167 | `167_pos_production_hotfixes.sql` | idempotency catch-up |
| 168 | `168_pos_reward_reversal_idempotency.sql` | unique key fix |

**Existing V1 tables:** 30+ `rateb_pos_*` tables. All satisfy core retail operations.

### 11.1 Phase 1 NEW tables (not yet in repo)

Per FINAL ARCHITECTURE AUDIT §11:

| Table | Purpose |
|-------|---------|
| `rateb_pos_audit_events` | Append-only V2 audit |
| `rateb_pos_approval_requests` | Manager approval workflow |
| `rateb_pos_approval_tokens` | Short-lived tokens |
| `rateb_pos_cart_snapshots` | Session recovery (schema ready; full use Phase 2) |
| `rateb_pos_print_jobs` | Async print queue |
| `rateb_pos_job_dedup` | Queue idempotency (optional Sprint 4) |

**Next migration number:** `169_pos_v2_phase1.sql`

**Note:** `docs/schema-proposal.sql` duplicates core tables already in 154–157 — **do not re-apply**. Use only for reference.

### 11.2 Existing columns relevant to V2

- `rateb_pos_orders.idempotency_key` — already exists (167 catch-up)
- `rateb_pos_settings.settings_json` — store `v2` config block
- `rateb_pos_terminals.device_meta` — terminal-level V2 overrides

---

## 12. Existing Models (14)

`PosTerminal`, `PosSession`, `PosShift`, `PosCashDrawer`, `PosCashDrawerEvent`, `PosOrder`, `PosOrderLine`, `PosPayment`, `PosInventoryReservation`, `PosSyncQueueItem`, `PosSetting`, `PosRefund`, `PosStoreCreditAccount`, `PosStoreCreditLedger`, `PosReturnRecord`

All in `app/Models/PosModels.php` barrel file.

**V2 Phase 1 new models (additive file or barrel extension):** `PosAuditEvent`, `PosApprovalRequest`, `PosApprovalToken`, `PosCartSnapshot`, `PosPrintJob`.

---

## 13. Bridge Candidates for Phase 1

| V2 UseCase | Bridge / V1 Service |
|------------|---------------------|
| AccessRegisterUseCase | `PosContextService`, `PosSessionService` |
| OpenShiftUseCase | `PosShiftService` |
| SearchCatalogUseCase | `PosInventoryBridgeService` |
| AddLineUseCase | `PosRegisterCartService`, `PosInventoryBridgeService`, `PosInventoryReservationService` |
| UpdateLineUseCase / RemoveLineUseCase / ClearCartUseCase | `PosRegisterCartService` |
| SearchCustomerUseCase / AttachCustomerUseCase | `PosCustomerBridgeService`, `PosSessionService` |
| ApplyDiscountUseCase | `PosPricingService`, `PosDiscountGuardService` |
| InitiateChargeUseCase | `PosPricingService`, `PosRegisterCartService` |
| RecordPaymentUseCase | Session payment state (new V2 layer) → `PosCheckoutService` |
| CompleteSaleUseCase | `PosCheckoutService` |
| PrintReceiptUseCase | `PosReceiptService`, `NullPosPrinter` |
| ApproveActionUseCase | New V2 domain + `PosAuditBridgeService` |

---

## 14. Potential Conflicts

| # | Conflict | Severity | Mitigation |
|---|----------|----------|------------|
| C1 | Duplicating cart/pricing logic in V2 | **High** | Adapter-only to `PosRegisterCartService` / `PosPricingService` |
| C2 | V2 route `/pos/api/v2` vs V1 `/pos/api/register` | Low | Strict prefix separation |
| C3 | Autoload namespace collision | Low | All V2 under `Rateb\App\Pos\*\V2\*` or `Controllers/V2` |
| C4 | `pos/register` URL — which UI loads | Medium | New URL `pos/v2/register`; optional redirect behind flag |
| C5 | Session cart shared between V1 and V2 | Medium | Same `PosSessionService` backend — intentional for pilot rollback |
| C6 | CSRF / auth middleware differences | Medium | Reuse `PosBaseController` patterns |
| C7 | Permission slug proliferation | Low | Map to existing slugs first |
| C8 | Queue worker absence in dev | Medium | Sync fallback for print jobs in dev |
| C9 | `PosModule.php` route loading | Low | Separate require file, not editing `pos.php` |
| C10 | Deploy script may miss new paths | Medium | Add `modules/pos/app/**/V2` to deploy if needed |

---

## 15. Safe Extension Points

| Location | Allowed change |
|----------|----------------|
| `public/index.php` | Add `require pos-v2.php` behind flag check |
| `routes/api.php` | Add `require pos-api-v2.php` |
| `modules/pos/PosModule.php` | Optional: register V2 autoload prefix (additive method) |
| `config/app.php` | Merge V2 entity permissions (same pattern as POS) |
| `rateb_pos_settings.settings_json` | Add `v2` key |
| New migrations 169+ | New tables only |
| `public/assets/pos/v2/` | New directory |
| `modules/pos/views/v2/` | New directory |
| `modules/pos/app/Controllers/V2/` | New directory |
| `.env` / env config | `POS_V2_ENABLED` constant |

---

## 16. Feature Flag Status

| Mechanism | Exists? |
|-----------|---------|
| `POS_V2_ENABLED` env | ❌ Not in codebase |
| `settings_json.v2.enabled` | ❌ Schema documented only |
| `PosV2FeatureService` | ❌ Not implemented |

**Phase 1 Task 1:** Implement feature flag resolver (env OR company setting OR terminal override).

---

## 17. Phase 1 Scope Boundary (from architecture docs)

### In scope
- Shift gate UI (V2)
- Register bootstrap API
- Catalog search + product grid
- Cart CRUD
- Customer attach
- Basic discount (pre-Charge)
- Charge → payment sheet → complete sale (cash primary)
- Receipt print queue (PDF/browser fallback)
- Basic manager approval (price override, high discount)
- V2 shell assets
- Audit event writes
- OpenAPI contract tests

### Out of scope (Phase 2+)
- Returns / exchange / suspend / quotes
- Session recovery UI
- Close shift V2 UX (use V1 shift close)
- Card terminal (N-Genius)
- Offline emergency sales
- Restaurant / pharmacy profiles
- Extension SDK implementation
- ZATCA template V2 redesign

---

## 18. Audit Conclusion

The repository is **ready for V2 scaffolding**. V1 is feature-complete for retail and must remain the default. All Phase 1 work can proceed through **new files only**, with **minimal additive hooks** in `index.php`, `api.php`, and permission migration.

**No files were modified during this audit.**

---

*End of PHASE1_IMPLEMENTATION_AUDIT.md*
