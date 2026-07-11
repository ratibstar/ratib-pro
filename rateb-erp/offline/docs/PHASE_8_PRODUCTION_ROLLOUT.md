# Phase 8 — Enterprise Offline Production Rollout

**Date:** 2026-07-11  
**Mode:** Operations planning only — **no new features**  
**Official production target:** `https://rateb.sa/rateb-erp/public/admin/`  
**Production filesystem:** `/home/admin/domains/rateb.sa/public_html/rateb-erp` (same inode as `/home/admin/public_html/rateb-erp`)  
**Production database:** `admin_rateb-erp`  
**Do not use:** `dev.rateb.sa` / `admin_rateb_dev` for production pilot decisions  
**Prerequisite:** Phase 7.1 hardening complete (H-BRANCH / H-DEVICE / H-AUTHZ closed)  
**Certification posture:** CONDITIONAL GO — dormant deploy allowed; enablement gated by checklist below  
**Rule:** All `RATEB_OFFLINE_*` remain **OFF** until each stage is signed off

---

## 1. Pilot rollout plan

### Goal
Prove offline sync on a **single low-risk company** (or one branch of a multi-branch company) before wider enablement.

### Pilot selection criteria
- 1 company, preferably **single-branch** or one designated branch
- Low transaction volume (Inv movements / HR attendance / Proc drafts)
- Staff with `pos.sync.manage` and `pos.devices.manage` available
- Devices can be registered and **activated** before any push
- Willing to run online-only fallback if flags are turned OFF

### Pilot phases (calendar)

| Day | Stage | Action |
|----:|-------|--------|
| 0 | Deploy dormant | Ship code + migrations **175–179**; **no** flags |
| 1 | Monitoring only | `RATEB_OFFLINE_MONITORING=1`; verify ops dashboard |
| 2 | Device registry | Register + activate pilot devices; confirm ACTIVE-only push gate |
| 3–4 | Master + Inv | Enable master + inventory for pilot company only (env or process-local soak first) |
| 5–6 | HR | Enable HR attendance if Inv stable |
| 7–8 | Procurement | Enable procurement drafts if HR stable |
| 9+ | POS complete | Only if `modules/pos` present and POS soak done |
| 10–14 | Observe | 24h+ watch; no expansion |
| 15 | Go / hold | Expand canary **or** freeze / rollback flags |

### Pilot exit criteria
- Queue healthy (failed < 25, depth < 500, open conflicts < 20)
- Success rate 24h ≥ 95% (or documented baseline)
- Zero Critical/High incidents
- Rollback drill completed (flag OFF) without data loss of online ERP

---

## 2. Canary deployment strategy

### Layers

```
Code deploy (flags OFF)
    → Monitoring canary (ops visibility)
        → Company canary (1 pilot tenant)
            → Module canary (Inv → HR → Proc → POS)
                → Branch expansion (if multi-branch)
                    → Fleet expansion (more companies)
```

### Canary rules
1. **Never** enable master on all production companies at once.
2. Prefer **env flags on a dedicated canary host** or company-scoped ops window before global `.env`.
3. If only global env is available: enable monitoring globally first; enable master only during a controlled window with on-call present; disable immediately on alert breach.
4. Canary traffic: ≤ 5% of offline-capable terminals (or 1–3 devices).
5. Hold canary ≥ **72 hours** after each module flag before the next module.
6. Dual-path awareness: POS sync (`rateb_pos_sync_*`) ≠ enterprise queue (`rateb_offline_*`). Treat as separate canaries.

### Canary abort triggers
- `MIGRATION_REQUIRED` or table errors
- Queue failed ≥ 25 or depth ≥ 500
- Open conflicts ≥ 20 sustained > 1 hour
- Cross-branch / wrong-company data suspected
- Device gate false positives blocking all pilot devices (ops misconfig) — fix activation, do not disable gate

---

## 3. Feature flag rollout sequence

| Order | Flag | Env | Effect | Depends on |
|------:|------|-----|--------|------------|
| 0 | *(none)* | — | Code + schema only | Deploy + backup |
| 1 | `offline.monitoring` | `RATEB_OFFLINE_MONITORING=1` | Ops dashboards / monitoring API | Migrations present; `pos.sync.manage` |
| 2 | `offline.enabled` | `RATEB_OFFLINE_ENABLED=1` | Master — push/process/replay allowed | Monitoring green; ACTIVE devices |
| 3 | `offline.inventory.movements` | `RATEB_OFFLINE_INVENTORY_MOVEMENTS=1` | Inv Tier-1 replay | Master ON |
| 4 | `offline.hr.attendance` | `RATEB_OFFLINE_HR_ATTENDANCE=1` | HR attendance / leave drafts | Master ON; Inv stable (recommended) |
| 5 | `offline.procurement` | `RATEB_OFFLINE_PROCUREMENT=1` | PR/RFQ/PO drafts | Master ON; Inv/HR stable |
| 6 | `offline.pos.complete` | `RATEB_OFFLINE_POS_COMPLETE` | POS bridge completeness (default **true** in config; review before master ON) | POS module + device activation |
| — | `offline.read_cache` | `RATEB_OFFLINE_READ_CACHE` | **Do not enable** in Phase 8 (not certified) | — |

### Disable order (fastest safe stop)
1. Turn OFF module flags (Proc → HR → Inv)
2. Turn OFF master (`RATEB_OFFLINE_ENABLED`)
3. Leave **monitoring ON** to drain visibility / pending alerts
4. Only if schema must be removed: follow rollback playbook (last resort)

### Authz reminder (7.1)
- Push **enqueues** for authenticated company + ACTIVE device
- Company-wide **replay/process** requires Sync Manage (`pos.sync.manage` / module abilities)
- Push does **not** auto-process unless caller has Sync Manage

---

## 4. Monitoring thresholds

Surfaces: company `offline/ops` · `GET /api/v1/offline/monitoring*`

| Metric | Healthy | Watch | Breach |
|--------|---------|-------|--------|
| Queue depth (pending+failed) | < 100 | 100–499 | ≥ 500 |
| Failed items | < 10 | 10–24 | ≥ 25 |
| Open conflicts | < 10 | 10–19 | ≥ 20 |
| High-retry (≥3) count | < 5 | 5–9 | ≥ 10 |
| Success rate 24h | ≥ 98% | 95–98% | < 95% |
| Synced last hour | > 0 when backlog | idle with backlog | backlog + 0 synced > 30m |
| Stale active devices (7d) | 0 | 1–4 | ≥ 5 |
| Migration / tables | present | — | missing |

**Ops cadence:** check dashboard at T+15m, T+1h, T+4h after each flag change; then 2× daily during pilot; daily during canary.

---

## 5. Alert thresholds

Aligned with `OfflineMonitoringService` (in-dashboard; no pager channel yet — poll ops UI / API).

| Code | Severity | Condition | Response |
|------|----------|-----------|----------|
| `MIGRATION_REQUIRED` | critical | Offline tables missing | Stop enablement; restore migrations; do not enable master |
| `QUEUE_FAILED_HIGH` | high | failed ≥ 25 | Pause module flags; inspect replay errors; process with Sync Manage only |
| `QUEUE_DEPTH_HIGH` | high | depth ≥ 500 | Scale process batches; check worker; consider master OFF if growing |
| `CONFLICTS_OPEN` | medium | open ≥ 20 | Triage conflict dashboard; resolve with Sync Manage |
| `RETRY_HOTSPOTS` | medium | retry≥3 items ≥ 10 | Inspect hotspots by module.action |
| `STALE_DEVICES` | low | active unseen ≥7d ≥5 | Revoke or re-activate devices |
| `MASTER_OFF_WITH_PENDING` | medium | master OFF + pending > 0 | Expected after emergency OFF — drain via process after re-enable or accept backlog |

**Escalation:** Critical/High → on-call + flag freeze within 15 minutes.

---

## 6. Rollback playbook

### R0 — Instant (preferred): flag kill-switch
```env
# Remove or set to 0/false
RATEB_OFFLINE_PROCUREMENT=0
RATEB_OFFLINE_HR_ATTENDANCE=0
RATEB_OFFLINE_INVENTORY_MOVEMENTS=0
RATEB_OFFLINE_ENABLED=0
# Keep monitoring for visibility:
RATEB_OFFLINE_MONITORING=1
```
- Effect: push rejected (`offline_disabled`); no new replay; online ERP unchanged
- Client queues retain rejected/conflict/pending (ZDL); do not clear IndexedDB manually
- **Time target:** < 5 minutes

### R1 — Stop processing
- Do not call `/api/v1/offline/process` except Sync Manage incident response
- Revoke compromised devices (`status=revoked`)

### R2 — Code rollback
- Redeploy previous release artifact (GitHub Actions / cPanel deploy)
- Keep flags OFF
- Confirm `/api/v1/offline/status` shows disabled

### R3 — Schema rollback (staging-proven; **production last resort**)
- Script reference: `offline/docs/rollback-offline-175-179.sql`
- **Before:** full DB backup; export `rateb_offline_*` if forensic keep needed
- Drops: conflicts, queue, cursors, devices
- Does **not** reverse WebAuthn columns from migration 178 (safe to keep)
- **Never** run against production without explicit change window + dual approval
- After drop: remove `RATEB_OFFLINE_*` from `.env`; redeploy if needed

### Rollback decision tree
```
Incident?
  → Data corruption / wrong tenant? → R0 immediately → investigate → R1 devices
  → Bad release only? → R0 + R2
  → Schema broken / migration failure? → R0 → backup → R3 (approved)
```

---

## 7. Disaster recovery playbook

### Scenarios

| Scenario | Detection | Recovery |
|----------|-----------|----------|
| Power/network loss mid-sync | Client pending remains; server idempotency | Reconnect; push; process (Sync Manage); clearable keys only |
| Browser refresh mid-flush | Atomic `removeMany` (4.5.1) | Queue intact or partial clearable removed; re-push duplicates OK |
| DB outage | API 5xx / migration errors | Flags OFF; online ERP; restore DB from backup |
| Bad replay batch | Spike failed/conflicts | Master OFF; fix; re-enable; process remaining |
| Device compromise | Unexpected device_id activity | Revoke device; rotate API tokens; review queue by device |
| Accidental schema drop | `MIGRATION_REQUIRED` | Restore backup; re-apply 175–179; flags OFF until verify |

### Backup requirements (before enablement)
- Full DB backup labeled `offline-pre-enable-YYYYMMDD`
- Confirm restore tested on staging within last 30 days
- Export pilot company offline rows (optional): queue, conflicts, devices

### RPO / RTO (operational targets)
- **RPO (offline queue):** last successful push (client retains until clearable ack)
- **RTO (flag kill):** ≤ 5 minutes
- **RTO (DB restore):** per existing ERP DR (not offline-specific)

### Dual-path note
POS offline and enterprise offline recover independently. Disabling enterprise master does not stop POS sync paths if POS flags/services remain active — coordinate POS ops separately.

---

## 8. Operations handbook

### Daily (while enabled)
1. Open `offline/ops` (or monitoring API overview)
2. Confirm no Critical/High alerts
3. Note depth, failed, open conflicts, synced 24h
4. Spot-check stale devices

### After each flag change
1. Record time, operator, flag, company/scope
2. Watch 15m / 1h / 4h
3. Run one known-good push + process (Sync Manage) on pilot device
4. Confirm idempotent re-push → duplicate/conflict, not double-write

### Device lifecycle
1. Register device → `pending`
2. Activate → `active` (required for enterprise push)
3. Heartbeat / last_seen via normal use
4. Revoke → `revoked` (push denied)

### Process / conflicts
- `/api/v1/offline/process` — Sync Manage only
- Resolve conflicts via Sync Manage; prefer server-authoritative when unsure
- Never delete client queue wholesale

### Permissions
- Ops dashboard: `pos.sync.manage`
- Devices: `pos.devices.manage`
- API tokens: abilities `pos` / `inventory` / `hr` / `procurement` (or unrestricted)

---

## 9. Support handbook

### Common tickets

| Symptom | Likely cause | Action |
|---------|--------------|--------|
| Push 403 `offline_disabled` | Master OFF | Expected if not rolled out; escalate only if should be ON |
| Push 403 `device_unknown` / `device_denied` | Not registered / not ACTIVE | Activate or re-register; do not bypass gate |
| Push 403 `branch_denied` | User lacks branch / bad branch_id | Fix branch access; send validated top-level `branch_id` only |
| Items stuck pending | No process / no Sync Manage auto-process | Authorized process; check module flags |
| Conflict on re-push | Idempotency working | Do not clear client conflict without review |
| Monitoring 403 | Flag OFF or no Sync Manage | Enable monitoring or grant permission |
| Wrong module skipped | Flag OFF for that module | Expected; enable only per sequence |

### What support must NOT do
- Disable device activation checks
- Run production schema rollback without approval
- Tell users to clear IndexedDB / wipe queue
- Enable `offline.read_cache`
- Approve payroll / payments / accounting via offline (not in scope)

### Severity for support
- **P1:** Cross-company data suspicion → R0 + security
- **P2:** Queue depth/failed breach → flag freeze + ops
- **P3:** Single device activation issues
- **P4:** How-to / monitoring access

---

## 10. Final Production Acceptance Checklist

### A. Pre-deploy (dormant)
- [ ] Phase 7.1 hardening merged (branch / device / authz)
- [ ] Offline suites green (Foundation, Durability, Inv, HR, Proc, Monitoring, Hardening, Phase 4.5 gate)
- [ ] Production backup taken and labeled
- [ ] Rollback SQL reviewed; production run requires dual approval
- [ ] `modules/pos` presence confirmed if POS in scope
- [ ] No `RATEB_OFFLINE_*` in production `.env` yet (or all 0)

### B. Deploy
- [ ] Code + `public/assets/offline/*` deployed
- [ ] Migrations **175–179** applied
- [ ] `OfflineModule` boot + offline API/web routes present
- [ ] Smoke: status endpoint / app loads; behavior unchanged with flags OFF

### C. Monitoring canary
- [ ] `RATEB_OFFLINE_MONITORING=1`
- [ ] Ops dashboard loads for Sync Manage user
- [ ] Readiness / queue / devices visible; no false `MIGRATION_REQUIRED`

### D. Pilot enablement
- [ ] Pilot company + devices ACTIVE
- [ ] Master ON for pilot window only
- [ ] Inv → (soak) → HR → (soak) → Proc per sequence
- [ ] Push without Sync Manage does **not** company-wide process
- [ ] Push with non-ACTIVE device rejected
- [ ] Payload `branch_id` cannot override queue branch (spot check)
- [ ] 72h hold per module; alerts within thresholds

### E. Production acceptance sign-off
- [ ] 24h continuous observation without High/Critical breach
- [ ] Success criteria met (below)
- [ ] Rollback drill (R0) executed once in pilot
- [ ] Ops + Support handbooks acknowledged
- [ ] Owner sign-off: Engineering · Ops · Product

**Accept:** CONDITIONAL production use for signed pilot/canary scope  
**Reject / hold:** Any Critical, unresolved High, or checklist gap

---

## Risk assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Premature global master ON | Med | High | Sequence + canary; R0 kill-switch |
| Pending backlog after master OFF | Med | Med | Keep monitoring; controlled re-process |
| Device not activated blocking pilot | Med | Low | Pre-activate devices; support handbook |
| Dual POS/enterprise confusion | Med | Med | Separate canaries; ops handbook |
| Schema rollback on prod | Low | Critical | R3 last resort; backup first |
| Optional branch weak isolation | Med | Med | Prefer single-branch pilot; always send branch_id |
| No external pager | High | Med | Scheduled ops polls; API alerts endpoint |
| Proc/POS soak gaps | Med | Med | Hold those modules until soak done |

---

## Rollback strategy (summary)

1. **Primary:** Flag kill-switch (R0) — seconds to minutes  
2. **Secondary:** Stop process + revoke devices (R1)  
3. **Tertiary:** Code redeploy previous build (R2)  
4. **Last resort:** Schema drop via approved rollback SQL after backup (R3)

Client ZDL preserved: never instruct mass local queue wipe.

---

## Success criteria

| Criterion | Target |
|-----------|--------|
| Critical findings in pilot window | 0 |
| High alert sustained > 1h | 0 |
| Queue depth | < 500 (prefer < 100 steady) |
| Failed items | < 25 (prefer < 10) |
| Open conflicts | < 20 (prefer < 10) |
| Success rate 24h | ≥ 95% (prefer ≥ 98%) |
| Device gate | 100% of denied pushes are non-ACTIVE/unknown |
| Authz | Non–Sync Manage push never drains company queue |
| Rollback drill | R0 completed < 5 minutes |
| Online ERP | No business-logic regression with flags OFF or ON |

---

## Production acceptance checklist (executive)

1. Hardening 7.1 in production build  
2. Migrations 175–179 applied; backup verified  
3. Flags OFF deploy smoke OK  
4. Monitoring ON and healthy  
5. Pilot devices ACTIVE  
6. Flag sequence followed with soaks  
7. Thresholds green for ≥ 24h  
8. R0 rollback drill done  
9. Ops + Support ready  
10. Written GO for defined canary scope only  

---

## Decision for Phase 8 planning

**CONDITIONAL GO to execute this rollout plan** — starting at dormant deploy + monitoring; module enablement only after checklist gates.
