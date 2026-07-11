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

Configure Android App Links / iOS Universal Links for your ERP host in the generated native projects after `cap add`.

## Note

Root `capacitor.config.json` (`com.tracking.app` / `mobile-app`) is untouched.
