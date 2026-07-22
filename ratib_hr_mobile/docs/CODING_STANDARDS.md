# Coding Standards — RATEB HR Mobile

## Language & UI

- Arabic is primary (RTL-first); English supported.
- Material 3 only.
- Card-based layouts; **no data tables**.
- Minimum touch target **48dp**.
- One-handed primary actions where possible.

## Architecture rules

1. **No business logic** in widgets or feature folders.
2. **No local databases** for HR entities.
3. **No duplicated validation** of attendance, leave, payroll, or approvals.
4. ERP adapters (when added) live under `lib/core/network/` and only forward to existing ERP contracts.
5. If a screen needs new ERP rules → **STOP** and report; do not invent.

## Dart style

- `package:` imports only (`always_use_package_imports`).
- Prefer `const` constructors.
- `avoid_print` — use structured logging later.
- Feature code must not import ERP PHP or scrape HTML.

## Git / secrets

- Never commit `.env`, keystores, or tokens.
- `flutter_secure_storage` for tokens only (Phase 1+).

## Testing

- Widget tests for shell/navigation in Phase 0+.
- Adapter contract tests against ERP fixtures in Phase 1+ (no invented fixtures that imply new rules).

## Naming

- Routes: `AppRoutes`
- Screens: `*Page` / `*Screen`
- Adapters (future): `*ErpAdapter` — never `*Service` that owns domain rules
