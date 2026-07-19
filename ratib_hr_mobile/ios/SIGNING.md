# iOS signing structure (Phase A0)

## Bundle IDs

| Flavor | Bundle ID |
|--------|-----------|
| production | `sa.rateb.hr.mobile` |
| staging | `sa.rateb.hr.mobile.stg` |
| dev | `sa.rateb.hr.mobile.dev` |

Xcconfig stubs live in `ios/Flutter/{Dev,Staging,Production}.xcconfig`.

## Phase A0 status

- Project generated on Windows (Xcode project files present).
- **Full iOS compile / Simulator / TestFlight requires macOS + Xcode** (not available in this environment).
- Real certificates / provisioning profiles are **not** created in A0.

## Phase J (future)

1. Create App IDs in Apple Developer for each bundle ID (or production only first).
2. In Xcode: create schemes `dev`, `staging`, `production` pointing at matching xcconfigs.
3. Enable automatic signing with the team ID.
4. Use `ExportOptions.plist.example` as a template for CI export.
5. Never commit `.p12`, provisioning profiles, or AuthKey `.p8` files.
