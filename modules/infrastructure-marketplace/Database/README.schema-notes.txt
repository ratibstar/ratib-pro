Planning notes (review before running SQL on any environment):

Tables (prefix rateb_infra_*):
- rateb_infra_catalog_item — SKU, billing code, entitlement flags (tenant_id nullable for global catalogue).
- rateb_infra_provisioning_job — job id, tenant_id, correlation_id, state machine, payloads JSON (encrypted at rest when sensitive).
- rateb_infra_provider_binding — logical role (hosting/registrar/dns/ssl), implementation class identifier, KMS pointer (no plaintext secrets).
- rateb_infra_provisioning_jobs — operational queue table (status lifecycle, retries, dead-letter state, lock timestamps).
- rateb_infra_job_logs — append-only audit-safe job logs per job id.
- rateb_infra_provider_bindings — tenant/agency scoped provider class bindings for operational runtime.
- rateb_infra_catalog_items — operational product catalog rows (SKU visibility per tenant scope).

Phase 2 migration adds pluralized operational tables while keeping phase 1 intact for safe rollback paths.

Phase 3 additions:
- rateb_infra_worker_heartbeats for daemon health and runtime memory telemetry.
- rateb_infra_audit_entries for immutable operational and admin action history.
- rateb_infra_secret_refs as encrypted secret reference preparation (no plaintext storage).

Phase 4 additions:
- rateb_infra_provider_activations for scoped provider enable/disable and priority weighting.
- rateb_infra_orders for idempotent tenant-safe order pipeline with provisioning linkage.
- rateb_infra_domain_search_cache for async domain-search caching abstraction.
- rateb_infra_services for exposed infrastructure service lifecycle tracking.

Phase 5 operational hardening:
- rateb_infra_domain_search_rate enforces per-scope query safety for live availability checks.
- Existing tables are reused for dead-letter retry tooling, provisioning trace viewing, and billing linkage snapshots.

Indexes always include tenant_id where applicable for isolation. Foreign keys to core tenant/agency tables only when those tables exist in the same database (split-DB deployments would use UUID references).
