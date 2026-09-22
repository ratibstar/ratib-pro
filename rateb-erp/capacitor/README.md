# RATEB ERP Capacitor (build-ready)

Native Android / iOS shell for **rateb-erp** (not `mobile-app` / root TrackingApp).

## Plugins

- `@capacitor/filesystem`
- `@capacitor/camera`
- `@capacitor/share`
- `@capacitor/app` (deep links via `appUrlOpen`)

## Setup

```bash
cd rateb-erp/capacitor
npm install
npm run sync:www
npx cap add android
npx cap add ios
npx cap sync
```

Open IDE:

```bash
npm run open:android
npm run open:ios
```

## Live ERP origin

Set `server.url` in `capacitor.config.json` to your deployed ERP base URL when packaging against a remote host. Offline SDK assets are copied from `rateb-erp/public/assets/offline`.

## Deep links

Android App Links (P1):

- `https://rateb.sa/rateb-erp/public/admin…` (`autoVerify`, pathPrefix `/rateb-erp/public/admin`)
- Custom scheme: `rateberp://app…`

Prepared Digital Asset Links (not published until approved):  
`native-templates/well-known/assetlinks.json` → must be served at `https://rateb.sa/.well-known/assetlinks.json`.

See `native-templates/well-known/README.md`.

## Note

Root `capacitor.config.json` (`com.tracking.app` / `mobile-app`) is untouched.

## Release signing + AAB (P2)

See `android/keystore/README.md` and `android/key.properties.example`.

- Upload keystore + `key.properties` are **local only** (gitignored).
- `./gradlew bundleRelease` fails closed if `key.properties` is missing (no debug signing fallback).
