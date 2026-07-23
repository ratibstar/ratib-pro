# Subscription Engine — Phase 7B Suspension Enforcement (Feature Flag)

**Status:** Feature-flagged access enforcement  
**Depends on:** Phase 7A SuspensionEngine + Phase 6 grace  
**Default:** `SUBSCRIPTION_ENFORCEMENT_ENABLED=false` — **ERP unchanged**

---

## 1. Enforcement Gate

`SubscriptionEnforcementGate`

- Flag OFF → always `ALLOW` (`enforcement_flag_off`)
- Flag ON → `DENY` when `shouldSuspend()` **or** `isSuspended()`, unless path allow-listed
- Logs DENY (and allow-list hits) via `error_log`

`SubscriptionAccessDecision` — immutable ALLOW/DENY + reason + redirect path.

`SubscriptionEnforcementMiddleware` — wires gate into ERP + API stacks; **fail-open** on internal errors.

---

## 2. Feature flag

| Name | Default |
|---|---|
| `SUBSCRIPTION_ENFORCEMENT_ENABLED` | **false** |

Enable:

```php
define('SUBSCRIPTION_ENFORCEMENT_ENABLED', true); // config/env
// or
SUBSCRIPTION_ENFORCEMENT_ENABLED=1  # .env / server env
```

Helper: `rateb_subscription_enforcement_enabled()`.

### Instant rollback

Unset/define false → next request behaves as Phase 7A shadow (no redirects).

---

## 3. Renewal placeholder page

Routes (auth required, allow-listed when suspended):

| Path | Page |
|---|---|
| `/admin/subscription/renew` | Status: Subscription expired + company / expiry / grace end |
| `/admin/subscription/invoices` | Placeholder |
| `/admin/subscription/payment-status` | Placeholder |
| `/admin/subscription/support` | Placeholder |
| `/admin/support` | Alias |

**No payment / no auto-renewal.** Renew button disabled.

---

## 4. Allow list while suspended

- `subscription/renew`
- `subscription/invoices`
- `subscription/payment-status`
- `subscription/support` / `support`
- `logout`

Everything else → redirect to renew (or JSON 403 on API).

---

## 5. Security

Enforcement is **tenant-level**. No bypass for company admin/owner/API roles.  
Platform super-admin without company context is unaffected; with ops company selected, the selected tenant is enforced.

---

## 6. Test report

`php rateb-erp/modules/subscription/tests/SubscriptionEnforcementPhase7bTest.php`

| # | Case | Expected |
|---|---|---|
| 1 | Flag OFF | ERP works (ALLOW) |
| 2 | Flag ON + Active | ALLOW |
| 3 | Flag ON + Grace | ALLOW |
| 4 | Flag ON + Suspended eligible | DENY → renew |
| 5 | Renewal / allow-list URL | ALLOW |

---

## 7. Wiring

- `rateb_erp_mw` includes `SubscriptionEnforcementMiddleware`
- `CompanySaaSMiddleware` invokes gate when company context set
- `ApiAuthMiddleware` invokes gate after tenant bind
- Routes: `routes/modules/subscription.php` in manifest

---

## Rollback instructions

1. Set `SUBSCRIPTION_ENFORCEMENT_ENABLED=0` or remove the define.  
2. No code deploy required for rollback if flag is env-based.  
3. Confirm `rateb_subscription_enforcement_enabled()` is false.  
4. ERP access returns to pre-7B behavior immediately.
