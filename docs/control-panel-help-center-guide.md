# Ratib Control Panel — Help Center Guide

This document is the **canonical operator guide** for the Ratib control panel. It is rendered inside the panel at **Help center** (`control-panel/pages/control/help-center.php`, requires dashboard permission) and kept in the repository under `docs/control-panel-help-center-guide.md`.

---

## 1. Purpose and audience

- **Who:** control panel operators, country admins, and super admins who manage countries, agencies, registrations, support, tracking, accounting, and (where permitted) infrastructure and system settings.
- **What:** how to navigate the panel safely, what each major area does, and how to use **Infrastructure** (Control · Dashboard · Providers).
- **Related files:** bilingual overview in `docs/CONTROL_PANEL_COMPLETE_USER_GUIDE_AR_EN.md`; infrastructure checklist in `docs/infrastructure-tabs-operator-checklist.md`.

---

## 2. First login and session

- Open the **control panel login** URL your organization provides (often under `/control-panel/pages/login.php`).
- Sign in with credentials issued by a **control admin**.
- If your role uses **country scope**, use **Select Country** (sidebar) so lists and actions apply to the correct country.
- Some workflows also require **agency** context; pick the agency when prompted.
- After **permission changes**, sign out and sign in again so the session reloads permissions.

---

## 3. Sidebar map (where to find things)

- **Dashboard** — KPIs, quick actions, recent activity.
- **Control hub** — one page with buttons to many tools (respects the same permissions as the sidebar).
- **Help center** — full operator guide (this document + infrastructure checklist appendix) rendered from `docs/*.md`.
- **Core management** — Select Country, Countries, Agencies, Country Users (when allowed).
- **Registration & Support** — registration queue, support chats, registration page link, public site content.
- **Business modules** — country program, accounting, HR, government/tracking tools (as enabled for your tenant).
- **Administration** — rollout/admin tools, panel settings, **Infrastructure** (runtime + ops + providers), logout under **Account**.

If a menu row is **hidden**, your account does not have the required permission; ask a super admin to adjust your role.

---

## 4. Country scope (why you sometimes see less data)

- **`control_select_country`** (or full admin): may operate across countries depending on policy.
- **`country_{slug}`** permissions: restrict you to assigned countries.
- **Session country** pins many lists to one country — do not assume you see “everything” unless your role allows it.
- **Best practice:** confirm the country in the header/sidebar context before approving registrations or editing agencies.

---

## 5. Daily workflow (suggested order)

- [ ] Log in and confirm **country** (and **agency** if applicable).
- [ ] Open **Dashboard** for counts and alerts.
- [ ] Work **registration requests** (paid / policy-safe queues per your org rules).
- [ ] Clear or assign **support chats**.
- [ ] Check **tracking** or **government** widgets if you operate those modules.
- [ ] Run **accounting** reconciliation if that is your duty.
- [ ] Log out from **Account** when finished.

---

## 6. Infrastructure — overview (single sidebar entry)

**Where:** **Infrastructure** in the sidebar (under Administration). One page hosts **three tabs**:

- **Control** — write **runtime configuration** (flags, queues, allowlist, provider overrides, Namecheap runtime fields). Persists to the module runtime overrides file. Use **Save**; reload when the UI asks so read-only summaries refresh.
- **Dashboard** — read-only **operational** picture: health, queues, workers, jobs, warnings, readiness. Use this first when **provisioning or marketplace flows fail**.
- **Providers** — provider **health/capability** views and **database activations** for registrar/DNS/SSL adapter classes (`ratib_infra_provider_activations`).

**URLs:** same script with `view=control`, `view=dashboard`, or `view=providers` (tabs update this for you).

---

## 7. Infrastructure — Control tab (detailed)

**Use when:** you intentionally change how the infrastructure marketplace **behaves** (not only to observe it).

- **Module enabled** — master switch for the module in this environment.
- **Dry-run** — when on, prefer paths that avoid destructive external side effects (still validate policy with your runbook).
- **Execution kill-switch** — emergency stop for automated execution; use only with understanding of queue/worker impact.
- **Queue driver / max attempts / pressure / worker loop limits** — tune throughput and failure handling; wrong values can stall or overload workers.
- **Default currency** — marketplace default (three-letter code).
- **Tenant allowlist** — comma-separated tenant IDs, or empty for “all tenants”; use a narrow list for staged rollout.
- **Provider execution overrides** — per provider **Live** / **Sandbox** toggles vs **Inherit** (env wins). Prefer **Inherit** unless the panel must override the server environment.
- **Namecheap API (runtime file)** — optional panel-stored credentials for checks; **API key** blank means “keep existing”.
- **Operator shortcuts** — open diagnostics/APIs (often a **new tab**); use only when you know what each endpoint does.

**After changes:** confirm on **Dashboard** tab that health and queues look sane before declaring rollout complete.

---

## 8. Infrastructure — Dashboard tab (detailed)

**Use when:** investigating **incidents**, slow provisioning, or “module looks off” without changing config yet.

- Scan **health** and **queue** sections first.
- Inspect **workers** and **failed / dead-letter** areas before raising infra changes.
- **Readiness / warnings** summarize config gaps (for example missing hosting API hooks); treat as work items, not noise.
- If panels stay **Loading…**, check browser network tab, session expiry, and that you are still logged into the **control panel** (same site, same origin).

---

## 9. Infrastructure — Providers tab (detailed)

**Use when:** adapters show **unavailable**, capabilities are empty, or you are **onboarding a new registrar/DNS/SSL** row.

- Read **Provider health** and **Capability discovery** blocks; any top **notice** explains degraded mode.
- **Database activations** — each row maps a **provider_type**, **provider_code**, **provider_class**, priority, enabled flag, and optional tenant/agency scope. **provider_class** must be a real PHP class implementing the adapter contract (example in the form for Namecheap).
- After **Save activation**, reload the page to refresh JSON panels.

**If everything is unavailable:** verify DB migrations for the marketplace module ran on the correct database, then confirm activation rows exist for your environment.

---

## 10. Which Infrastructure tab first? (decision guide)

- **Planned rollout / policy change** → **Control** → then **Dashboard** to verify.
- **Production incident (“nothing provisions”)** → **Dashboard** → **Providers** if adapters inactive → **Control** only if configuration must change.
- **New provider adapter** → **Providers** (activations + health) → **Dashboard** → **Control** for execution flags if needed.

---

## 11. Permissions (basics)

- Parent permissions can imply **child** permissions (see `control-panel/includes/control-permissions.php`).
- **`*`** in permission list means full access (rare; audit carefully).
- **Infrastructure** uses the same **system settings / infrastructure** gate as other administration links.
- If you need access, your administrator assigns the correct **role** or permission set — not via self-service inside this guide.

---

## 12. Security and compliance

- Never share **control admin** passwords; use individual accounts.
- Apply **least privilege**: only the permissions needed for the job.
- Treat **approve / reject / delete** and **runtime overrides** as **auditable** actions.
- For infrastructure, prefer **dry-run** and **narrow allowlist** in non-production or pilot phases.

---

## 13. Troubleshooting (common)

- **Wrong country data visible** — re-check session country and `country_{slug}` permissions; re-login after permission edits.
- **Empty lists after scope change** — your effective country list may be empty; ask an admin to fix assignments.
- **Infrastructure save fails** — read the error message; confirm CSRF/session, disk permissions for runtime file, and API `control-update` responses in network tools.
- **Support badge shows unread but list empty** — refresh; confirm filters on the support page.

---

## 14. Repository documentation index

- `docs/CONTROL_PANEL_COMPLETE_USER_GUIDE_AR_EN.md` — **EN/AR** control panel user guide (includes **§4.10** Infrastructure summary).
- `docs/infrastructure-tabs-operator-checklist.md` — **checklist** format for Infrastructure tabs.
- `docs/infrastructure-marketplace-production-readiness-review.md` — production readiness notes for the marketplace module.
- `modules/infrastructure-marketplace/Migrations/Phase2/` — **commerce foundation** (`ratib_infra_products`, `ratib_infra_plans`, `ratib_infra_plan_features`, `ratib_infra_pricing`, `ratib_tenant_resources`); see `README.txt` for run order.
- `modules/infrastructure-marketplace/Reports/ArchitectureAuditReport.php` — run `php .../ArchitectureAuditReport.php` to regenerate subsystem audit (JSON + HTML under `Reports/`).

---

## 15. Arabic quick reference | مرجع سريع بالعربية

- **لوحة التحكم:** للإدارة (دول، مكاتب، تسجيل، دعم، تتبع، محاسبة…).
- **البنية التحتية (Infrastructure):** تبويب **Control** للإعدادات، **Dashboard** للمراقبة، **Providers** للمزودين وتفعيلات قاعدة البيانات.
- **الدولة في الجلسة:** تأكد من اختيار الدولة الصحيحة قبل الموافقة على الطلبات أو تعديل المكاتب.
- **الصلاحيات:** اطلب من المشرف تعيين الدور المناسب؛ لا تتجاوز نطاق عملك.

---

## 16. Versioning

- Guide file version tracked in Git with the rest of the repo. When product behavior changes, update this file and redeploy so **Help center** shows the new text automatically.
