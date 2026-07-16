# Zero-touch proof — Offline V1 (Phase 11)

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
  rateb-erp/public/v2/js/business/identity-module.js \
  rateb-erp/public/v2/js/business/business-module-framework.js
```

Expected: empty for frozen paths (Identity + platform unchanged).
