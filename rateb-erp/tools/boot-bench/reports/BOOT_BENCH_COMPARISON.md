# RATIB ERP — Real Browser Boot Benchmark

Generated: 2026-07-14T12:18:01.889Z

Method: Chrome + Playwright Performance API + Lighthouse (throttlingMethod=provided, desktop).

Before commit: 20ebcf58 — After: current optimized layout.

| Metric | Before | After | Delta |
|--------|--------|-------|-------|
| Navigation Start | 0.0 ms | 0.0 ms | 0.0 (n/a) |
| TTFB / responseStart (Playwright) | 3.0 ms | 3.2 ms | 0.2 (6.7%) |
| First Paint (Playwright paint API) | 248.0 ms | 316.0 ms | 68.0 (27.4%) |
| First Contentful Paint (Lighthouse) | 139.6 ms | 96.4 ms | -43.2 (-31.0%) |
| Largest Contentful Paint (Lighthouse) | 220.2 ms | 227.1 ms | 6.9 (3.1%) |
| DOMContentLoaded (Playwright nav timing) | 140.8 ms | 77.9 ms | -62.9 (-44.7%) |
| Load Event End (Playwright) | 157.0 ms | 83.8 ms | -73.2 (-46.6%) |
| Time To Interactive (Lighthouse) | 185.5 ms | 109.8 ms | -75.7 (-40.8%) |
| Total Blocking Time (Lighthouse) | 0.0 ms | 0.0 ms | 0.0 (n/a) |
| Speed Index (Lighthouse) | 204.0 ms | 219.0 ms | 15.0 (7.4%) |
| Cumulative Layout Shift (Lighthouse) | 0.1 score | 0.1 score | 0.0 (0.0%) |

## Lighthouse observed (same run)

| Metric | Before | After | Delta |
|--------|--------|-------|-------|
| observedFirstPaint (LH) | 140.0 | 96.0 | -44.0 (-31.4%) |
| observedFirstContentfulPaint (LH) | 140.0 | 96.0 | -44.0 (-31.4%) |
| observedLargestContentfulPaint (LH) | 220.0 | 227.0 | 7.0 (3.2%) |
| observedDomContentLoaded (LH) | 186.0 | 110.0 | -76.0 (-40.9%) |
| observedLoad (LH) | 233.0 | 113.0 | -120.0 (-51.5%) |
| observedSpeedIndex (LH) | 204.0 | 219.0 | 15.0 (7.4%) |
| observedTotalBlockingTime (LH) | NOT MEASURED | NOT MEASURED |  |
| observedCumulativeLayoutShift (LH) | 0.1 | 0.1 | 0.0 (0.0%) |

Performance score: before=1 after=1

Raw JSON: efore-1784031431293.json, fter-1784031462456.json, BOOT_BENCH_COMPARISON.json