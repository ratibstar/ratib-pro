# Phase Z — Offline V2 Production Hardening

**Board decision:** APPROVED — Production Hardening ONLY  
**Scope:** Startup reliability + deployment. No new features. No ERP modules. No architecture redesign.  
**Status:** COMPLETE — Enterprise Production Startup = **PASS** (production gate 2026-07-16).

## Problem (production audit)

| # | Finding | Impact |
|---|---------|--------|
| 1 | `public/v2/vendor/sqlite/*` missing on production | SQLite Runtime cannot load |
| 2 | Root `.gitignore` `vendor/` excluded required WASM/ESM | Assets never committed / never deployed |
| 3 | `boot.js` waited **20s** for DB Ready | Artificial startup hang |
| 4 | SW `cache.addAll(PRECACHE)` included `./` (404) + missing sqlite URLs | Atomic install failure → empty cache |
| 5 | Shell Ready never reached | Cascading FAIL after DB timeout |

## Fixes shipped

1. **`.gitignore`** — un-ignore `rateb-erp/public/v2/vendor/**` while keeping generic `vendor/` ignored.
2. **Commit vendored `@sqlite.org/sqlite-wasm`** under `rateb-erp/public/v2/vendor/sqlite/` (`index.mjs`, `sqlite3.wasm`, `sqlite3-opfs-async-proxy.js`, workers).
3. **Deploy pipeline** — treat `.wasm` as binary upload (multipart) so WASM is not corrupted by text `save_file_content`.
4. **`v2/sw.js`** — resilient per-URL precache; remove directory URL `./`; fail install only if **critical** assets missing.
5. **`v2/js/boot.js`** — replace 20s blind timeout with ≤4s poll + vendor HEAD probe; emit **Shell Ready** as soon as shell self-test passes.
6. **`v2/.htaccess`** — `DirectoryIndex index.html` + MIME for `.mjs` / `.wasm`.

## Architecture freeze (honored)

No redesign of HCI, Runtime, Package Manager, SQLite Runtime design, Router, Shell, Sync, Module SDK, BM framework, Identity, Inventory, Procurement, Sales, Accounting, CRM, HR, Manufacturing, or Offline V1.

## Identity boundary (honored)

Online ERP remains the only Authentication Authority. Identity stores sealed identity / claims / RBAC snapshot / device trust / unlock metadata only — never passwords, hashes, cookies, JWTs, or auth secrets.

## Success criteria

| Gate | Target |
|------|--------|
| Production startup (Shell Ready) | &lt; 3 seconds |
| Shell Ready | PASS |
| SQLite Runtime | PASS |
| Service Worker | PASS |
| Offline cache | PASS |
| HTTP 404 on runtime assets | None |
| Architecture / Identity / V1 | Untouched |

See companion evidence files in this folder.
