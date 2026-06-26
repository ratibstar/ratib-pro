# Security Operational Validation — Production

**Date:** 2026-06-26  
**Host:** `https://rateb.sa`

## 1. Document barcode / scan

### Unauthenticated access

| Request | HTTP | Behaviour |
|---------|------|-----------|
| `GET /rateb-erp/public/scan/doc/PO00030000000123` | **302** | `Location: …/login` |

✅ Anonymous users cannot view document cards (redirect to login — equivalent to auth gate).

### Authenticated cross-tenant / permission

| Test | Status |
|------|--------|
| Login as Company A, scan Company B barcode | **NOT EXECUTED** — no test credentials on auditor workstation |
| Branch isolation with HQ user | **NOT EXECUTED** |

**Code deployed:** `ErpAuthMiddleware` on route + `canViewBarcodeRecord()` in `DocumentBarcodeService` (verified in build; runtime cross-tenant test pending credentials).

## 2. Static security certification probe

```
GET /rateb-erp/public/erp-security-cert.php
```

| Field | Value |
|-------|-------|
| `ok` | `true` |
| `critical` | **0** |
| `high` | **0** |
| `open_findings` | **0** |

## 3. Security headers

### `GET /rateb-erp/public/erp-health.php`

| Header | Present |
|--------|---------|
| Content-Security-Policy | ✅ |
| Strict-Transport-Security | ✅ `max-age=31536000; includeSubDomains` |
| X-Frame-Options | ✅ `SAMEORIGIN` |
| X-Content-Type-Options | ✅ `nosniff` |
| Referrer-Policy | ✅ `strict-origin-when-cross-origin` |
| Permissions-Policy | ✅ `camera=(), microphone=(), geolocation=()` |

### `GET /rateb-erp/public/login`

All six headers above: ✅ present (CSP confirmed via full header dump).

## 4. API rate limiting

**Test:** 130 sequential `GET /rateb-erp/public/api/v1` (no auth) from single IP.

| HTTP code | Count |
|-----------|------:|
| 200 | 119 |
| **429** | **11** |

✅ Rate limiter triggers **HTTP 429** under burst load.

Token-authenticated read/write limits: **NOT separately measured** (requires API credentials).

## 5. CMS XSS / SVG (requires admin session)

| Test | Status |
|------|--------|
| Inject `<script>alert(1)</script>` in `custom_head_code` | **NOT EXECUTED** — no CMS admin credentials |
| Upload malicious SVG | **NOT EXECUTED** — no CMS admin credentials |

**Deployed controls:** `HtmlSanitizer::sanitizeAnalyticsEmbed` on save/render; SVG MIME removed from `CmsMediaService` allowlist.

## Conclusion

| Area | Production result |
|------|-------------------|
| Health / probes | ✅ PASS |
| Security headers | ✅ PASS |
| API 429 | ✅ PASS |
| Barcode unauthenticated | ✅ PASS (302 login) |
| Barcode cross-tenant (live) | ⏸ PENDING credentials |
| CMS XSS/SVG (live) | ⏸ PENDING credentials |
