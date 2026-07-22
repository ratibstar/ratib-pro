# RATEB HR Mobile

**Enterprise Employee Self-Service (ESS)** presentation app for **RATEB ERP**.

| | |
|--|--|
| Official ESS app | `ratib_hr_mobile` |
| Current phase | **L1 — iOS Production Build Preparation** |
| Next phase | Mac `./tool/build_ios_macos.sh` + Xcode Archive → TestFlight (operator) |
| Source of truth | RATEB ERP |
| Not this | `rateb_mobile`, Capacitor Admin, Tracking |

## Architecture lock

- Presentation layer only — **no business logic in Flutter**
- Thin adapters → ERP APIs
- Extensible for future Manager / Supervisor / CEO modules without restructuring
- See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) and [docs/ROADMAP.md](docs/ROADMAP.md)

## Phase A0 (native)

```powershell
cd ratib_hr_mobile
$flutter = "$env:LOCALAPPDATA\flutter\bin\flutter.bat"

& $flutter pub get
& $flutter test

# Android flavors: dev | staging | production
& $flutter build apk --flavor production --dart-define=APP_FLAVOR=production
.\tool\run_android.ps1 -Flavor dev -ErpBaseUrl "https://YOUR_HOST/rateb-erp/public"
```

| Flavor | Package ID |
|--------|------------|
| production | `sa.rateb.hr.mobile` |
| staging | `sa.rateb.hr.mobile.stg` |
| dev | `sa.rateb.hr.mobile.dev` |

Signing placeholders: `android/key.properties.example`, `ios/SIGNING.md` — **no store secrets in A0**.

iOS project is generated; full Simulator/TestFlight builds require **macOS + Xcode**.

## Bottom navigation (max 5)

1. Home  
2. Attendance  
3. Leave  
4. Requests  
5. More → documents, payslips, notifications, profile, approvals  

## Documentation

- [Roadmap](docs/ROADMAP.md)
- [Phase A0](docs/PHASE_A0.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Coding standards](docs/CODING_STANDARDS.md)
