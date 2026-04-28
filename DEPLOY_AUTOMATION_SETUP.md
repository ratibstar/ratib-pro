# Auto Deploy Setup (Cursor -> GitHub -> cPanel)

This project is ready for automatic deployment using:

- `.github/workflows/deploy.yml`
- SFTP upload to `public_html`
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

## 3) Add required GitHub Secrets

In GitHub repository:

`Settings` -> `Secrets and variables` -> `Actions` -> `New repository secret`

Create exactly these keys:

- `SFTP_HOST` (example: `out.ratib.sa` or server IP)
- `SFTP_USERNAME` (cPanel username, example: `outratib`)
- `SFTP_PASSWORD` (cPanel/FTP password)
- `SFTP_PORT` (usually `22`)
- `SFTP_TARGET_DIR` (`public_html`)

## 4) How deployment works

Any push to `main` triggers deployment automatically.

```powershell
git add .
git commit -m "Update website"
git push
```

Then verify in GitHub -> `Actions`.

## 5) Notes

- `.env` and `config/ngenius.secrets.php` are excluded from deployment.
- `vendor/` and `node_modules/` are excluded to keep deploy fast.
- The workflow uses mirror with `--delete`, so remote files not in repo may be removed.
- Do not store production secrets in repository files.
