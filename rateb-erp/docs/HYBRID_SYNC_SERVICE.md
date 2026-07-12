# Phase C.1 — Enterprise Always-On Hybrid Sync Service

## Verdict scope

Orchestration only. The Sync Engine from Phase C is reused exactly.
`Database::connection()` remains the only runtime seam.
Cloud stays raw MySQL. Branch stays SQLite.

## Architecture

```
SQLite (branch)
    │
    ▼
rateb_sync_outbox (capture — Phase C)
    │
    ▼
HybridSyncEngine (push / pull / resume / conflict / audit / crypto)
    │
    ▼
HybridSyncDaemon (Always-On loop — Phase C.1)
    │
    ▼
Cloud MySQL (or mirror sink in tests)
```

The daemon contains **no** business logic. It only calls:

| Call | Purpose |
|------|---------|
| `HybridSyncEngine::isOnline()` | Connectivity |
| `resumeInterrupted()` | Crash recovery |
| `status()` | Pending outbox? |
| `pushPending()` | Drain outbox |
| `pullEntity()` | Incremental pull |
| Engine internals | Conflict, audit, encrypt/sign (unchanged) |

## Runtime loop

1. Start → acquire single-instance flock (`storage/branch/hybrid-sync.daemon.lock`)
2. Initialize via existing engine + resume interrupted (`syncing` → `pending`)
3. If offline → structured log → sleep **5s**
4. If online and no pending work → optional pull → sleep **2s**
5. If pending → push → pull → conflict/audit/retry via engine → sleep **2s**
6. Repeat until SIGTERM/SIGINT or `--stop` (stop file)

## Single instance / crash safety

- `flock(LOCK_EX|LOCK_NB)` prevents duplicate daemons (no duplicate sync).
- Outbox idempotency + cloud inbox (Phase C) prevent duplicate replay/transactions.
- Interrupted batches resumed via `resumeInterrupted()` on every cycle and on startup.
- WAL + `busy_timeout` unchanged (SQLite path from Phase B.2).

## Entrypoints

| Platform | Artifact |
|----------|----------|
| CLI | `bin/hybrid-sync-service.php` |
| Linux | `deploy/systemd/rateb-hybrid-sync.service` (`Restart=always`, `RestartSec=5`, `After=network-online.target`) |
| Windows | WinSW: `bin/windows/rateb-hybrid-sync.xml` + `install-hybrid-sync-service.ps1` (not Task Scheduler) |

## Branch env (required)

```
RATEB_RUNTIME=branch
RATEB_HYBRID_SYNC_ENABLED=1
RATEB_HYBRID_SYNC_SINK=mysql
```

Cloud: do **not** set `RATEB_RUNTIME=branch`. Sync service must not run on cloud.

## Logging

Structured JSON lines to:

- `storage/branch/logs/hybrid-sync.jsonl`
- STDERR (journald / WinSW)

Events: `startup`, `shutdown`, `internet_change`, `offline`, `push`, `pull`, `retry`, `resume`, `conflict`, `error`, `success`.

## Explicit non-goals

- No second Sync Engine
- No Controllers/Services/Models/Routes/Views/UI/POS changes
- No HybridRuntime rewrite
- No Designed/ edits
