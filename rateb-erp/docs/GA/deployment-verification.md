# Deployment Verification — RATIB ERP v1.0 GA

**Verified:** 2026-06-26T18:46Z (UTC)  
**Environment:** Production `https://rateb.sa`  
**Staging:** No separate staging hostname found in repository or DNS probes — verification performed on Production only.

## Git / Repository

| Field | Value |
|-------|-------|
| Branch | `main` |
| Local HEAD | `3eec9ce1f34aa672b0c78fa91e61981829f59417` |
| Remote `origin/main` | `3eec9ce1f34aa672b0c78fa91e61981829f59417` |
| Commit message | `update-20260626-214421` |
| Commit timestamp | `2026-06-26 21:44:21 +0300` |
| Sync status | Local = remote (**in sync**) |

## Production build marker

**Request:**
```
GET https://rateb.sa/rateb-erp/public/ratib-erp-build.txt
```

**Response:** HTTP 200  
**Body:**
```
rateb-erp-ga-security-20260626
```

**Expected build (from GA security commit):** `rateb-erp-ga-security-20260626`  
**Result:** ✅ **MATCH**

## Application version

| Setting | Value |
|---------|-------|
| `RATEB_APP_VERSION` (config) | `1.0.0` |
| `RATEB_ASSET_BUILD` | `20260626-ga-security-blockers` |

Version string is not exposed on public health endpoint (by design post-security fix).

## Deployment timestamp

| Source | Value |
|--------|-------|
| Git commit time | 2026-06-26 21:44:21 +0300 |
| GitHub Actions run | Not queried (`gh` CLI unavailable on auditor workstation) |
| Live evidence | Build marker + health behaviour changed from pre-GA audit → **deploy confirmed operationally** |

## Smoke endpoints

| URL | HTTP | Body (truncated) |
|-----|------|------------------|
| `/rateb-erp/public/erp-health.php` | 200 | `{"status":"ok"}` |
| `/erp-health.php` (root alias) | 200 | `{"status":"ok"}` |
| `/rateb-erp/public/erp-security-cert.php` | 200 | `"ok": true`, critical=0, high=0 |

## Conclusion

✅ **Deployment verification PASSED** — production serves GA security build `rateb-erp-ga-security-20260626` with expected anonymous health response.
