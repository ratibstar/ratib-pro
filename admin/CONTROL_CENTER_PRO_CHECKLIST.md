# Admin Control Center - Pro Usage Checklist

This guide explains how to use the full Admin Control Center safely and effectively.

## Quick Navigation Checklist

Use this checklist as your click map. Click any item to jump to its explanation.

- [ ] [1) Access and Safety Rules](#1-access-and-safety-rules)
- [ ] [2) Overview Panel](#2-overview-panel)
- [ ] [3) Tenant Control (Create / Edit / Suspend / Activate / Delete)](#3-tenant-control-create--edit--suspend--activate--delete)
- [ ] [4) Database Control Panel (Test / Migration / Rebuild)](#4-database-control-panel-test--migration--rebuild)
- [ ] [5) Query Console (Execution Engine)](#5-query-console-execution-engine)
- [ ] [6) Gateway Policies](#6-gateway-policies)
- [ ] [7) Safety Events](#7-safety-events)
- [ ] [8) Logs Explorer](#8-logs-explorer)
- [ ] [9) System Flags](#9-system-flags)
- [ ] [10) Emergency Controls](#10-emergency-controls)
- [ ] [11) Live Auto Refresh](#11-live-auto-refresh)
- [ ] [12) Daily Operating Checklist (Recommended)](#12-daily-operating-checklist-recommended)
- [ ] [13) Troubleshooting Checklist](#13-troubleshooting-checklist)

---

## 1) Access and Safety Rules

Before doing anything:

- Confirm you are on `admin/control-center.php`.
- Confirm banner shows: `ADMIN CONTROL MODE — ACTIONS ARE LIVE AND EFFECT SYSTEM-WIDE`.
- Use this page only with admin/super-admin accounts.
- Assume every write action is production-impacting.

Golden rule:

- Read operations are safe by default.
- Write/destructive operations require deliberate confirmation and typed keywords.

---

## 2) Overview Panel

What it gives you:

- `Total Tenants`: quick tenant count.
- `System Mode`: current enforcement mode (`SAFE` vs `STRICT` behavior in stack).
- `Gateway Decisions`: policy decisions captured recently.
- `Safety Events`: count of security/safety signals.

How to use:

- Start here every session to understand current system posture.
- If Safety Events is high, investigate before running any destructive action.

---

## 3) Tenant Control (Create / Edit / Suspend / Activate / Delete)

### Create Tenant

Fields:

- `Tenant Name`
- `Domain`
- `Database Name` (optional at creation, recommended)
- `DB Host` (optional if default host is used)
- `DB User`
- `DB Password`
- `Status` (`provisioning`, `active`, `suspended`)

Steps:

1. Fill tenant fields.
2. Click `Create Tenant`.
3. Verify row appears in tenant table.

### Edit Tenant

1. Click `Edit`.
2. Update tenant metadata.
3. Save and verify changes in row.

### Suspend / Activate

1. Click `Suspend` or `Activate`.
2. Complete confirmation modal (typed keyword when required).
3. Verify status badge updates.

### Delete

1. Click `Delete`.
2. Confirm and type required keyword.
3. Ensure you intend permanent removal before confirming.

Best practice:

- Prefer suspend over delete for operational incidents.

---

## 4) Database Control Panel (Test / Migration / Rebuild)

### Test Connection

Purpose:

- Verifies tenant DB credentials and DB reachability.

Use:

1. Click `Test Connection`.
2. Wait for status result.
3. Fix DB config if failure is shown.

### Run Migration

Purpose:

- Triggers/logs migration workflow for tenant schema updates.

Use:

1. Click `Run Migration`.
2. Confirm completion message/log entry.

### Rebuild Schema (Danger)

Purpose:

- High-risk operation for schema recovery/re-initialize flows.

Use:

1. Click `Rebuild Schema`.
2. Review modal fields (`Action`, `Tenant ID`, `Required Text`).
3. Type required keyword exactly (for example `REBUILD`).
4. Confirm only when sure.

---

## 5) Query Console (Execution Engine)

Execution modes:

- `SAFE`: default mode for non-destructive checks.
- `STRICT`: stricter policy checks (tenant-aware validation).
- `SYSTEM`: required for write-level system queries.

Recommended safe queries:

```sql
SELECT id, name, domain, status, created_at
FROM tenants
ORDER BY id DESC
LIMIT 20;
```

```sql
SELECT 1 AS ok;
```

Write query protection:

- Non-read queries require `SYSTEM` mode.
- UI requests explicit confirmation before execution.

Checklist before running SQL:

- Correct tenant context selected.
- Correct execution mode selected.
- Query reviewed for impact.

---

## 6) Gateway Policies

What it shows:

- Query policy outcomes such as allowed/blocked/warned decisions.
- Reason codes and tenant targeting.

How to use:

- Use search/status/tenant filters to isolate policy issues.
- Validate why a query was blocked before retrying.

---

## 7) Safety Events

What it shows:

- Security and safety signals (tenant-scope warnings, strict-mode blocks, allowlist bypass traces).

How to use:

- Treat repeated events as hardening signals.
- Correlate with Gateway Policies and Logs Explorer for root-cause analysis.

---

## 8) Logs Explorer

Filters:

- Keyword
- Log level
- Tenant ID
- Pagination

How to use:

1. Filter by tenant to investigate isolated incidents.
2. Filter by level (`warn` / `error`) for urgent issues.
3. Track action outcomes after every critical operation.

---

## 9) System Flags

What it shows:

- Runtime flags such as strict mode and context enforcement.
- Read-only visibility for operational awareness.

How to use:

- Confirm flag posture before sensitive changes and debugging.

---

## 10) Emergency Controls

Purpose:

- Last-resort operational controls (maintenance, disable-all, kill-query, gateway lock).

How to use safely:

1. Use only for incident response.
2. Complete double confirmation flow.
3. Document why action was executed.
4. Verify system state immediately after execution.

---

## 11) Live Auto Refresh

What it does:

- Refreshes dashboard data periodically.
- Pauses while typing to prevent interrupting form input.

How to use:

- Enable during monitoring.
- Disable during long editing/debug sessions if needed.

---

## 12) Daily Operating Checklist (Recommended)

- [ ] Open Control Center and verify admin banner.
- [ ] Review Overview counters.
- [ ] Check Safety Events and Gateway Policies.
- [ ] Validate tenant DB health using Test Connection.
- [ ] Run only required SQL in correct mode.
- [ ] Review events after every high-impact action.
- [ ] Avoid Rebuild/Emergency unless incident requires it.

---

## 13) Troubleshooting Checklist

### Buttons not clickable or odd popup behavior

- Hard refresh (`Ctrl+Shift+R`).
- Ensure latest assets are loaded from `control-center-assets.php`.

### 403 Forbidden

- Verify admin session and permissions.
- Verify control center enable flags for environment/host.

### Tenant table/query errors

- Confirm you are in `System Context` for control-plane `tenants` queries.
- Confirm `tenants` table exists in control DB.

### DB test fails

- Verify `database_name`, `db_user`, `db_password`, and `db_host`.
- Confirm DB user has required privileges.

---

## Notes for Admin Teams

- Keep this file updated when controls or policies change.
- Pair this operational guide with your incident-response SOP.

---

## 14) Observability Architecture

- `system_events` is the single source of truth for operational observability.
- `request_id` is mandatory and enables cross-service tracing for each request.
- Event `level` defines severity (`info`, `warn`, `error`, `critical`).
- `metadata` stores structured context for filtering, diagnostics, and forensics.
