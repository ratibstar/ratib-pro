# iOS signing & production validation (Phase A0 → B → B2)

## Bundle IDs

| Flavor | Bundle ID | xcconfig |
|--------|-----------|----------|
| production | `sa.rateb.hr.mobile` | `Flutter/Production.xcconfig` |
| staging | `sa.rateb.hr.mobile.stg` | `Flutter/Staging.xcconfig` |
| dev | `sa.rateb.hr.mobile.dev` | `Flutter/Dev.xcconfig` |

## Phase B2 wiring (21 Jul 2026)

| Item | Status |
|------|--------|
| Release / Profile base | `Production.xcconfig` |
| Archive scheme | `Production.xcscheme` (Release Archive) |
| Debug APS | `RunnerDebug.entitlements` → `aps-environment=development` |
| Release APS | `RunnerRelease.entitlements` → `aps-environment=production` |
| Display name | `$(DISPLAY_NAME)` from xcconfig |
| ATS | `NSAllowsArbitraryLoads=false` |
| Face ID usage | Present |
| Camera / Photos / Location / Associated Domains | Not required (no plugins) |
| Firebase plist | `GoogleService-Info.plist.example` — real file gitignored |
| Keychain Sharing | Not required |
| Compile on Windows | **Impossible** — Flutter has no `build ios` subcommand on Windows |

## macOS validation (required for ✔ build)

```bash
cd ratib_hr_mobile
chmod +x tool/build_ios_macos.sh
./tool/build_ios_macos.sh
# or:
flutter clean && flutter pub get
flutter build ios --release --no-codesign --dart-define=APP_FLAVOR=production
```

Then in Xcode:

1. Open `ios/Runner.xcworkspace`
2. Scheme **Production** (or Runner → Archive uses Release → Production.xcconfig)
3. Signing & Capabilities → Development Team (Automatic)
4. Confirm Push Notifications + Background Modes (remote-notification)
5. Copy Firebase `GoogleService-Info.plist` locally (BUNDLE_ID must be `sa.rateb.hr.mobile`)
6. Product → Archive → Distribute App (use `ExportOptions.plist.example` as template)
7. Never commit `.p12`, provisioning profiles, AuthKey `.p8`, or real `GoogleService-Info.plist`

## Phase L1 (build prep)

Operator Mac path and TestFlight checklist: [docs/PHASE_L1.md](../docs/PHASE_L1.md) · `tool/build_ios_macos.sh`

## Phase K (store upload)

1. Set Apple Team ID in a local `ExportOptions.plist` (from `ExportOptions.plist.example`).
2. Archive with **Production** scheme (Release + `aps-environment=production`).
3. Distribute → App Store Connect.
4. Complete [STORE_ASSETS_CHECKLIST.md](../docs/STORE_ASSETS_CHECKLIST.md) + [COMPLIANCE.md](../docs/COMPLIANCE.md).
5. Decision remains **NO-GO** until Team signing + assets + Privacy Policy URL are live — see [PHASE_K.md](../docs/PHASE_K.md).
