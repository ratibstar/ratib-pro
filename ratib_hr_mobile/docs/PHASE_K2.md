# Phase K2 — Google Play Release Preparation

**Status:** COMPLETE (prep only — **no upload**)  
**Date:** 2026-07-21  
**Depends on:** K1 signed production AAB

## Deliverables

| Item | Location |
|------|----------|
| Play release runbook + listing drafts | [PLAY_STORE_RELEASE.md](PLAY_STORE_RELEASE.md) |
| Store assets matrix | [STORE_ASSETS_CHECKLIST.md](STORE_ASSETS_CHECKLIST.md) |
| Data Safety / Privacy drafts | [COMPLIANCE.md](COMPLIANCE.md) + Play doc §4–5 |

## Verified (no code/signing changes)

- AAB identity: `sa.rateb.hr.mobile` · `1.0.0+200` · `production`
- Manifest: only justified permissions (see Play doc §2)
- targetSdk **36** / minSdk **24**
- Cleartext disabled

## Explicit non-goals

- No Play Console upload
- No signing / keystore changes
- No Flutter architecture or ERP API changes

## Next (operator)

1. Backup upload keystore  
2. Play App Signing + Internal testing upload  
3. Live Privacy Policy URL + Data Safety  
4. Graphics + AR/EN screenshots  
5. Closed → Production checklist in PLAY_STORE_RELEASE.md
