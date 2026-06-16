# Production verification checklist — Infrastructure Marketplace

Run after deploy + migrations on **control panel database**.

## 1. Migrations applied

- [ ] `SHOW TABLES LIKE 'rateb_infra_%';` includes:
  - `rateb_infra_provider_activations`
  - `rateb_infra_provider_secrets`
  - `rateb_infra_provider_events`
- [ ] `php modules/infrastructure-marketplace/Cli/production-verify.php` → `overall` is `PASS` or `WARN` (not `FAIL`)

## 2. Secret encryption

- [ ] `RATEB_INFRA_SECRET_KEY` set in server env (32+ char random; not in git)
- [ ] `production-verify.php` → `secret_encryption` = `PASS`
- [ ] Sample: `SELECT encrypted_value FROM rateb_infra_provider_secrets LIMIT 1;` — value is base64 JSON envelope, not readable API key

## 3. Provider activations

- [ ] Control Panel → Infrastructure → Providers loads without PHP errors
- [ ] At least one **registrar** row enabled (e.g. Namecheap adapter class)
- [ ] Infrastructure → Control → Namecheap credentials + **Module enabled**

## 4. Health monitor

- [ ] Cron active: `provider-health-monitor.php` every 5 minutes
- [ ] `rateb_infra_provider_events` receives `health_check` rows after cron runs
- [ ] Providers UI shows health summary (not ParseError)

## 5. Provider events

- [ ] Trigger a test action (domain search or hosting step) — new rows in `rateb_infra_provider_events`
- [ ] Rows include `request_id`, `duration_ms` where applicable
- [ ] `rateb_infra_audit_entries` still receives orchestration audits (unchanged)

## 6. Cron active

- [ ] Health monitor cron in crontab
- [ ] Retention cron daily (`provider-events-retention.php`)
- [ ] Worker process running (Supervisor preferred, or cron fallback)
- [ ] Log files writable under `/home/admin/logs/` (or your path)

## 7. DB indexes

- [ ] `SHOW INDEX FROM rateb_infra_provider_events;` includes:
  - `idx_rateb_infra_provider_events_provider`
  - `idx_rateb_infra_provider_events_created`
- [ ] `production-verify.php` → `provider_events_indexes` = `PASS`

## 8. No plaintext secrets

- [ ] No API keys in `rateb_infra_provider_secrets.encrypted_value` (verify sample)
- [ ] Runtime Namecheap fields in Control panel file / env — not copied into public repo
- [ ] `production-verify.php` → `no_plaintext_secrets_sample` = `PASS`

## 9. Throttling / retention (hardening)

- [ ] Optional env tuned if needed:
  - `RATEB_INFRA_HEALTH_LOG_MAX_PER_MINUTE` (default 12)
  - `RATEB_INFRA_FAILURE_LOG_MAX_PER_MINUTE` (default 30)
  - `RATEB_INFRA_EVENTS_RETAIN_HEALTH_DAYS` (default 7)
- [ ] Retention dry-run: `php .../provider-events-retention.php --dry-run`
- [ ] Failure rows (`failed`/`retry`/`degraded`) are **not** deleted by retention

## 10. Public marketing smoke test

- [ ] `https://rateb.sa/pages/home.php?density=full#domains` — search returns results when Namecheap active
- [ ] Help nav goes to contact section (not Rateb Pro login)

## Sign-off

| Role | Name | Date | Result |
|------|------|------|--------|
| Operator | | | |
| Technical | | | |
