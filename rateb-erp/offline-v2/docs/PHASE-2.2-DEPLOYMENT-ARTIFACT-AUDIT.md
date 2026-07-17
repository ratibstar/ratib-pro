# Phase 2.2 — Deployment Artifact Audit

**Status:** AUDIT ONLY · NO FIX · NO EXTRACTION  
**Date:** 2026-07-17  
**Subject:** Why `https://rateb.sa/rateb-erp/public/assets/offline/shared/*` still returns HTTP 200 after Phase 2.1 rollback  
**Related:** [PHASE-2.1-EXTRACTION-ABORTED](./PHASE-2.1-EXTRACTION-ABORTED.md)

---

## Verdict (evidence)

HTTP 200 exists because **Apache is serving real files that remain on the production DocumentRoot disk**. Those files were uploaded during Phase 2.1 extraction (`1b88339d`, Actions run `29606485850`) and were **never deleted** by the rollback deploy.

They are **not** served by:

- Cloudflare / CDN edge cache
- Service Worker cache
- Browser cache alone (cache-busted GETs still 200)
- OPcache (static JS/WASM, not PHP)
- Rewrite/Alias remapping to another path
- A second Git-owned tree in the repository (repo has **zero** `assets/offline/shared` files at HEAD)

---

## Checklist evidence (1–20)

### 1. Physical files on production

| Path under `/rateb-erp/public/` | HTTP | Bytes | Last-Modified (GMT) | SHA256 prefix |
|---------------------------------|------|-------|---------------------|---------------|
| `assets/offline/shared/runtime/runtime.js` | 200 | 17502 | 2026-07-17 **19:09:26** | `c54d4c49f5615b9f` |
| `assets/offline/shared/db/sqlite-runtime.js` | 200 | 12295 | 19:09:26 | `1f754de06eb8b899` |
| `assets/offline/shared/db/migrations.js` | 200 | 5127 | 19:09:26 | `4f6b2994e7b0a11d` |
| `assets/offline/shared/identity/identity-module.js` | 200 | 43348 | 19:09:26 | `465a40920fea7185` |
| `assets/offline/shared/sync/sync-engine.js` | 200 | 40333 | 19:09:26 | `198684b5b65a4345` |
| `assets/offline/shared/vendor/sqlite/index.mjs` | 200 | 578559 | 19:09:26 | `f80870f0fa03a39a` |
| `assets/offline/shared/vendor/sqlite/sqlite3.wasm` | 200 | 864752 | 19:09:26 | `02d7e48164395fa6` |
| `assets/offline/shared/vendor/sqlite/sqlite3-worker1.mjs` | 200 | 571858 | 19:09:26 | `060ec0274c112339` |
| `assets/offline/shared/vendor/sqlite/sqlite3-opfs-async-proxy.js` | 200 | 32289 | 19:09:26 | `502762db7bd24130` |
| `assets/offline/shared/vendor/sqlite/README.md` | 200 | **290** | 19:09:26 | body = extraction text |

Smoking-gun body (production README, live fetch):

```text
Admin-owned canonical package used by the shared SQLite runtime
(`public/assets/offline/shared/db/sqlite-runtime.js`).
```

That text exists only in commit `1b88339d` (extraction). Current Git HEAD README says:

```text
Used by Offline V2 L3 only (`public/v2/js/db/sqlite-runtime.js`).
```

(231 characters in Git; 290 bytes on production shared README.)

### 2. Symlinks

Cannot SSH-list inodes from this audit. HTTP evidence:

- `rateb.sa` and `www.rateb.sa` return the **same ETag** for shared runtime (`"445e-656d34b94b519"`).
- Workflow / script defaults target `/home/admin/domains/rateb.sa/public_html` (rsync) and `/home/admin/public_html` (Fileman default). Historical ops note: account `public_html` may symlink to the domain DocumentRoot. Either way, one Apache origin serves the bytes.

No evidence that shared URLs are rewritten to a different host tree.

### 3. Apache / LiteSpeed DocumentRoot

Response headers: `Server: Apache/2`.

ERP public `.htaccess` (first-match for existing files):

```apache
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]
```

So any file that exists under DocumentRoot is served as a static asset. That matches HTTP 200 with `Content-Type: application/javascript` / raw WASM and Apache `ETag` / `Last-Modified`.

### 4. Alias directives

No `Alias` for `assets/offline/shared` in:

- repo root `.htaccess`
- `rateb-erp/public/.htaccess`
- `rateb-erp/public/v2/.htaccess`

No `rateb-erp/public/assets/.htaccess` or `.../offline/.htaccess` exists.

### 5. Rewrite rules

Rewrites defer to real files (`-f` → pass-through). Shared URLs are not mapped to `v2/` or PHP. No rewrite explains the 200; the file must exist.

### 6. Deploy workflow

`.github/workflows/deploy.yml`:

- Job name: **Deploy PHP App (DirectAdmin rsync / cPanel)**
- Backend selection:

```bash
if [ "${DEPLOY_BACKEND}" = "cpanel" ] && [ -n "${CPANEL_API_TOKEN:-}" ]; then
  bash scripts/github-cpanel-fileman-deploy.sh
else
  bash scripts/github-rsync-deploy.sh   # DEFAULT
fi
```

Default when `DEPLOY_BACKEND` is unset: **rsync**.

### 7. GitHub Actions artifacts

Relevant runs (public API):

| Run | Commit | Created (UTC) | Conclusion |
|-----|--------|---------------|------------|
| [29606485850](https://github.com/ratibstar/ratib-pro/actions/runs/29606485850) | `1b88339d` extraction | **2026-07-17T19:09:18Z** | success |
| [29607095452](https://github.com/ratibstar/ratib-pro/actions/runs/29607095452) | `11726c18` rollback | **2026-07-17T19:19:19Z** | success |

Shared `Last-Modified: 19:09:26 GMT` aligns with extraction deploy (±8s).  
Restored V2 paths `Last-Modified: 19:19:26 GMT` align with rollback deploy.  
Shared timestamps **did not advance** on rollback → remote shared tree untouched.

Actions log zip download returned **403** without auth token (cannot paste step stdout here). Code-path evidence below does not require the zip.

### 8. Rsync / delete behavior

`scripts/github-rsync-deploy.py`:

- Uploads with `rsync -avz --files-from <list>` **without `--delete`**.
- Purges only `SECURITY_REMOTE_DELETE_FILES` via SSH `rm -f`.
- **Does not call** `EXTRACTION_ROLLBACK_REMOTE_DELETE_FILES` / `purge_aborted_extraction_files`.

Therefore: any file uploaded in extraction and removed from Git later **remains on the server forever** under the rsync backend.

### 9. SCP overwrite behavior

No SCP path in the production workflow. Upload is rsync or cPanel Fileman. Overwrite applies only to files present in the upload list; it cannot invent deletions.

### 10. CDN cache

No `Age`, `Via`, `X-Cache`, `X-CDN`, or surrogate headers on shared responses.

### 11. Cloudflare cache

No `CF-RAY`, no `CF-Cache-Status`. Not Cloudflare-fronted for these assets (or CF is fully transparent without those headers — still no cache evidence; Apache origin headers dominate).

### 12. Browser cache

Probes used unique `?audit=<epoch>` plus `Cache-Control: no-cache` / `Pragma: no-cache`. Still HTTP 200 with stable Apache `ETag`. Origin file present.

### 13. Service Worker cache

Current repo `public/v2/sw.js`:

- Cache name: `rateb-offline-v2-bootstrap-v2`
- PRECACHE has **no** `assets/offline/shared` entries
- Fetch handler scoped to `/v2/` (and not shared Admin paths in the rolled-back SW)

Direct browser/CI GETs to `/rateb-erp/public/assets/...` are **outside** V2 SW control for a never-visited client. SW is not the source of these 200s.

### 14. OPcache

Not applicable. Responses are static JS/WASM/Markdown from Apache, not PHP opcodes.

### 15. Duplicate directories (repository / workspace)

| Location | `assets/offline/shared` present? |
|----------|----------------------------------|
| Git `HEAD` | **No** (`git cat-file` fatal: path does not exist) |
| Working tree `rateb-erp/public/assets/offline/` | Only Offline V1 files + `modules/`; **no `shared/`** |
| Local recursive `**/offline/**/shared` | **None** |

Production therefore holds a **server-only** copy.

### 16. Static asset manifests

No hits for `assets/offline/shared` in:

- `public/v2/sw.js`
- `public/v2/manifest.webmanifest`
- `public/pos-sw.js`

No fingerprint map references shared paths.

### 17. Build output folders

Shared tree was a **direct Git move of source assets**, not a generated build output. No separate build package republishes it after rollback.

### 18. Deployment exclusions

Fast deploy only uploads the allowlisted / commit-changed file list. Deleting a path from Git **excludes it from the upload list**; it does **not** schedule a remote delete (except explicit purge lists). Extraction rollback files are listed only for Fileman, not rsync.

### 19. Asset fingerprinting

URLs are unfingerprinted path names. Cache-busting query strings still hit the same inode/ETag family → not a hashed-asset ghost.

### 20. Every physical copy of `assets/offline/shared/*` (observed via HTTP)

All of the following still return **200** on production origin:

```text
/rateb-erp/public/assets/offline/shared/runtime/runtime.js
/rateb-erp/public/assets/offline/shared/db/sqlite-runtime.js
/rateb-erp/public/assets/offline/shared/db/migrations.js
/rateb-erp/public/assets/offline/shared/identity/identity-module.js
/rateb-erp/public/assets/offline/shared/sync/sync-engine.js
/rateb-erp/public/assets/offline/shared/vendor/sqlite/index.mjs
/rateb-erp/public/assets/offline/shared/vendor/sqlite/sqlite3.wasm
/rateb-erp/public/assets/offline/shared/vendor/sqlite/sqlite3-worker1.mjs
/rateb-erp/public/assets/offline/shared/vendor/sqlite/sqlite3-opfs-async-proxy.js
/rateb-erp/public/assets/offline/shared/vendor/sqlite/README.md
```

(Additional vendor files from extraction such as `index.d.mts` / `node.mjs` were in the Fileman rollback list but were not all re-probed; the README body alone proves the extraction tree remains.)

Content overlap with restored V2:

| Component | shared SHA prefix | v2 SHA prefix | Notes |
|-----------|-------------------|---------------|-------|
| Runtime | `c54d4c49f5615b9f` | `c54d4c49f5615b9f` | Identical bytes; different Last-Modified |
| Identity | `465a40920fea7185` | `465a40920fea7185` | Identical |
| Sync | `198684b5b65a4345` | `198684b5b65a4345` | Identical |
| Vendor index.mjs | `f80870f0fa03a39a` | `f80870f0fa03a39a` | Identical |
| SQLite runtime | `1f754de06eb8b899` | `5125ba550947e75f` | Differ (extraction had relative-import path tweak) |

---

## Answers required by Phase 2.2

### Physical file owner

**Production Apache DocumentRoot filesystem** under the ERP public tree, path:

```text
{DEPLOY_REMOTE_BASE}/rateb-erp/public/assets/offline/shared/**
```

Default rsync DocumentRoot in code: `/home/admin/domains/rateb.sa/public_html`.

Owner process: static file serving by **Apache/2** (`RewriteCond %{REQUEST_FILENAME} -f`).

### Source directory

**Source that created the files:** Git commit `1b88339d` extraction package

```text
rateb-erp/public/assets/offline/shared/
```

uploaded by Actions run `29606485850` at ~19:09Z.

**Not** present in current Git / working tree after `11726c18` / `a85f00aa`.

### Why HTTP 200 exists

The URL maps to a **still-existing static file** on disk. Apache returns 200.

### Why rollback did not remove it

1. Rollback (`11726c18`) restored V2 paths by **uploading** restored files (`Last-Modified` 19:19:26).
2. Fast deploy **never deletes** remote files merely because they left Git.
3. The explicit purge list `EXTRACTION_ROLLBACK_REMOTE_DELETE_FILES` lives in `github-cpanel-fileman-deploy-core.py` and is invoked only from the **Fileman** `main()`.
4. Production default backend is **rsync** (`github-rsync-deploy.py`), which:
   - uses `rsync --files-from` **without `--delete`**
   - purges **only** `SECURITY_REMOTE_DELETE_FILES`
   - **never** iterates `EXTRACTION_ROLLBACK_REMOTE_DELETE_FILES`
5. Therefore the shared tree uploaded at 19:09:26 remained untouched through rollback and the later evidence commit.

Secondary note: even the Fileman unlink helper swallows all exceptions (`except Exception: pass`), so a Fileman-backend purge would also be unverifiable / silent on failure — but the primary production path gap is **rsync never calls the extraction purge at all**.

### Exact deployment layer responsible

**Layer:** GitHub Actions production deploy → **`scripts/github-rsync-deploy.py`** (DirectAdmin/SSH rsync fast mode).

**Failure mode:** additive sync without remote deletion of extraction artifacts.

**Not responsible:** CDN, Cloudflare, browser cache, Service Worker, OPcache, Alias/Rewrite, fingerprint manifests, local repo duplicates.

### Exact remediation (audit prescription only — not executed)

1. On the live DocumentRoot that serves `rateb.sa`, delete the orphan tree, e.g. SSH:

   ```bash
   rm -rf /home/admin/domains/rateb.sa/public_html/rateb-erp/public/assets/offline/shared
   ```

   (Adjust if DocumentRoot differs; confirm with `realpath` / File Manager.)

2. **Or** extend the **rsync** deploy path to SSH-`rm` the same paths listed in `EXTRACTION_ROLLBACK_REMOTE_DELETE_FILES` (mirror security purge), then redeploy.

3. Verify with cache-busted GETs that every path in §20 returns **404** (not 200).

4. Do **not** re-run extraction until that 404 proof exists.

---

## Layer exclusion matrix

| Layer | Involved in 200? | Evidence |
|-------|------------------|----------|
| Physical DocumentRoot files | **YES** | Last-Modified 19:09:26; extraction README body; Apache ETag |
| Rsync fast deploy (no `--delete`) | **YES (cause of persistence)** | `github-rsync-deploy.py` |
| Fileman EXTRACTION_ROLLBACK list | Listed but **not on default backend** | Only in Fileman `main()` |
| Cloudflare / CDN | No | No CF/Age/Via headers |
| Browser cache | No | Query-bust still 200 |
| Service Worker | No | Rolled-back SW has no shared PRECACHE |
| OPcache | No | Static assets |
| Alias / Rewrite to another owner | No | `-f` pass-through only |
| Git HEAD / local tree | No copy | Path absent |
| Asset fingerprint / build output | No | Unfingerprinted static paths |

---

## Audit constraints honored

- No application code changes in this phase beyond this evidence document.
- No extraction, no moves, no deletions performed against production.
- No fixes implemented.
