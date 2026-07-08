# Phase 2.10 — Sprint S2 Enterprise Operations

Architecture **v1.3.1** (locked). Sprint S1 remains closed; this document covers Sprint S2 additive capabilities.

## Migrations (M015–M020)

| Migration | Tables |
|-----------|--------|
| `015_import_staging` | `import_sources`, `import_batches`, `import_batch_rows` |
| `016_integration_outbox` | `integration_outbox`, `webhook_subscriptions`, `webhook_deliveries` |
| `017_product_prices` | `product_prices` |
| `018_collections_channels` | `collections`, `collection_translations`, `collection_products`, `channels`, `channel_translations`, `product_channels` |
| `019_duplicates_saved_filters` | `duplicate_rules`, `duplicate_groups`, `duplicate_group_products`, `saved_filters` |
| `020_erp_sync_import_logs` | `import_logs`, `import_log_items`, `erp_product_sync`, `sync_logs` |

Run: `php bin/migrate.php`

## Feature flags and defaults

| Variable | Default | Notes |
|----------|---------|-------|
| `QUEUE_ADAPTER` | `database` | Set `redis` for Redis queue |
| `CACHE_ADAPTER` | `file` | Set `redis` for Redis cache |
| `SEARCH_ADAPTER` | `database` | Set `opensearch` for OpenSearch |
| `RATE_LIMIT_ENABLED` | `true` | Uses Redis when `CACHE_ADAPTER=redis` |
| `CDN_PURGE_ENABLED` | `false` | Requires `CDN_PURGE_URL` |
| `CATALOG_VIRUS_SCAN_ENABLED` | `false` | Async via `media_jobs` |
| `CATALOG_INTEGRATION_TESTS` | unset | Set `1` for MySQL integration CI |

Sprint S1 defaults unchanged: `STORAGE_ADAPTER=local`, `CATALOG_S3_ENABLED=false`, `CATALOG_SIGNED_URLS_ENABLED=false`.

## New API surface (additive)

- Import staging: `POST/GET /catalog/import/batches/*`
- Webhooks: `/catalog/webhooks`
- Collections: `/catalog/collections`
- Channels: `/catalog/channels`, product channel assignments
- Pricing: `/catalog/products/{uuid}/prices`
- Duplicates: `/catalog/duplicates`, `/catalog/duplicate-rules`
- Saved filters: `/catalog/saved-filters`
- ERP sync: `GET /catalog/sync/{company_id}`
- Bulk: `/catalog/bulk/products/*`
- Jobs: `GET /catalog/jobs/{id}/items`, `DELETE /catalog/jobs/{id}`

## Middleware order

1. Correlation ID (`X-Correlation-Id`)
2. Rate limiting (`X-RateLimit-*`, HTTP 429)
3. Idempotency (`Idempotency-Key`)

## Health readiness

`GET /ready` checks: `database`, `storage`, `search`, `cache`, `queue`, and `redis` (when Redis adapters enabled).

## CI integration

Enable MySQL integration gate:

```bash
CATALOG_INTEGRATION_TESTS=1 php tests/run.php
```

Optional adapter tests:

```bash
CATALOG_ADAPTER_TESTS=redis CATALOG_REDIS_TESTS=1 php tests/run.php
```

## Smoke tests after deploy

1. `GET /health` → 200
2. `GET /ready` → `status: ready` (with DB + storage)
3. `GET /catalog/channels` → seeded channels
4. `POST /catalog/import/batches` with sample rows → preview → validate
5. Repeat bulk POST with `Idempotency-Key` → replay header
6. `GET /catalog/sync/1?since=` → company-scoped payload

## Security notes

- Webhook secrets encrypted at rest (`SecretCipher`, `CATALOG_SECRET_KEY`)
- HMAC verification: `X-Rateb-Signature`, `X-Rateb-Timestamp` (±5 min)
- ERP sync scoped by `erp_company_id` + RBAC `catalog.sync.view`
