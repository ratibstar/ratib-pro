# RATIB ERP — Deployment Coverage Audit

**Date:** 2026-06-27  
**Scope:** Full read-only deployment coverage analysis  
**Branch audited:** `release/v1.0.1` vs `origin/main`  
**Environments:** Production (`rateb.sa`), Staging (`dev.rateb.sa`)  
**Infrastructure:** GitHub Actions → SSH/rsync or cPanel Fileman → DirectAdmin on Hetzner Cloud  

**Mode:** READ ONLY — no deploy, commit, push, merge, or file modifications performed for this audit.

---

## Executive Summary

| Finding | Status |
|---------|--------|
| Active GitHub Actions deploy workflow | **1** — `.github/workflows/deploy.yml` only |
| Separate `deploy-production.yml` / `deploy-staging.yml` / `deploy-fast.yml` / `deploy-hotfix.yml` | **Do not exist** — modes are embedded in `deploy.yml` |
| v1.0.1 merged to `main` | **No** — production deploy **not triggered** |
| v1.0.1 runtime files auto-deployable by GHA | **4 of 6** (all under `rateb-erp/`) |
| v1.0.1 files excluded from GHA | **2 runtime** (`.gitignore`, `config/test-control-db.php`) + **14 docs** |
| Production ERP build marker (live) | `rateb-erp-ga-security-20260626` (v1.0.0) |
| Staging ERP build marker (live) | `rateb-erp-v1.0.1-maintenance-20260627` (v1.0.1) |
| Staging deploy mechanism | **Manual** SCP/rsync — **not** GitHub Actions |

**Bottom line:** GitHub Actions deploys **production only** on push to `main`, using **fast mode** by default. Staging is **out of band**. When v1.0.1 merges, **4 ERP files** will upload automatically; **`config/test-control-db.php` will not** unless deploy rules change or a manual upload is performed.

---

## 1. Infrastructure Map

```
Repository (GitHub: ratibstar/ratib-pro)
        │
        ▼
GitHub Actions — .github/workflows/deploy.yml
        │  trigger: push to main | workflow_dispatch
        │  environment: rateb.sa (secrets)
        │  concurrency: production-deploy
        │
        ├── backend=rsync (default) ──► scripts/github-rsync-deploy.sh
        │                                      └── github-rsync-deploy.py
        │                                      └── github-cpanel-fileman-deploy-core.py (file list)
        │
        └── backend=cpanel ──► scripts/github-cpanel-fileman-deploy.sh
                                   └── github-cpanel-fileman-deploy-core.py (Fileman API upload)

        ▼
SSH (Hetzner) / cPanel Fileman API (DirectAdmin)
        │
        ▼
/home/admin/domains/rateb.sa/public_html/   ← production document root (primary)
/home/admin/public_html/                   ← alternate sync target (cpanel-deploy-targets.txt)
/home/admin/repositories/rateb-pro/        ← git checkout (source; not served directly)

        ▼
Live URLs
  https://rateb.sa/                         ← marketing + ERP under /rateb-erp/
  https://dev.rateb.sa/                     ← staging (manual deploy only)
        └── /home/admin/domains/dev.rateb.sa/public_html/
```

**Post-deploy steps (production only, in `deploy.yml`):**

1. `run-rateb-erp-migrations.sh`
2. `run-restore-super-admins.sh`
3. `run-rcc-migrations.sh`
4. `run-rcc-realtime-hub.sh`
5. LiteSpeed cache purge
6. Enterprise certification script
7. Live HTTP verification (~20s)

---

## 2. GitHub Actions Workflow Inventory

| File | Exists | Active | Purpose |
|------|--------|--------|---------|
| `.github/workflows/deploy.yml` | ✅ | ✅ | Production deploy on `main` push |
| `.github/workflows/deploy-production.yml` | ❌ | — | Not in repository |
| `.github/workflows/deploy-staging.yml` | ❌ | — | Not in repository |
| `.github/workflows/deploy-fast.yml` | ❌ | — | **Fast mode is `DEPLOY_MODE=fast` inside `deploy.yml`** |
| `.github/workflows/deploy-hotfix.yml` | ❌ | — | Not in repository |
| `.github/workflow-drafts/*.yml` | ✅ | ❌ | Inactive drafts (PR validation, backup verify, etc.) |

### `deploy.yml` deploy modes

| Mode | When | Script behavior |
|------|------|-----------------|
| **fast** | Default on `push` to `main` | `FAST_FILES` baseline + commit-changed paths under allow prefixes |
| **critical** | `workflow_dispatch` + `full_sync=true` | `CRITICAL` file list only |
| **all** | Not exposed in workflow UI | Full tree via `find` with exclusions |
| **list** | `workflow_dispatch` + `infra_only=true` | `scripts/infra-deploy-23-files.list` |

### Deploy backend selection

```yaml
DEPLOY_BACKEND: secrets.DEPLOY_BACKEND || 'rsync'
```

- **rsync (default):** SSH to Hetzner → rsync file list to `DEPLOY_REMOTE_BASE`
- **cpanel:** cPanel Fileman API multipart upload (same file list logic)

### Remote path inconsistency (documented)

| Step | Default `DEPLOY_REMOTE_BASE` |
|------|------------------------------|
| Deploy step (line 51) | `/home/admin/public_html` |
| RCC Realtime Hub step (line 110) | `/home/admin/domains/rateb.sa/public_html` |

**Recommendation:** Align GitHub Environment `rateb.sa` secret `DEPLOY_REMOTE_BASE` to the canonical DirectAdmin path (see § DirectAdmin Analysis).

---

## 3. Deploy Path Rules (`github-cpanel-fileman-deploy-core.py`)

### Allowed prefixes (`DEPLOY_ALLOW_PREFIXES`)

```
includes/, pages/, control-panel/, rateb-erp/, ratib-contact-center/,
js/, css/, api/, storage/, modules/infrastructure-marketplace/,
config/env/, public/, public/profile-media/,
assets/images/government/, assets/images/diagrams/,
uploads/rateb_cms_media/
```

### Allowed root files (`DEPLOY_ALLOW_FILES`)

```
.htaccess, index.php, rateb-profile-fix.php, config/env.php
```

### Denied prefixes (`DEPLOY_DENY_PREFIXES`)

```
Designed/, .git/, .github/, .cursor/, archive/, node_modules/
```

### Dotfiles

Any path starting with `.` (including `.gitignore`, `.env`) → **never auto-deployed**.

### Fast deploy always-sync baseline (`FAST_FILES`)

Ships **every push** even when unchanged (~40 paths). Ends with `public/rateb-build.txt` (marketing marker).

**Not in baseline:** `rateb-erp/public/ratib-erp-build.txt` — deploys only when changed in commit.

### Full ERP bundle trigger

When commit touches `rateb-erp/migrations/` or control-panel `rateb-erp*` bridge files → uploads **entire** `rateb-erp/` tree.  
**v1.0.1 does not trigger this** — only 4 individual ERP files upload.

---

## 4. Ignored / Excluded Paths Audit

| Path | Git tracked | GHA auto-deploy | rsync `all` mode find | Notes |
|------|-------------|-----------------|----------------------|-------|
| `docs/` | ✅ | ❌ Not in allow list | ❌ `*.md` excluded | GitHub-only documentation |
| `.github/` | ✅ | ❌ `DEPLOY_DENY_PREFIXES` | ❌ excluded | Workflows never uploaded |
| `config/` (root) | Partial | ❌ except `config/env/` + `config/env.php` | Partial | `test-control-db.php` **not deployed** |
| `config/env/` | ✅ | ✅ | ✅ | Host profiles (e.g. `rateb_sa.php`) |
| `storage/` (root) | Partial | ✅ prefix | ✅ | Server runtime state mostly gitignored |
| `rateb-erp/storage/cache/` | gitignored | ✅ if under `rateb-erp/` change | — | Cleared on staging manually |
| `vendor/` | gitignored | ❌ not in allow | — | Composer deps server-local |
| `node_modules/` | gitignored | ❌ denied | ❌ excluded | Never deployed |
| `logs/`, `tmp/`, `cache/` | gitignored | ❌ | ❌ `*.log` in all mode | Runtime only |
| `uploads/` | Partial | ✅ `uploads/rateb_cms_media/` only | Partial | User content server-local |
| `.env` | gitignored | ❌ dotfile | ❌ | **Never deployed from Git** |
| `Designed/` | varies | ❌ denied | — | Off-limits per project rules |
| `archive/` | ✅ | ❌ denied | ❌ excluded | GitHub-only |
| `scripts/` | ✅ | ❌ unless in FAST/CRITICAL | Partial | Staging scripts local-only |

---

## 5. Every File Changed in `release/v1.0.1`

**Diff:** `origin/main..release/v1.0.1` — **21 files**, **2 commits**.

### Per-file deployment matrix

| # | Repository Path | Deploy Target (production) | Workflow | Uploaded by GHA? | Staging Status | Reason if skipped |
|---|-----------------|------------------------------|----------|------------------|----------------|-------------------|
| 1 | `.gitignore` | — | deploy.yml (fast) | **Skipped** | N/A | Dotfile denied |
| 2 | `config/test-control-db.php` | `public_html/config/test-control-db.php` | deploy.yml (fast) | **Skipped** | Manual SCP ✅ | `config/` not in allow list (only `config/env/`) |
| 3 | `docs/archive/ARCHIVE-PLAN.md` | — | — | **Skipped** | N/A | `docs/` not deployable |
| 4–17 | `docs/v1.0.1/*.md` (14 files) | — | — | **Skipped** | N/A | Documentation — not in allow prefixes |
| 18 | `rateb-erp/app/services/DeploymentReadinessService.php` | `.../rateb-erp/app/services/DeploymentReadinessService.php` | deploy.yml (fast) | **Yes** (on merge) | Manual SCP ✅ | `rateb-erp/` prefix |
| 19 | `rateb-erp/app/controllers/Marketing/CustomerPortalController.php` | `.../rateb-erp/app/controllers/Marketing/CustomerPortalController.php` | deploy.yml (fast) | **Yes** (on merge) | Manual SCP ✅ | `rateb-erp/` prefix |
| 20 | `rateb-erp/config/app.php` | `.../rateb-erp/config/app.php` | deploy.yml (fast) | **Yes** (on merge) | Manual SCP ✅ | `rateb-erp/` prefix |
| 21 | `rateb-erp/public/ratib-erp-build.txt` | `.../rateb-erp/public/ratib-erp-build.txt` | deploy.yml (fast) | **Yes** (on merge) | Manual SCP ✅ | `rateb-erp/` prefix |

### Additional staging-only file (not in release commit)

| Repository Path | In Git commit? | GHA | Staging |
|-----------------|----------------|-----|---------|
| `config/env/dev_rateb_sa.php` | ❌ Untracked locally | ❌ Would deploy if committed (`config/env/`) | ✅ SCP to server |

---

## 6. Three-Way State: GitHub vs Production vs Staging

**Evidence date:** 2026-06-27 (live HTTP probes + git tree)

| Asset | GitHub (`release/v1.0.1`) | Production (`rateb.sa`) | Staging (`dev.rateb.sa`) |
|-------|---------------------------|-------------------------|---------------------------|
| ERP build marker | `rateb-erp-v1.0.1-maintenance-20260627` | `rateb-erp-ga-security-20260626` | `rateb-erp-v1.0.1-maintenance-20260627` ✅ |
| Marketing build marker | `rateb-asset-assignments-fix-20260620` | `rateb-asset-assignments-fix-20260620` ✅ | **404** (not deployed) |
| `RATEB_APP_VERSION` | `1.0.1` | `1.0.0` (inferred from build) | `1.0.1` |
| ERP health | — | `{"status":"ok"}` | `{"status":"ok"}` |
| Backup verifier fix | ✅ in Git | ❌ not deployed | ✅ deployed |
| Portal logout redirect | ✅ in Git | ❌ not deployed | ✅ deployed |
| `config/test-control-db.php` SEC-M01 fix | ✅ in Git | ❌ unknown / likely old | ✅ deployed (manual) |
| `config/env/dev_rateb_sa.php` | Local untracked | N/A | ✅ server-only |
| v1.0.1 documentation | ✅ in Git | ❌ never deployed | ❌ never deployed |

**Interpretation:** Production matches Git **main** (v1.0.0). Staging matches Git **release branch ERP runtime**. Marketing shell on staging is incomplete (no `public/rateb-build.txt`).

---

## 7. Build Marker & Version Deployment

### Marketing marker — `public/rateb-build.txt`

| Check | Result |
|-------|--------|
| In `FAST_FILES` baseline | ✅ Always uploaded on every production deploy |
| Changed in v1.0.1 | ❌ No |
| Git value | `rateb-asset-assignments-fix-20260620` |
| Production live | `rateb-asset-assignments-fix-20260620` — **match** |
| Staging live | **404** — marketing `public/` not bootstrapped |

### ERP marker — `rateb-erp/public/ratib-erp-build.txt`

| Check | Result |
|-------|--------|
| In `FAST_FILES` | ❌ Not in baseline |
| Changed in v1.0.1 | ✅ Yes |
| Deploy on merge | ✅ Via commit-changed path |
| Production live | **Stale** — v1.0.0 GA marker |
| Staging live | **Current** — v1.0.1 marker |

### Version constants — `rateb-erp/config/app.php`

| Constant | v1.0.1 Git | Production (expected) | Staging |
|----------|------------|----------------------|---------|
| `RATEB_APP_VERSION` | `1.0.1` | `1.0.0` | `1.0.1` |
| `RATEB_ASSET_BUILD` | `20260627-v1.0.1-maintenance` | `20260626-*` | `20260627-v1.0.1-maintenance` |

**GHA post-deploy verify:** Checks `public/rateb-build.txt` and ERP health — does **not** assert ERP build marker string.

---

## 8. Configuration & `.env` Handling

### `.env` (project root)

| Property | Value |
|----------|-------|
| Git | **gitignored** — never in repository |
| GHA deploy | **Never uploaded** (dotfile + not in allow list) |
| Production | Server-local at `/home/admin/domains/rateb.sa/public_html/.env` |
| Staging | Server-local; `RATEB_ERP_DB_NAME=admin_rateb_dev`, `RATEB_ENV=staging` |
| Loader | `config/env/load.php` → `dotenv_bridge.php` merges bridge keys only |

### Host profiles — `config/env/*.php`

| File | Deployed by GHA? | Production | Staging |
|------|------------------|------------|---------|
| `rateb_sa.php` | ✅ (in FAST_FILES) | ✅ | Copied from prod bootstrap |
| `dev_rateb_sa.php` | ✅ if committed | N/A | ✅ manual SCP |
| `load.php`, `dotenv_bridge.php`, `directadmin_db.php` | ✅ (FAST_FILES) | ✅ | ✅ from bootstrap |

### Secrets in GitHub Environment `rateb.sa`

```
DEPLOY_SSH_HOST, DEPLOY_SSH_USER, DEPLOY_SSH_PRIVATE_KEY, DEPLOY_SSH_PORT
DEPLOY_REMOTE_BASE, DEPLOY_BACKEND, DEPLOY_SITE_URL
CPANEL_HOST, CPANEL_USER, CPANEL_API_TOKEN (optional cPanel backend)
RATEB_ERP_MIGRATE_TOKEN, RCC_MIGRATE_TOKEN
```

**Migrate token side-effect:** When any `rateb-erp/` file uploads, deploy writes `rateb-erp/storage/deploy-migrate-token` temporarily and rsyncs it (uses `CPANEL_API_TOKEN`).

---

## 9. Symlinks & Document Root

### DirectAdmin document roots (from `config/cpanel-deploy-targets.txt`)

```
/home/admin/public_html
/home/admin/domains/rateb.sa/public_html
/home/admin/rateb.sa/public_html
/home/admin/repositories/rateb-pro          ← git checkout (sync source)
/home/admin/domains/dev.rateb.sa/public_html  ← staging (not in targets file)
```

### `cpanel-deploy-sync.sh` behavior

- Reads cPanel userdata for `documentroot:` when available
- If git root **equals** document root → `git pull` updates live files directly (no rsync copy)
- Default rsync: `rsync -a` **without** `--delete` unless `RATEB_RSYNC_DELETE=1`
- Excludes only `.git/` in full-tree rsync

### Symlinks

No explicit symlink creation in deploy scripts. If DirectAdmin maps `public_html` → domain path via symlink, both targets in `cpanel-deploy-targets.txt` receive sync copies. **Not verified via SSH in this audit** — recommend operator confirm with `readlink -f` on server.

---

## 10. rsync / SCP Exclusions

### GitHub Actions rsync (`github-rsync-deploy.py`)

- Uploads **explicit file list only** — no directory-wide rsync of repo
- No `--delete` on production fast deploy
- Missing local files → `SKIP missing`

### Staging bootstrap (`scripts/staging-deploy-dev-rateb.sh`)

```bash
rsync -rlptgoD \
  --exclude='storage/backups/*.sql.gz' \
  --exclude='storage/backups/*.tar.gz'
```

Copies from production → dev for: `rateb-erp`, `config`, `includes`, `css`, `js`, `pages`, `api`, `control-panel`, plus root `index.php`, `.htaccess`, `composer.json`.

**Does not copy:** `docs/`, `.github/`, `scripts/`, full `public/` marketing tree (hence staging 404 on marketing build marker).

### Server-side full sync (`cpanel-deploy-sync.sh`)

- Excludes: `.git/` only (when using rsync)
- Does **not** exclude `docs/`, `vendor/`, `node_modules/` in fallback `cp -a` — but GHA does not run this script (cPanel git hook / manual only)

---

## 11. Files Modified in Git but Never Copied to Server

### Production (expected until merge)

| File | Impact |
|------|--------|
| All 4 `rateb-erp/*` v1.0.1 changes | ERP fixes not live on production |
| `config/test-control-db.php` | SEC-M01 fix not on production via GHA |
| `.gitignore` | No runtime impact |
| All `docs/v1.0.1/*` | Documentation only |

### Staging

| File | Status |
|------|--------|
| 4 ERP runtime files | ✅ Deployed manually |
| `config/test-control-db.php` | ✅ Deployed manually |
| `config/env/dev_rateb_sa.php` | ✅ Server only (not in Git commit) |
| `docs/v1.0.1/*` | ❌ Not on server (expected) |

---

## 12. Files on Server but Not Tracked in Git

| Category | Examples | Expected? |
|----------|----------|-------------|
| `.env` | DB credentials, tokens | ✅ Yes — gitignored |
| `rateb-erp/storage/cache/*` | Compiled cache | ✅ Yes |
| `rateb-erp/storage/backups/*.sql.gz` | DB backups | ✅ Yes — gitignored |
| `rateb-erp/storage/logs/*` | Application logs | ✅ Yes |
| `uploads/*` | User/CMS uploads | ✅ Yes |
| `vendor/`, `node_modules/` | Dependencies | ✅ Yes — if installed on server |
| `config/env/dev_rateb_sa.php` | Staging host profile | ⚠️ Should be committed or documented as server-managed |
| `.rateb-deploy-stamp` | Deploy timestamp | ✅ Server-generated |
| `rateb-erp/storage/deploy-migrate-token` | Migrate auth | ✅ Deploy-generated |

---

## 13. Deployment Gaps

| ID | Gap | Severity | Detail |
|----|-----|----------|--------|
| **GAP-01** | No staging workflow | Medium | `dev.rateb.sa` requires manual SCP/rsync; drift from Git likely |
| **GAP-02** | `config/test-control-db.php` not in deploy allow list | Medium | SEC-M01 fix won't reach production automatically on merge |
| **GAP-03** | ERP build marker not in `FAST_FILES` | Low | Verify step doesn't check ERP marker; stale marker possible if commit misses file |
| **GAP-04** | `DEPLOY_REMOTE_BASE` defaults differ in workflow | Low | Could cause split-brain if secret unset |
| **GAP-05** | `docs/` never deployed | Info | By design — docs live in GitHub only |
| **GAP-06** | Marketing `public/rateb-build.txt` stale in Git | Low | Git and prod match at `20260620` but may not reflect latest `main` intent |
| **GAP-07** | `dev_rateb_sa.php` untracked | Medium | Staging config not reproducible from Git alone |
| **GAP-08** | No PR deploy preview | Low | Draft `pr-validation.yml` inactive |
| **GAP-09** | v1.0.1 not merged | Info | Production intentionally on v1.0.0 |

---

## 14. GitHub Actions Analysis

| Aspect | Finding |
|--------|---------|
| Trigger | `push` to `main` only — **`release/v1.0.1` push does not deploy** |
| Concurrency | Single production deploy group |
| Timeout | 20 minutes |
| Default mode | `fast` (~1–2 min, ~40 baseline + changed files) |
| Full sync | Manual `workflow_dispatch` with `full_sync=true` → `critical` mode |
| Infra-only | Manual `infra_only=true` → 23-file list |
| Post-deploy | Migrations, super-admin restore, RCC, WebSocket hub, cache purge, enterprise cert, HTTP verify |
| Staging | **Not covered** |
| Branch protection | Not verified in this audit |

**On v1.0.1 merge to `main`:** Expect fast deploy uploading **4 ERP files** + **FAST_FILES baseline** (~40 files including unchanged `public/rateb-build.txt`).

---

## 15. DirectAdmin Analysis

| Item | Value |
|------|-------|
| Panel | DirectAdmin on Hetzner Cloud |
| Production domain | `rateb.sa` |
| Staging subdomain | `dev.rateb.sa` |
| Primary docroot | `/home/admin/domains/rateb.sa/public_html` |
| Alternate docroot | `/home/admin/public_html` |
| Git checkout path | `/home/admin/repositories/rateb-pro` |
| cPanel hook | `.cpanel.yml` runs `cpanel-deploy-sync.sh` on git pull (server-side) |
| File upload API | cPanel Fileman (optional backend) |
| Cache | LiteSpeed — purged via HTTP after deploy |

**ERP path on live site:** `https://rateb.sa/rateb-erp/public/` (clean URLs via `.htaccess`)

---

## 16. Hetzner Analysis

| Item | Detail |
|------|--------|
| Hosting | Hetzner Cloud VPS (inferred from project context) |
| Access | SSH key deploy (`DEPLOY_SSH_PRIVATE_KEY`) from GitHub Actions |
| SSH user | `admin` (from paths and staging scripts) |
| Server IP | Used in staging session docs (`167.233.71.107`) — not probed in this audit |
| Deploy transport | rsync over SSH (primary) or cPanel HTTPS API |
| Database | MySQL/MariaDB on same host — `admin_rateb-erp` (prod), `admin_rateb_dev` (staging) |

---

## 17. Staging Deployment Trace (Phase 10 — Manual)

```
release/v1.0.1 (local/GitHub)
        │
        ▼
Manual SSH + SCP (NOT GitHub Actions)
        │
        ├── Bootstrap: rsync copy FROM production public_html → dev.rateb.sa
        ├── Patch .env: RATEB_ERP_DB_NAME=admin_rateb_dev
        ├── Create config/env/dev_rateb_sa.php
        └── SCP v1.0.1 runtime files (6 files)
        │
        ▼
/home/admin/domains/dev.rateb.sa/public_html/
        │
        ▼
https://dev.rateb.sa/rateb-erp/public/
```

**Helper scripts (local, may be uncommitted):**

- `scripts/staging-deploy-dev-rateb.sh`
- `scripts/staging-fix-dev-env.sh`
- `scripts/staging-verify-dev.sh`
- `scripts/staging-mysql-probe.php`
- `scripts/staging-grant-dev-db.sql`

---

## 18. Missing Deployed Files (Summary)

### Will NOT deploy on v1.0.1 merge (automatic)

1. `.gitignore`
2. `config/test-control-db.php` ← **security fix gap**
3. All 14 `docs/v1.0.1/*.md` files
4. `docs/archive/ARCHIVE-PLAN.md`

### Will deploy on v1.0.1 merge (automatic)

1. `rateb-erp/app/services/DeploymentReadinessService.php`
2. `rateb-erp/app/controllers/Marketing/CustomerPortalController.php`
3. `rateb-erp/config/app.php`
4. `rateb-erp/public/ratib-erp-build.txt`

---

## 19. Final Recommendations

### Immediate (before production merge)

1. **Add `config/test-control-db.php` to deploy allow list** — extend `DEPLOY_ALLOW_FILES` or add `config/` diagnostic allow pattern — so SEC-M01 reaches production.
2. **Commit `config/env/dev_rateb_sa.php`** — reproducible staging config.
3. **Confirm `DEPLOY_REMOTE_BASE` secret** — set explicitly to `/home/admin/domains/rateb.sa/public_html` in GitHub Environment.
4. **Post-merge verify** — curl ERP build marker and test portal logout redirect on production.

### Short term (v1.0.2)

1. **Create `.github/workflows/deploy-staging.yml`** — deploy `release/*` or manual dispatch to `dev.rateb.sa`.
2. **Add ERP build marker to deploy verify step** in `deploy.yml`.
3. **Add `rateb-erp/public/ratib-erp-build.txt` to `FAST_FILES`** or post-deploy assert.
4. **Activate `workflow-drafts/pr-validation.yml`** after review.

### Repository cleanup

1. Commit Phase 8–10 docs and this audit to `release/v1.0.1`.
2. Commit staging helper scripts or move to `docs/runbooks/`.
3. Update marketing `public/rateb-build.txt` when next marketing deploy intended.

### CI/CD improvements

1. Branch protection on `main` requiring deploy verify green.
2. Separate GitHub Environment for staging secrets (`dev.rateb.sa`).
3. Document `DEPLOY_REMOTE_BASE` vs `cpanel-deploy-targets.txt` canonical path.

---

## 20. Appendix

### A. Complete v1.0.1 changed file list

```
M  .gitignore
M  config/test-control-db.php
A  docs/archive/ARCHIVE-PLAN.md
A  docs/v1.0.1/CHANGELOG-v1.0.1.md
A  docs/v1.0.1/KNOWN-ISSUES-v1.0.1.md
A  docs/v1.0.1/MIGRATION-NOTES-v1.0.1.md
A  docs/v1.0.1/PHASE-01-GIT-REPORT.md
A  docs/v1.0.1/PHASE-02-REPOSITORY-REPORT.md
A  docs/v1.0.1/PHASE3-MAINTENANCE-REPORT.md
A  docs/v1.0.1/PHASE4-REVIEW-REPORT.md
A  docs/v1.0.1/PHASE5-RELEASE-VALIDATION.md
A  docs/v1.0.1/PHASE6-RELEASE-CANDIDATE-AUDIT.md
A  docs/v1.0.1/PHASE7-RELEASE-COMMIT.md
A  docs/v1.0.1/RELEASE-CANDIDATE-CHECKLIST.md
A  docs/v1.0.1/RELEASE-CANDIDATE-SUMMARY.md
A  docs/v1.0.1/RELEASE-NOTES-v1.0.1.md
A  docs/v1.0.1/SECURITY-CHANGES-v1.0.1.md
M  rateb-erp/app/controllers/Marketing/CustomerPortalController.php
M  rateb-erp/app/services/DeploymentReadinessService.php
M  rateb-erp/config/app.php
M  rateb-erp/public/ratib-erp-build.txt
```

### B. Live probe evidence (2026-06-27)

| URL | Response |
|-----|----------|
| `https://rateb.sa/public/rateb-build.txt` | `rateb-asset-assignments-fix-20260620` |
| `https://rateb.sa/rateb-erp/public/ratib-erp-build.txt` | `rateb-erp-ga-security-20260626` |
| `https://dev.rateb.sa/public/rateb-build.txt` | HTTP 404 |
| `https://dev.rateb.sa/rateb-erp/public/ratib-erp-build.txt` | `rateb-erp-v1.0.1-maintenance-20260627` |
| `https://rateb.sa/rateb-erp/public/erp-health.php` | `{"status":"ok"}` |
| `https://dev.rateb.sa/rateb-erp/public/erp-health.php` | `{"status":"ok"}` |

### C. Reference documents

- `docs/v1.0.1/FINAL-CONSOLIDATED-REPORT-v1.0.1.md`
- `docs/v1.0.1/STAGING-DEPLOYMENT.md`
- `.cursor/rules/deploy-on-update.mdc`
- `scripts/github-cpanel-fileman-deploy-core.py`
- `.github/workflows/deploy.yml`

### D. Audit limitations

This audit did **not** SSH into Hetzner to diff full server trees. Server-only file inventory is inferred from deploy rules, gitignore, staging reports, and live HTTP probes. A full filesystem diff requires operator-run `find` + `sha256sum` on both hosts.

---

*Deployment Coverage Audit — READ ONLY — RATIB ERP v1.0.1*
