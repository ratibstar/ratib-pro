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

Current file includes the **upload keystore** public certificate SHA-256 for `sa.rateb.erp` (local upload key).

**Not published** to `https://rateb.sa/.well-known/assetlinks.json` until the product owner confirms.

After Play App Signing is enabled, also add the **App signing key certificate** SHA-256 from Play Console (keep the upload fingerprint listed as well for sideloaded/upload builds if needed).

## Verification (after publish)

```bash
adb shell pm get-app-links sa.rateb.erp
# or
adb shell am start -a android.intent.action.VIEW -d "https://rateb.sa/rateb-erp/public/admin"
```

Custom scheme `rateberp://app/...` does **not** require assetlinks.
