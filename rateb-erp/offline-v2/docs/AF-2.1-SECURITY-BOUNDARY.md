# Architecture Freeze v2.1 — Security Boundary

**Binding companion to:** `AF-2.1-ARCHITECTURE-FREEZE.md`

## 1. Trust boundaries

```
┌─────────────────────────────────────────────────────────────┐
│  TRUST BOUNDARY A — Online ERP (Authentication Authority)   │
│  Credentials · sessions · tokens · TOTP · password policy     │
└───────────────────────────┬─────────────────────────────────┘
                            │ enrollment package only
                            │ (no passwords/tokens inside)
                            ▼
┌─────────────────────────────────────────────────────────────┐
│  TRUST BOUNDARY B — Identity Module (Platform Foundation)   │
│  Owns: sealed · claims · RBAC snapshot · device · unlock     │
│        meta · derived session · identity diagnostics         │
│  APIs: module.identity.*                                     │
└───────────────────────────┬─────────────────────────────────┘
                            │ published service results only
                            ▼
┌─────────────────────────────────────────────────────────────┐
│  TRUST BOUNDARY C — ERP BusinessModules                     │
│  May read claims/session/rbac/device via Identity APIs        │
│  MUST NOT touch identity storage, vault, or identity.* SQL   │
└─────────────────────────────────────────────────────────────┘
```

## 2. Hard security invariants

1. **Online ERP is the only Authentication Authority.**
2. **Identity never stores credentials** (passwords, hashes, cookies, sessions, tokens, TOTP, recovery/reset secrets).
3. **Identity never authenticates against the server.**
4. **Identity never generates, syncs, or exports credentials.**
5. **Identity never exposes secrets** through SQLite dumps, OPFS, Sync outbox, logs, diagnostics, events, caches, or Package Manager artifacts.
6. **No BusinessModule may bypass Identity** to read sealed identity, unlock metadata, RBAC snapshot storage, or identity-owned tables.
7. **Direct OPFS access to identity storage is prohibited** for non-Identity modules.
8. **Direct SQLite queries to `identity.*` entity types are prohibited** for non-Identity modules.
9. **Sync Engine must never carry auth secrets**; Identity refuses credential sync.
10. **Offline V1 remains zero-touch** and is not an Identity dependency.

## 3. Allowed Identity operations (inside boundary B)

| Operation | Allowed |
|-----------|---------|
| `applyEnrollmentPackage` (online-produced, secret-free) | Yes |
| `setLocalUnlockPin` / `unlock` / `lock` | Yes (local only) |
| Read/write `entity_row` types `identity.*` | Yes (Identity owner only) |
| `fetchEnrollmentFromOnline` with existing browser session cookies | Yes (never password login) |
| Publish `module.identity.*` services | Yes |

## 4. Denied operations (any non-Identity module)

| Operation | Denied |
|-----------|--------|
| `SELECT/INSERT/... WHERE entity_type LIKE 'identity.%'` | Yes — denied |
| Read/write `vault/vault.bin` for identity | Yes — denied |
| Parse sealed identity blobs | Yes — denied |
| Read unlock_verifier / salt | Yes — denied |
| Call Online ERP login/token mint from module | Yes — denied |
| Put passwords/tokens on Sync outbox | Yes — denied |

## 5. Violation class

Breaches of this boundary are **Category B Architecture Violations** and require Architecture Board remediation before further ERP module work.

## 6. Verification expectations (future modules)

Each future ERP module evidence pack must include:

- Dependency declaration on `identity`
- Proof of Identity API usage (no direct `identity.*` SQL)
- Security statement: no credential storage/sync
- Zero-touch Offline V1 confirmation
