# RATEB Platform Catalog — Phase 2.8 Production Release

**Release:** `2.8.1`  
**Phase:** `2.8`  
**Architecture:** `v1.3.1` (LOCKED)  
**Build:** `2026-07-08T06:15:00+03:00`  
**Tests:** `131/131 PASS`

---

## Release identifier

| Marker | Location |
|--------|----------|
| Release version | `config/release.php` → `RATEB_PLATFORM_CATALOG_RELEASE` |
| Phase | `config/release.php` → `RATEB_PLATFORM_CATALOG_PHASE` |
| Architecture version | `config/app.php` + `config/release.php` |
| Build timestamp | `config/release.php` → `RATEB_PLATFORM_CATALOG_BUILD_TIMESTAMP` |
| Deploy marker file | `public/rateb-catalog-build.txt` |
| Runtime check | `GET /health` → `data.release`, `data.architecture_version`, `data.build_timestamp` |

---

## 1. Deployment checklist

### Pre-deploy

- [ ] Staging smoke tests passed (see §4)
- [ ] Database backup taken: `mysqldump` → `storage/backups/catalog-{Ymd-His}.sql.gz`
- [ ] M012 preflight: no duplicate SEO slugs
  ```sql
  SELECT slug, language_code, COUNT(*) AS c
  FROM product_seo_translations
  WHERE slug IS NOT NULL AND slug <> ''
  GROUP BY slug, language_code
  HAVING c > 1;
  ```
  Must return **0 rows**.
- [ ] Production env vars set (see §2)
- [ ] Gateway trust configured on reverse proxy
- [ ] `storage/` writable by PHP user

### Deploy steps

1. Put application in maintenance mode (stop write traffic if applicable).
2. Deploy code to `rateb-platform-catalog/` (document root → `public/`).
3. Set environment variables on the server (§2).
4. Run migrations:
   ```bash
   php bin/migrate.php
   ```
5. Verify migration log shows M010–M014 applied (or “Already applied”).
6. Start / confirm cron jobs (§3).
7. `GET /health` → `200`, `release: 2.8.1`
8. `GET /ready` → `200`, `database: true`, `storage: true`
9. Run smoke tests (§4).
10. Resume traffic.

### Post-deploy (first 24h)

- [ ] Monitor `storage/logs/catalog.log` for `scheduled_publish_failed`
- [ ] Monitor `audit_events` for unexpected denials
- [ ] Confirm scheduler clears due `publish_at` / `archive_at` rows
- [ ] Confirm search worker processes `search_reindex` jobs (no-op index writes when using database adapter; queue remains for compatibility)

---

## 2. Environment variable checklist

### Required — database

| Variable | Example | Notes |
|----------|---------|-------|
| `RATEB_PLATFORM_CATALOG_DB_HOST` | `127.0.0.1` | Write host |
| `RATEB_PLATFORM_CATALOG_DB_PORT` | `3306` | |
| `RATEB_PLATFORM_CATALOG_DB_USER` | `catalog_app` | Least privilege |
| `RATEB_PLATFORM_CATALOG_DB_PASS` | *(secret)* | Never commit |
| `RATEB_PLATFORM_CATALOG_DB_NAME` | `admin_rateb_platform_catalog` | |

Optional: `RATEB_PLATFORM_CATALOG_DB_READ_HOST` for read replica.

### Required — production security (gateway trust)

| Variable | Production value | Notes |
|----------|------------------|-------|
| `RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_ENABLED` | `1` | **Mandatory** |
| `RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_SECRET` | Strong random secret | Shared with gateway only |

Gateway must:

- **Strip** client `X-Platform-User-Id` and `X-Platform-Gateway-Token`
- **Inject** both after authenticating the platform user

### Required — storage

| Variable | Default | Notes |
|----------|---------|-------|
| `RATEB_PLATFORM_CATALOG_STORAGE_PATH` | `{root}/storage` | Must be writable |

Writable subdirs (auto-created): `catalog/`, `logs/`, `cache/`, `queue/`, `backups/`.

### Search & queue (Release 2.8.1)

| Variable | Default | Notes |
|----------|---------|-------|
| `SEARCH_ADAPTER` | `database` | MariaDB/MySQL via `DatabaseSearchAdapter` |
| `MEILISEARCH_HOST` | — | Required only when `SEARCH_ADAPTER=meilisearch` |
| `MEILISEARCH_API_KEY` | — | Optional for Meilisearch |
| `QUEUE_ADAPTER` | `database` | |
| `STORAGE_ADAPTER` | `local` | |

**Database search (default):** No external search service is required. Search reads live relational data through `MysqlSearchIndexReadRepository` using FULLTEXT, SKU, and barcode indexes added in migration **M014**.

**Meilisearch (optional):** Set `SEARCH_ADAPTER=meilisearch` and configure `MEILISEARCH_HOST` to use the legacy adapter. All search APIs remain unchanged.

### Integration tests (CI/staging only — not production)

| Variable | Value |
|----------|-------|
| `CATALOG_INTEGRATION_TESTS` | `1` |

---

## 3. Scheduler & worker configuration

| Script | Purpose | Suggested schedule |
|--------|---------|-------------------|
| `php bin/rpc-scheduler.php` | Due publish/archive + maintenance enqueue | Every 1–5 min (cron) |
| `php bin/rpc-worker.php --queue=search,maintenance` | Process search reindex + maintenance | Continuous or every minute |
| `php bin/rpc-search-reindex.php` | Full reindex (DR / adapter migration) | On demand |

Scheduler runs as system user via `InternalActorContext` (not HTTP headers).

With `SEARCH_ADAPTER=database`, index write operations are no-ops; the queue and workers remain for API compatibility and event-driven enqueue paths.

---

## 4. Smoke test procedure

Run on staging after deploy; repeat on production before traffic cutover.

### Infrastructure

```http
GET /health
```
Expect: `status: ok`, `release: 2.8.1`, `architecture_version: 1.3.1`

```http
GET /ready
```
Expect: `status: ready`, `checks.database: true`, `checks.storage: true`

### Search (database adapter — default)

| Test | Expected |
|------|----------|
| `GET /catalog/search?q=test` | `200`, results from MariaDB |
| `GET /catalog/search/variants?q=test` | `200` |
| `GET /catalog/search/barcode/{barcode}` | `200` or `404` |
| Search adapter health (via worker `health_check` job) | Passes when database is reachable |

### Security

| Test | Expected |
|------|----------|
| API call with spoofed `X-Platform-User-Id` only | `403` / no identity |
| API call with valid gateway token + user id | Identity resolved |
| `PUT /catalog/admin/users/00000000-0000-4000-8000-000000000001/roles` | `422` system user protected |

### RBAC & catalog

| Endpoint | Permission | Expected |
|----------|------------|----------|
| `GET /catalog/admin/roles` | `catalog.rbac.manage` | `200` |
| `GET /catalog/products` | `catalog.products.view` | `200` |
| `GET /catalog/products/{uuid}/workflow/history` | `catalog.products.view` | `200` |

### Workflow & scheduler

1. Set test product: `status=approved`, `publish_at` in the past.
2. Run `php bin/rpc-scheduler.php`.
3. Verify: `status=published`, `publish_at` cleared.
4. Verify `audit_events` action `scheduled_publish`.
5. Verify search queue / `search_reindex` job enqueued.

### Failure visibility

Force stale `lock_version` on scheduled product → run scheduler → verify:

- `audit_events` action `scheduled_publish_failed`
- Entry in `storage/logs/catalog.log`

---

## 5. Rollback procedure

### Application rollback (code only)

1. Stop write traffic.
2. Redeploy previous release artifact.
3. `GET /ready` → confirm healthy.
4. Resume traffic.

**Do not** run `php bin/migrate.php rollback` on production unless DBA approves — M011 rollback is partial. M014 index rollback is safe but removes search performance indexes.

### Rollback search adapter to Meilisearch

1. Ensure Meilisearch is running and reachable.
2. Set environment:
   ```
   SEARCH_ADAPTER=meilisearch
   MEILISEARCH_HOST=http://127.0.0.1:7700
   MEILISEARCH_API_KEY=<key-if-required>
   ```
3. Run full reindex:
   ```bash
   php bin/rpc-search-reindex.php
   ```
4. Verify `GET /catalog/search?q=test` returns Meilisearch-backed results.
5. No API or schema changes are required — `SearchAdapterInterface` is unchanged.

### Database rollback (preferred for schema issues)

1. Stop write traffic.
2. Restore latest `storage/backups/catalog-*.sql.gz` to `admin_rateb_platform_catalog`.
3. Redeploy matching application version if needed.
4. Run full search reindex (Meilisearch only):
   ```bash
   php bin/rpc-search-reindex.php
   ```
5. `GET /ready` → `200`
6. Run smoke tests (§4).
7. Resume traffic.

### RPO / RTO targets (architecture §17.8)

| Metric | Target |
|--------|--------|
| RPO | ≤ 24 hours |
| RTO | ≤ 4 hours |

---

## 6. Release freeze scope

**Included (Phase 2.8 / Release 2.8.1):**

- Workflow, versioning, change requests, completeness
- RBAC admin APIs, audit events, product SEO
- Scheduled publish/archive, gateway trust hardening
- System user protection, scheduler failure logging
- **DatabaseSearchAdapter** (default production search)
- Migration **M014** (FULLTEXT + SKU/barcode search indexes)

**Excluded (not in this release):**

Phase 2.9, Import, Export, Outbox, Webhooks, Redis, RabbitMQ, SQS, OpenSearch, S3, ERP Bridge, Collections, Channels, Pricing, Duplicate Detection, Saved Filters.

---

## 7. Verification commands

```bash
# Migrations (includes M014 database search indexes)
php bin/migrate.php

# Full test suite (CI/staging)
CATALOG_INTEGRATION_TESTS=1 php tests/run.php

# Build marker
cat public/rateb-catalog-build.txt
```

Expected test result: **Passed: 131, Failed: 0**

### M014 migration summary

Migration `014_database_search_indexes` adds:

- `FULLTEXT` on `product_translations` (name, short_description, description)
- `FULLTEXT` on `product_variant_translations` (name, description)
- SKU and published-status indexes on `products` / `product_variants`
- Active barcode indexes on `product_barcodes` / `product_variant_barcodes`

Required before production database search performs acceptably at scale.
