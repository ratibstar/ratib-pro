# RATEB Platform Catalog — Test Suite (Release 2.8.1)

Architecture **v1.3.1** (LOCKED). Production search default: `SEARCH_ADAPTER=database`.

## Core Certification Suite

The default runner certifies the **production path** for Release 2.8.1:

- Unit tests (`tests/Unit/`)
- `DatabaseSearchAdapter` integration (`tests/Integration/DatabaseSearchIntegrationTest.php`)
- Phase 2.8 enterprise integration (`tests/Integration/Phase28EnterpriseTest.php`, SEO, snapshot restore, scheduled publish)
- Database-backed queue integration (`tests/Integration/SearchQueueIntegrationTest.php` — queue idempotency and scheduler only)

**130 tests** in the core suite.

### Run core certification

```bash
php tests/run.php
```

### Run core certification with live database integration

```bash
CATALOG_INTEGRATION_TESTS=1 php tests/run.php
```

Requires valid `RATEB_PLATFORM_CATALOG_DB_*` credentials, migrations **M001–M014** applied, and catalog seed data where tests mutate rows.

### Expected summary (default, no live DB)

```
Core Tests:
116 PASS
0 FAIL
14 SKIP

Optional Adapter Tests:
Not Executed
```

With a configured catalog database and `CATALOG_INTEGRATION_TESTS=1`, core integration skips drop to **0** when the environment is fully ready.

## Optional Adapter Suites

Optional adapters are **not** part of Release 2.8.1 production certification. They validate rollback / legacy configurations.

| Suite | Env flag | Location |
|-------|----------|----------|
| Meilisearch | `CATALOG_ADAPTER_TESTS=meilisearch` | `tests/Integration/Adapters/Meilisearch/` |

Meilisearch remains fully supported in production when `SEARCH_ADAPTER=meilisearch` and `MEILISEARCH_HOST` is set. The optional suite exercises live index/search/barcode roundtrip against a Meilisearch instance.

### Run Meilisearch adapter integration

```bash
CATALOG_ADAPTER_TESTS=meilisearch MEILISEARCH_HOST=http://127.0.0.1:7700 php tests/run.php
```

Optional API key:

```bash
CATALOG_ADAPTER_TESTS=meilisearch \
  MEILISEARCH_HOST=http://127.0.0.1:7700 \
  MEILISEARCH_API_KEY=your-master-key \
  php tests/run.php
```

### Expected summary (adapter suite enabled, Meilisearch reachable)

```
Core Tests:
130 PASS
0 FAIL
0 SKIP

Optional Adapter Tests:
1 PASS
0 FAIL
0 SKIP
```

## CI examples

### Release gate (every push) — core only

```yaml
- name: Catalog core certification
  run: php rateb-platform-catalog/tests/run.php
  working-directory: .
```

### Staging / pre-deploy — core + database integration

```yaml
- name: Catalog core + DB integration
  env:
    CATALOG_INTEGRATION_TESTS: "1"
    RATEB_PLATFORM_CATALOG_DB_HOST: 127.0.0.1
    RATEB_PLATFORM_CATALOG_DB_USER: catalog_app
    RATEB_PLATFORM_CATALOG_DB_PASS: ${{ secrets.CATALOG_DB_PASS }}
    RATEB_PLATFORM_CATALOG_DB_NAME: admin_rateb_platform_catalog
  run: php rateb-platform-catalog/tests/run.php
```

### Rollback / Meilisearch adapter regression (scheduled or manual)

```yaml
- name: Meilisearch adapter compatibility
  env:
    CATALOG_ADAPTER_TESTS: meilisearch
    MEILISEARCH_HOST: http://meilisearch:7700
    MEILISEARCH_API_KEY: ${{ secrets.MEILISEARCH_API_KEY }}
  run: php rateb-platform-catalog/tests/run.php
```

## Skip semantics

- `[SKIP]` in core tests increments **Core SKIP** (not PASS).
- Adapter tests run only when `CATALOG_ADAPTER_TESTS=meilisearch` is set; otherwise the summary shows `Optional Adapter Tests: Not Executed`.
- Exit code **1** only when any core or adapter test **FAIL**s.

## Build marker

Deploy verification: `public/rateb-catalog-build.txt`

```
release=2.8.1
phase=2.8
architecture=1.3.1
build=2026-07-08T06:15:00+03:00
search_adapter_default=database
core_certification_tests=130
core_certification_status=130/130
optional_adapter_tests=1
optional_adapter_suites=meilisearch
```

Core certification gate: **130/130 PASS, 0 FAIL, 0 SKIP** with `CATALOG_INTEGRATION_TESTS=1` and a live catalog database.
