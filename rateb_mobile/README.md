# RATEB Mobile

Production-ready Flutter portal for **RATEB** (Recruitment & Workforce Management Platform).

Single app, **role-based dashboards** after login — not a super app:

| Role | Dashboard |
|------|-----------|
| `worker` | Worker portal (tasks, profile) |
| `company` | Company portal (workers, requests) |
| `agency` | Agency portal (pipeline, assignments) |

## Prerequisites

- [Flutter SDK](https://docs.flutter.dev/get-started/install) 3.16+
- Xcode (iOS) / Android Studio (Android)

## Setup

```bash
cd rateb_mobile

# Generate platform folders if missing (first time only)
flutter create . --org com.ratib --project-name rateb_mobile

flutter pub get
```

## Run

```bash
# Default backend: https://out.ratib.sa/api
flutter run

# Custom backend (local/staging)
flutter run --dart-define=RATEB_API_BASE_URL=https://your-host/api
```

## Architecture

```
lib/
├── core/           # API, auth, models, services, theme, routing
├── features/       # auth, worker, company, agency
├── shared/         # Reusable widgets
└── main.dart
```

## Authentication

`POST /api/mobile/login.php`

```json
{ "email": "user@example.com", "password": "secret" }
```

Response:

```json
{
  "success": true,
  "token": "...",
  "role": "worker | company | agency"
}
```

Token is stored in **flutter_secure_storage** and sent as `Authorization: Bearer`.

## Future features (stubs)

- QR login → `core/services/future_services.dart` (`QrLoginService`)
- Push notifications → `PushNotificationService`
- Offline cache → `OfflineCacheService`

## Backend endpoints

Mobile auth lives in the main RATEB repo under `api/mobile/`. Deploy with the rest of `api/` when ready for production.
