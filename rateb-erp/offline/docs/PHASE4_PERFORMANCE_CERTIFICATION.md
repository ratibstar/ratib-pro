# Phase 4 — Performance Certification (Measured)

**Date:** 2026-07-14  
**Host:** local workstation (Windows)  
**Method:** PHP unit/stress runners only — no estimates.

## Hybrid Sync Engine (SQLite ↔ mirror sink)

| Ops | Push accepted | Inbox after push | Replay | Result |
|-----|---------------|------------------|--------|--------|
| 100 | 200 | 201 | dup-safe | PASS |
| 500 | 1000 | 1001 | dup-safe | PASS |
| 1000 | 2000 | 2001 | dup-safe | PASS |
| 5000 | 10000 | 10001 | dup-safe | PASS |
| Partial batch | accepted=1, ms=**6** | — | — | PASS |

Smoke push: **ms=5**; decrypt/sign/resume/pull: PASS (14/0).

## Offline client/server queue (in-process)

| Suite | Result | Wall clock / embedded ms |
|-------|--------|---------------------------|
| Queue durability (incl. 5000 delete-by-key) | **15/15 PASS** | **75.7 ms** total suite |
| Inventory stress ack 5000 | PASS | in suite |
| Inventory stress conflict 2000 | PASS | in suite |
| Inventory stress sanitizer 1000 | PASS | in suite |
| Procurement sanitizer 1000 | PASS | **0.72 ms** |
| Procurement conflict 2000 | PASS | **0.95 ms** |
| Client queue max certified | **500** | `sync-policy.php` |
| Client queue **10000** | **Not certified** | Cap is 500 by policy |

## Not measured (environment)

- MySQL cloud E2E latency (MySQL `127.0.0.1` connection refused) — verifier SKIPPED
- k6 / Apache Bench HTTP load
- Browser IndexedDB heap profiling

**Verdict:** Hybrid sync stress **100→5000 ops measured PASS**. Client offline queue durability **5000 keys measured PASS**. HTTP/MySQL load remains staging-ops.
