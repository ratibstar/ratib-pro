# Phase I.3 — Flutter ESS Push Client Integration

**Status:** COMPLETE (Flutter client only)  
**Date:** 20 Jul 2026  
**Depends on:** I.1 `POST /api/v1/mobile/devices/push-token` · Phase J device register

---

## Architecture Lock

- ERP remains notification authority and device registry owner.
- Flutter acquires OS/FCM token and POSTs it — **no routing / business rules**.
- Settings notification toggle remains a **local preference only** (does not block ERP registration).

```
Auth → employee → device register/heartbeat → push token → ERP
```

---

## Flutter pieces

| Piece | Role |
|-------|------|
| `MobileDevicePort.updatePushToken` | Contract for push-token API |
| `ErpDeviceRegistryAdapter` | `POST …/push-token` — never sends user/company ids |
| `PushMessagingGateway` | Firebase or Noop |
| `PushNotificationService` | Init, permission, token → ERP, refresh, foreground display |
| `AuthSession` | Soft-fail network/missing Firebase; hard-fail `device_revoked` |

---

## Native

- Android: `POST_NOTIFICATIONS`, optional `google-services.json` (see `.example`), conditional Google Services plugin
- iOS: `UIBackgroundModes` remote-notification, APNs register in `AppDelegate`, `GoogleService-Info.plist.example`

Copy Firebase config locally — **do not commit production secrets**.

---

## Non-goals

Firebase server keys · ERP worker changes · Manager app · Store · rich actions

---

## Tests

```
flutter test test/phase_i3_push_client_test.dart
flutter test test/phase_j_device_registry_test.dart
```

Coverage: token → ERP, no user/company fields, refresh, revoked, network soft-fail, missing Firebase soft-fail, notifications page surface unchanged.

**Result (2026-07-20):** I.3 8/8 + Phase J 8/8 passed.
