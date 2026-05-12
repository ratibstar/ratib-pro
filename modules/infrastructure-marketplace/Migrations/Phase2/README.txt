# Phase 2 — Commerce foundation migrations

Run **after** the base `ALL_for_outratib_control_panel_db.sql` (or equivalent 001–006) on the **same** database that holds `ratib_infra_*`.

## Order

1. `001_commerce_foundation_tables.sql` — creates `ratib_infra_products`, `ratib_infra_plans`, `ratib_infra_plan_features`, `ratib_infra_pricing`.
2. `002_tenant_resources_overlay.sql` — creates `ratib_tenant_resources` (FKs to products/plans).

## Notes

- Does **not** modify `ratib_infra_catalog_items`.
- Commerce plan `commerce_state` is **not** the same namespace as `ratib_infra_provisioning_jobs.status`.
- `ratib_tenant_resources.ownership_state` uses literals **OWNED**, **UNCLAIMED**, **DISABLED**, **PENDING_LINK** (distinct from queue and plan commerce states).
