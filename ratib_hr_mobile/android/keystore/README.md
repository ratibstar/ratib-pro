# Android upload keystore (Phase A0 placeholder)

This directory is reserved for the Play Store **upload** keystore.

## Rules

- Do **not** commit `*.jks` / `*.keystore` files.
- Do **not** commit `../key.properties` (see `../key.properties.example`).
- Generate the keystore only when preparing Play Console (Phase J).

## Future generation (not run in A0)

```bash
keytool -genkey -v -keystore ratib-hr-mobile-upload.jks -keyalg RSA -keysize 2048 -validity 10000 -alias ratib_hr_mobile
```

Then copy `key.properties.example` → `../key.properties` and fill values.
