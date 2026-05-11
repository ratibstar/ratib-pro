Planning notes (review before running SQL on any environment):

Tables (prefix ratib_infra_*):
- ratib_infra_catalog_item — SKU, billing code, entitlement flags (tenant_id nullable for global catalogue).
- ratib_infra_provisioning_job — job id, tenant_id, correlation_id, state machine, payloads JSON (encrypted at rest when sensitive).
- ratib_infra_provider_binding — logical role (hosting/registrar/dns/ssl), implementation class identifier, KMS pointer (no plaintext secrets).

Indexes always include tenant_id where applicable for isolation. Foreign keys to core tenant/agency tables only when those tables exist in the same database (split-DB deployments would use UUID references).
