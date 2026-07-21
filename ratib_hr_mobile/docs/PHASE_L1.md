# Phase L1 — iOS Production Build Preparation

**Status:** COMPLETE (prep only — **no Archive / no certificates / no secrets**)  
**Date:** 2026-07-21  
**Host of record:** Windows (cannot compile iOS)  
**Depends on:** Phase B2 iOS wiring · no Flutter architecture changes in L1

Related: [PHASE_B2.md](PHASE_B2.md) · [ios/SIGNING.md](../ios/SIGNING.md) · [STORE_ASSETS_CHECKLIST.md](STORE_ASSETS_CHECKLIST.md) · [COMPLIANCE.md](COMPLIANCE.md)

---

## Explicit non-goals

- No certificates, provisioning profiles, `.p12`, AuthKey `.p8`, or Team ID committed
- No App Store Connect / TestFlight upload
- No Flutter architecture or ERP API changes
- No real `GoogleService-Info.plist` in git

---

## 1. iOS folder review (static)

| Area | Finding | OK |
|------|---------|----|
| Workspace | `ios/Runner.xcworkspace` | Yes |
| Project | `ios/Runner.xcodeproj` | Yes |
| Schemes | `Runner.xcscheme` + **`Production.xcscheme`** (shared) | Yes |
| Production.xcscheme Archive | `ArchiveAction` → **Release** | Yes |
| Production.xcscheme Launch/Test | **Release** + `--dart-define=APP_FLAVOR=production` | Yes |
| Release / Profile base | `Flutter/Production.xcconfig` | Yes |
| Debug base | `Flutter/Debug.xcconfig` | Yes |
| Bundle ID (Release/Profile/Debug Runner) | **`sa.rateb.hr.mobile`** | Yes |
| Flavor variants (xcconfig only) | `.stg` / `.dev` for future; production Archive uses production ID | Yes |
| APS Debug | `RunnerDebug.entitlements` → `development` | Yes |
| APS Release/Profile | `RunnerRelease.entitlements` → **`production`** | Yes |
| Deployment target | iOS **13.0** | Yes |
| ATS | `NSAllowsArbitraryLoads=false` | Yes |
| Face ID string | Present | Yes |
| Camera / Photos / Location / Associated Domains | Absent | Yes |
| Background modes | `fetch` + `remote-notification` | Yes |
| Firebase | `GoogleService-Info.plist.example` only; real plist gitignored | Yes |
| Export template | `ExportOptions.plist.example` (placeholder Team ID) | Yes |
| Podfile | Not present (Flutter SPM / generated plugins path) | N/A |
| Signing secrets in tree | None | Yes |

---

## 2. Bundle ID verification

| Source | Value |
|--------|--------|
| `Flutter/Production.xcconfig` | `PRODUCT_BUNDLE_IDENTIFIER = sa.rateb.hr.mobile` |
| `project.pbxproj` Runner Release/Profile/Debug | `PRODUCT_BUNDLE_IDENTIFIER = sa.rateb.hr.mobile` |
| `Info.plist` | `$(PRODUCT_BUNDLE_IDENTIFIER)` |
| AppConfig / Android production parity | `sa.rateb.hr.mobile` |
| Firebase example `BUNDLE_ID` | `sa.rateb.hr.mobile` |

**Must match** App Store Connect app record and Firebase iOS app.

---

## 3. macOS + Xcode prerequisites (operator)

| Requirement | Notes |
|-------------|--------|
| macOS | Current Xcode-supported version |
| Xcode | Stable; open once to accept license |
| Xcode CLT | `xcode-select -p` points at Xcode.app |
| Flutter (stable) | Same major family as CI/dev where possible |
| Cocoa / SPM | Let Flutter generate plugin integration on `pub get` / first build |
| Apple ID + **paid Apple Developer Program** | Required for device Archive → TestFlight |
| Local Development Team in Xcode | Automatic signing — **never commit Team ID into repo** |
| Optional local Firebase plist | Copy from Console; BUNDLE_ID must be production |

---

## 4. Mac build script

```bash
cd ratib_hr_mobile
chmod +x tool/build_ios_macos.sh
./tool/build_ios_macos.sh
```

Script behavior (L1):

1. Fail fast if not Darwin  
2. Static preflight: Bundle ID + Production scheme Archive=Release (no secrets)  
3. `flutter clean` + `flutter pub get`  
4. Warn if `GoogleService-Info.plist` missing (push soft-fail)  
5. `flutter build ios --release --no-codesign --dart-define=APP_FLAVOR=production`  
6. Print Archive / TestFlight next steps (operator only)

**Does not** install certificates, does not read keychains, does not upload.

---

## 5. Xcode Archive steps (operator)

1. `open ios/Runner.xcworkspace`  
2. Scheme: **Production**  
3. Destination: **Any iOS Device (arm64)**  
4. Signing & Capabilities → Team (local) → confirm Bundle ID `sa.rateb.hr.mobile`  
5. Confirm Push Notifications capability + Background Modes (remote notifications)  
6. Product → **Archive**  
7. Organizer → Distribute App → **App Store Connect**  
8. Use a **local** `ExportOptions.plist` copied from `ExportOptions.plist.example` (fill Team ID locally only)

---

## 6. TestFlight checklist (operator)

### App Store Connect

- [ ] App created with Bundle ID **`sa.rateb.hr.mobile`**
- [ ] Agreements / tax / banking current
- [ ] Build uploaded (Transporter / Xcode / `xcrun altool` / `notary` path as preferred)
- [ ] Build processed (email / Activity)
- [ ] TestFlight → Internal Testing group + testers
- [ ] Export compliance / encryption answers (HTTPS-only app — follow legal)
- [ ] Missing Compliance cleared if prompted

### Build identity

- [ ] Version `CFBundleShortVersionString` = `1.0.0` (from Flutter `1.0.0+200`)
- [ ] Build `CFBundleVersion` = `200`
- [ ] Bundle ID = `sa.rateb.hr.mobile`

### Smoke (device via TestFlight)

- [ ] Install / launch
- [ ] Login against production ERP HTTPS
- [ ] Leave / payslip or documents (tenant-enabled)
- [ ] Notification permission prompt
- [ ] Face ID / PIN unlock if enabled on device
- [ ] Offline banner / queue smoke

### Store gate (later — not L1)

- [ ] Privacy Policy URL
- [ ] App Privacy labels ([COMPLIANCE.md](COMPLIANCE.md))
- [ ] Screenshots / icon ([STORE_ASSETS_CHECKLIST.md](STORE_ASSETS_CHECKLIST.md))

---

## 7. Blockers (operator — L1 cannot clear on Windows)

| # | Blocker | Blocks |
|---|---------|--------|
| 1 | **macOS + Xcode** required for `flutter build ios` / Archive | Compile + IPA |
| 2 | **Apple Developer Team** + Automatic signing (local only) | Device-signed Archive |
| 3 | **App Store Connect** app record for `sa.rateb.hr.mobile` | TestFlight upload |
| 4 | Local **`GoogleService-Info.plist`** (optional for first binary; required for live FCM/APNs client config) | Push client config |
| 5 | **TestFlight** processing + tester invites | Internal iOS QA |
| 6 | Store assets + Privacy URL | App Store production (post-TestFlight) |

**Not blockers for L1 prep docs:** Flutter architecture, Bundle ID mismatch, missing Production scheme (all verified statically).
