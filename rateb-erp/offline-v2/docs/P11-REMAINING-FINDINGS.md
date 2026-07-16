# Phase 11 — Remaining Findings

1. Operator Chromium gate for Inventory self-test.
2. Serial numbers / cycle-count UI / accounting GL postings deferred (not required for BM architecture proof).
3. Adjustment uses **delta** semantics (safer than online absolute-set).
4. Transfers use **separate source/dest item balances** (avoids online WH-transfer net-zero bug).
5. SW cache: `rateb-offline-v2-host-p11`.

No Category B violations.
