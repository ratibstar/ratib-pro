# Phase K3 — Google Play Internal Release Checklist

**Status:** COMPLETE (checklist only — **no upload executed**)  
**Date:** 2026-07-21  
**Depends on:** K1 signed AAB · K2 store prep ([PLAY_STORE_RELEASE.md](PLAY_STORE_RELEASE.md))

**Operator goal:** First upload of RATIB HR to Google Play **Internal testing** track.

---

## Explicit non-goals (this phase)

- No Play Console upload from CI/agent
- No signing / keystore / `applicationId` changes
- No Flutter architecture or ERP API changes
- No secrets requested or committed

---

## 1. Final AAB review (local evidence)

| Check | Expected | Evidence |
|-------|----------|----------|
| Artifact path | `build/app/outputs/bundle/productionRelease/app-production-release.aab` | Present (~55 MB, built 2026-07-21) |
| Package / applicationId | `sa.rateb.hr.mobile` | Merged productionRelease manifest |
| versionName | `1.0.0` | Manifest + `pubspec.yaml` `1.0.0+200` |
| versionCode | `200` | Manifest |
| Flavor | `production` | Build path / Gradle product flavor |
| Signature | Upload key (not debug) | `jarsigner -verify` → **jar verified** (self-signed upload cert — expected before Play App Signing) |
| minSdk / targetSdk | 24 / 36 | Merged manifest |
| Cleartext | Disabled | `usesCleartextTraffic=false` |

Rebuild (if needed, operator machine with local `key.properties` only):

```powershell
powershell -File tool/build_android_aab.ps1
```

**Do not** regenerate the upload keystore. **Do not** change `applicationId`.

---

## 2. Permissions (merged productionRelease)

| Permission | Required for |
|------------|----------------|
| `INTERNET` | ERP HTTPS |
| `ACCESS_NETWORK_STATE` | Connectivity |
| `POST_NOTIFICATIONS` | Notifications (API 33+) |
| `WAKE_LOCK` | FCM |
| `VIBRATE` | Notification UX |
| `USE_BIOMETRIC` / `USE_FINGERPRINT` | Local unlock (`local_auth`) |
| `com.google.android.c2dm.permission.RECEIVE` | FCM |
| App-signature dynamic receiver permission | AndroidX / FCM plumbing |

**Absent (good for Play):** camera, mic, location, SMS, contacts, broad storage.

Details: [PLAY_STORE_RELEASE.md](PLAY_STORE_RELEASE.md) §2 · [COMPLIANCE.md](COMPLIANCE.md)

---

## 3. Privacy & Data Safety (gate before external/closed; recommended before Internal)

| Item | Draft status | Operator action |
|------|--------------|-----------------|
| Privacy Policy HTTPS URL | Draft requirements in K2 | Paste live URL in Play Console |
| Data Safety answers | Draft in COMPLIANCE + PLAY_STORE_RELEASE §5 | Submit in App content |
| Ads | App has no ads | Declare **No** |
| Target audience | Workforce / 18+ | Complete questionnaire |

Internal testing can start with limited testers; complete Privacy Policy + Data Safety before widening beyond the internal list.

---

## 4. Operator steps — first Internal release

### Step 1 — Create App in Play Console

1. Open [Google Play Console](https://play.google.com/console).
2. **Create app**.
3. App name: **RATIB HR** (AR listing: **راتب للموارد البشرية**).
4. Default language: Arabic or English; add the other locale later.
5. App or game → **App**; Free/Paid per commercial model.
6. Accept declarations.

### Step 2 — Enable Play App Signing

1. Release → Setup → **App signing** (or prompted on first upload).
2. Enroll in **Play App Signing**.
3. Use the existing **upload key** from K1 (`ratib-hr-upload-key.jks` / alias `ratib_hr_upload`) — do not create a new key unless Play walks you through a reset.
4. Let Google hold the **app signing key**.
5. Download/export the **upload certificate** from Console after enrollment and store offline with the keystore backup.

### Step 3 — Upload `app-production-release.aab`

1. Confirm local file:  
   `ratib_hr_mobile/build/app/outputs/bundle/productionRelease/app-production-release.aab`
2. Play Console → **Testing** → **Internal testing** → Create new release.
3. Upload the AAB.
4. Confirm Console shows:
   - Package name: `sa.rateb.hr.mobile`
   - Version: `1.0.0` (200)
5. Paste release notes (AR/EN drafts in [PLAY_STORE_RELEASE.md](PLAY_STORE_RELEASE.md) §3).
6. Save → Review → **Start rollout to Internal testing**.

### Step 4 — Configure Internal Testing

1. Ensure Internal testing track is active.
2. Complete any required **App content** warnings that block rollout (Privacy Policy often required for broader tracks; Internal may warn).
3. Optional: fill short description early so the store listing card is not empty for testers.

### Step 5 — Add tester accounts

1. Internal testing → **Testers** → create email list (or Google Group).
2. Add employee Google accounts that will install from Play.
3. Copy the **opt-in URL** and share with testers.
4. Testers must accept the invite, then install from Play Store (Internal track).

### Step 6 — Verify install / update flow

On at least one physical Android device (API 24+):

| Check | Pass criteria |
|-------|----------------|
| Install from Internal opt-in | App installs; package `sa.rateb.hr.mobile` |
| Cold start | Splash → login (or session restore) |
| Login | Against production ERP `https://rateb.sa/rateb-erp/public` |
| Core ESS | Home / leave / payslip or documents as tenant allows |
| Notifications permission | Runtime prompt on API 33+; deny still allows app use |
| Offline queue (smoke) | Banner / queue behavior if network toggled |
| Update path | Upload a later versionCode (>200) to Internal; device offers update; data/session behavior acceptable |

Record pass/fail with device model + Android version for Closed testing gate.

---

## 5. Pre-upload operator checklist (print / tick)

- [ ] Upload keystore + `key.properties` backed up offline (never commit)
- [ ] AAB path confirmed; versionCode **200**; package **sa.rateb.hr.mobile**
- [ ] Play Console app created
- [ ] Play App Signing enrolled with **existing** upload key
- [ ] AAB uploaded to **Internal testing** only (not Production yet)
- [ ] Tester list + opt-in link shared
- [ ] Install/update smoke on real device signed off
- [ ] Privacy Policy URL + Data Safety prepared for next track

---

## 6. After Internal — next tracks (not K3)

See [PLAY_STORE_RELEASE.md](PLAY_STORE_RELEASE.md) §7.5–7.6:

1. Closed testing (wider cohort + listing assets)
2. Production release checklist

---

## 7. Blockers (operator — not code)

| # | Blocker |
|---|---------|
| 1 | Play Console access / app creation |
| 2 | Play App Signing enrollment with local upload key |
| 3 | Manual AAB upload (this phase does not upload) |
| 4 | Live Privacy Policy URL (before widening testers) |
| 5 | Real-device Internal smoke (not done in-repo) |

**Code/AAB:** No K3 code blocker if the K1 production AAB above remains the upload candidate.
