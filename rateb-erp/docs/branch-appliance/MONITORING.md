# Monitoring Guide

```bash
php bin/hybrid-branch-health.php --once
php bin/hybrid-branch-health.php --max-cycles=10
```

Monitors: service lock, internet, sync latency, pending outbox, SQLite integrity, disk %, memory, queue growth, retries, conflicts.

Health score 0–100 → `green` / `amber` / `red`.

Artifacts: `storage/branch/health/last-health.json`, `health.jsonl`.
