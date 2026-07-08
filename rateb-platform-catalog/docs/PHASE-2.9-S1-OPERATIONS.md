# RATEB Platform Catalog — Phase 2.9 Sprint S1 Operations

**Release baseline:** `2.8.1`  
**Architecture:** `v1.3.1` (LOCKED)  
**Sprint:** `S1` — Upload validation, S3 storage, signed URLs, API idempotency

---

## Feature flags

| Variable | Default | Effect when `false` |
|----------|---------|---------------------|
| `CATALOG_S3_ENABLED` | `false` | `STORAGE_ADAPTER=s3` falls back to `LocalStorageAdapter` |
| `CATALOG_SIGNED_URLS_ENABLED` | `false` | `signedUrl()` returns `publicUrl()` on all adapters |

Both flags are opt-in. Existing production behavior remains local storage with public document URLs unless explicitly enabled.

---

## Storage configuration

### Local (default)

| Variable | Default | Notes |
|----------|---------|-------|
| `STORAGE_ADAPTER` | `local` | Production default |
| `RATEB_PLATFORM_CATALOG_STORAGE_PATH` | `{root}/storage` | Writable path |
| `RATEB_PLATFORM_CATALOG_CDN_BASE` | *(empty)* | Optional CDN prefix for `publicUrl()` |

### S3 / MinIO / compatible object storage

S3 activates only when **both** conditions are true:

1. `STORAGE_ADAPTER=s3`
2. `CATALOG_S3_ENABLED=true`

| Variable | Required | Example | Notes |
|----------|----------|---------|-------|
| `S3_ENDPOINT` | Recommended | `https://minio.example.test` | Leave empty for AWS default endpoints |
| `S3_BUCKET` | Yes | `rateb-catalog` | Target bucket |
| `S3_KEY` | Yes | `catalog-app` | Access key |
| `S3_SECRET` | Yes | *(secret)* | Secret key — never commit |
| `S3_REGION` | Yes | `eu-west-1` | Signing region |
| `S3_USE_PATH_STYLE` | No | `true` | Required for most MinIO deployments |

Adapter tests (optional):

```bash
CATALOG_ADAPTER_TESTS=s3 S3_ENDPOINT=... S3_BUCKET=... S3_KEY=... S3_SECRET=... php tests/run.php
```

---

## Signed URLs

Enable with:

```bash
CATALOG_SIGNED_URLS_ENABLED=true
SIGNED_URL_SECRET=<strong-random-secret>
```

### Local adapter

- Generates HMAC-SHA256 URLs: `/catalog/signed-storage?key=...&expires=...&sig=...`
- Served by `SignedStorageController` after signature verification
- Does not expose filesystem paths outside the signed route

### S3 adapter

- Uses native AWS Signature Version 4 presigned URLs when signed URLs are enabled
- Falls back to `publicUrl()` when `CATALOG_SIGNED_URLS_ENABLED=false`

`MediaMapper` continues to request one-hour signed URLs for product files. When the feature flag is disabled, clients receive deterministic public URLs (unchanged from Phase 2.8).

---

## Upload validation

`UploadValidator` runs in `MediaService`, `FileService`, and `VideoService` (self-hosted video payloads) **before** storage writes.

Validation uses `asset_types` metadata:

| Check | Source |
|-------|--------|
| MIME type | `mime_patterns` JSON (category defaults if null) |
| Extension | `extension_patterns` JSON (category defaults if null) |
| Size | Category limits in `config/upload.php` |
| Image dimensions | Required for image uploads |
| Forbidden executables | Built-in deny list (`.exe`, `.php`, etc.) |
| Empty uploads | Rejected |
| Invalid base64 | Rejected via `MediaUploadHelper` |

Optional upload size overrides:

| Variable | Default |
|----------|---------|
| `CATALOG_UPLOAD_MAX_IMAGE_BYTES` | 20 MB |
| `CATALOG_UPLOAD_MAX_DOCUMENT_BYTES` | 50 MB |
| `CATALOG_UPLOAD_MAX_VIDEO_BYTES` | 500 MB |

Rejected uploads return HTTP `422` with an envelope error message. No storage write occurs.

---

## API idempotency

Middleware: `IdempotencyMiddleware` (registered in `public/index.php`).

| Header | Rule |
|--------|------|
| `Idempotency-Key` | Optional; max 128 chars |
| `X-Idempotency-Scope` | Optional; default `api` |

Applies to `POST`, `PUT`, `PATCH` under `/catalog/*`.

| Behavior | Detail |
|----------|--------|
| Lookup key | `(idempotency_key, scope)` unique per M008 schema |
| Request hash | `SHA-256(method + path + raw body)` |
| TTL | 24 hours |
| Replay | Cached JSON response + `X-Idempotency-Replayed: true` |
| Conflict | Same key, different hash → HTTP `409` |
| Cleanup | Expired rows deleted on each middleware invocation |

No migration required — uses existing `idempotency_records` table from M008.

---

## Unchanged defaults (regression safety)

| Component | Default |
|-----------|---------|
| `SEARCH_ADAPTER` | `database` |
| `QUEUE_ADAPTER` | `database` |
| `STORAGE_ADAPTER` | `local` |
| `CATALOG_S3_ENABLED` | `false` |
| `CATALOG_SIGNED_URLS_ENABLED` | `false` |

---

## Smoke tests after enablement

1. Upload image with valid PNG → `201`
2. Upload `.exe` renamed as image → `422`
3. Repeat `POST` with same `Idempotency-Key` → `X-Idempotency-Replayed: true`
4. With S3 enabled: put/get/delete roundtrip via adapter test suite
5. With signed URLs enabled: file list URLs include `sig=` (local) or `X-Amz-Signature=` (S3)

---

## Deferred to Sprint S2

**Status: IMPLEMENTED in Sprint S2 (Phase 2.10).** See `docs/PHASE-2.10-S2-OPERATIONS.md`.

Previously deferred per Phase 2.8 release notes and architecture §17:

- Import / Export pipelines — **done**
- Outbox / Webhooks — **done**
- Redis / RabbitMQ / SQS queue adapters — **done**
- OpenSearch adapter — **done**
- ERP Bridge catalog APIs — **done**
- Collections / Channels / Pricing — **done**
- Duplicate detection — **done**
- Saved filters — **done**
- Rate limiting middleware — **done**
- Bulk async APIs — **done**
- Media virus scanning pipeline — **done**
- CDN purge integration — **done**

---

## Enterprise remediation (S1 certification follow-up)

### Self-hosted video upload contract

`VideoService` supports two modes for `video_type=self_hosted`:

| Mode | Request | Behavior |
|------|---------|----------|
| Binary upload | `content_base64` and/or multipart `file` | `UploadValidator` runs, checksum computed, storage key generated via `MediaStorageKeyBuilder::productVideo()`, binary written through `StorageAdapterInterface::put()`, metadata persisted with generated `storage_key`. Storage is rolled back if repository persistence fails. |
| Metadata-only | `storage_key` only (no binary fields) | Client supplies a pre-uploaded object key. `storage_key` must be non-empty, must not contain `..`, and must reference an object that already exists in storage. |

`storage_key` must **not** be sent together with binary upload fields.

External video types (`youtube`, `vimeo`) are unchanged and require `external_url`.

### Idempotency concurrent acquisition

`MysqlIdempotencyWriteRepository::acquire()` uses `INSERT IGNORE` for first pending rows. When two requests race on the same `(idempotency_key, scope)`, the loser re-selects the row under `FOR UPDATE` and receives `IN_PROGRESS` instead of an uncaught duplicate-key error.

Integration coverage (requires MySQL):

```bash
CATALOG_INTEGRATION_TESTS=1 php tests/run.php
```

### Signed storage MIME resolution

`SignedStorageController` resolves `Content-Type` in order:

1. Known extension map (`StorageMimeResolver`)
2. Local filesystem `mime_content_type()` when the object exists on disk
3. `finfo` buffer sniffing on the first 8 KB of the response stream when still `application/octet-stream`

Optional `StorageMimeResolver::resolve($key, $mimeHint)` accepts caller-provided MIME metadata when available (images/files store MIME in the database; signed URLs continue to sign only `key` + `expires`).

---

## Enterprise hardening (pre-Sprint S2)

- `SignedStorageController` and `MediaServeController` always close storage streams in `finally` blocks.
- `StorageMimeResolver::sanitizeForHeader()` strips CR/LF from `Content-Type` values before response headers are sent.
- `IdempotencyMiddleware` rejects `X-Idempotency-Scope` values longer than 80 characters (matches `idempotency_records.scope`).
- Expired idempotency cleanup runs on ~1% of keyed requests in production; always in `RATEB_CATALOG_TESTING`.
- `HealthService` readiness uses S3 configuration validation when `STORAGE_ADAPTER=s3` and `CATALOG_S3_ENABLED=true`; otherwise checks local storage writability.
- `MysqlIdempotencyWriteRepository` uses `INSERT IGNORE` when recycling non-cacheable idempotency rows to avoid duplicate-key races.
