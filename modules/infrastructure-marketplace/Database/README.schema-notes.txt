Planning notes (review before running SQL on any environment):

Tables (prefix ratib_infra_*):
- ratib_infra_catalog_item — SKU, billing code, entitlement flags (tenant_id nullable for global catalogue).
- ratib_infra_provisioning_job — job id, tenant_id, correlation_id, state machine, payloads JSON (encrypted at rest when sensitive).
- ratib_infra_provider_binding — logical role (hosting/registrar/dns/ssl), implementation class identifier, KMS pointer (no plaintext secrets).
- ratib_infra_provisioning_jobs — operational queue table (status lifecycle, retries, dead-letter state, lock timestamps).
- ratib_infra_job_logs — append-only audit-safe job logs per job id.
- ratib_infra_provider_bindings — tenant/agency scoped provider class bindings for operational runtime.
- ratib_infra_catalog_items — operational product catalog rows (SKU visibility per tenant scope).

Phase 2 migration adds pluralized operational tables while keeping phase 1 intact for safe rollback paths.

Phase 3 additions:
- ratib_infra_worker_heartbeats for daemon health and runtime memory telemetry.
- ratib_infra_audit_entries for immutable operational and admin action history.
- ratib_infra_secret_refs as encrypted secret reference preparation (no plaintext storage).

Indexes always include tenant_id where applicable for isolation. Foreign keys to core tenant/agency tables only when those tables exist in the same database (split-DB deployments would use UUID references).
