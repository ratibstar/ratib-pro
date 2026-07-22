# Phase K — Store compliance checklist

**App:** RATEB HR Mobile · presentation-only ESS client for RATEB ERP  
**Architecture Lock:** No passwords stored in Identity vault; Online ERP = Authentication Authority

## Platform targets (engineering evidence)

| Platform | Setting | Value | Source |
|----------|---------|-------|--------|
| Android `minSdk` | Flutter default | **24** | Flutter 3.44 `FlutterExtension` |
| Android `targetSdk` / `compileSdk` | Flutter default | **36** | Flutter 3.44 |
| iOS deployment target | Xcode project | **13.0** | `IPHONEOS_DEPLOYMENT_TARGET` |
| Android cleartext | Disabled | `usesCleartextTraffic=false` | Manifest |
| iOS ATS | HTTPS only | `NSAllowsArbitraryLoads=false` | Info.plist |

## Permissions

| Permission | Platform | Purpose | Declarable in stores |
|------------|----------|---------|----------------------|
| `INTERNET` | Android | ERP HTTPS API | Network |
| `ACCESS_NETWORK_STATE` | Android | Connectivity (plugin) | Network |
| `POST_NOTIFICATIONS` | Android 13+ | Push / local notifications | Notifications |
| `WAKE_LOCK` | Android | FCM delivery | Notifications |
| `VIBRATE` | Android | Notification UX | Notifications |
| `USE_BIOMETRIC` / `USE_FINGERPRINT` | Android | Local app unlock (`local_auth`) | Biometrics (local only) |
| `com.google.android.c2dm.permission.RECEIVE` | Android | FCM | Notifications |
| Face ID usage | iOS | Unlock existing ERP session (`local_auth`) | Biometrics |
| Camera / Photos / Location / Mic | — | **Not used** | Do not declare |

Merged productionRelease manifest reviewed in Phase K2 — see [PLAY_STORE_RELEASE.md](PLAY_STORE_RELEASE.md) §2.

## Google Play — Data Safety (draft answers)

| Data type | Collected? | Shared? | Purpose | Notes |
|-----------|------------|---------|---------|-------|
| Email / phone | May display from ERP profile | No (to third parties by app) | App functionality | Server-side ERP SoT |
| Name / employee ID | Display from ERP | No | App functionality | |
| Device IDs | Install id for device registry | With ERP only | Fraud prevention / push | Phase J registry |
| Push tokens | FCM/APNs handle | With ERP only | Messaging | Not an auth secret |
| Auth tokens | Stored in secure storage on device | No | Auth | ERP-issued; not in Identity vault |
| Photos / files | Payslip/document download cache optional | No | App functionality | Online fetch |
| Location | **No** | — | — | |
| Approximate/precise location | **No** | — | — | |

Security practices to claim only if true in Console:

- ☐ Data encrypted in transit (HTTPS to ERP) — **Yes**
- ☐ Users can request deletion — via ERP/company admin process (document URL)

## Apple — App Privacy (draft)

| Category | Linked to user? | Used for tracking? | Notes |
|----------|-----------------|--------------------|-------|
| Contact Info | Yes (from ERP account) | No | |
| Identifiers (Device ID) | Yes | No | Device registry |
| Diagnostics | No unless added later | No | |
| Usage Data | No | No | |
| Location | No | No | |
| Sensitive Info | No national ID / salary in mobile DTO | — | Profile excludes salary/national_id |

## Notifications

- Android: runtime `POST_NOTIFICATIONS` + FCM (optional `google-services.json`)
- iOS: APNs register + `UIBackgroundModes` remote-notification + production APS entitlement
- In-app notification center uses ERP APIs (independent of push delivery)

## Explicit non-claims

- Do not claim on-device encryption for ERP business data beyond OS secure storage for tokens
- Do not claim offline payroll/documents sync
- Do not claim Manager approvals (placeholder)
