# RATIB HR Mobile — Architecture (Phase 0)

## Principle

```
┌─────────────────────────────┐
│   RATIB HR Mobile (Flutter) │  ← presentation only
│   UI · Navigation · Theme   │
└──────────────┬──────────────┘
               │ thin adapters (Phase 1+)
               ▼
┌─────────────────────────────┐
│         RATIB ERP           │  ← single source of truth
│ Auth · RBAC · HR Services   │
│ Offline Engine · Sync       │
└─────────────────────────────┘
```

## Boundaries

| Layer | Owns | Must not own |
|-------|------|--------------|
| Mobile | Screens, nav, l10n, theme, secure token store | Attendance/leave/payroll/approval rules |
| Adapters (future) | Map UI events → existing ERP endpoints/services | New validation or workflows |
| ERP | Business logic, DB, RBAC, offline queues/replay | — |

## App structure

```
lib/
  core/
    config/      App constants (Phase 0)
    theme/       Material 3
    routing/     go_router skeleton
    network/     Reserved — adapters later
    storage/     Reserved — secure storage later
    security/    Reserved — biometric unlock later
  features/      ESS modules (placeholders in Phase 0)
  shared/widgets/
  l10n/
  main.dart
```

## Navigation

- Login (outside shell)
- Stateful shell with **5** bottom tabs
- Nested routes under Attendance / Leave / Requests / More

## Offline (future)

Reuse **existing** ERP offline engine only:

- `attendance.create`
- `leave_request.draft`

No new queues. Check Out / permission / request writes remain online-only per Architecture Lock.

## Identity / Auth (future)

- Online ERP = Authentication Authority
- Device biometric = unlock stored session/token only
- No PIN system
- No password storage in Identity vault

## Out of scope forever for this app

- Extending `rateb_mobile` (workforce portal)
- Capacitor wrapping Admin ERP as ESS
- HR administration (employees, payroll runs, recruitment admin) — Phase 2 / desktop ERP
