# Rollout Runbook (Single Control Plane)

This runbook defines how to release Ratib Pro updates safely across many agencies and countries using the rollout flag engine.

## Scope

- Single operator UI: `admin/control-center.php`
- Shared rollout engine:
  - `control_rollout_feature_flags`
  - `control_rollout_flag_overrides`
  - resolver: `trf_resolve_effective_flag()`

## Standard waves

- `canary` -> small pilot
- `wave1` -> early production
- `wave2` -> broad production
- `full` -> all target contexts

Recommended percentages:

- `canary`: 1-5%
- `wave1`: 10%
- `wave2`: 30-60%
- `full`: 100%

## Release checklist

1. Deploy code once to all target servers.
2. Confirm DB migrations are complete.
3. Create/update flag in rollout engine.
4. Start in `canary` with low percentage.
5. Monitor errors, latency, and support alerts.
6. Promote to `wave1`, then `wave2`, then `full`.
7. If issues appear, disable via override or set default OFF.

## Emergency rollback

1. Disable affected flag at global level OR
2. Disable country/tenant overrides for impacted scope.
3. Verify API enforcement (dashboard/accounting/audit) reflects disabled state.
4. Record incident note in Admin Control Center event/audit logs.

## Required guardrails

- All country-specific behavior must be behind a rollout flag.
- High-impact write paths must enforce flags server-side (not UI-only).
- Use tenant override only for targeted hotfixes; remove stale overrides after stabilization.

## Minimum monitoring signals

- flag stage distribution (canary/wave1/wave2/full)
- override count
- failed queries (1h)
- safety warnings (1h)
- support alerts trend

## Naming convention

- Use stable, namespaced keys:
  - `control.dashboard.enable_all_agencies_audit`
  - `control.accounting.enable_write_actions`
  - `invoice.sa.zatca_enabled`
