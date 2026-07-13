# Troubleshooting (Universal)

| Symptom | Action |
|---------|--------|
| Installer: no PHP | Allow network for Windows PHP download, or place `runtime/php`, or install php-cli + extensions |
| Port in use | Installer picks next free port — open URL from `appliance.env` / post-install.html |
| HTTP fail | Check Web service; `hybrid-branch-serve.php` logs under `storage/branch/logs` |
| Sync idle offline | Expected; resumes when online |
| Verify failed / rollback | Read installer log; ensure writable `storage/branch`; re-run installer |
| Corrupt DB | Wait for recover watchdog or run `hybrid-branch-recover.php` |
| Wrong URL | Never hardcode — use `RATEB_BRANCH_HTTP_URL` in `appliance.env` |

## Verification suite

```bash
php bin/hybrid-phase-d3-enterprise-verify.php
```

Evidence: `storage/branch/phase-d3-enterprise-verify.json`
