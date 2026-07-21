# Google Play — Release Preparation (Phase K2)

**Status:** Prep complete — **do not upload** until operator checklist below is green.  
**App:** RATIB HR (`sa.rateb.hr.mobile`)  
**AAB (local):** `build/app/outputs/bundle/productionRelease/app-production-release.aab`  
**Version:** `1.0.0` (versionCode `200`) · flavor `production`

Related: [PHASE_K1.md](PHASE_K1.md) · [PHASE_K3.md](PHASE_K3.md) (Internal checklist) · [COMPLIANCE.md](COMPLIANCE.md) · [STORE_ASSETS_CHECKLIST.md](STORE_ASSETS_CHECKLIST.md)

---

## 1. AAB review (verified)

| Check | Value | OK |
|-------|--------|----|
| applicationId | `sa.rateb.hr.mobile` | Yes |
| versionName / versionCode | `1.0.0` / `200` | Yes |
| Flavor | `production` | Yes |
| ERP base (build) | `https://rateb.sa/rateb-erp/public` | Yes |
| Signing | Upload keystore (`CN=RATIB HR Mobile Upload`) — not debug | Yes (K1) |
| Cleartext HTTP | Disabled | Yes |
| Minify / shrink | Enabled (release) | Yes |

Rebuild: `powershell -File tool/build_android_aab.ps1`

---

## 2. Android Manifest & Play policy (reviewed)

### Declared in app `AndroidManifest.xml`

- `INTERNET`
- `POST_NOTIFICATIONS` (API 33+)

### Merged (plugins — expected & used)

| Permission | Source / purpose |
|------------|------------------|
| `INTERNET` | ERP HTTPS API |
| `ACCESS_NETWORK_STATE` | connectivity_plus |
| `POST_NOTIFICATIONS` | FCM / local notifications UX |
| `WAKE_LOCK` | Firebase Messaging |
| `VIBRATE` | Notification UX |
| `USE_BIOMETRIC` | local_auth (PIN unlock) |
| `USE_FINGERPRINT` | local_auth (legacy API) |
| `com.google.android.c2dm.permission.RECEIVE` | FCM |

**Not present (good):** `CAMERA`, `RECORD_AUDIO`, `ACCESS_FINE_LOCATION`, `READ_EXTERNAL_STORAGE`, `WRITE_EXTERNAL_STORAGE`, `READ_MEDIA_*`, SMS, contacts, phone.

### SDK levels (merged)

| Field | Value | Play note |
|-------|--------|-----------|
| minSdk | 24 | OK |
| targetSdk | 36 | Meets current Play target requirement (API 35+ for new apps / updates as of 2025–2026 policy waves) |
| compileSdk | 36 | Aligns with Flutter 3.44 defaults |

No permission removals needed for this release; all merged permissions map to shipped features.

---

## 3. Store listing copy (draft)

### Short description (≤ 80 characters)

**EN:**  
`Employee self-service for leave, payslips, and HR tasks — powered by RATIB ERP.`

**AR:**  
`الخدمة الذاتية للموظفين: إجازات وكشوف رواتب ومهام الموارد البشرية عبر راتب.`

### Full description — English

```
RATIB HR is the official employee self-service app for organizations running RATIB ERP.

Sign in with your workplace account to:
• View your dashboard and pending HR tasks
• Request leave and track approvals
• Open payslips and HR documents (when enabled by your employer)
• Manage profile details allowed by your organization
• Receive HR notifications (when push is enabled for your tenant)
• Continue securely offline for queued actions, then sync when back online

Authentication and business rules live in RATIB ERP. This app is a secure presentation client — your employer remains the source of truth for HR data and access rights.

Need help? Contact your HR administrator or visit https://rateb.sa
```

### Full description — Arabic

```
راتب للموارد البشرية هو التطبيق الرسمي للخدمة الذاتية للموظفين لدى المؤسسات التي تستخدم نظام راتب ERP.

سجّل الدخول بحساب عملك لـ:
• عرض لوحة المتابعة ومهام الموارد البشرية
• طلب الإجازات ومتابعة الموافقات
• الاطلاع على كشوف الرواتب والمستندات (حسب تفعيل جهة العمل)
• إدارة بيانات الملف الشخصي المسموح بها
• استلام إشعارات الموارد البشرية (عند تفعيل الإشعارات لمؤسستك)
• العمل بأمان دون اتصال لطابور الإجراءات ثم المزامنة عند عودة الشبكة

المصادقة وقواعد العمل موجودة في راتب ERP. هذا التطبيق واجهة آمنة فقط — وجهة عملك هي مصدر الحقيقة لبيانات الموارد البشرية وصلاحيات الوصول.

للمساعدة: تواصل مع مسؤول الموارد البشرية أو زر https://rateb.sa
```

### Feature highlights (store “What’s new” bullets / listing bullets)

1. Secure ERP sign-in (token-based; no password stored on device)
2. Leave requests & approval status
3. Payslips & HR documents (tenant-enabled)
4. Offline queue with sync when online
5. Optional biometric / PIN app unlock
6. Arabic & English UI (RTL supported)
7. Push notification foundation (tenant/FCM when configured)

### Release notes — `1.0.0 (200)`

**EN:**  
`First Play release of RATIB HR: employee self-service for leave, payslips, profile, offline sync, and notifications — connected to RATIB ERP.`

**AR:**  
`الإصدار الأول على Google Play لتطبيق راتب للموارد البشرية: خدمة ذاتية للإجازات وكشوف الرواتب والملف الشخصي والمزامنة دون اتصال والإشعارات — متصل بنظام راتب ERP.`

---

## 4. Privacy Policy — requirements checklist

Before production (and ideally before closed testing with external testers):

- [ ] Public HTTPS **Privacy Policy** URL (Arabic + English, or bilingual page)
- [ ] States controller / company legal name and contact
- [ ] Describes data processed via ERP APIs (account identifiers, HR records the employer enables)
- [ ] States **no passwords** stored on device; session tokens handled per security design
- [ ] Describes offline/local cache (encrypted where applicable) and retention/wipe on logout
- [ ] Describes push tokens (FCM) if notifications enabled
- [ ] Describes biometric/PIN used only for **local app unlock** (not sent to server as credentials)
- [ ] Children’s / age suitability statement (workforce app; not directed at children)
- [ ] Link same URL in Play Console → App content → Privacy policy
- [ ] Optional but recommended: public Terms of Use URL

**Suggested host paths (operator):** pages under `https://rateb.sa/...` owned by product/legal — not committed as final legal text in this repo.

See also [COMPLIANCE.md](COMPLIANCE.md).

---

## 5. Data Safety questionnaire — draft answers

Fill in Play Console → App content → Data safety. Confirm with legal before submit.

| Question theme | Draft answer |
|----------------|--------------|
| Does the app collect / share user data? | **Yes — collect** (via ERP over HTTPS). Sharing with third parties: **No** beyond Google infrastructure (FCM) as required for notifications. |
| Data collected | Account ID / username (employer account); employment-related data displayed from ERP (leave, payslip metadata, profile fields employer allows); device push token (if notifications on); app diagnostics only if you later enable Crashlytics (currently not required). |
| Data encrypted in transit? | **Yes** (HTTPS / TLS). |
| Users can request deletion? | **Yes** — via employer / ERP account lifecycle (app does not own accounts). Document in Privacy Policy. |
| Data sold? | **No**. |
| Location | **Not collected**. |
| Photos / video / files from device gallery | **Not collected** by default (no camera/storage permission). Document downloads may be viewed/opened via system handlers if ERP provides URLs. |
| Financial info | Payslip **view** from employer ERP — treat as employer-provided employment data; follow Play “Financial info” guidance with legal if classified as such. |
| Biometrics | Used for **local unlock only**; biometric templates stay on device OS; app does not upload biometrics. |
| Approximate / precise location | **No**. |

Declare permissions that match: Internet, notifications, biometric (local).

---

## 6. Store assets — required list

Full matrix: [STORE_ASSETS_CHECKLIST.md](STORE_ASSETS_CHECKLIST.md).

| Asset | Spec (Play) | Status |
|-------|-------------|--------|
| App icon | 512×512 PNG, 32-bit | Pending design |
| Feature graphic | 1024×500 PNG/JPG | Pending design |
| Phone screenshots | ≥ 2; max 8; 16:9 or 9:16 | Pending (capture from release build) |
| 7-inch tablet screenshots | Optional unless targeting tablets | Optional |
| 10-inch tablet screenshots | Optional | Optional |
| Arabic RTL screenshots | Same sizes; UI locale `ar` | **Required for AR listing quality** — capture with device/emulator language Arabic |
| English screenshots | Same | Recommended |
| Promo video | Optional | Optional |

**Suggested screenshot set (phone):** Login → Home → Leave → Payslip → Profile → Offline/sync indicator (if visible).

---

## 7. Play Console — upload & tracks (operator; do not auto-upload)

### 7.1 Create / open app

1. [Google Play Console](https://play.google.com/console) → Create app (or open existing).
2. App name: **RATIB HR** / **راتب للموارد البشرية**.
3. Default language: Arabic or English (add the other as translation).
4. App / Game → **App**; Free/Paid → per commercial model.
5. Declarations: Privacy Policy URL, Data safety, Ads (likely **No** ads), Target audience (employees / 18+), News app = No, COVID = No, etc.

### 7.2 Play App Signing

1. Release → Setup → **App signing**.
2. Prefer **Upload key** = current `ratib-hr-upload-key.jks` (K1).
3. Let Google hold the **app signing key** (recommended).
4. Export and store **upload certificate** / PEM from Console after first enrollment.
5. Keep local keystore backup offline; losing upload key requires Play support reset process.

**Do not** replace signing in CI or regenerate keystore without a documented key-reset plan.

### 7.3 Upload AAB

1. Build: `tool/build_android_aab.ps1`
2. Artifact: `app-production-release.aab`
3. Play Console → Testing or Production → Create release → Upload AAB
4. Confirm package `sa.rateb.hr.mobile`, versionCode `200`
5. Paste release notes (EN + AR)

### 7.4 Internal testing

1. Create **Internal testing** track.
2. Add testers (email lists / Google Groups).
3. Upload AAB → Review → Start rollout to internal.
4. Install via opt-in link; verify login against production ERP, leave, payslip, offline queue, notifications permission prompt.

### 7.5 Closed testing

1. Create **Closed testing** (alpha/beta) with wider employee cohort.
2. Complete store listing + Privacy Policy (required for external testers in many flows).
3. Address pre-launch report / policy warnings.
4. Collect crash-free sessions before production.

### 7.6 Production release checklist

- [ ] Internal testing signed off
- [ ] Closed testing signed off (recommended)
- [ ] Store listing complete (EN + AR)
- [ ] Short + full descriptions pasted
- [ ] Icon + feature graphic + phone screenshots (+ AR RTL set)
- [ ] Privacy Policy URL live
- [ ] Data safety form submitted
- [ ] Content ratings questionnaire done
- [ ] Target audience / news / ads declarations done
- [ ] Play App Signing enrolled; upload key backed up
- [ ] AAB `1.0.0+200` uploaded
- [ ] Countries / pricing set
- [ ] Rollout % chosen (start 20% optional)
- [ ] Support email / website set
- [ ] No debug / staging applicationId
- [ ] ERP production URL only in this binary

---

## 8. Explicit non-goals (this phase)

- No Play Console upload from CI/agent
- No signing key rotation or architecture changes
- No Flutter/ERP feature work in K2
- No iOS upload (separate App Store track)

---

## 9. Blockers before final production publish

| # | Blocker | Owner |
|---|---------|--------|
| 1 | Play Console app + **Play App Signing** enrollment | Operator |
| 2 | Live **Privacy Policy** (+ ideally Terms) HTTPS URLs | Legal / product |
| 3 | **Data safety** + content ratings submitted | Operator + legal |
| 4 | Store **graphics & screenshots** (incl. Arabic RTL) | Design |
| 5 | Upload-keystore **offline backup** verified | Operator (K1) |
| 6 | At least **Internal** (preferably Closed) test pass on real devices | QA |

None of these are code blockers in the AAB itself if K1 artifact remains valid.
