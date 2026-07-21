# Phase K — Enterprise Store Release Preparation

**Status:** PREPARATION COMPLETE — **NO-GO for store upload** (secrets + Mac + assets pending)  
**Date:** 21 Jul 2026  
**Scope:** `ratib_hr_mobile` only — no ERP / no business features

## Version strategy

| Field | Rule | Current |
|-------|------|---------|
| `versionName` | Marketing semver in `pubspec.yaml` left of `+` | **1.0.0** |
| `versionCode` / CFBundleVersion | Monotonic integer right of `+` | **200** |
| Bump rule | Every Play/App Store upload increments code; name bumps on user-visible release | |

## Android readiness

| Check | Result |
|-------|--------|
| Production `applicationId` | `sa.rateb.hr.mobile` |
| Flavors | dev / staging / production |
| AAB command | `tool/build_android_aab.ps1` / `flutter build appbundle --flavor production` |
| AAB artifact (this host) | `build/app/outputs/bundle/productionRelease/app-production-release.aab` (~55MB) produced |
| Native symbol strip | **Host warning** — Flutter exit 1 (`failed to strip debug symbols`); cmdline-tools incomplete per `flutter doctor` |
| Signing | Placeholders only — `key.properties` gitignored |
| R8 / minify / shrink | **Enabled** on release + `proguard-rules.pro` |
| Cleartext | `usesCleartextTraffic=false` |
| Permissions | `INTERNET`, `POST_NOTIFICATIONS` |
| targetSdk | 36 (Flutter default) |
| Uploadable AAB | **Blocked** until real upload keystore |

## iOS readiness

| Check | Result |
|-------|--------|
| Release → Production.xcconfig | Wired (Phase B2) |
| Archive scheme | `Production.xcscheme` |
| APS production | `RunnerRelease.entitlements` |
| ExportOptions | `ExportOptions.plist.example` |
| Info.plist ATS / Face ID / background | Present |
| Signing Team / certs | **Not present** |
| `flutter build ios` | **Requires macOS** |

## Store assets / compliance

- Checklist: [STORE_ASSETS_CHECKLIST.md](STORE_ASSETS_CHECKLIST.md)
- Compliance: [COMPLIANCE.md](COMPLIANCE.md)

## Remaining blockers (upload)

1. Generate and secure Android upload keystore + `key.properties`
2. Fix Android SDK cmdline-tools / NDK strip so `flutter build appbundle` exits 0
3. macOS: `flutter build ios --release` + Archive with Apple Team
4. Firebase `google-services.json` / `GoogleService-Info.plist` locally
5. Complete store listing assets (icons, screenshots, copy)
6. Legal: live Privacy Policy + Terms URLs
7. Play Data Safety + App Store Privacy questionnaires
8. Live FCM/APNs server send (ERP Push I.4+) — optional for first binary, required for push UX
9. Hardware QA on Android 10+ / iOS devices (Phase B gap)

## Scores (Phase K close)

| Dimension | Score |
|-----------|------:|
| Android engineering readiness | **7**/10 |
| iOS engineering readiness | **5**/10 |
| Google Play upload readiness | **2**/10 |
| App Store upload readiness | **2**/10 |
| **Final production** | **4**/10 |

## Decision

# **NO-GO**

Engineering release **preparation** is complete. Store **upload** is blocked by signing secrets, macOS archive, listing assets, and legal URLs.
