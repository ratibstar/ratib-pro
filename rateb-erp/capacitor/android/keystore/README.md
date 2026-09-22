# Android upload keystore (P2) — sa.rateb.erp

Local-only Google Play **upload** keystore for RATEB ERP Capacitor.

## Rules

- Do **not** commit `*.jks` / `*.keystore` / `key.properties`.
- Recommended file: `keystore/rateb-erp-upload-key.jks`
- Recommended alias: `rateb_erp_upload`
- Algorithm: RSA 2048 · validity ≥ 10000 days
- Do **not** use the Android debug keystore for release/AAB.

## Generate (operator machine only)

```powershell
New-Item -ItemType Directory -Force -Path android/keystore | Out-Null
$keytool = "C:\Program Files\Android\Android Studio\jbr\bin\keytool.exe"
& $keytool -genkeypair -v `
  -keystore android/keystore/rateb-erp-upload-key.jks `
  -storetype JKS `
  -alias rateb_erp_upload `
  -keyalg RSA -keysize 2048 -validity 10000 `
  -storepass "<STORE_PASS>" -keypass "<KEY_PASS>" `
  -dname "CN=RATEB ERP Upload, OU=Mobile, O=Rateb, L=Riyadh, ST=Riyadh, C=SA"
```

Then:

```powershell
Copy-Item android/key.properties.example android/key.properties
# Edit passwords + paths in android/key.properties
```

## Build signed AAB

```powershell
cd rateb-erp/capacitor/android
.\gradlew.bat bundleRelease
```

Output: `app/build/outputs/bundle/release/app-release.aab`

## SHA-256 for assetlinks / Play

```powershell
$keytool = "C:\Program Files\Android\Android Studio\jbr\bin\keytool.exe"
& $keytool -list -v `
  -keystore android/keystore/rateb-erp-upload-key.jks `
  -alias rateb_erp_upload
```

After Play App Signing is enabled, also copy the **App signing key certificate** SHA-256 from Play Console into `assetlinks.json` (publish only when approved).
