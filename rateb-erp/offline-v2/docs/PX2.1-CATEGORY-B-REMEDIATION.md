# Phase PX2.1 — Category B Architecture Boundary Remediation

**Decision:** PASS — Category B violations: **0**  
**Scope:** Identity and Inventory BusinessModules only, plus evidence documents  
**Architecture Freeze:** AF 2.1 / AF 2.1.1 remains active  
**PX3:** Not started; Architecture Board approval is still required

## Permanent boundary

- Online ERP remains the only Authentication Authority.
- Identity stores only sealed identity, Identity Claims, RBAC Snapshot, Device
  Trust State, and Unlock Metadata.
- Identity stores no password, password hash, session cookie, bearer token,
  JWT, TOTP secret, WebAuthn server credential, API token, or authentication
  secret.
- Non-Identity BusinessModules consume Identity only through published
  `module.identity.*` APIs.
- No BusinessModule may read or write Identity SQLite rows, `vault.bin`, or
  Identity OPFS storage.

## Violation 1 — forbidden Identity classes

### Classification

| Legacy class | Classification | Required persistence? | Remediation |
|---|---|---:|---|
| `identity.config` | Local unlock/session policy configuration | Policy only | Move unlock policy fields into approved `identity.unlock_meta` |
| `identity.local_session` | Derived local session state | No | Keep only in module memory; a document reload is locked |
| `identity.security_meta` | Security diagnostics/status | No | Remove persistence; derive diagnostics in memory |

None of these classes is an authentication credential or makes Identity an
Authentication Authority. They nevertheless violated the strict “may store
ONLY” allowlist.

### New persistent allowlist

The active `ENTITY` map now contains exactly:

```text
identity.sealed
identity.claims
identity.rbac
identity.device
identity.unlock_meta
```

### Legacy migration

On first Identity-store initialization:

1. Query only the three known legacy class names.
2. If no legacy rows exist, return without writes or checkpointing.
3. If `identity.config` exists, copy:
   - `unlockTtlSec` → `unlock_ttl_sec`
   - `idleTtlSec` → `idle_ttl_sec`
   - `maxOfflineSec` → `max_offline_sec`
   into `identity.unlock_meta`.
4. Delete all three legacy classes.
5. Persist one SQLite checkpoint.

`identity.local_session` and `identity.security_meta` are deliberately
discarded. A local unlocked state cannot survive a document/process restart.

### Runtime behavior

- `IdentityModule._session` is initialized locked in memory.
- Enrollment and lock reset `_session` in memory.
- Unlock derives a local verifier, validates approved Identity records, and
  creates a memory-only session with an expiry.
- `getLocalSession()` reads only memory and expires it in memory.
- The Online enrollment policy remains available through approved Unlock
  Metadata.
- `securityScan()` scans approved Identity classes and stores no diagnostic
  record.

## Violation 2 — Inventory direct Identity SQL

Removed:

```text
InventoryStore.assertNoIdentityTouch()
SELECT ... WHERE entity_type LIKE 'identity.%'
```

InventoryStore now enforces a positive namespace allowlist:

```text
entity_type must begin with "inv."
```

The guard runs before every Inventory `put`, `get`, `list`, and `remove`.
Unsupported namespaces reject with `inv_storage_namespace_forbidden` before
`db.exec()` can run.

The negative boundary self-test now probes `foreign.claims`; it does not name,
read, or write an Identity class.

Normal Inventory identity checks remain unchanged and use:

```text
module.identity.session
module.identity.claims
module.identity.rbac
```

## Files changed

- `public/v2/js/business/identity-module.js`
- `public/v2/js/business/inventory-module.js`

No frozen platform layer or Offline V1 file was changed.

## Validation

### Syntax and lint

- Identity JavaScript syntax: PASS
- Inventory JavaScript syntax: PASS
- IDE lints: zero
- Git whitespace validation: PASS

### Legacy migration test

A fresh local installed profile was seeded with all three forbidden classes,
checkpointed, and reloaded through Identity activation.

After migration:

```json
{
  "entity_types": ["identity.unlock_meta"],
  "unlock_ttl_sec": 1234,
  "idle_ttl_sec": 234,
  "max_offline_sec": 3456
}
```

The DB was closed and reloaded again. The same approved-only result remained,
proving cleanup was persisted.

### Identity lifecycle test

Identity self-test: PASS

- Enrollment package applied.
- Local unlock configured and unlocked.
- Session is derived and contains no server credentials.
- Claims/RBAC/device APIs work.
- Lock and bad-PIN rejection work.
- Credential store/sync refusals work.
- Security scan is clean.
- Hot unload/reload works.

After synthetic enrollment, persistent Identity classes were exactly:

```text
identity.claims
identity.device
identity.rbac
identity.sealed
identity.unlock_meta
```

### Inventory lifecycle test

Inventory self-test: PASS

- Namespace boundary refusal passed.
- Identity dependency remained published-API-only.
- Inventory CRUD/posting behavior passed.
- No page errors.

## Outcome

- Forbidden Identity classes: **0 active**
- Direct Inventory Identity SQL: **0**
- Direct Identity OPFS/vault access by BusinessModules: **0**
- Credential storage: **0**
- Frozen-layer modifications: **0**

**PX2.1 remediation status: PASS.**
