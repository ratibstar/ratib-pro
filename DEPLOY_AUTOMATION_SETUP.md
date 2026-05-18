# Auto Deploy Setup (Cursor -> GitHub -> cPanel)

This project is ready for automatic deployment using:

- `.github/workflows/deploy.yml`
- cPanel Git Deploy Hook trigger from GitHub Actions
- Safe exclusions for secrets, logs, caches, and heavy folders

## 1) Install Git on your Windows machine

If `git` is not available, install Git for Windows first:

- Download: https://git-scm.com/download/win
- Install with default options
- Reopen Cursor terminal after installation

Quick check:

```powershell
git --version
```

## 2) Create GitHub repository and push project

Run these commands inside project root:

```powershell
git init
git add .
git commit -m "Initial project with auto deploy workflow"
git branch -M main
git remote add origin https://github.com/<YOUR_USERNAME>/<YOUR_REPO>.git
git push -u origin main
```

## 3) Add required GitHub Secret

In GitHub repository:

`Settings` -> `Secrets and variables` -> `Actions` -> `New repository secret`

Create these keys:

**Required (cPanel Git API):**

- `CPANEL_HOST` — hostname only, e.g. `server.ratib.sa` (not `https://`)
- `CPANEL_USER` — e.g. `outratib`
- `CPANEL_API_TOKEN` — cPanel → **Manage API Tokens** → create token with Version Control
- `CPANEL_REPO_ROOT` — exact path from cPanel → **Git Version Control** → your repo, e.g. `/home/outratib/repositories/ratib-pro` **or** `/home/outratib/public_html` if git lives in docroot

**Strongly recommended (direct upload to live site — fixes “green but not on site”):**

- `CPANEL_SFTP_HOST` — usually same as `CPANEL_HOST` or server IP
- `CPANEL_SFTP_USER` — usually `outratib` (defaults to `CPANEL_USER` if omitted)
- `CPANEL_SFTP_PASSWORD` — cPanel account password or SFTP-only password
- `CPANEL_SFTP_REMOTE_DIR` — `/home/outratib/public_html/` (trailing slash)

Optional:

- `CPANEL_PORT` — default `2083`
- `CPANEL_SITE_URL` — default `https://out.ratib.sa`
- `RATIB_DEPLOY_SYNC_KEY` — only if PHP curl deploy is enabled on server (usually fails; use SFTP instead)

## 4) How deployment works

Any push to `main` triggers deployment automatically.

```powershell
git add .
git commit -m "Update website"
git push
```

Then verify in GitHub -> `Actions` (deploy step must be green; verify step checks live Profile).

Flow:

1. GitHub Actions calls cPanel **VersionControl/update** + **VersionControlDeployment/create** (git pull + `.cpanel.yml`).
2. `.cpanel.yml` runs `scripts/cpanel-deploy-sync.sh`, which **must** sync the git checkout to **`/home/outratib/public_html`** (live docroot for `out.ratib.sa`).
3. If secret `RATIB_DEPLOY_SYNC_KEY` is set, Actions also calls `https://out.ratib.sa/ratib-profile-check.php?deploy=1&key=...` as a fallback.
4. Verify step reads `ratib-profile-check.php` on the live site for `brand-profile` / `company-profile.php`.

**Important:** The workflow now **fails** unless the live site shows the same `public/ratib-build.txt` marker as GitHub. Green only means `public_html` was actually updated.

If git is in `/home/outratib/repositories/ratib-pro`, `.cpanel.yml` must rsync to `/home/outratib/public_html`. If that path is wrong, add **SFTP secrets** so GitHub uploads straight to `public_html`.

## 5) cPanel one-time check

In cPanel → **Git Version Control**:

- Repository path = same as GitHub secret `CPANEL_REPO_ROOT`
- Click **Update from Remote**, then **Deploy HEAD Commit**
- Open `https://out.ratib.sa/pages/ratib-deploy-status.txt` — should show a recent timestamp after deploy

Live docroot (confirmed): `/home/outratib/public_html`

## 6) Notes

- cPanel runs deployment using project `.cpanel.yml` (no longer forced `exit 0` on sync failure).
- Do not store production secrets in repository files.
