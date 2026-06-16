# Procurement defensibility review — RATEB public enterprise pages

**Audience:** Enterprise procurement, legal, and security reviewers evaluating RATEB as a software vendor.

---

## Defensible claims (kept and clarified)

| Claim | Basis | Public wording |
|-------|--------|----------------|
| Saudi legal entity | Stated company name; CR/VAT on request | `rateb-procurement-legal-data.php`, profile |
| Multi-agency tenancy | Architectural design: shared app, separate DBs | Architecture, security, procurement pages |
| TLS, RBAC, audit history | Platform design features | Security trust center |
| No SOC2/ISO unless contracted | Explicit disclaimer | Security hero + procurement notice |
| Labor oversight modules | Product modules described functionally | Profile governance, security governance |
| Field location support | Operational feature set | Telemetry → field operations wording |
| Enterprise contact | `info@rateb.sa` | All procurement CTAs |

---

## Claims removed or softened (defensibility risk)

| Prior wording | Risk | Mitigation |
|---------------|------|------------|
| sovereign-grade / sovereign workforce programs | Implies government-grade accreditation | Replaced with regulated-program / compliance-oriented language |
| 99.9% edge target | Implies measured production SLO without public audit | Replaced with agreement-based service targets |
| Unlabeled KPI numbers on profile/home | Implies live production telemetry | Sample labels + illustrative disclaimers |
| Government execution mode | Implies government-operated deployment mode | Strict policy profile |
| mission-critical programs | Contractor / national-critical tone | high-volume programs |
| telemetry intelligence | Suggests intel product category | field operations support / monitoring |
| finance-grade | Implies regulated financial institution standard | integrated ledger / operational finance |

---

## Procurement page posture (`/procurement-legal/`)

**Strengths after pass:**

- Clear disclaimer: no implied government partnerships or certifications.
- Legal identity fields with NDA-qualified CR/VAT.
- Service scope states software vendor role (not brokerage/legal unless contracted).
- Links to security and architecture without duplicating inflated adjectives.

**Phrase updates:**

| Original | Revised | Why |
|----------|---------|-----|
| control plane layers (walkthrough) | platform layers | Reviewers map to software layers, not K8s sovereign stack |
| Control plane (boundary diagram) | Platform core | Matches data-boundary explanation |
| telemetry intelligence (service scope) | field-operations support | Accurate product category for RFP responses |

---

## Security trust center (`/security-compliance/`)

| Original | Revised | Why |
|----------|---------|-----|
| Defense-in-depth … control plane | Layered security … platform core | Standard term kept once; removed duplicate “control plane” |
| Governance layer for sovereign workforce programs | Governance for regulated workforce programs | Regulated = believable; sovereign = overreach |
| Control plane vs program datastores | Platform core vs program datastores | Same model, procurement-plain language |
| mission-critical programs | high-volume programs | Continuity without national-critical implication |
| Workforce telemetry intelligence … | Field operations monitoring … | Visibility, not intelligence product |

**Unchanged (appropriate):** TLS 1.3, tenant isolation, HMAC webhooks, WebAuthn-ready, no false SOC2 badge.

---

## Questions a reviewer should be able to answer after reading

1. **What does RATEB sell?** Multi-agency workforce program software (workflows, records, field support, finance, governance).
2. **How is data separated?** Platform configuration vs per-agency program databases.
3. **What is not claimed?** Government partnership, SOC2/ISO, live production metrics on marketing pages.
4. **How to engage?** `info@rateb.sa`, architecture/security review paths.

---

## Evidence pack alignment (for sales/procurement follow-up)

Public pages now **describe** capabilities; **evidence** (CR copy, DPA, security questionnaire, SLA schedules) remains under NDA/signed agreement — consistent with on-page disclaimers.
