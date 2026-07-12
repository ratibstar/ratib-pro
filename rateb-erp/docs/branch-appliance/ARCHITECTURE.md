# Phase D — Branch Appliance Architecture

## Seam (unchanged)

```
Model → Database::connection() → HybridRuntime → MySQL (cloud) | SQLite (branch)
```

## Appliance stack

```
BranchApplianceInstaller
    ├── HybridRuntime + serve.env
    ├── SqliteSchemaBootstrap + BranchSeedService
    ├── BranchRegistration (UUID / cert / keys)
    └── Hybrid Sync config (Phase C / C.1)

Branch operations
    ├── BranchDiagnostics
    ├── BranchHealthMonitor
    ├── BranchBackupService
    ├── BranchUpdater
    ├── BranchAutoRecovery
    └── BranchCertification

Sync (unchanged)
    SQLite → Outbox → HybridSyncEngine → HybridSyncDaemon → Cloud MySQL
```

## Explicit non-goals

- No second ERP / runtime / sync engine
- No Controllers / Services / Models / Routes / Views / UI / POS changes
- Cloud MySQL path identical to pre-hybrid
