# Performance Verification — Final

**Date:** 2026-06-26  
**Endpoint:** `GET https://rateb.sa/rateb-erp/public/erp-health.php`

## k6

| Item | Result |
|------|--------|
| `k6` installed on auditor workstation | **No** |
| Staged load script (`bin/enterprise-perf/k6-load.js`) | **Not executed** |

## Apache Bench (`ab`)

| Item | Result |
|------|--------|
| `ab` installed | **No** |
| Run | **Not executed** |

## Production smoke — PowerShell sequential sample (n=30)

| Metric | Value |
|--------|------:|
| Average | 120.07 ms |
| Median | 111 ms |
| P90 | 118 ms |
| P95 | 120 ms |
| P99 | 122 ms |
| Min | 108 ms |
| Max | 344 ms |

## Not measured

| Metric | Status |
|--------|--------|
| CPU | ❌ No server access |
| RAM | ❌ No server access |
| MySQL query latency | ❌ No DB access |
| Application pages (dashboard, reports) | ❌ Not profiled |

## API rate-limit burst (related load signal)

130× `GET /api/v1` → 119× HTTP 200, 11× HTTP 429 (rate limiter active under burst).

## Conclusion

🟡 **Partial performance evidence only** (health endpoint latency). Full k6/AB/MySQL profiling **NOT COMPLETE**.
