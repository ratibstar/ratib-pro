# Android upload keystore (Phase K1)

Local-only Play Console **upload** keystore.

## Rules

- Do **not** commit `*.jks` / `*.keystore` / `key.properties`.
- File name: `ratib-hr-upload-key.jks`
- Alias: `ratib_hr_upload`
- Algorithm: RSA 2048 · validity 10000 days

## Generate (operator machine)

```powershell
$keytool = "C:\Program Files\Android\Android Studio\jbr\bin\keytool.exe"
& $keytool -genkeypair -v `
  -keystore android/keystore/ratib-hr-upload-key.jks `
  -storetype JKS `
  -alias ratib_hr_upload `
  -keyalg RSA -keysize 2048 -validity 10000 `
  -storepass "<STORE_PASS>" -keypass "<KEY_PASS>" `
  -dname "CN=RATEB HR Mobile Upload, OU=Mobile, O=Rateb, L=Riyadh, ST=Riyadh, C=SA"
```

Then create `android/key.properties` from `key.properties.example`.

## Build signed AAB

```powershell
.\tool\build_android_aab.ps1
```
