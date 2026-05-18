# Enterprise Trust Gap Analysis

**Date:** 2026-05-18  
**Scope:** Public enterprise trust surfaces for RATIB (marketing home, profile, procurement, security, architecture)

---

## Executive summary

RATIB now exposes a **four-page enterprise trust cluster** aimed at different reviewer personas. Remaining gaps are mostly **evidence artifacts** (downloadable packs, signed attestations) and **operational proof** (status, DPA templates) — not additional marketing copy.

| Page | Primary persona | Trust job |
|------|-----------------|-----------|
| `/profile/` | Business / leadership | Who we are, markets, mission |
| `/procurement-legal/` | Procurement / legal | Identity, engagement, requests |
| `/security-compliance/` | InfoSec / compliance | Controls, isolation, procurement CTAs |
| `/architecture/` | CTO / enterprise architecture | Layers, events, deployment |

---

## Coverage matrix (before → after)

| Buyer need | Before | After `/procurement-legal/` |
|------------|--------|-----------------------------|
| Legal entity name | Profile only | Dedicated identity registry |
| CR / VAT posture | Profile, scattered | Explicit NDA / invoice wording |
| Official contact | Footer, chat | Prominent **info@out.ratib.sa** escalation block |
| Engagement process | Implicit in sales | Five-step enterprise engagement |
| Security for RFP | Home bullets | Summary + link to trust center |
| Architecture for RFP | Home / profile | Summary + link to architecture page |
| Tenant separation | Trust center only | Procurement-oriented summary + diagram |
| Legal scope / platform role | Weak or absent | Four defensible legal notes |
| Structured procurement CTAs | Partial on trust page | Four deck/demo/brief/team CTAs |

---

## Persona fit

### Government buyers
- **Strengths:** Formal tone, HQ in KSA, labor oversight positioning, no false partnership claims  
- **Gaps:** No public sector reference list (intentionally omitted); no downloadable government-specific pack; Arabic legal mirror not on this page  

### Enterprise procurement
- **Strengths:** CR/VAT handling language, engagement steps, mailto CTAs with subject lines  
- **Gaps:** No PDF company deck hosted on-site; no vendor registration number in public HTML (NDA-only is correct but may slow first pass)  

### International partners
- **Strengths:** Corridor onboarding step, multi-market context on profile, isolation model  
- **Gaps:** No data residency statement per region; no standard DPA / subprocessors page  

### Compliance reviewers
- **Strengths:** Cross-links to security + architecture; explicit non-claim of SOC 2 / ISO  
- **Gaps:** No control matrix mapping (e.g. ISO 27001 annex); no penetration test summary (even redacted)  

---

## Remaining trust gaps (recommended, not implemented)

Priority ordered by procurement impact:

1. **Downloadable enterprise pack** — PDF deck + one-pager (hosted or gated), linked from procurement CTAs  
2. **DPA / subprocessors page** — Standard SaaS procurement artifact; keep factual vendor list only  
3. **Status / incident communication** — Public status page or committed channel (even “email info@ for status”)  
4. **Arabic procurement mirror** — Formal `ar` page or section for KSA government reviewers  
5. **Questionnaire response bank** — Internal doc; optional “request SIG Lite / CAIQ” path via enterprise mail  
6. **Customer references** — Named logos only with permission; never implied government endorsements  

---

## Content consistency checks

| Field | Source of truth | Status |
|-------|-----------------|--------|
| Legal name | `ratib-about-profile-data.php` / procurement data | Aligned |
| Email | `info@out.ratib.sa` | Aligned across public surfaces |
| Phone | +966 599 863 868 | Aligned |
| CR | NDA on request | Consistent, not over-claimed |
| VAT | Invoice / request | Consistent, not over-claimed |

---

## Risk register (public copy)

| Risk | Mitigation on `/procurement-legal/` |
|------|-------------------------------------|
| Implied government partnership | Hero notice + no logo wall |
| False certification | Security summary references signed-agreement-only claims |
| Over-broad legal scope | Service scope limits platform vs brokerage/legal services |
| Shared-tenant data fear | Isolation section + diagram |

---

## Suggested navigation hierarchy

```
Company (mega nav)
  ├── Company profile
  ├── Procurement & legal  ← hub for buyers
  ├── Security & compliance
  ├── Platform architecture
  └── Platform overview (marketing)
```

Footer Legal column now lists: Procurement & legal → Architecture → Security & compliance.

---

## Conclusion

The public site can support **first-pass** enterprise, government, and partner diligence without overstating certifications. Closing the remaining gaps requires **document artifacts and process** (PDFs, DPA, status), not more landing-page sections. The new procurement page is the appropriate **hub** linking technical depth on security and architecture pages.
