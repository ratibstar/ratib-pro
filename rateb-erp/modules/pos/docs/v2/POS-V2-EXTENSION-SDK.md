# RATEB POS V2 — Extension SDK

**Version:** 1.0.0  
**Goal:** Third-party modules installable without modifying POS core

---

## 1. Extension Manifest

File: `rateb-pos-extension.json` (package root)

```json
{
  "schema_version": 1,
  "id": "vendor.my-pos-extension",
  "name": "My POS Extension",
  "version": "1.0.0",
  "min_pos_version": "2.0.0",
  "provider": "Vendor\\MyPosExtension\\PosExtensionServiceProvider",
  "permissions": ["pos.ext.my_feature.use"],
  "dependencies": []
}
```

---

## 2. Module Providers

Extensions register via Laravel-style `PosExtensionServiceProvider`:

```php
abstract class PosExtensionServiceProvider
{
    abstract public function register(PosExtensionRegistry $registry): void;
    abstract public function boot(): void;
}
```

**Discovery:** Composer PSR-4 + manifest scan in `bootstrap/extensions.php`.

---

## 3. Service Providers

Extensions may bind services into POS container:

```php
$registry->bind(MyPaymentAdapter::class, MyPaymentAdapter::class);
```

**Rule:** Bind to interfaces only; never replace core singletons without alias.

---

## 4. Hooks

| Hook | Signature | Purpose |
|------|-----------|---------|
| `register.toolbar.secondary` | `(ToolbarBuilder $b): void` | Add More menu items |
| `register.toolbar.charge_area` | `(ChargeAreaBuilder $b): void` | Pre/post Charge buttons |
| `register.payment.methods` | `(PaymentMethodRegistry $r): void` | New tender types |
| `register.receipt.sections` | `(ReceiptBuilder $r): void` | Extra receipt blocks |
| `register.sidebar.items` | `(SidebarBuilder $s): void` | Control panel sidebar |
| `register.catalog.filters` | `(CatalogFilterRegistry $f): void` | Product filters |
| `register.checkout.validators` | `(ValidatorChain $v): void` | Pre-complete validation |
| `register.shift.close.steps` | `(ShiftCloseBuilder $s): void` | Extra close checklist |

**Execution order:** Priority integer (default 100).

---

## 5. Events

Extensions subscribe via:

```php
$registry->listen(OrderCompleted::class, MyListener::class);
```

**Rule:** Listeners must be idempotent and fast; heavy work → queue job.

---

## 6. UI Extensions

### 6.1 Toolbar extensions
Inject buttons into More sheet (max 5 per extension, configurable).

### 6.2 Payment extensions
Register `PaymentMethodDriverInterface` + icon + settlement handler.

### 6.3 Receipt extensions
Append JSON blocks merged into print template.

### 6.4 Sidebar extensions
Control panel links (not register surface — ERP terminology allowed there).

### 6.5 Dashboard widgets
`WidgetProviderInterface` → KPI cards on manager dashboard.

### 6.6 Custom screens
Full-screen overlay via `CustomScreenInterface`:
- Route: `/pos/v2/ext/{extension_id}/{screen_slug}`
- Loaded in iframe shell or native V2 component mount point

---

## 7. Permissions

Extensions declare permissions in manifest; migration generated on install:

```php
'pos.ext.{extension_id}.{action}'
```

Assigned via ERP role management. Register checks via `ExtensionPolicy`.

---

## 8. Menu Injection

Control panel menu entries:

```php
$registry->menu('pos.settings', MenuItem::make('My Settings', '/pos/ext/settings'));
```

Register surface: **hooks only**, not free menu injection (UX guardrail).

---

## 9. API Extensions

Prefix: `/pos/api/v2/ext/{extension_id}/`

- OpenAPI fragment merged at build time
- Middleware: `PosV2Auth`, `ExtensionEnabled`
- Rate limit: 120/min default

**Rule:** Cannot override core V2 routes.

---

## 10. Installation Lifecycle

```
Install package → Validate manifest → Run extension migrations
  → Register provider → Merge permissions → Enable in company settings
  → Cache clear → Extension booted
```

**Disable:** Soft disable in settings; hooks not executed; data preserved.

**Uninstall:** Remove hooks; optional data purge with confirmation.

---

## 11. Sandboxing Rules

| Allowed | Forbidden |
|---------|-----------|
| Hook execution | Direct V1 view edits |
| Interface bindings | Core class overrides |
| Custom API routes under /ext/ | /pos/api/v2/register/* overrides |
| Event subscribe | Database schema changes to core POS tables |
| Additive migrations (prefixed tables) | Inline JS/CSS injection |

---

## 12. Example: Loyalty Points Extension

1. Manifest + provider
2. Hook `register.payment.methods` — "Points redemption"
3. Hook `register.receipt.sections` — points balance
4. Listen `OrderCompleted` — deduct points via CRM API
5. Permission `pos.ext.loyalty.redeem`

---

## 13. Versioning

- Extension API version in `PosExtensionRegistry::API_VERSION`
- Breaking hook changes require major POS version bump
- Deprecation period: 2 minor releases

---

*End of POS-V2-EXTENSION-SDK.md*
