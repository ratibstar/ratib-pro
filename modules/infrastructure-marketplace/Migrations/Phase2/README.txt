# Phase 2 — Commerce foundation migrations

Run **after** the base `ALL_for_admin_control_panel_db.sql` (or equivalent 001–006) on the **same** database that holds `rateb_infra_*`.

## Order

1. `001_commerce_foundation_tables.sql` — creates `rateb_infra_products`, `rateb_infra_plans`, `rateb_infra_plan_features`, `rateb_infra_pricing`.
2. `002_tenant_resources_overlay.sql` — creates `rateb_tenant_resources` (FKs to products/plans).

## Notes

- Does **not** modify `rateb_infra_catalog_items`.
- Commerce plan `commerce_state` is **not** the same namespace as `rateb_infra_provisioning_jobs.status`.
- `rateb_tenant_resources.ownership_state` uses literals **OWNED**, **UNCLAIMED**, **DISABLED**, **PENDING_LINK** (distinct from queue and plan commerce states).
