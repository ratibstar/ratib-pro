# Zero-touch proof — Offline V1 (Phase 4)

**Captured:** 2026-07-16

```
git diff --name-only HEAD -- \
  rateb-erp/public/pos-sw.js \
  rateb-erp/public/rateb-offline-sw.js \
  rateb-erp/public/offline-shell.html \
  rateb-erp/public/assets/offline \
  rateb-erp/offline \
  rateb-erp/public/assets/js/erp-nav-instant.js \
  rateb-erp/capacitor
```

Expected: empty. Only `rateb-erp/offline-v2/` and `rateb-erp/public/v2/` untracked/changed.
