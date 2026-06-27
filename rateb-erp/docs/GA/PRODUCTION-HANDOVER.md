# RATIB ERP v1.0 — Production Handover

**Handover date:** 2026-06-27  
**Version:** 1.0.0  
**Production host:** `https://rateb.sa`

---

## System URLs

| System | URL |
|--------|-----|
| **ERP Admin** | https://rateb.sa/rateb-erp/public/admin |
| **ERP Login** | https://rateb.sa/rateb-erp/public/login |
| **Company Portal** | https://rateb.sa/site/portal |
| **Control Panel** | https://rateb.sa/control-panel/ |
| **Marketing site** | https://rateb.sa/site/ |
| **Health endpoint** | https://rateb.sa/rateb-erp/public/erp-health.php |
| **Security certificate** | https://rateb.sa/rateb-erp/public/erp-security-cert.php?enterprise=1 |
| **Build marker** | https://rateb.sa/rateb-erp/public/ratib-erp-build.txt |
| **Enterprise cert runner** | https://rateb.sa/rateb-erp/public/enterprise-cert-run.php *(token-gated)* |

---

## ERP

| Field | Value |
|-------|-------|
| Server path | `/home/admin/domains/rateb.sa/public_html/rateb-erp` |
| Database | `admin_rateb-erp` |
| PHP | 8.3.31 |
| Build | `rateb-erp-ga-security-20260626` |

---

## Control Panel

| Field | Value |
|-------|-------|
| Path | `/home/admin/domains/rateb.sa/public_html/control-panel/` |
| Database | `admin_control_panel_db` |
| ERP migrate bridge | `control-panel/api/control/rateb-erp-migrate-run.php` |

---

## Health endpoint

```
GET https://rateb.sa/rateb-erp/public/erp-health.php
```

Expected response: HTTP 200, body `{"status":"ok"}`

Token-gated administrative probes use `X-Rateb-Health-Token` or `X-Rateb-Migrate-Token` (same secret as migrations).

---

## Security certificate

```
GET https://rateb.sa/rateb-erp/public/erp-security-cert.php?enterprise=1
```

Expected: `critical: 0`, `high: 0`, `enterprise_suite.failed: 0` (31/31 PASS).

Reset dry-run preview (read-only):

```
GET https://rateb.sa/rateb-erp/public/erp-security-cert.php?enterprise=1&reset_dry_run=1
```

---

## Backup procedure

### Manual backup

```bash
cd /home/admin/domains/rateb.sa/public_html/rateb-erp
php bin/erp-backup.php
```

Expected output: `Backup: .../storage/backups/erp-admin_rateb-erp-{Ymd-His}.sql.gz`

### Artifacts

| File | Contents |
|------|----------|
| `erp-{db}-{timestamp}.sql.gz` | Full MySQL dump (gzip) |
| `erp-files-{timestamp}.tar.gz` | `storage/uploads/` archive (when uploads exist) |

### Location

```
/home/admin/domains/rateb.sa/public_html/rateb-erp/storage/backups/
```

### Latest certified backup (GA closeout)

| Artifact | SHA256 |
|----------|--------|
| `erp-admin_rateb-erp-20260627-024200.sql.gz` | `0474aea7bbd91f58ce32612544423d6e43aa1908116c0095dab71fed61f3aefb` |
| `erp-files-20260627-024201.tar.gz` | `e1a0f49f14c8e4def4d0c3f04eacf76e5de8f2fa20da0812196964a8da7b53a3` |

### Token-gated HTTP backup (alternative)

```bash
curl -X POST "https://rateb.sa/rateb-erp/public/enterprise-cert-run.php" \
  -H "X-Rateb-Migrate-Token: $RATEB_ERP_MIGRATE_TOKEN" \
  -d "action=backup"
```

---

## Restore procedure

### Verify backup integrity

```bash
cd /home/admin/domains/rateb.sa/public_html/rateb-erp
php bin/erp-restore.php --verify storage/backups/erp-admin_rateb-erp-YYYYMMDD-HHMMSS.sql.gz
```

**Note:** MariaDB 10.11 dumps may fail official `--verify` (512-byte false negative). Confirm with extended manual check or restore drill if verify fails:

```bash
zcat storage/backups/erp-admin_rateb-erp-*.sql.gz | head -30
zcat storage/backups/erp-admin_rateb-erp-*.sql.gz | grep -c 'CREATE TABLE'
```

### Full restore (disaster recovery only)

```bash
cd /home/admin/domains/rateb.sa/public_html/rateb-erp
php bin/erp-restore.php storage/backups/erp-admin_rateb-erp-YYYYMMDD-HHMMSS.sql.gz
```

### Restore uploads (if needed)

```bash
tar -xzf storage/backups/erp-files-YYYYMMDD-HHMMSS.tar.gz -C storage/
```

### Post-restore validation

```bash
php bin/enterprise-test/run.php --json
curl -s https://rateb.sa/rateb-erp/public/erp-health.php
```

---

## Rollback procedure

1. **Identify last known-good backup** in `storage/backups/`.
2. **Verify** dump integrity (see restore procedure).
3. **Stop cron** temporarily to prevent concurrent writes:
   ```bash
   # Comment erp-cron and erp-backup lines in crontab
   ```
4. **Restore database** from `.sql.gz` (see above).
5. **Restore uploads** from `erp-files-*.tar.gz` if applicable.
6. **Rollback code** via GitHub Actions fast deploy to previous commit/build marker if code regression suspected.
7. **Run enterprise tests** and health check.
8. **Re-enable cron**.

Rollback target for GA closeout: `erp-admin_rateb-erp-20260627-024200.sql.gz`

---

## Daily backup schedule

Recommended cron (adjust path if account layout differs):

```
0 2 * * * /usr/local/bin/php /home/admin/domains/rateb.sa/public_html/rateb-erp/bin/erp-backup.php >> /home/admin/logs/erp-backup.log 2>&1
```

Retention is controlled by `AutomationSettings::backupRetentionDays()`.

---

## Weekly verification

| Task | Command / check |
|------|-----------------|
| Latest backup exists | `ls -lh storage/backups/erp-*.sql.gz \| tail -3` |
| Backup size reasonable | Compare to prior week (expect >50 KB for schema+data) |
| Verify dump header | `zcat latest.sql.gz \| head -20` — expect MariaDB dump header |
| Health endpoint | `curl -s https://rateb.sa/rateb-erp/public/erp-health.php` |
| Enterprise probe | `curl -s '.../erp-security-cert.php?enterprise=1'` — 31/31 PASS |
| Cron health | Admin → Automation Health / Queue Monitor |

---

## Monthly restore drill

1. Select latest `.sql.gz` from `storage/backups/`.
2. Import to **isolated scratch database** (not production).
3. Run `php bin/enterprise-test/run.php --json` against scratch DB.
4. Confirm **31/31 PASS**.
5. Drop scratch `rateb_*` tables.
6. Record: filename, duration, table count, enterprise result, operator initials.

GA closeout reference drill: restore to `admin_designed` scratch — **143 tables**, **31/31 PASS**, **1 sec**.

---

## Operator responsibilities

| Role | Responsibility |
|------|----------------|
| **DBA / Ops** | Daily backups, weekly verify, monthly restore drill, disk space on `storage/backups/` |
| **DevOps** | Deploy via GitHub Actions; confirm build marker after each push |
| **Security** | Review `erp-security-cert.php` monthly; rotate migrate token on schedule |
| **Support** | Monitor Automation Health, Queue Monitor, Login Activity |
| **Product** | Approve production reset separately if pre-GA data wipe required |

### Super-admin accounts (preserved)

- `admin@rateb.sa`
- `ahmedashrafabdalmonem77@gmail.com`

### Cron jobs

```
*/15 * * * * php .../rateb-erp/bin/erp-cron.php       # queue, alerts
0  2 * * *   php .../rateb-erp/bin/erp-backup.php     # daily backup
```

---

## Disaster recovery checklist

| Step | Action | Done |
|------|--------|:----:|
| 1 | Confirm incident scope (DB corruption, data loss, host failure) | ☐ |
| 2 | Notify stakeholders | ☐ |
| 3 | Stop writes (disable cron, maintenance mode if available) | ☐ |
| 4 | Identify latest valid backup + files archive | ☐ |
| 5 | Verify backup (`--verify` or manual header check) | ☐ |
| 6 | Restore database from `.sql.gz` | ☐ |
| 7 | Restore uploads from `erp-files-*.tar.gz` | ☐ |
| 8 | Run `php bin/enterprise-test/run.php --json` | ☐ |
| 9 | Verify health endpoint + admin login | ☐ |
| 10 | Re-enable cron; monitor for 24 hours | ☐ |
| 11 | Post-incident report | ☐ |

**RTO target:** Restore drill completed in **1 second** (DB import) + validation ~5 minutes.  
**RPO target:** Daily backup at 02:00 — maximum 24-hour data window.

---

*RATIB ERP v1.0 Production Handover — operator reference. No production changes during GA closeout.*
