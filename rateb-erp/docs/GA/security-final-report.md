# Security Final Report — GA Blocker Fixes

**Generated:** 2026-06-26  
**Build:** `rateb-erp-ga-security-20260626`

## Static + local runtime validation

### `php public/erp-security-cert.php` (local, no DB)

```json
{
  "ok": true,
  "certification": { "critical": 0, "high": 0, "medium": 1, "low": 0 },
  "open_findings": ["Could not verify migration 133 — DB connection refused"]
}
```

### Health endpoint (local)

| Test | Result |
|------|--------|
| `GET erp-health.php` | `{"status":"ok"}` |
| `?probe=branch-ops` | `{"status":"forbidden"}` (HTTP 404) |
| `?probe=schema` without token | `{"status":"forbidden"}` (HTTP 403) |

### Fixes mapped to GA IDs

| ID | Fix |
|----|-----|
| GA-SEC-C01 | Removed all session impersonation; blocked branch-ops/admin-live/dispatch; admin probes require `X-Rateb-Health-Token` |
| GA-SEC-C02 | Production schema probe returns only `ok` + `migrations_ready` (no DB name/metrics) |
| GA-SEC-H01 | `canViewBarcodeRecord` + `ErpAuthMiddleware` on `/scan/doc/{code}` |
| GA-SEC-H02 | `HtmlSanitizer::sanitizeAnalyticsEmbed` on save + render |
| GA-SEC-H03 | SVG upload disabled; legacy SVG served with CSP sandbox + attachment |
| GA-SEC-H04 | `ApiRateLimiter` on `/api/v1/*` (file-backed); 429 on exceed |
| GA-SEC-H05 | `SecurityHeaders` global (CSP, HSTS, X-Frame-Options, etc.) |

## Production (rateb.sa) — pre-deploy

**Still vulnerable until deploy:** `?probe=branch-ops` returned `ok:true` with super-admin dispatch on 2026-06-26 audit.

Post-deploy verification required:

```bash
curl -s https://rateb.sa/rateb-erp/public/erp-health.php
# expect {"status":"ok"}

curl -s -o /dev/null -w "%{http_code}" "https://rateb.sa/rateb-erp/public/erp-health.php?probe=branch-ops"
# expect 404

curl -s https://rateb.sa/rateb-erp/public/erp-security-cert.php
# expect critical=0 high=0
```

## Tenant / branch isolation

- Document scan: company_id + branch_id + entity permission enforced in `DocumentBarcodeService`
- API: unchanged `ApiBranchGuardService` + new rate limits

## Conclusion

**Security code fixes: COMPLETE locally**  
**Production runtime: PENDING DEPLOY**
