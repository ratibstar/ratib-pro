# Phase B2 — iOS Production Validation

**Status:** COMPLETE (project prepared; compile **BLOCKED** on Windows host)  
**Date:** 21 Jul 2026  
**Scope:** `ratib_hr_mobile` iOS only — no ERP / no Android / no business features

## Objective

Prepare production-quality iOS native integration for App Store / TestFlight path.

## Host evidence

| Check | Result |
|-------|--------|
| OS | Windows 11 — **no Xcode** |
| `flutter build` subcommands | `aar`, `apk`, `appbundle`, `bundle`, `web`, `windows` only |
| `flutter build ios --release --no-codesign` | **Not available** on this host (exit 64 / unknown option) |
| `flutter clean` + `flutter pub get` | **PASS** |
| Static suite `phase_b2_ios_production_test.dart` | Run in close-out |

## Fixes applied

| ID | Change |
|----|--------|
| B2-1 | Release/Profile base → `Production.xcconfig` (`sa.rateb.hr.mobile`) |
| B2-2 | `Production.xcscheme` — Archive = Release |
| B2-3 | Split entitlements: Debug=`development`, Release=`production` APS |
| B2-4 | Info.plist: ATS HTTPS-only, `$(DISPLAY_NAME)`, Face ID kept |
| B2-5 | Explicit non-goals: no Camera / Photos / Location / Associated Domains |
| B2-6 | Gitignore real `GoogleService-Info.plist`; example keeps production BUNDLE_ID |
| B2-7 | `tool/build_ios_macos.sh` for Mac compile gate |
| B2-8 | ExportOptions example for App Store Connect |

## Verification matrix

| Area | Result |
|------|--------|
| Runner target | Present |
| Release configuration | Wired to Production.xcconfig |
| Production flavor | xcconfig + scheme |
| Bundle ID | `sa.rateb.hr.mobile` |
| Signing placeholders | SIGNING.md + ExportOptions.example |
| Capabilities (Push / Background) | Entitlements + UIBackgroundModes |
| Schemes | Runner + Production |
| Archive compatibility | ArchiveAction → Release |
| Info.plist privacy | Face ID yes; camera/photo/location N/A |
| APS production | RunnerRelease.entitlements |
| Keychain Sharing | Not required / absent |
| Firebase example mapping | BUNDLE_ID matches production |
| No hardcoded Firebase secrets in git | PASS |
| No `print`/`debugPrint` in lib | PASS |
| Asserts | Flutter strips in release — OK |
| Startup / memory on device | **BLOCKED** — needs Mac + device |

## Remaining blockers

1. **macOS + Xcode** to run `flutter build ios --release --no-codesign`
2. Apple Development Team + certificates (Phase K)
3. Local `GoogleService-Info.plist` from Firebase Console
4. Device / Simulator runtime QA
5. Live APNs send still ERP I.4+ (out of B2)

## Mac commands (operator)

```bash
cd ratib_hr_mobile
./tool/build_ios_macos.sh
open ios/Runner.xcworkspace
# Scheme: Production → Product → Archive
```
