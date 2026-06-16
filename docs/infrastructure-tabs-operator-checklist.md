# Infrastructure tabs — operator checklist (Control · Dashboard · Providers)

Use this checklist so **new control-panel operators** know **where to go**, **what each tab is for**, and **what to do first**. These screens live under **Control Panel → Infrastructure**, then use the in-page tabs **Control**, **Dashboard**, and **Providers**.

**Who can see this:** users with permission to open system / infrastructure settings in the control panel (same gate as before the tabs were merged).

---

## Before you start

- [ ] Confirm you are logged into the **control panel** (not the public site).
- [ ] Open **Infrastructure** from the sidebar (one entry; it opens the Infrastructure shell).
- [ ] Use the three tabs at the top of the main area to switch views **without hunting for separate menu items**:
  - **Control** — configuration and runtime flags (what the module *should* do).
  - **Dashboard** — live operational status (what the system *is* doing).
  - **Providers** — registrar/DNS/SSL adapter health and database activations.

---

## Tab: Control

**Purpose:** Apply **runtime controls** stored in the module’s runtime overrides file (flags, queue settings, tenant allowlist, provider execution overrides, Namecheap fields in the runtime file, shortcuts to JSON diagnostics).

| Step | Check |
|------|--------|
| 1 | Read the **effective** summaries on the page (read-only) so you know current state before changing anything. |
| 2 | Prefer **dry-run** and narrow **tenant allowlist** when testing rollout. |
| 3 | Understand **kill-switch** and **queue driver** implications before enabling aggressive settings in production. |
| 4 | For **provider execution** overrides, use **Inherit** unless you intentionally override environment-based flags from this panel. |
| 5 | After **Save**, reload if prompted so summaries match what was written. |
| 6 | Use **Operator shortcuts** only when you intend to hit APIs or open diagnostics (often opens in a new tab). |

---

## Tab: Dashboard

**Purpose:** **Operational overview** — health, queues, workers, catalog visibility, jobs, diagnostics, readiness-style panels (embedded admin dashboard).

| Step | Check |
|------|--------|
| 1 | Start here when someone reports **“infra / marketplace / provisioning isn’t working”** — scan health and queue sections first. |
| 2 | Use **queue / workers / failed jobs** panels to see backlog or stuck work before changing Control settings. |
| 3 | Treat **warnings / readiness** sections as hints; fix configuration or capacity issues they point to. |
| 4 | If panels stay on **Loading…**, check session/network and that APIs used by the dashboard are reachable (same-origin, control session). |

---

## Tab: Providers

**Purpose:** **Provider integrations** — JSON health/capability snapshots and **database activations** (`rateb_infra_provider_activations`) for which adapter classes are enabled.

| Step | Check |
|------|--------|
| 1 | Confirm **Provider health** and **Capability discovery** panels load; read any banner at the top if present. |
| 2 | If everything shows **unavailable** / empty capabilities, verify migrations ran and **activations** rows exist for your scope (see upsert form / DBA runbook). |
| 3 | When **upserting** an activation, ensure **provider_class** is a real PHP class name (example in form is for Namecheap registrar adapter). |
| 4 | After saves, **reload the page** (or follow the on-screen notice) to refresh the pre blocks. |

---

## Typical flows (which tab first?)

- [ ] **New rollout / policy change** → **Control** (flags & allowlist), then **Dashboard** to confirm behavior.
- [ ] **Incident / “nothing provisions”** → **Dashboard** first, then **Providers** if adapters look inactive, then **Control** only if config must change.
- [ ] **New registrar or adapter wiring** → **Providers** (activations + health), then **Dashboard**, then **Control** for execution flags if needed.

---

## Where this appears in the product

- Sidebar: **Infrastructure** (single item).
- Same page: tabs **Control** · **Dashboard** · **Providers** (URL query `view=control|dashboard|providers`).
- **Control hub** may show one card with three buttons that deep-link to the same views.

---

## Revision

- Align this checklist with internal runbooks (`docs/infrastructure-marketplace-production-readiness-review.md`, migrations under `modules/infrastructure-marketplace/Migrations/`) when production processes change.
- Bilingual summary for operators: `docs/CONTROL_PANEL_COMPLETE_USER_GUIDE_AR_EN.md` sections **4.10** and **4.11**.
- **In-app:** this file is embedded as **Appendix A** on **Help center** (`control-panel/pages/control/help-center.php`).
