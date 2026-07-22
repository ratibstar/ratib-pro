# Phase K1 — Android Production Signing Automation

**Status:** COMPLETE  
**Date:** 21 Jul 2026  
**Scope:** `ratib_hr_mobile` Android only — no ERP / Flutter business changes

## Evidence (this host)

| Check | Result |
|-------|--------|
| `flutter build appbundle --flavor production --release` | **PASS** exit 0 |
| AAB path | `build/app/outputs/bundle/productionRelease/app-production-release.aab` (~55.5MB) |
| `applicationId` | `sa.rateb.hr.mobile` |
| `versionName` / `versionCode` | `1.0.0` / `200` |
| Signer | `CN=RATEB HR Mobile Upload` · alias `ratib_hr_upload` |
| Cert SHA-256 | `8F:E3:0E:B9:…:C9:EA` (matches upload JKS) |
| `jarsigner -verify` | `jar verified` (self-signed upload key — expected) |
| Debug signing | **Not used** |
| Secrets in git | **No** — `key.properties` + `*.jks` gitignored |

## Delivered (repo)

| Item | Detail |
|------|--------|
| Gradle | Release **requires** `android/key.properties`; `storeFile` via `rootProject.file` |
| Example | `android/key.properties.example` · alias `ratib_hr_upload` |
| Script | `tool/build_android_aab.ps1` (`clean` + `pub get` + AAB + ERP defines) |
| Local keystore | `android/keystore/ratib-hr-upload-key.jks` (**not committed**) |

## Operator backup (mandatory)

Securely back up **both** `android/key.properties` and `ratib-hr-upload-key.jks`. Losing the upload key blocks Play updates unless Play App Signing recovery applies.

## Remaining (outside K1)

- Upload AAB to Play Console + enroll Play App Signing  
- Store listing assets / legal URLs (Phase K checklist)  
- iOS Archive still needs macOS  
