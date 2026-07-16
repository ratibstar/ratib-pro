# P1-09 — Secondary Host HCI Compatibility (Documentation Only)

Phase 1 does **not** implement Capacitor, Tauri, or Electron.

| HCI capability | Chromium PWA (v2.0) | Future Capacitor | Future Tauri/Electron |
|----------------|---------------------|------------------|------------------------|
| Durable root | OPFS `rateb-offline-v2` | App filesystem mirroring **same tree names** | Host FS mirroring **same tree names** |
| Layout | P1-00A exact | P1-00A exact | P1-00A exact |
| Packages | Local only | Local only — **never** `server.url=https://…` | Local only |
| Database path | `database/ratib.sqlite` | Same relative path | Same |
| Vault path | `vault/vault.bin` | Same | Same |

## Forbidden on secondary hosts

- Remote origin as application runtime (`server.url` to live ERP)
- Different top-level directory names
- IndexedDB as ERP database
- Reuse of Offline V1 SW / HTML snapshot runtime
