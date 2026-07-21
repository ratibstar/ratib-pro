# Phase B — Enterprise Device QA

**Status:** COMPLETE (with documented environment limits)  
**Date:** 21 Jul 2026  
**Project:** `ratib_hr_mobile` only  
**Depends on:** Phase A0 native shell + ESS feature phases already in tree

## Objective

Perform enterprise Device QA for Android and iOS. Fix only real defects. No architecture or ERP business-logic changes.

## Environment evidence (this host)

| Check | Result |
|-------|--------|
| Flutter | 3.44.0 stable (`C:\flutter-sdk`) |
| Connected Android device | **None** (`adb devices` empty) |
| Android emulator / AVD | **None** — no system-images under SDK; cmdline-tools missing |
| iOS compile | **N/A on Windows** — macOS + Xcode required |
| `flutter test` | **PASS** — 86/86 (2026-07-21) |
| `flutter build apk --flavor production` | **PASS** → `app-production-release.apk` (~59.8MB) |

## Defects discovered and fixed

| ID | Defect | Fix |
|----|--------|-----|
| B1 | Production Android `applicationId` was `sa.rateb.hr.mobile.z812` (diverged from ROADMAP / Architecture Lock) | Restored `sa.rateb.hr.mobile` |
| B2 | Test stubs `_NoopOffline` missing `pendingItems` / `pendingCount` / `replaceAll` → D/E suites failed to compile | Implemented noop methods |
| B3 | Phase marker `I3+PR` broke allowlists / exact I3 assert | Set `AppConfig.phase = 'B'`; updated allowlists |
| B4 | iOS missing `Runner.entitlements` / `aps-environment` / `CODE_SIGN_ENTITLEMENTS` | Added entitlements + pbxproj wiring |
| B5 | iOS missing `NSFaceIDUsageDescription` (blocks future `local_auth` Face ID) | Added to `Info.plist` |
| B6 | Offline check-in/leave called `AppLocator.connectivity` **before** enqueue — unbound locator aborted queue | Enqueue first; soft-fail connectivity UX signal |

## Android QA matrix

| Area | Method | Result |
|------|--------|--------|
| Login / session / logout / modules | Automated phase tests (auth, config, D–J) + static routes | **PASS** (unit/widget) |
| Offline banner / queue / sync | `phase_h` + B offline contract test | **PASS** |
| Language / RTL-LTR / theme | B widget tests + shell locale chip | **PASS** (widget) |
| Rotation | Manifest `configChanges` includes orientation | **PASS** (static) |
| POST_NOTIFICATIONS | Manifest permission | **PASS** (static) |
| Release build | `flutter build apk --flavor production` | Evidence in close-out |
| Deep links | No `VIEW` intent-filter | **FAIL** — not implemented (deferred; not invented in B) |
| Real device / emulator lifecycle / battery / memory | No AVD / device on host | **BLOCKED** — host environment |
| Android 10+ hardware QA | No device | **BLOCKED** |

## iOS QA matrix

| Area | Result |
|------|--------|
| Project integrity / AppDelegate / SceneDelegate | **PASS** |
| Podfile | **N/A** — Flutter SPM plugin package (no CocoaPods) |
| Info.plist background modes | **PASS** |
| Entitlements / APNs `aps-environment` | **PASS** (development placeholder) |
| Signing placeholders | **PASS** |
| Flavor xcconfigs present | **PASS** |
| Flavor schemes wired in Xcode | **PARTIAL** — configs exist; schemes still created on Mac |
| Simulator / device / signed run | **BLOCKED** — Windows host |

## Remaining blockers (not Phase B code defects)

1. No Android emulator system image / cmdline-tools on this host → no interactive device QA.
2. No physical Android / iOS device attached.
3. iOS compile requires macOS + Xcode (+ Development Team for automatic signing).
4. Deep links not in product scope yet.
5. Store signing secrets remain Phase K.
6. Live FCM/APNs server send remains outside B (Push I.4+).

## Tests

```
flutter test
flutter test test/phase_b_device_qa_test.dart
flutter build apk --flavor production --dart-define=APP_FLAVOR=production
```

## Explicit non-goals

- ERP API / business rule changes
- Store release (Phase K)
- Inventing deep-link product behavior
- Touching `rateb_mobile` / Capacitor / Tracking / Admin V2
