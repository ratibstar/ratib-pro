# Production migration rollout (Infrastructure Marketplace)

Target database: **control panel DB** (`CONTROL_PANEL_DB_NAME`, e.g. `outratib_control_panel_db`).

Do **not** run `007_drop_ratib_infra_from_outratib_out.sql` on the control panel database.

## Downtime / locking expectations

| Step | Risk | Notes |
|------|------|--------|
| `001`–`003`, `005`, `006`, `008` | **Low** | `CREATE TABLE IF NOT EXISTS` only — online-safe. |
| `004_state_machine_normalization.sql` | **Low–medium** | `UPDATE` on `ratib_infra_provisioning_jobs` — brief row locks; run off-peak if table is large. |
| Full bundle `ALL_for_outratib_control_panel_db.sql` | **Low** | Same as above; safe for empty or partially-migrated DBs. |

No `DROP TABLE` in the production path.

## Recommended order (incremental)

1. `001_foundation.sql`
2. `002_operational_layer.sql`
3. `003_execution_layer.sql`
4. `004_state_machine_normalization.sql` *(only if `ratib_infra_provisioning_jobs` already exists)*
5. `005_provider_activation_marketplace.sql`
6. `006_release_safety.sql`
7. `008_provider_secrets_and_events.sql`

## Commands

### Option A — shell script (recommended)

```bash
export RATIB_INFRA_MYSQL_HOST=127.0.0.1
export RATIB_INFRA_MYSQL_PORT=3306
export RATIB_INFRA_MYSQL_USER=your_mysql_user
export RATIB_INFRA_MYSQL_PASS=your_mysql_password
export RATIB_INFRA_MYSQL_DB=outratib_control_panel_db

bash scripts/run-infra-migrations-safe.sh
```

### Option B — single bundle

```bash
mysql -h127.0.0.1 -uUSER -p outratib_control_panel_db \
  < modules/infrastructure-marketplace/Migrations/ALL_for_outratib_control_panel_db.sql
```

### Option C — phpMyAdmin

Import `ALL_for_outratib_control_panel_db.sql` into the control panel database only.

## Post-migration verify

```bash
php modules/infrastructure-marketplace/Cli/production-verify.php
```

Expect `overall: PASS` or `WARN` (WARN if `RATIB_INFRA_SECRET_KEY` not set yet).

## Rollback notes

Infrastructure DDL is **additive**. Rollback is operational, not a single undo:

1. **Disable module** — Control Panel → Infrastructure → Control → uncheck *Module enabled*; set kill-switch if needed.
2. **Stop cron** — remove health/retention cron entries (see `Docs/cron/infrastructure-marketplace.cron.example`).
3. **Optional table rollback** — only if you must remove new tables and accept data loss:
   - `DROP TABLE IF EXISTS ratib_infra_provider_events;`
   - `DROP TABLE IF EXISTS ratib_infra_provider_secrets;`
   - Do **not** drop `ratib_infra_audit_entries` or `ratib_infra_provider_activations` if production activations exist.
4. **Restore secrets** — re-import from env/runtime file if encrypted rows were dropped.
5. **Redeploy previous code** — checkout prior build marker via git deploy.

There is no automatic down-migration script by design (avoids accidental production data loss).
