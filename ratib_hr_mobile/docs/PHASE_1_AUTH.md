# Phase 1 — ERP Authentication

## ERP source

`POST /api/v1/auth/token` (`ApiController::createToken`)

JSON body (ERP contract):

```json
{ "email": "...", "password": "..." }
```

Success:

```json
{ "success": true, "token": "...", "expires_at": "..." }
```

## Mobile flow

1. Load `ERP_BASE_URL` + `APP_FLAVOR` from `--dart-define`
2. Login UI → `AuthPort.signIn`
3. `ErpAuthAdapter` posts to ERP token endpoint
4. Token saved in `SecureTokenStore` (no password stored)
5. Router redirects to home shell placeholder

## Run

```bash
cd ratib_hr_mobile
flutter create . --org sa.rateb --project-name ratib_hr_mobile --platforms=android,ios
flutter pub get
flutter run --dart-define=ERP_BASE_URL=https://YOUR_HOST/rateb-erp/public --dart-define=APP_FLAVOR=production
```

## Stop

Phase 1 ends at successful ERP login. Do not implement MePort / dashboard / attendance here.
