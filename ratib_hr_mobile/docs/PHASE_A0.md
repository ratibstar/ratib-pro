# Phase A0 — Native production Flutter shell

**Status:** COMPLETE (with documented environment limits)  
**Prerequisite for:** Phase A (MobileConfig / white-label)

## Objective

Prepare `ratib_hr_mobile` as a real production Flutter application (Android + iOS project trees), without implementing MobileConfig or business features.

## Delivered in repo

| Item | Location |
|------|----------|
| Android project | `android/` |
| iOS project | `ios/` |
| Package IDs | `sa.rateb.hr.mobile` (+ `.dev` / `.stg`) |
| Android flavors | `dev`, `staging`, `production` in `android/app/build.gradle.kts` |
| iOS flavor xcconfigs | `ios/Flutter/{Dev,Staging,Production}.xcconfig` |
| Signing placeholders | `android/key.properties.example`, `android/keystore/README.md`, `ios/SIGNING.md`, `ios/ExportOptions.plist.example` |
| Run/build helpers | `tool/run_android.ps1`, `tool/build_android.ps1` |
| Roadmap | `docs/ROADMAP.md` (A0 before A) |
| Deferred gen-l10n | `docs/l10n.yaml.deferred` (manual `AppLocalizations` remains SoT) |

## Package IDs

| Flavor | Android | iOS |
|--------|---------|-----|
| production | `sa.rateb.hr.mobile` | `sa.rateb.hr.mobile` |
| staging | `sa.rateb.hr.mobile.stg` | `sa.rateb.hr.mobile.stg` |
| dev | `sa.rateb.hr.mobile.dev` | `sa.rateb.hr.mobile.dev` |

## Verification (this machine — 20 Jul 2026)

| Check | Result |
|-------|--------|
| `flutter test` | **PASS** (9 tests) |
| `flutter build apk --flavor production` | **PASS** → `app-production-release.apk` |
| `flutter build apk --flavor dev` | Run as part of A0 close-out |
| Android emulator / physical device | **None listed** — SDK present; no AVD created; cmdline-tools incomplete per `flutter doctor` |
| iOS project generation | **PASS** (`ios/Runner` present) |
| iOS compile / Simulator | **N/A on Windows** — requires macOS + Xcode (see `ios/SIGNING.md`) |
| Store signing secrets | **Not created** (placeholders only) |

### Windows path note

Gradle fails on non-ASCII user paths unless:

1. `android.overridePathCheck=true` in `android/gradle.properties`
2. ASCII junctions for SDKs, e.g. `C:\flutter-sdk` → Flutter, `C:\android-sdk` → Android SDK
3. `android/local.properties` points at those junctions (gitignored)

## Commands

```powershell
cd ratib_hr_mobile
$env:FLUTTER_ROOT = "C:\flutter-sdk"
$flutter = "C:\flutter-sdk\bin\flutter.bat"

& $flutter pub get
& $flutter test
& $flutter build apk --flavor production --dart-define=APP_FLAVOR=production
& $flutter build apk --flavor dev --dart-define=APP_FLAVOR=development
.\tool\run_android.ps1 -Flavor dev -ErpBaseUrl "https://YOUR_HOST/rateb-erp/public"
```

## Exit criteria

- [x] `android/` + `ios/` present
- [x] `flutter test` passes
- [x] `flutter build apk --flavor production` succeeds
- [x] Android device/emulator path documented (no AVD on this host)
- [x] iOS project generated; full iOS build deferred to macOS
- [x] No real keystores / secrets committed
- [x] Phase A unblocked

## Explicitly out of A0

- MobileConfig / white-label
- Feature screens beyond existing Phases 1–3
- Real Play Store / App Store signing secrets
- Touching `rateb_mobile` / Capacitor / Tracking
