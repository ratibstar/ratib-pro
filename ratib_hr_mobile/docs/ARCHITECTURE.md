# RATEB HR Mobile — Architecture

**Official product:** Employee Self-Service (ESS)  
**Project:** `ratib_hr_mobile` only  
**Source of truth:** RATEB ERP  
**Roadmap:** see [ROADMAP.md](ROADMAP.md) (Phase **A0** before Phase **A**)

## Principle

```
┌─────────────────────────────┐
│   RATEB HR Mobile (Flutter) │  ← presentation only
│   UI · Navigation · Theme   │
└──────────────┬──────────────┘
               │ thin adapters
               ▼
┌─────────────────────────────┐
│         RATEB ERP           │  ← single source of truth
│ Auth · RBAC · HR Services   │
│ Mobile Apps Management      │
│ Offline Engine · Sync       │
└─────────────────────────────┘
```

## Boundaries

| Layer | Owns | Must not own |
|-------|------|--------------|
| Mobile | Screens, nav, l10n, theme, secure token store | Attendance/leave/payroll/approval rules |
| Adapters | Map UI events → existing ERP endpoints | New validation or workflows |
| ERP | Business logic, DB, RBAC, offline queues/replay, tenant mobile config | — |

## Extensibility (no restructure)

Today the app is **ESS-only**. Future modules must be addable as new `features/*` (and ERP APIs) without renaming the tree:

- Manager
- HR (admin-lite)
- Supervisor
- CEO Dashboard

```
lib/
  core/           # shared infra — auth, http, config, DI, contracts
  features/
    login/
    home/
    attendance/
    leave/
    …             # ESS today
    # manager/    # future — add folder, do not fork project
    # supervisor/
    # ceo_dashboard/
  shared/         # design system + shell
```

Sibling products (`rateb_mobile`, Capacitor ERP shell, Tracking) stay **untouched**. They must not be merged into this tree. Shared capability belongs in **ERP APIs/services**.

## App structure

```
lib/
  core/
    config/
    theme/
    routing/
    network/
    storage/
    security/
    contracts/
    adapters/
    env/
  features/      ESS (+ future role modules)
  shared/
  l10n/
  main.dart
android/         Phase A0
ios/             Phase A0
```

## Native IDs (Phase A0)

| Flavor | applicationId / bundle |
|--------|------------------------|
| production | `sa.rateb.hr.mobile` |
| staging | `sa.rateb.hr.mobile.stg` |
| dev | `sa.rateb.hr.mobile.dev` |

## Offline

Reuse **existing** ERP offline engine only:

- `attendance.create`
- `leave_request.draft`

No new queues. Check-out remains online-only per Architecture Lock.

## Identity / Auth

- Online ERP = Authentication Authority (`POST /api/v1/auth/token`)
- Device biometric = unlock stored session/token only
- No PIN system
- No password storage in Identity vault

## Isolation — do not touch

- `rateb_mobile` (workforce portal)
- Capacitor Admin / Tracking wrappers
- Inventing HR administration workflows in Flutter
