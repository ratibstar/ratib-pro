# Safe QA v2 — framework documentation for operators and automation authors

## Session manifest

Every Safe QA run creates:

`scripts/qa-manifest/sessions/SAFE-QA-YYYYMMDD-HHMMSS.json`

Each **write** registers an object immediately after server confirmation via `qa-manifest-resolve.php` (exact slug/email/code lookup — not HTML parsing).

## Required prefixes

| Type    | Prefix        | Example identifier                          |
|---------|---------------|---------------------------------------------|
| Company | `QA-COMPANY-` | slug `QA-COMPANY-20260627120000`            |
| User    | `QA-USER-`    | email `QA-USER-20260627120000@test.local`   |
| Role    | `QA-ROLE-`    | slug `QA-ROLE-20260627120000`               |
| Branch  | `QA-BRANCH-`  | code `QA-BRANCH-001` (Control Panel only)   |

## ID resolution (no HTML scraping)

After each create POST:

1. Wait briefly for DB commit.
2. `POST /rateb-erp/public/qa-manifest-resolve.php` with `X-Rateb-Migrate-Token`.
3. Body: `{ "type": "company|user|role|branch", "slug"|"email"|"code", "company_id" }`.
4. Record returned `id` in manifest.

Super-admin rows are **never** returned by the resolver (`super_admin_protected`).

## Cleanup

```powershell
# Preview
.\scripts\qa-manifest\cleanup-from-manifest.ps1 -ManifestPath .\scripts\qa-manifest\sessions\SAFE-QA-20260627011943.json -WhatIf

# Execute (users → roles → companies)
.\scripts\qa-manifest\cleanup-from-manifest.ps1 -ManifestPath .\scripts\qa-manifest\sessions\SAFE-QA-20260627011943.json
```

Delete order respects FK dependencies. Only IDs listed in the manifest file are targeted.

## Legacy session pending cleanup

`scripts/qa-manifest/sessions/SAFE-QA-20260627011943.json`:

- Company **10** — `QA-COMPANY-20260627011943`
- User **12** — `QA-USER-20260627011943@test.local`

Operator approval required before running cleanup script on this file.

## Failure rule

If resolver returns `not_found` or token missing → **STOP**, no cleanup, incident report.

## Files

| File | Purpose |
|------|---------|
| `rateb-erp/bin/QaManifestResolver.php` | DB lookup with QA-prefix guard |
| `rateb-erp/public/qa-manifest-resolve.php` | Token-gated HTTP API |
| `scripts/qa-manifest/SafeQaManifest.psm1` | Manifest CRUD + validation |
| `scripts/qa-safe-mode-run-v2.ps1` | Sample create + register flow |
| `scripts/qa-manifest/cleanup-from-manifest.ps1` | Manifest-only delete |
