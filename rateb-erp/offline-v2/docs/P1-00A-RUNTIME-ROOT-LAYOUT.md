# P1-00A — Runtime Root Layout (IMMUTABLE)

OPFS application root name: `rateb-offline-v2`

```
/
├── runtime/
│   ├── runtime.pkg
│   ├── runtime.manifest
│   └── active.json
├── packages/
│   ├── runtime/
│   ├── modules/
│   ├── language/
│   └── assets/
├── slots/
│   ├── slot-a/
│   ├── slot-b/
│   └── slot-c/
├── database/
│   └── ratib.sqlite
├── vault/
│   └── vault.bin
├── logs/
├── temp/
├── updates/
└── backups/
```

## Rules

1. Runtime NEVER writes into `packages/`.
2. `packages/**` immutable after verify (L7).
3. Runtime replaceable only via L7 atomic activate.
4. Database independent.
5. Vault survives updates/rollbacks.
6. Backups independent from packages.
7. `temp/` disposable.
8. Active slot never modified in place.
9. IndexedDB is NOT the ERP database.
10. All access through HCI only.
11. No additional top-level directories.
12. No renames of top-level directories.
13. No business data outside `database/`.
14. No secrets outside `vault/`.
