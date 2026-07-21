# Android upload keystore (Phase K)

This directory holds the Play Console **upload** keystore locally only.

## Rules

- Do **not** commit `*.jks` / `*.keystore` files.
- Do **not** commit `../key.properties` (see `../key.properties.example`).
- Play App Signing: upload key signs the AAB; Google holds the app signing key.

## Generate (operator machine — once)

```bash
keytool -genkey -v -keystore ratib-hr-mobile-upload.jks -keyalg RSA -keysize 2048 -validity 10000 -alias ratib_hr_mobile
```

1. Copy `android/key.properties.example` → `android/key.properties`
2. Fill `storePassword`, `keyPassword`, `keyAlias`, `storeFile`
3. Build: `.\tool\build_android_aab.ps1`

## Verify

```powershell
# After key.properties exists:
flutter build appbundle --flavor production --dart-define=APP_FLAVOR=production --release
# Output: build/app/outputs/bundle/productionRelease/app-production-release.aab
```
