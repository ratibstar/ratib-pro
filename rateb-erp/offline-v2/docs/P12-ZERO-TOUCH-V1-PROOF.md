# Zero-touch proof — Offline V1 + locked layers (Phase 12)

```
git diff --name-only HEAD -- \
  rateb-erp/public/pos-sw.js \
  rateb-erp/public/rateb-offline-sw.js \
  rateb-erp/public/offline-shell.html \
  rateb-erp/public/assets/offline \
  rateb-erp/offline \
  rateb-erp/public/assets/js/erp-nav-instant.js \
  rateb-erp/capacitor \
  rateb-erp/public/v2/js/hci.js \
  rateb-erp/public/v2/js/runtime \
  rateb-erp/public/v2/js/router \
  rateb-erp/public/v2/js/ui \
  rateb-erp/public/v2/js/sync \
  rateb-erp/public/v2/js/modules \
  rateb-erp/public/v2/js/db \
  rateb-erp/public/v2/js/package-manager.js \
  rateb-erp/public/v2/js/business/identity-module.js \
  rateb-erp/public/v2/js/business/inventory-module.js \
  rateb-erp/public/v2/js/business/business-module-framework.js
```

Expected: **empty** (frozen paths unchanged).

Procurement artifacts only under:

- `public/v2/js/business/procurement-module.js`
- host wiring (`index.html`, `boot.js`, `sw.js`)
- `public/v2/js/routes/route-manifest.json` (host alias for Router relative URL; Router code untouched)
- `offline-v2/docs/P12-*`
