# RATIB HR Mobile — Production Roadmap

**Official product:** Employee Self-Service (ESS)  
**Project:** `ratib_hr_mobile` only  
**Source of truth:** RATIB ERP  
**Status:** Architecture Lock approved (20 Jul 2026) with Phase A0 mandatory before Phase A

---

## Binding rules

1. Do **not** modify `rateb_mobile`, Capacitor projects, Tracking, or other mobile apps.
2. `ratib_hr_mobile` stays isolated (presentation layer only).
3. No business logic in Flutter — thin adapters to ERP APIs only.
4. No code duplication — reuse ERP services/APIs for future apps.
5. Consume ERP auth + HR ESS APIs + Mobile Apps Management (`GET /api/mobile/config`).
6. Architecture must stay **extensible** for future modules without restructuring:
   - Manager
   - HR (admin-lite)
   - Supervisor
   - CEO Dashboard  
   These are **future feature areas / entry roles** in this app or sibling clients that share ERP APIs — not a reason to fork the Flutter tree today.

---

## Phase order (do not skip)

| Phase | Name | Status |
|-------|------|--------|
| **A0** | Native production Flutter shell | **COMPLETE** — see [PHASE_A0.md](PHASE_A0.md) |
| **A** | MobileConfig + white-label + feature flags | **COMPLETE** — see [PHASE_A.md](PHASE_A.md) |
| **B** | Native hardening / device QA (extends A0) | Pending |
| **C** | Enterprise ESS modules | **COMPLETE** — see [PHASE_C.md](PHASE_C.md) |
| **D** | Attendance deep wiring | **COMPLETE** — see [PHASE_D.md](PHASE_D.md) |
| **E** | Leave Management | **COMPLETE** — see [PHASE_E.md](PHASE_E.md) |
| **F** | Payslips + Documents | **COMPLETE** — see [PHASE_F.md](PHASE_F.md) |
| **G** | Profile | **COMPLETE** — see [PHASE_G.md](PHASE_G.md) |
| **H** | Offline hardening | Next |
| **I** | Approvals (if ERP ready) | Optional |
| **J** | Unified Mobile Device Registry | **DESIGN COMPLETE** — see [PHASE_J.md](PHASE_J.md) (implement later) |
| **K** | Store production release | After A–H |

---

## Phase A0 — Native production shell (mandatory)

**Objective:** Prepare `ratib_hr_mobile` as a real production Flutter application.

### Tasks

- [x] Document A0 before A (this file)
- [x] Generate native Android project
- [x] Generate native iOS project
- [x] Configure package / bundle IDs
- [x] Verify Flutter builds successfully
- [x] Verify Android emulator/device path (documented — no AVD on host)
- [x] Verify iOS project generation
- [x] Configure build flavors: `dev` / `staging` / `production`
- [x] Prepare future Play Store / App Store signing structure
- [x] Do **not** implement real store signing secrets yet

### Package IDs

| Flavor | Android `applicationId` | iOS Bundle ID |
|--------|-------------------------|---------------|
| production | `sa.rateb.hr.mobile` | `sa.rateb.hr.mobile` |
| staging | `sa.rateb.hr.mobile.stg` | `sa.rateb.hr.mobile.stg` |
| dev | `sa.rateb.hr.mobile.dev` | `sa.rateb.hr.mobile.dev` |

### Exit criteria

1. `android/` and `ios/` exist and are committed (minus secrets / local.properties).
2. `flutter build apk --flavor production` succeeds.
3. Flavors selectable via Gradle / Xcode xcconfig + `--dart-define=APP_FLAVOR=…`.
4. Signing placeholders present (`key.properties.example`, iOS notes) — no real keystores in git.
5. Phase A (MobileConfig) may start — **A0 exit met**.

---

## Phase A — MobileConfig / white-label (after A0)

- `MobileConfigPort` + `ErpMobileConfigAdapter` → `GET /api/mobile/config`
- Runtime branding (`app_name`, theme, logo)
- Feature flags gate tabs/routes
- Inactive company fail-closed

---

## Extensibility (no restructure later)

```
lib/
  core/           # shared infra (auth, http, config, DI, contracts)
  features/
    login/
    home/
    attendance/
    leave/
    ...
    # future (add folders, do not rename tree):
    # manager/
    # supervisor/
    # ceo_dashboard/
  shared/         # design system + shell widgets
```

- New roles/modules = new `features/*` + ports/adapters calling **ERP APIs**.
- Shared backend services stay in ERP so Manager / Workforce / Driver apps can reuse them without conflicts.
- Shell navigation may later switch by RBAC/role from ERP — without rewriting core.

---

## Explicit non-goals

- Store signing secrets in repo
- MobileConfig / feature UI in A0
- Touching `rateb_mobile` or Capacitor
- Inventing HR business rules in Flutter
