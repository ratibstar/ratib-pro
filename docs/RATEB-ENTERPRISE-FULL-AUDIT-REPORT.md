# RATEB — Enterprise Audit (Executive Brief)

**Scope:** Full `ratibprogram` repository (~1,876 files) · **Read-only** · May 2026  
**Brand:** RATEB — Recruitment Automation & Telemetry Enterprise Base  
**Live:** https://out.ratib.sa

---

## 1. Platform Overview

**What it is:** Multi-tenant **Workforce Program Infrastructure** — not a job board or lightweight CRM. One platform for cross-border labor programs: worker lifecycle, agents, field tracking, finance, partners, and government-style oversight.

**Purpose:** Run regulated, high-volume recruitment corridors (11+ sending markets → KSA and others) with unified records, auditable workflows, and operational visibility.

**Positioning:** Enterprise-facing public layer (Trust Hub, procurement pages, gov operations page). HQ Riyadh, founded 2018.

**Main domains:** Agency ops · Accounting/ERP · HR · Field telemetry · Partner & client portals · Control panel · Infrastructure marketplace · Payments (N-Genius, Tap, PayPal) · Enterprise marketing.

**Architecture (simple):** Shared app core + **separate DB per agency**. Two code styles coexist: mature legacy PHP/API and newer `app/` layer (workflows, events). Functional but not fully unified.

---

## 2. Folder Inventory (Summary)

| Area | Role | Ready? |
|------|------|--------|
| `api/` (~410 files) | All business APIs | Yes* |
| `pages/` | Agency UI + public marketing | Yes |
| `control-panel/` | Country/agency admin console | Yes |
| `includes/` | Auth, CMS, payments, QR login | Yes |
| `modules/infrastructure-marketplace/` | Domains, SSL, hosting | Yes — best runbooks |
| `modules/client-dashboard/` | Client self-service hub | Yes |
| `app/` | Modern workflows & SaaS modules | Partial |
| `mobile-app/` + `android/` + `ios/` | Field GPS tracker (Capacitor) | Yes |
| `admin/` | Platform control center | Yes |
| `Designed/` | Separate e-commerce product | Separate deploy |
| `tests/` (8 files) | Automated tests | Weak |

\*Debug/maintenance endpoints remain in tree — see risks.

---

## 3. Feature Inventory (Condensed)

**Core:** Worker lifecycle, documents, Musaned, visa, agent/sub-agent hierarchy, cases, reports, notifications, help center.

**Auth:** Password, WebAuthn, biometric, **QR/barcode workforce login** (pairing + badge + audit).

**Telemetry:** GPS, geofences, SOS, offline mobile sync, tracking map, health dashboard.

**Finance:** Double-entry accounting, AR/AP, multi-currency, linked to workers/agents.

**Partners:** Partner portal, CVs, deployments, statements.

**Commercial:** Subscriptions, registration (Pro), client marketplace, infra orders.

**Enterprise:** Trust hub, security/compliance, architecture brief, procurement legal, gov ops page, print-ready enterprise packs (HTML).

**Internal/ops:** Deploy scripts, cache purge, sync utilities — powerful if exposed.

---

## 4. UI / Branding

| Surface | Grade |
|---------|-------|
| Public marketing & trust pages | **A-** — tokens, glass UI, honest sample-data labels |
| Agency workspace (in-app) | **B** — works; legacy labels & spacing |
| Control panel | **B+** |
| Mobile field app | **B-** |

**Procurement visuals:** Strong narrative; missing binary PDFs, DPA page, Arabic legal mirror.

---

## 5. Security & Infrastructure

**Strengths:** RBAC (page + API + control panel), tenant isolation, QR audit trail, session regeneration, idempotency, infra emergency runbooks.

**Weaknesses:** Hardcoded DB password fallbacks in committed env files; maintenance pages (`ratib-sync-from-github`, `fix-perms`, etc.) in deploy tree; thin test coverage; debug APIs present.

**Deploy:** GitHub Actions → cPanel fast deploy, build marker, post-deploy HTTP checks — **mature**. SQL migrations **not** auto-deployed.

---

## 6. Data Model (High Level)

**Accounting/SaaS:** countries → agencies → customers → subscriptions → wallets → ledger (immutable double-entry) → settlements.

**Workforce:** workers, agents, documents, workflows, visa, partner deployments.

**Gov/telemetry:** gov tracking, violations, blacklist, inspections; GPS events; QR audit & trusted devices.

**Tenancy:** Platform config centralized; **agency data in separate databases**.

---

## 7. Readiness Scores (/100)

| Dimension | Score |
|-----------|-------|
| Enterprise maturity | 86 |
| Operational maturity | 84 |
| UI maturity | 80 |
| Security maturity | 74 |
| Procurement readiness | 85 |
| Investor readiness | 78 |
| Scalability perception | 82 |
| Brand consistency | 82 |
| Product clarity | 88 |
| Government suitability | 80 |
| **Overall** | **82** |

Public marketing alone: **~91**. Full diligence: **~74**.

---

## 8. Risks & Gaps

**Critical:** Committed DB credential fallbacks; exposed ops/debug endpoints.

**Procurement blockers:** No PDF packs on-site, no DPA/subprocessors, no control matrix, no pen-test summary.

**Technical debt:** Dual architecture (legacy + `app/`), workflow logic in 3 places, 147+ audit markdown files at root, only 8 tests.

**Unfinished:** In-app terminology migration, Arabic trust mirror, payment rail consolidation (N-Genius + Tap + PayPal).

---

## 9. Competitive Position

**Best fit:** Vertical **workforce program infrastructure** for international staffing — deeper than ATS, more operational than HRIS, more integrated than standalone GPS.

**Not:** Job board, generic CRM, pure fleet tracking.

**Moat:** Corridor compliance, integrated finance, agent hierarchy, gov oversight, QR field identity, infra marketplace upsell.

---

## 10. Executive Summary

RATEB is a **real, broad, production-deployed platform** — not a prototype. It feels like a **scale-up approaching enterprise**: strong public positioning and deep domain features, but engineering governance (secrets, tests, architecture unity) still catching up.

**Most impressive:** Scope (workforce + finance + telemetry + gov + marketplace in one product), enterprise trust layer, infra runbooks, QR workforce auth, automated deploy.

**Needs most work:** Credential hygiene, reduce attack surface, compliance evidence pack, finish in-app rebrand, expand tests.

---

## 11. Final Verdict

| Audience | Impression |
|----------|------------|
| **Enterprise buyer** | Credible for short-list; full approval needs security fix + DPA/PDF pack |
| **Investor** | Interesting vertical depth; technical DD required |
| **Procurement** | Strong first meeting; conditional vendor approval |
| **Operational reality** | **Yes — feels live and production-capable** |

**One line:** RATEB is a substantively real workforce infrastructure platform that markets as enterprise-ready; closing the gap to formal certification requires security remediation and standard procurement artifacts.

**Overall: 82/100** · Production: **85/100** · Security audit: **68/100**

---

*Read-only audit · No code modified · Full detail available on request*
