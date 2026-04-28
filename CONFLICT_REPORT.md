# Duplicate & Conflict Report

**Scan Date:** 2025-02-17  
**Scope:** Full project scan for duplicates and conflicts

---

## 1. DUPLICATE FUNCTIONS — REFACTORED

### `buildTapUrl()` — tap-payments
| Location | Status |
|----------|--------|
| `tap-payments/pay.php` | **REMOVED** — now uses shared helper |
| `tap-payments/verify.php` | **REMOVED** — now uses shared helper |
| `tap-payments/helpers.php` | **CREATED** — single implementation with `$params` support and `TAP_LIVE_MODE` check |

### `calculateTax()` — financial calculation
| Location | Status |
|----------|--------|
| `tap-payments/verify.php` | **REFACTORED** — now calls `calculateTax()` |
| `tap-payments/helpers.php` | **CREATED** — single source, uses `TAP_TAX_RATE` from config |
| `tap-payments/config.php` | **UPDATED** — added `TAP_TAX_RATE` constant |

**Note:** Tax `0.15` (15%) still appears in:
- `paypal-checkout/create-order.php` — legacy, uses `$amount * 0.15`
- `pages/home.php` — inline JS (multiple occurrences)
- `paypal-checkout/index.php` — JS `subtotal * 0.15`

**Recommendation:** Add `TAX_RATE` to paypal-checkout config and home.php. Consider a shared JS constant for client-side tax.

---

## 2. DUPLICATE CLASSES — NONE FOUND

No duplicate class definitions across `app/Modules/`.

---

## 3. DUPLICATE ROUTES — NONE FOUND

| Provider | Routes | Conflict |
|----------|--------|----------|
| PaymentServiceProvider | `/api/payments/*` | None |
| SubscriptionServiceProvider | `/api/subscription-plans`, `/api/subscriptions`, `/api/customers/*` | None |

---

## 4. DUPLICATE CSS SELECTORS — REPORTED (NO REFACTOR)

| Selector | Files | Context |
|----------|-------|---------|
| `.card` | `app.css`, `contact.css`, `dashboard.css`, `notifications.css` | BEM vs Bootstrap; different scopes |
| `.btn` | `app.css`, `contact.css`, `dashboard.css`, `modal.css`, etc. | Multiple files |
| `.form-control` | `contact.css`, `hr.css`, `nav.css` | Bootstrap-style |
| `.table` | `app.css`, `contact.css`, `dashboard.css` | Different contexts |
| `.dashboard` | `app.css` (`.dashboard`) vs `dashboard.css` (`.dashboard-content`) | Different classes |

**Assessment:** `app.css` uses BEM (e.g. `.card--financial`). Legacy CSS uses Bootstrap patterns (e.g. `.btn-primary`). Loading both can cause conflicts on shared pages.

**Recommendation:** Ensure pages load only the CSS they need. Use distinct class prefixes for new components (e.g. `ratib-dashboard`).

---

## 5. DUPLICATE JS FUNCTIONS — REPORTED

| Function | Location | Notes |
|----------|----------|-------|
| `updatePaymentSummary` | `pages/home.php` (inline) | Called from multiple handlers |
| Tax calculation `subtotal * 0.15` | `pages/home.php` (lines 884, 1215, 1328) | Duplicated in 3 places |
| Tax calculation | `paypal-checkout/index.php` (line 112) | `subtotal * 0.15` |

**Recommendation:** Extract tax constant and `updatePaymentSummary` to `public/assets/js/app.js` or a shared payments script.

---

## 6. FINANCIAL CALCULATION DUPLICATION — REFACTORED

### Commission calculation
| Location | Formula | Status |
|----------|---------|--------|
| `CommissionService.php` | `amount * (rate/100)` | **SINGLE SOURCE** — uses `config('commission.agency_rate')` |

### Commission description
| Location | Before | After |
|----------|--------|-------|
| `CommissionService.php` | Hardcoded "10%" | **REFACTORED** — uses `sprintf('Commission (%s%%) from payment #%d', $rate, $id)` |

### Ledger account codes
| Location | Before | After |
|----------|--------|-------|
| `WalletService.php` | Hardcoded "1100, 4100" in error | **REFACTORED** — uses `config('ledger.accounts.*')` |

### Tax calculation (tap-payments)
| Location | Before | After |
|----------|--------|-------|
| `verify.php` | `$amount * 0.15` | **REFACTORED** — uses `calculateTax($amount)` from helpers |

---

## 7. REMAINING ITEMS (LOWER PRIORITY)

| Item | Location | Recommendation |
|------|----------|----------------|
| `buildHomeUrl()` | `tap-payments/success.php` | Different purpose than buildTapUrl; keep separate |
| `buildPaymentFailureEmail` vs `buildPaymentVoucherEmail` | `email_helper.php` | Different functions; no duplication |
| Tax in home.php | Multiple inline JS blocks | Extract to shared JS when refactoring home |
| Tax in paypal-checkout | create-order.php, index.php | Add PAYPAL_TAX_RATE to config if standardizing |

---

## 8. REFACTORING SUMMARY

### Files Created
- `tap-payments/helpers.php` — shared `buildTapUrl()`, `calculateTax()`

### Files Modified
- `tap-payments/pay.php` — removed duplicate `buildTapUrl`, uses helpers
- `tap-payments/verify.php` — removed duplicate `buildTapUrl`, uses `calculateTax()`
- `tap-payments/config.php` — added `TAP_TAX_RATE`
- `app/Modules/Commission/Services/CommissionService.php` — dynamic rate in description
- `app/Modules/Wallet/Services/WalletService.php` — config-based error message
- `config/tax.php` — created for Laravel tax config (future use)
