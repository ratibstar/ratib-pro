# RATIB HR Mobile

**Enterprise Employee Self-Service (ESS)** presentation app for **RATIB ERP**.

| | |
|--|--|
| Phase | **0 — Foundation only** |
| Role | Mobile UI / navigation shell |
| Source of truth | RATIB ERP |
| Not this | `rateb_mobile` workforce portal, Capacitor Admin wrapper, new HR system |

## Phase 0 scope (done)

- Flutter project skeleton (`ratib_hr_mobile`)
- Folder architecture (`core/`, `features/`, `shared/`)
- Material 3 theme (enterprise navy + teal)
- Arabic RTL-first + English localization
- go_router skeleton + **5-tab** ESS shell
- Placeholder destinations (no business logic)
- Coding standards + architecture docs
- Declared dependencies (not wired to ERP)

## Phase 0 explicitly NOT done

- API / adapter connection
- Models / services
- Real login against ERP
- Attendance / leave / approvals logic
- Offline queue wiring
- Platform folders (`android/` / `ios/`) — generate with Flutter SDK

## Setup (when Flutter SDK is available)

```bash
cd ratib_hr_mobile
flutter create . --org sa.rateb --project-name ratib_hr_mobile --platforms=android,ios
flutter pub get
flutter run
```

## Bottom navigation (max 5)

1. Home  
2. Attendance  
3. Leave  
4. Requests  
5. More → documents, payslips, notifications, profile, approvals  

## Documentation

- [Architecture](docs/ARCHITECTURE.md)
- [Coding standards](docs/CODING_STANDARDS.md)
- [Phase 0 checklist](docs/PHASE_0.md)

## Controlled GO constraints (binding)

No new ERP business logic, tables, permissions, queues, PIN, GPS rules, or approval workflows.  
Thin adapters to existing RATIB ERP only — **after Phase 0 approval**.
