# Health Endpoint Validation — Production

**Host:** `https://rateb.sa`  
**Date:** 2026-06-26

## Public health (required)

| Request | HTTP | Response body |
|---------|------|---------------|
| `GET /rateb-erp/public/erp-health.php` | **200** | `{"status":"ok"}` |
| `GET /erp-health.php` | **200** | `{"status":"ok"}` |

✅ Matches specification.

## Dangerous probes (must be blocked)

| Request | HTTP | Response body | Session / impersonation |
|---------|------|---------------|-------------------------|
| `?probe=branch-ops` | **404** | `{"status":"forbidden"}` | None — no `ok:true`, no `session user_id` |
| `?probe=admin-live` | **404** | (empty body in client) | None |
| `?dispatch=login` | **404** | `{"status":"forbidden"}` | None |
| `?probe=schema` | **403** | `{"status":"forbidden"}` | No DB name, no migrations list |
| `?probe=ping` | **403** | `{"status":"forbidden"}` | No PHP version leak without token |

### Pre-GA comparison (same host, prior audit)

| Probe | Before deploy | After deploy |
|-------|---------------|--------------|
| `branch-ops` | `ok:true`, super-admin dispatch | **404 forbidden** |
| `schema` | Full DB name + metrics | **403 forbidden** |

## Information disclosure check

| Data type | Present without token? |
|-----------|------------------------|
| Database name | ❌ No |
| Company count | ❌ No |
| Migration filenames | ❌ No |
| Dashboard metrics | ❌ No |
| `render_len` | ❌ No |
| Internal paths in JSON | ❌ No |

## Conclusion

✅ **Health endpoint validation PASSED** on Production.
