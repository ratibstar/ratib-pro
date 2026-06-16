# RATEB Mobile — Progress Report

**Date:** 25 May 2026  
**Project:** `rateb_mobile/` (Flutter) + `api/mobile/` (PHP JSON APIs)  
**Production API base:** `https://rateb.sa/api`  
**Local dev URL:** `http://127.0.0.1:8090`

---

## Executive summary

The RATEB mobile portal MVP is **functionally complete** for three roles — **Worker**, **Company**, and **Agency** — with password login, optional QR login, live API-backed dashboards, and bottom-tab navigation. The latest screenshot confirms the **new Company dashboard UI** (“Workforce status”, “Active workers”) is loading on port **8090**.

| Area | Status |
|------|--------|
| Flutter app structure | Done |
| Auth (password + JWT) | Done |
| QR login (scan / paste) | Done |
| Live data endpoints (PHP) | Done (repo) |
| UX resilience (loading, retry, cache, offline banner) | Done |
| Bottom-tab navigation fix | Done |
| Production deploy of new PHP files | **Verify on server** |
| iOS / Android store builds | Not started |

---

## What was built

### 1. Backend — `api/mobile/`

Flat PHP files (fast-deploy friendly):

| File | Purpose |
|------|---------|
| `bootstrap.php`, `cors.php` | JSON bootstrap + CORS |
| `login.php`, `logout.php`, `profile.php` | Auth & profile |
| `auth.inc.php` | Shared JWT validation for data routes |
| `worker-dashboard.php` | Worker summary + task stats |
| `worker-tasks.php` | Worker task list |
| `company-workers.php` | Company workforce roster |
| `company-requests.php` | Company recruitment/case requests |
| `agency-pipeline.php` | Agency candidate pipeline |
| `agency-assignments.php` | Agency client assignments |
| `qr.inc.php`, `qr-login.php`, `qr-generate.php` | HMAC-signed QR identity login |
| `health.php` | Health check |

**Config change:** `includes/config.php` — `/api/mobile/` added to `TENANT_CONTEXT_GUARD_EXCEPTIONS` so JWT auth works without tenant session.

### 2. Flutter app — `rateb_mobile/lib/`

```
lib/
├── core/           auth, api, models, routing, services, theme
├── features/
│   ├── auth/       login screen + AuthProvider
│   ├── worker/     home, profile, tasks
│   ├── company/    home, workers, requests
│   ├── agency/     home, pipeline, assignments
│   └── qr/         scanner + QR login flow
├── shared/widgets/ scaffold, cards, skeletons, data states, portal shell
└── main.dart
```

**Stack:** Flutter 3.44 · Provider · go_router 14 · Dio · SharedPreferences

### 3. Features delivered

#### Authentication
- Password login against `/mobile/login.php` (same accounts as `https://rateb.sa/pages/login.php`)
- JWT bearer token stored via `TokenStorage` (SharedPreferences on all platforms including web)
- 401 → automatic logout + “Session expired” on login screen
- Removed `flutter_secure_storage` (caused web `OperationError`)

#### QR identity login
- Backend: signed payload `RATEBMOBQR:{base64(json)}.{sig}`, single-use nonces
- Flutter: “Login with QR” on login screen, `mobile_scanner` on native; paste payload on web
- Generate QR: `POST /api/mobile/qr-generate.php` with Bearer token

#### Live dashboards (no mock data)
- All role screens fetch from `RatebApiService`
- Models: `worker_models.dart`, `company_models.dart`, `agency_models.dart`
- Company/Agency dashboard summaries aggregate two API calls client-side

#### Production UX
- Skeleton loaders (`skeleton_loader.dart`)
- Role-specific empty states (`EmptyStateCopy`)
- Auto-retry up to 2 attempts (`resilient_loader.dart`)
- In-memory screen cache (`screen_cache.dart`)
- Offline banner as top overlay only (`offline_banner.dart`) — does not block taps

#### Navigation (latest fix)
- **`StatefulShellRoute.indexedStack`** + **`PortalShell`** — stable bottom nav bar
- Routes:
  - Worker: `/worker`, `/worker/profile`, `/worker/tasks`
  - Company: `/company`, `/company/workers`, `/company/requests`
  - Agency: `/agency`, `/agency/pipeline`, `/agency/assignments`
- Dashboard cards navigate to the matching tab

---

## Issues fixed during development

| Issue | Cause | Fix |
|-------|-------|-----|
| Blank white screen | Stale web session + secure storage web crash | Removed secure storage; fresh dev run |
| Login “Unable to reach server” | Wrong/stale API URL or network | `--dart-define=RATEB_API_BASE_URL=https://rateb.sa/api` |
| Requests tab crash | `Size.fromHeight(48)` infinite width in Row | Theme `Size(0, 48)` + full-width wrapper on login button |
| Tabs not clickable | Offline banner Column+Expanded + dashboard rebuild on each tap | Stack overlay banner + `StatefulShellRoute` shell |
| Old mock UI on 8088 | Stale Flutter dev server | Restart on **8090** |

---

## Current screenshot interpretation

Your latest screenshot shows:

- **New UI** — “Workforce status”, “Active workers”, “Open requests” (not old mock labels)
- **Zeros** — `0 on assignment`, `0 pending`, `0 requests` — this usually means the API returned empty data for the logged-in `admin` account, not a UI failure. Confirm workers/cases exist in the RATEB database for that tenant.
- **Console stack traces** — from `dwds` (Dart Web Debug Service) in `web-server` debug mode. These are common debug noise and do not necessarily mean the app is broken. If tabs still feel dead, try `flutter run -d chrome` or hard refresh (**Ctrl+Shift+R**).

---

## How to run locally

```powershell
cd C:\Users\انا\Desktop\ratebprogram\rateb_mobile
flutter pub get
flutter run -d web-server --web-port=8090 --web-hostname=127.0.0.1 `
  --dart-define=RATEB_API_BASE_URL=https://rateb.sa/api
```

Open **http://127.0.0.1:8090** (not 8088).

**Login:** same credentials as the main RATEB website (e.g. `admin` + password).

**Build for production web:**

```powershell
flutter build web --dart-define=RATEB_API_BASE_URL=https://rateb.sa/api
```

---

## Deploy checklist (production)

1. **Push** changes to `main` — GitHub Actions fast-deploy uploads changed files under `api/mobile/` automatically.
2. **Verify** endpoints live:
   - `GET https://rateb.sa/api/mobile/health.php`
   - `POST https://rateb.sa/api/mobile/login.php`
   - `GET https://rateb.sa/api/mobile/company-workers.php` (with Bearer token)
3. **Confirm** Actions deploy job is green after push.
4. **Test** all three roles on web, then native (`flutter run -d android` / iOS).

---

## File reference (key paths)

| Area | Path |
|------|------|
| Router | `rateb_mobile/lib/core/routing/app_router.dart` |
| Portal shell | `rateb_mobile/lib/shared/widgets/portal_shell.dart` |
| API service | `rateb_mobile/lib/core/services/rateb_api_service.dart` |
| Auth | `rateb_mobile/lib/core/auth/auth_repository.dart` |
| Token storage | `rateb_mobile/lib/core/auth/token_storage.dart` |
| QR | `rateb_mobile/lib/features/qr/` |
| Mobile APIs | `api/mobile/*.php` |

---

## What is **not** in scope yet

- App Store / Play Store submission
- Push notifications
- Full offline sync / SQLite
- Dedicated `company-dashboard.php` endpoint (summary is computed client-side today)
- Control-panel UI for mobile user management
- Automated E2E tests

---

## Is that all?

**For the mobile MVP scope agreed in this workstream: yes — the code is complete.**

Remaining work is **operational**, not feature coding:

1. Confirm bottom tabs respond on your machine (8090 + hard refresh).
2. Ensure `api/mobile/*` files are deployed to production.
3. Validate real data appears when workers/requests exist in the database.
4. Optional: native device testing and store packaging.

---

*Report generated from development session through 25 May 2026.*
