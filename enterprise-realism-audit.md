# Enterprise realism audit — RATIB public surfaces

**Date:** 2026-05-18  
**Scope:** `/profile/`, `/`, `/security-compliance/`, `/architecture/`, `/procurement-legal/`, home CMS strings, mega nav, enterprise trust layer.  
**Objective:** Credible B2B workforce operations vendor — not pseudo-defense or inflated sovereign-infrastructure branding.

---

## Executive summary

| Area | Finding | Action taken |
|------|---------|--------------|
| Company profile | High density of control-plane, intelligence, sovereign, and finance-grade language | Moderated in `ratib-about-profile-data.php` + `ratib-about-sections.php` |
| Home | Hero and trust block read as live production / sovereign orchestration | Moderated in `site-content-home-data.php`; sample UI labels on dash |
| Trust pages | Repeated “control plane”, mission-critical, telemetry intelligence | Moderated in data PHP files + section templates |
| Visual | Purple glow, strong gradients, green “ok” chips | Reduced in `about-enterprise.css`, `enterprise-trust-layer.css` |
| Metrics | Hero KPIs implied audited production scale | Labeled illustrative; deltas say “sample UI” |

**Core positioning retained:** multi-agency workforce program platform, tenant separation, workflows, field operations, governance, integrated finance, procurement pages.

---

## Phrase moderation register (representative)

| Original | Revised | Why more credible |
|----------|---------|-------------------|
| operations control plane | enterprise operations workspace / agency workspace | “Control plane” over-implies infrastructure authority without proof |
| sovereign-grade | compliance-oriented / policy & oversight | Implies national-grade certification not documented publicly |
| workforce telemetry intelligence | field operations support / operational visibility | Avoids intelligence/surveillance connotation |
| intelligence at ingest | validated at intake / signal validation | Sounds like classified pipeline processing |
| threat fusion / escape prediction | signal validation / consistency review | Defense-analytics vocabulary without product evidence |
| finance-grade | integrated ledger / operational finance | Implies audited financial institution grade |
| production surface | agency workspace / sample screenshot | Implies live sovereign deployment |
| Government execution mode | strict policy profile | Implies government-operated system |
| mission-critical programs | high-volume programs | Standard enterprise without contractor tone |
| Defense-in-depth at the edge and in the control plane | layered security at the edge and platform core | Keeps security concept; drops stacked jargon |
| Anti-spoof check (UI flow) | signal check | Less surveillance-themed |
| Layered control plane | platform layers | Same architecture story, calmer wording |
| Sovereign-grade orchestration infrastructure (home) | enterprise operations architecture | Procurement-safe, still enterprise |
| Production control plane (home hero) | enterprise workspace | Describes product, not fictional infra tier |
| 99.9% edge target (highlight) | service targets defined per agreement | Avoids unaudited SLA claim |
| hero metrics (2.8k, 94.6%, etc.) | sample workload / sample metric + disclaimer | Prevents implied live production counters |

---

## Files changed (implementation)

- `includes/ratib-about-profile-data.php`
- `includes/ratib-about-sections.php`
- `includes/site-content-home-data.php`
- `includes/ratib-enterprise-trust-home.php`
- `includes/ratib-security-compliance-data.php`
- `includes/ratib-security-compliance-sections.php`
- `includes/ratib-architecture-data.php`
- `includes/ratib-architecture-sections.php`
- `includes/ratib-procurement-legal-data.php`
- `includes/ratib-procurement-legal-sections.php`
- `includes/ratib-mega-nav-config.php`
- `pages/home.php`, `pages/architecture.php`, `pages/ratib-which-page.php`
- `css/pages/about-enterprise.css`, `css/pages/enterprise-trust-layer.css`

---

## Residual watch list (not changed — out of scope)

- Internal admin “control plane” labels (`admin/`, `control-panel/`) — operator tooling, not public procurement copy.
- Legal acronym expansion in `header.php` (“Tracking Intelligence Base”) — brand legacy; consider separate brand pass if buyers confuse it with defense AI.
- `api/worker-tracking/update-location.php` comments reference anti-spoof / threat fusion — backend comments only; public copy no longer mirrors them.

---

## Recommended reviewer checks

1. Load `/profile/` — headline “operations platform”, metrics disclaimer, no sovereign-grade eyebrow.
2. Load `/` — enterprise block title “Enterprise operations architecture”, dash labeled “Sample”.
3. Load trust trio — shorter section titles, “platform core” instead of “control plane” where shown.
4. Skim mega nav — no “telemetry intelligence” in descriptions.
