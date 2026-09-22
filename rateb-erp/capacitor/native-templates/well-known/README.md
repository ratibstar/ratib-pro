# Digital Asset Links (Android App Links) — prepared, NOT published

**Do not copy to production** (`https://rateb.sa/.well-known/assetlinks.json`) until the product owner confirms.

## Target

| Field | Value |
|---|---|
| Host | `rateb.sa` |
| App package | `sa.rateb.erp` |
| Manifest pathPrefix | `/rateb-erp/public/admin` |
| Aligns with `server.url` | `https://rateb.sa/rateb-erp/public/admin` |

## Publish location (when approved)

```
https://rateb.sa/.well-known/assetlinks.json
```

Must be HTTPS, `Content-Type: application/json`, no auth redirect.

## Fingerprints in `assetlinks.json`

Current file includes the **local Android debug keystore** SHA-256 only (for device debug installs).

Before Play release, **add** the upload/App signing certificate SHA-256 (do not remove debug until debug testing is finished, or maintain separate staging file).

```bash
# Debug (already embedded):
keytool -list -v -keystore ~/.android/debug.keystore -alias androiddebugkey -storepass android -keypass android

# Release upload key (after P2 signing exists):
keytool -list -v -keystore /path/to/upload.jks -alias <alias>
```

Play Console → App signing also shows the **App signing key certificate** SHA-256 — that fingerprint must be listed for Play-distributed builds.

## Verification (after publish)

```bash
adb shell pm get-app-links sa.rateb.erp
# or
adb shell am start -a android.intent.action.VIEW -d "https://rateb.sa/rateb-erp/public/admin"
```

Custom scheme `rateberp://app/...` does **not** require assetlinks.
