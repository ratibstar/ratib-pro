# Phase Z — Deployment Checklist

## Pre-push

- [x] `.gitignore` allows `rateb-erp/public/v2/vendor/**`
- [x] SQLite vendor files present locally under `public/v2/vendor/sqlite/`
- [x] `scripts/github-cpanel-fileman-deploy-core.py` includes `.wasm` in `BINARY_EXTENSIONS`
- [x] `rateb-erp/` remains under `DEPLOY_ALLOW_PREFIXES` (changed files auto-upload)
- [x] Offline V1 paths not modified for this phase

## Required runtime assets (must be in git + on production HTTP 200)

| Path | Role |
|------|------|
| `rateb-erp/public/v2/vendor/sqlite/index.mjs` | ESM entry for `sqlite-runtime.js` |
| `rateb-erp/public/v2/vendor/sqlite/sqlite3.wasm` | WASM engine |
| `rateb-erp/public/v2/vendor/sqlite/sqlite3-opfs-async-proxy.js` | OPFS VFS proxy |
| `rateb-erp/public/v2/vendor/sqlite/sqlite3-worker1.mjs` | Worker support (locateFile) |
| `rateb-erp/public/v2/js/db/sqlite-runtime.js` | Runtime wrapper |
| `rateb-erp/public/v2/sw.js` | Host SW (resilient precache) |
| `rateb-erp/public/v2/js/boot.js` | Boot orchestration |
| `rateb-erp/public/v2/.htaccess` | DirectoryIndex + MIME |
| `rateb-erp/public/v2/index.html` | Host document |

## Deploy notes

- Fast deploy uploads **commit-changed** paths under `rateb-erp/`.
- First ship of vendor tree will upload large `.mjs` (text) + `.wasm` (multipart binary).
- Confirm Actions job green before validating production.
- Start URL for install: `/rateb-erp/public/v2/index.html` (directory URL also served via DirectoryIndex after `.htaccess`).

## Post-deploy verification

```text
curl -I https://rateb.sa/rateb-erp/public/v2/vendor/sqlite/index.mjs
curl -I https://rateb.sa/rateb-erp/public/v2/vendor/sqlite/sqlite3.wasm
curl -I https://rateb.sa/rateb-erp/public/v2/vendor/sqlite/sqlite3-opfs-async-proxy.js
curl -I https://rateb.sa/rateb-erp/public/v2/
curl -I https://rateb.sa/rateb-erp/public/v2/sw.js
```

Expect HTTP 200 (not 404). Then fresh-browser Shell Ready &lt; 3s + offline reload.
