# GitHub Workflow Drafts (INACTIVE)

These workflows are **prepared for v1.0.1** but **not activated**.

GitHub Actions only loads files from `.github/workflows/*.yml`.  
Drafts live here until explicitly copied and approved.

## Activation procedure

1. Review draft with technical lead
2. Copy to `.github/workflows/`
3. Enable branch protection + required checks
4. Test on a non-production branch first

## Drafts

| File | Purpose |
|------|---------|
| `pr-validation.yml` | PHP lint + enterprise test (no DB) on pull requests |
| `backup-verify.yml` | Weekly backup verify via workflow_dispatch |
| `rollback-checklist.yml` | Manual rollback reminder + artifact links |
| `tag-validation.yml` | Validate annotated tags match semver + build marker |

**Do not copy to workflows/ until approved.**
