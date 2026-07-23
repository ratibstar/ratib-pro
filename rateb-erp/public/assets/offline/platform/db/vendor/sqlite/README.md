# Moved (Offline Fix 4)

SQLite WASM vendor now lives at:

`rateb-erp/public/v2/vendor/sqlite/`

Reason: V2 Service Worker scope is `/v2/` only — WASM must be under that
prefix for precache + fetch cache hits. Engine files are unchanged.
