# iOS signing structure (Phase A0 + Phase B)

## Bundle IDs

| Flavor | Bundle ID |
|--------|-----------|
| production | `sa.rateb.hr.mobile` |
| staging | `sa.rateb.hr.mobile.stg` |
| dev | `sa.rateb.hr.mobile.dev` |

Xcconfig stubs: `ios/Flutter/{Dev,Staging,Production}.xcconfig`.

## Phase B status (21 Jul 2026)

| Check | Result |
|-------|--------|
| Project trees (`Runner`, schemes) | Present |
| `UIBackgroundModes` remote-notification + fetch | Present in `Info.plist` |
| `NSFaceIDUsageDescription` | Present (pre-wires `local_auth`) |
| `Runner.entitlements` + `aps-environment=development` | Present; wired via `CODE_SIGN_ENTITLEMENTS` |
| CocoaPods `Podfile` | Not required — Flutter SPM plugin package |
| Flavor schemes `dev` / `staging` / `production` | **Not yet** — create on macOS (below) |
| Full iOS compile / Simulator | **Requires macOS + Xcode** |
| Real certificates / provisioning | **Not created** (Phase K) |

## macOS / Xcode validation (no store secrets)

1. `flutter pub get` (materializes ephemeral SPM package under `ios/Flutter/ephemeral/`).
2. Open `ios/Runner.xcworkspace` (or `.xcodeproj` — SPM path).
3. Signing & Capabilities → select Development Team (Automatic).
4. Confirm Push Notifications capability reflects `Runner.entitlements`.
5. Optional flavors: duplicate Debug/Release → set base configuration to `Flutter/Dev.xcconfig` (etc.) → schemes `dev` / `staging` / `production`.
6. `flutter build ios --no-codesign --dart-define=APP_FLAVOR=production` to prove compile without distributing.
7. Never commit `.p12`, provisioning profiles, or AuthKey `.p8` files.

## Phase K (store)

1. Create App IDs in Apple Developer for each bundle ID (or production only first).
2. Switch `aps-environment` to `production` for App Store / TestFlight distribution builds.
3. Use `ExportOptions.plist.example` as a CI export template.
