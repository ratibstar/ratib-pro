# RATEB POS V2 — Phase 1 Compatibility Report

**Version:** 1.0.0  
**Date:** 2026-07-06  
**Purpose:** Verify planned V2 files do not conflict with V1  
**Method:** Cross-reference PHASE1_FOLDER_STRUCTURE against repository audit

---

## 1. Executive Verdict

| Check | Result |
|-------|--------|
| **No overwritten V1 files** | ✅ PASS — all V2 in new paths |
| **No replaced V1 classes** | ✅ PASS — new namespaces only |
| **No route conflicts** | ✅ PASS — `/v2/` prefix isolation |
| **No migration conflicts** | ✅ PASS — new tables 169+ only |
| **No namespace conflicts** | ✅ PASS — `V2` sub-namespace |
| **No asset conflicts** | ✅ PASS — `assets/pos/v2/` isolated |
| **V1 operational with flag off** | ✅ PASS — by design |

**Approval status:** ✅ **Compatible — safe to proceed after sign-off**

---

## 2. File Overwrite Analysis

### 2.1 Files marked MODIFY (minimal additive)

| File | Change type | V1 impact if rolled back |
|------|-------------|--------------------------|
| `public/index.php` | Add 3–5 lines: conditional `require pos-v2.php` | Remove lines → V1 only |
| `routes/api.php` | Add 3–5 lines: conditional `require pos-api-v2.php` | Remove lines → V1 only |

**No other existing files will be modified in Phase 1.**

Optional (not required):
| `modules/pos/PosModule.php` | Add static method `isV2Enabled()` | Optional helper only |
| `config/app.php` | Merge V2 entity permissions | Additive array merge |

### 2.2 Files explicitly NOT modified

| Path | Reason |
|------|--------|
| `modules/pos/routes/pos.php` | V1 web routes frozen |
| `modules/pos/routes/pos-api.php` | V1 API routes frozen |
| `modules/pos/app/Controllers/PosRegisterController.php` | V1 register entry |
| `modules/pos/app/Controllers/PosRegisterApiController.php` | V1 cart/checkout API |
| `modules/pos/app/Services/*` | Bridge reuse via adapters, not edits |
| `modules/pos/views/register/index.php` | V1 UI preserved |
| `public/assets/pos/css/pos-register.css` | V1 styles preserved |
| `public/assets/pos/js/pos-register.js` | V1 JS preserved |

---

## 3. Class & Namespace Conflicts

### 3.1 V1 namespace root

```
Rateb\App\Pos\Controllers\PosRegisterController     ← exists
Rateb\App\Pos\Services\PosCheckoutService             ← exists
Rateb\App\Pos\Models\PosOrder                         ← exists
```

### 3.2 V2 namespace root (planned)

```
Rateb\App\Pos\Controllers\V2\RegisterController       ← new
Rateb\App\Pos\UseCases\V2\Payment\CompleteSaleUseCase ← new
Rateb\App\Pos\DTO\V2\Cart\CartResponse                ← new
Rateb\App\Pos\Repositories\V2\Adapters\CartRepository ← new
```

**Autoload rule:** Existing `PosModule` autoload maps `Rateb\App\Pos\` → `app/{path}.php`.  
Subfolder `Controllers/V2/RegisterController.php` → `Rateb\App\Pos\Controllers\V2\RegisterController` — **no collision**.

### 3.3 Prohibited names (will NOT create)

| Forbidden | Conflict with |
|-----------|---------------|
| `PosRegisterController` in V2 folder | V1 controller |
| `PosCheckoutService` in V2/Services | V1 service |
| `CartService` without V2 prefix path | Ambiguity |

---

## 4. Route Conflict Matrix

### 4.1 Web routes

| V1 route | V2 route | Conflict? |
|----------|----------|-----------|
| `GET pos/register` | `GET pos/v2/register` | ❌ No |
| `GET pos/shifts/open` | `POST pos/api/v2/shift/open` | ❌ No |
| `POST pos/api/register/checkout` | `POST pos/api/v2/payment/complete` | ❌ No |

### 4.2 API routes

| V1 prefix | V2 prefix |
|-----------|-----------|
| `/admin/ops/pos/api/register/*` | `/admin/ops/pos/api/v2/*` |
| `/api/v1/pos/register/*` | `/api/v2/pos/*` |

**Verification rule:** Grep route registry before merge — no V2 path without `v2` segment.

### 4.3 Middleware

V2 routes reuse same `$posMw('pos/register')` entity guards — **compatible** with existing RBAC. New policies add finer checks inside controllers.

---

## 5. Migration Conflict Matrix

### 5.1 Existing migration sequence

Latest: `168_pos_reward_reversal_idempotency.sql`

### 5.2 Planned migrations

| Migration | Action | Conflicts |
|-----------|--------|-----------|
| `169_pos_v2_phase1.sql` | CREATE TABLE ×5 | None — new tables |
| `170_pos_v2_permissions.sql` | INSERT permissions | None — additive slugs |

### 5.3 Tables NOT created (already exist)

These appear in `docs/schema-proposal.sql` but **must not** be re-created:

- `rateb_pos_terminals`, `sessions`, `shifts`, `orders`, `sync_queue`, etc.

### 5.4 Columns NOT altered in Phase 1

No `ALTER TABLE` on V1 tables except potential future:
- `settings_json` — no schema change needed (JSON is flexible)

`idempotency_key` on orders — **already exists** (migration 167).

---

## 6. Asset Conflict Matrix

| V1 asset | V2 asset | Browser load |
|----------|----------|--------------|
| `assets/pos/css/pos-register.css` | `assets/pos/v2/css/pos-v2-*.css` | Mutually exclusive per page |
| `assets/pos/js/pos-register.js` | `assets/pos/v2/js/app.js` | Mutually exclusive per page |

**Rule:** V2 shell loads ONLY `v2/` assets. V1 register page loads ONLY V1 assets.

### Cache / CDN

New paths — no cache invalidation of V1 bundles.

---

## 7. Session & Data Compatibility

| Concern | Assessment |
|---------|------------|
| Shared cart session | **Intentional** — V1 and V2 use same `PosSessionService` for pilot rollback |
| Shared shift | Same `PosShiftService` — one open shift per terminal |
| Order idempotency | V2 CompleteSale writes to same `rateb_pos_orders` |
| Settings | V2 reads `settings_json.v2`; V1 ignores unknown keys |

**Risk:** User switches V1↔V2 mid-sale — acceptable for pilot; document in pilot checklist.

---

## 8. Permission Compatibility

| Approach | Compatible? |
|----------|-------------|
| New slugs `pos.v2.*` | ✅ Yes — V1 ignores |
| Reuse `pos.register` for V2 access | ✅ Yes — preferred default |
| Deny V2 if only V1 permission | ✅ Configurable in policy |

No existing permission **removed** or **renamed**.

---

## 9. Hook Compatibility (ADR-015)

V2 views must implement subset of V1 `data-pos-*` hooks.  

**Conflict type:** None — hooks are HTML attributes, not JS global registry.

V1 JS will **not** bind to V2 pages (different asset bundle). V2 JS will bind to same hook names for future extension parity.

---

## 10. Deploy Pipeline Compatibility

Per workspace deploy rules, changed paths auto-upload:

| Path | Deployed on change? |
|------|-------------------|
| `modules/pos/app/**` | ✅ Yes |
| `modules/pos/views/v2/**` | ✅ Yes (under views/) |
| `public/assets/pos/v2/**` | ⚠️ Verify — may need `public/` in deploy script |
| `migrations/169*.sql` | Manual / separate migration run |
| `public/index.php` | ✅ Root file in FAST_FILES |

**Action item before Sprint 1:** Confirm `public/assets/pos/v2/` in deploy core script if not already covered by `public/` glob.

---

## 11. Feature Flag Isolation Test Plan

| Scenario | Expected |
|----------|----------|
| `POS_V2_ENABLED=false`, visit `pos/register` | V1 UI |
| `POS_V2_ENABLED=false`, visit `pos/v2/register` | 404 or redirect to V1 |
| `POS_V2_ENABLED=true`, visit `pos/register` | V1 UI (unchanged unless redirect configured) |
| `POS_V2_ENABLED=true`, visit `pos/v2/register` | V2 UI |
| V1 API `pos/api/register/checkout` | Works unchanged |

---

## 12. Conflict Resolutions (pre-committed)

| ID | Resolution |
|----|------------|
| C1 Duplicate logic | Adapter pattern mandatory — code review gate |
| C4 URL confusion | Document: pilot uses `pos/v2/register` URL explicitly |
| C5 Shared session | Accepted for Phase 1 pilot |
| C9 Route loading | Separate `pos-v2.php` file |
| C10 Deploy | Add v2 assets path to deploy verification |

---

## 13. Sign-Off Checklist

- [x] No V1 file overwrites planned
- [x] Route prefix `/v2/` verified unique
- [x] Migration numbers 169+ verified unused
- [x] Namespace `V2` verified unique under `Rateb\App\Pos`
- [x] Asset path `v2/` verified unique
- [ ] Product owner approves shared-session behavior for pilot
- [ ] Ops confirms migration runbook for 169–170
- [ ] Deploy script verified for `public/assets/pos/v2/`

---

*End of COMPATIBILITY_REPORT.md*
