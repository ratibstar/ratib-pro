# Performance Report — GA

**Generated:** 2026-06-26

## k6 load test

| Item | Status |
|------|--------|
| k6 installed locally | **No** (`where k6` → not found) |
| Full 100→1000 VU staged run | **Not executed** |

`bin/enterprise-perf/k6-load.js` updated to hit **public** `GET /erp-health.php` only (no privileged probes).

## Apache Bench

**Not executed** — `ab` not run in this session.

## Production smoke (pre-deploy build still live on rateb.sa)

**Endpoint:** `https://rateb.sa/rateb-erp/public/erp-health.php`  
**Sample:** 10 sequential requests (PowerShell `Measure-Command`)

| Metric | Value |
|--------|------:|
| Samples | 10 |
| Average | 182.7 ms |
| Min | 154 ms |
| Max | 405 ms |
| P95 (index) | 163 ms |
| Median / P90 / P99 | Not computed (n=10) |
| CPU / RAM / MySQL latency | Not measured (no server access) |

**Note:** Production still serves **pre-GA** health responses until this commit is deployed.

## Local public health (post-fix)

```bash
php -r "define('RATEB_ROOT', getcwd()); require 'public/erp-health.php';"
# → {"status":"ok"}
```

## Conclusion

**Performance certification: INCOMPLETE** — only lightweight production smoke; no k6/AB/MySQL profiling.
