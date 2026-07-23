# SQLite WASM vendor (Offline V2)

Vendored `@sqlite.org/sqlite-wasm` engine files used by
`assets/offline/platform/db/sqlite-runtime.js`.

Located under `/v2/vendor/sqlite/` so the V2 Service Worker (scope `/v2/`)
can precache and serve `index.mjs` + `sqlite3.wasm` without a network round-trip.
