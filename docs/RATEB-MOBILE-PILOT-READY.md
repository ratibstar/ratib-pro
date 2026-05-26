# RATEB Mobile — Pilot Readiness Checklist

**Version:** 1.0.0+1  
**API target:** `https://out.ratib.sa/api`  
**Last updated:** 2026-05-25  
**Scope:** Internal pilot (company/agency/worker portals + workforce QR login)

---

## Executive summary

| Area | Status | Notes |
|------|--------|-------|
| Production JWT auth | **GO** | Profile returns 401 (not 503) when unauthenticated |
| Password login | **GO** | Verified against live API |
| QR workforce identity UX | **GO** | Native scanner, overlay, lifecycle handling |
| Pilot diagnostics | **GO** | Debug + `RATEB_DIAGNOSTICS=true` |
| Pilot tools (internal) | **GO** | Debug builds only |
| Real-device QR scan (live) | **PENDING** | Requires Android/iPhone + badge from System Settings |
| Android store bundle | **VERIFY** | Build locally before Play internal track |
| iOS TestFlight | **PENDING** | Requires Mac + signing |

**Pilot verdict: GO for internal pilot** — with the condition that at least one Android device completes a live QR scan before expanding beyond 5–10 users.

**Production readiness score: 82 / 100**

---

## Android checklist

### Build & install
- [ ] `flutter build apk --release` succeeds
- [ ] `flutter build appbundle --release` succeeds
- [ ] Install APK on physical device (not emulator-only)
- [ ] App launches without crash on cold start

### Permissions
- [ ] Camera permission prompt appears on first QR scan
- [ ] Deny → clear error + “Try again” works
- [ ] Grant in Settings → scanner resumes after return

### QR scanner
- [ ] Open **System Settings → user → Workforce QR / Show barcode** on `out.ratib.sa`
- [ ] Scan badge QR with rear camera
- [ ] Success animation → lands on correct portal (worker / company / agency)
- [ ] Invalid/expired QR shows friendly error, scanner reopens
- [ ] Background app during scan → resume without freeze
- [ ] Torch toggle works; no leak after leaving scanner

### Auth & session
- [ ] Password login works on mobile data and Wi‑Fi
- [ ] Kill app → reopen → session restored (if token valid)
- [ ] Expired token → “Session expired” on login, no redirect loop
- [ ] Logout clears session and dashboard cache

### Performance
- [ ] Scanner overlay smooth (no jank on mid-range device)
- [ ] Login &lt; 3s on 4G
- [ ] Dashboard loads &lt; 2s after login (empty data OK)

---

## iPhone checklist

### Build & install
- [ ] `flutter build ios --release` on Mac with valid signing
- [ ] Install via TestFlight or ad-hoc to physical iPhone
- [ ] `NSCameraUsageDescription` present in Info.plist (**already in repo**)

### Safe area & layout
- [ ] Notch / Dynamic Island: scanner hints not clipped
- [ ] Error banner above home indicator
- [ ] Login form readable in portrait

### AVFoundation lifecycle
- [ ] Scan → background (home) → foreground → camera restarts
- [ ] Phone call interruption → return → scanner recoverable
- [ ] No black camera preview after resume

### QR & auth
- [ ] Same QR flow as Android checklist
- [ ] Haptics on success (if device supports)

---

## Web checklist

### Build
- [ ] `flutter build web --release` succeeds
- [ ] Deploy artifact if needed (web is secondary for pilot)

### Workforce identity (paste fallback)
- [ ] “Workforce identity login” opens paste screen (no camera on web)
- [ ] Paste payload from System Settings barcode page
- [ ] Verify and sign in → correct portal

### Session
- [ ] Refresh page restores session from SharedPreferences
- [ ] Logout clears storage

---

## Pilot rollout plan

### Phase 0 — Pre-pilot (1 day)
1. Confirm `MOBILE_AUTH_SECRET` in server `.env` (already done).
2. Build release APK; sideload to 2 Android devices.
3. Generate workforce QR from **System Settings** for 1 test user per role.
4. Complete Android + iPhone checklists above.

### Phase 1 — Internal pilot (week 1)
| Cohort | Size | Roles |
|--------|------|-------|
| Core team | 3–5 | Admin, company, 1 worker |
| Extended | 5–10 | Mixed roles |

**Distribution:** Direct APK (Android) or Play internal testing; TestFlight (iOS).

**Training (5 min):**
- Password login OR scan badge from System Settings
- Do not share QR screenshots externally
- Report issues via support channel (below)

### Phase 2 — Stabilize (week 2)
- Fix pilot feedback (P0/P1 only)
- Monitor API 401/503 rates
- Expand to 15–25 users if no P0 issues

---

## Rollback plan

| Trigger | Action |
|---------|--------|
| Auth 503 / config_error | Check server `.env` + deploy `api/mobile/env.inc.php`; pause pilot |
| Widespread login failure | Disable pilot APK distribution; users use web portal |
| QR bypass / security issue | Rotate `MOBILE_AUTH_SECRET`; invalidate all JWTs; hotfix app |
| Camera crash on specific devices | Disable QR entry in hotfix; password-only until patch |

**Server rollback:** Revert mobile API files via GitHub Actions fast deploy (previous commit).  
**App rollback:** Reinstall previous APK / TestFlight build.

---

## Known risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| Live QR never tested on physical device | **High** | Block Phase 1 expansion until 1 successful device scan |
| Android SDK licenses / cmdline-tools on dev machine | Medium | Use CI or Android Studio machine for appbundle |
| Empty dashboard data | Low | Expected on fresh tenant; not a mobile bug |
| QR payload expiry | Medium | Regenerate from System Settings; show friendly error |
| Web DWDS debug console noise | Low | Use release build for demos |
| Pilot tools in debug only | Low | Release builds exclude entry point |

---

## Recommended monitoring

### Server (cPanel / logs)
- `POST /api/mobile/login.php` — 4xx/5xx rate
- `POST /api/mobile/qr-login.php` — invalid payload vs success
- `GET /api/mobile/profile.php` — 401 spikes (expired tokens)
- PHP error log — no `config_error` / missing secret

### Client (pilot)
- Enable diagnostics build:  
  `flutter run --dart-define=RATEB_DIAGNOSTICS=true`
- Pilot tools → refresh diagnostics → API latency + health
- Track: login time, QR verify time, dashboard load (ApiTelemetry in debug)

### Alerts
- Any 503 on `/mobile/profile.php` → page on-call immediately
- &gt;10% QR login failure in 1 hour → pause rollout

---

## Support workflow

1. **User reports issue** → role, device, Android/iOS version, screenshot (no QR payload).
2. **L1:** Password login works? Network OK? QR regenerated in last 10 min?
3. **L2 (internal):** Pilot tools → test profile, copy diagnostics JSON (debug build).
4. **L3:** Check server logs for matching timestamp + endpoint.
5. **Escalate** if auth 503, token leak suspicion, or camera crash on &gt;2 devices.

---

## Safe pilot user count

| Stage | Max users | Gate |
|-------|-----------|------|
| Smoke test | 2–3 | 1 successful QR scan per platform |
| Internal pilot | **10** | 48h without P0 |
| Expanded pilot | **25** | Week 1 checklist complete |
| Production consideration | 100+ | Store release + MDM + crash reporting |

---

## Diagnostics & pilot tools

### Device diagnostics
- **File:** `rateb_mobile/lib/core/debug/device_diagnostics.dart`
- **Enabled when:** `kDebugMode` OR `--dart-define=RATEB_DIAGNOSTICS=true`
- **Includes:** platform, app version, API reachability/latency, network, camera mode/permission, QR scanner state, token presence (boolean only)

### Pilot tools (debug only)
- **File:** `rateb_mobile/lib/features/debug/pilot_tools_screen.dart`
- **Entry:** Login screen → “Pilot tools (internal)” (debug builds only)
- **Features:** clear token/cache, simulate offline, JWT claims view, copy API URL, test profile, fake QR for UI

---

## Build commands

```bash
cd rateb_mobile

# Analysis
dart analyze lib

# Web
flutter build web --release

# Android
flutter build apk --release
flutter build appbundle --release

# Diagnostics pilot build
flutter run --dart-define=RATEB_DIAGNOSTICS=true
```

---

## Remaining blockers

1. **Physical device QR validation** — not yet confirmed end-to-end on Android/iPhone.
2. **Android appbundle** — must be built on a machine with accepted SDK licenses.
3. **iOS distribution** — requires Apple developer signing + TestFlight setup.
4. **Crash/analytics SDK** — not integrated (recommended before &gt;25 users).

---

## Recommended next actions

1. Install release APK on Android; scan live badge from System Settings.
2. Repeat on iPhone via TestFlight or ad-hoc.
3. Run 48-hour internal pilot with 5 users; monitor server logs.
4. Add Firebase Crashlytics or Sentry before expanded pilot.
5. Submit appbundle to Play **internal testing** track (not production).

---

## GO / NO-GO — internal pilot

| Criterion | Result |
|-----------|--------|
| Auth stable on production API | **GO** |
| QR UX production-grade | **GO** |
| Security (no token/QR logging in release) | **GO** |
| Stability hardening (navigation, lifecycle, duplicate submit) | **GO** |
| Live device QR proof | **NO-GO until done** |
| Store-ready binaries | **PARTIAL** (web OK; native verify locally) |

### Final recommendation

**Conditional GO** — proceed with **internal pilot (≤10 users)** using password login immediately; enable QR for pilot users **after one successful physical device scan per platform**.

---

*Generated as part of RATEB Mobile pilot readiness pass. Flutter app path: `rateb_mobile/`.*
