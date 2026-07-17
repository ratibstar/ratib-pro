# PX-Deploy — Permanent Deployment Integrity

**Status:** ACCEPTED · BINDING FOR ALL PRODUCTION DEPLOYS  
**Date:** 2026-07-17  
**Related:** [Phase 2.2 audit](../rateb-erp/offline-v2/docs/PHASE-2.2-DEPLOYMENT-ARTIFACT-AUDIT.md) — additive rsync left orphans such as `assets/offline/shared/*`

---

## Goal

**Git HEAD is the single source of truth for managed production trees.**

```text
Repository (managed roots)  ==  Production (managed roots)
No orphan files. No silent leftovers after rollback.
```

---

## 1. Deployment flow diagram

```text
                    push main / workflow_dispatch
                              │
                              ▼
                 .github/workflows/deploy.yml
                              │
              ┌───────────────┴───────────────┐
              │ DEPLOY_BACKEND=cpanel?        │
              ▼                               ▼
   Fileman upload path              rsync SSH path (DEFAULT)
   github-cpanel-fileman-           github-rsync-deploy.py
   deploy-core.py
              │                               │
              ├─ upload changed/baseline      ├─ rsync --files-from (additive OK)
              ├─ Fileman unlink soft purge    │
              └───────────────┬───────────────┘
                              │
                              ▼
                 PX-Deploy integrity gate
                 scripts/deploy_integrity.py
                              │
          ┌───────────────────┼───────────────────┐
          ▼                   ▼                   ▼
   Build expected       Delete orphans       SHA256 tree hash
   manifest from        + commit-deleted     local HEAD blobs
   Git HEAD managed     + explicit purge     vs remote files
   roots                lists                │
          │                   │               │
          └───────────────────┴───────────────┘
                              │
                    Heal upload once if
                    missing/mismatched
                              │
                              ▼
                    Re-verify integrity
                              │
              ┌───────────────┴───────────────┐
              ▼                               ▼
         PASS → orphan                   FAIL → deploy exits 1
         report artifact                 orphans or hash ≠ Git
```

Manual / emergency / cPanel git-hook:

```text
cpanel-deploy-sync.sh  ──►  px_deploy_integrity_cli.py
manual SSH             ──►  python3 scripts/px_deploy_integrity_cli.py
```

---

## 2. Path audit

| Path | Script | Pre-PX behavior | PX-Deploy behavior |
|------|--------|-----------------|--------------------|
| GitHub rsync (default) | `github-rsync-deploy.py` | Additive `--files-from`, no orphan delete | Upload → integrity purge + hash gate |
| GitHub Fileman | `github-cpanel-fileman-deploy-core.py` | Soft unlink lists only; errors swallowed | Upload → same SSH integrity gate (required) |
| cPanel git hook / manual sync | `cpanel-deploy-sync.sh` | Optional full `--delete` off by default | Calls `px_deploy_integrity_cli.py` on public_html |
| Emergency / SSH | `px_deploy_integrity_cli.py` | N/A | Local or remote integrity |
| Rollback commits | any of the above | Restored files uploaded; deleted paths left on disk | Commit-deleted + full orphan scan removes them |

---

## 3. Deletion strategy

1. **Expected set** = all files in Git HEAD under `INTEGRITY_MANAGED_ROOTS` (see `deploy_integrity.py`), minus exclude prefixes (`storage/`, `uploads/`, `Designed/`, …).
2. **Remote inventory** = walk the same roots on DocumentRoot.
3. **Orphans** = remote − expected.
4. **Force purge** = union of:
   - `SECURITY_REMOTE_DELETE_FILES`
   - `EXTRACTION_ROLLBACK_REMOTE_DELETE_FILES`
   - paths deleted in the current commit (`git diff-tree --diff-filter=D`)
5. Delete orphan + force-purge **files**, verify `! exists`.
6. Remove empty orphan **directories** bottom-up.
7. Classify orphans: js / css / php / wasm / manifest / service_worker / other.
8. If any orphan remains after delete → **fail**.

This is **not** blind `rsync --delete` of entire `public_html` (which would destroy runtime data). It is scoped managed-tree reconciliation.

---

## 4. Rollback strategy

1. Revert / rollback commit restores Git paths via normal upload.
2. Paths removed from Git by that commit appear in `--diff-filter=D` and are force-purged.
3. Paths removed in an *earlier* commit but still on disk (classic Phase 2.1 shared leftovers) are caught by the **full orphan scan** against HEAD — not only the current commit diff.
4. Integrity fails the deploy until production matches HEAD.

---

## 5. Integrity verification

| Check | Method |
|-------|--------|
| Expected content | `git show HEAD:<path>` → SHA256 (repository bytes, not CRLF working tree) |
| Remote content | SHA256 of on-disk file under DocumentRoot |
| Tree equality | Deterministic hash of sorted `path\\0sha256\\n` lines |
| Orphans | Re-walk after delete; must be empty |
| Report | `deploy-orphan-report.json` (+ Actions artifact) |

Deploy **fails** if:

- orphans remain
- expected files missing after heal
- any hash mismatch after heal
- local tree hash ≠ remote tree hash

---

## 6. Orphan File Report

Every deploy writes `deploy-orphan-report.json` with:

- `orphans_found[]` (path, kind, deleted, verified_absent)
- `orphan_directories_removed[]`
- `commit_deleted[]`
- `explicit_purge[]`
- `hash_mismatches[]`
- `missing_expected[]`
- `local_tree_hash` / `remote_tree_hash`
- `ok` boolean

GitHub Actions uploads it as artifact `deploy-orphan-report`.

---

## 7. Managed roots (Git == Production)

```text
rateb-erp/public
public
js
css
pages
includes
api
control-panel
modules/infrastructure-marketplace
rateb-platform-catalog/public
ratib-contact-center/public
config/env
app/Accounting
```

Excluded from orphan deletion: `storage/`, `uploads/`, `Designed/`, `node_modules/`, tooling caches.

---

## 8. Constraints

- No ERP application / UI / feature / extraction changes in this phase.
- Deployment integrity only.
- First integrity pass may heal-upload missing managed files once, then must pass.
