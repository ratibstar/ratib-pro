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

- `CPANEL_HOST` (example: `server.ratib.sa`)
- `CPANEL_USER` (example: `outratib`)
- `CPANEL_API_TOKEN` (cPanel API token from `Manage API Tokens`)
- `CPANEL_REPO_ROOT` (example: `/home/outratib/repositories/ratib-pro`)

## 4) How deployment works

Any push to `main` triggers deployment automatically.

```powershell
git add .
git commit -m "Update website"
git push
```

Then verify in GitHub -> `Actions`.

## 5) Notes

- cPanel runs deployment using project `.cpanel.yml`.
- Workflow calls cPanel UAPI `VersionControlDeployment::create` endpoint.
- Keep `.cpanel.yml` valid YAML and committed to `main`.
- Do not store production secrets in repository files.
