# Enterprise copy moderation report

**Full phrase log:** original → revised → rationale  
(Senior content + procurement realism pass, 2026-05-18)

---

## Company profile (`includes/ratib-about-profile-data.php`)

| # | Original | Revised | Rationale |
|---|----------|---------|-----------|
| 1 | operations control plane (mission) | workflow coordination … operational visibility | Describes product behavior, not fictional infra tier |
| 2 | workforce telemetry intelligence | field-operations support | Removes intelligence-product framing |
| 3 | finance-grade (mission) | finance linked to program events | Accurate module scope |
| 4 | operations control plane (summary) | multi-agency workflow platform | Believable vendor category |
| 5 | 99.9% edge target | Service targets · Defined per agreement | No public unaudited SLA |
| 6 | intelligence at ingest | (removed from features) | Replaced with signal validation |
| 7 | threat fusion / escape prediction | (removed) | Defense analytics without evidence |
| 8 | Anomaly & anti-spoof | Signal validation | Operational, not surveillance |
| 9 | Government execution mode | Strict policy mode | Optional program setting, not gov ops mode |
| 10 | sovereign workforce programs (trust) | (removed) | Overstates authority |
| 11 | hero_metrics labels | Sample workload / sample metric / sample log / sample panel | Illustrative, not production claims |
| 12 | Operations control plane (is_infrastructure) | Workforce program workflow platform | Category clarity |
| 13 | Telemetry Layer title | Field operations layer | Aligns layer name with moderated copy |
| 14 | Finance-grade (is_infrastructure) | Finance module linked to placements | Defensible scope |

## Profile template (`includes/ratib-about-sections.php`)

| # | Original | Revised | Rationale |
|---|----------|---------|-----------|
| 15 | RATIB control plane (hero) | RATIB operations platform | Headline credibility |
| 16 | production surface (figcaption) | sample screenshot | Honest marketing asset label |
| 17 | Layered control plane | Platform layers | Architecture without cosplay |
| 18 | Operations control plane (ops) | Agency workspace | What buyers actually buy |
| 19 | Workforce telemetry intelligence | Field operations support | See above |
| 20 | intelligence at ingest … sovereign-grade audit | offline sync … audit-friendly event history | Removes stacked buzzwords |
| 21 | Sovereign-grade (eyebrow) | Policy & oversight | Compliance without sovereign claim |
| 22 | Finance-grade ledger subsystem | Integrated ledger and invoicing | Product fact |
| 23 | Critical capability (eyebrow) | Field operations | Neutral section label |
| 24 | (new) metrics disclaimer | Illustrative sample metrics … | Procurement-safe |

## Home CMS (`includes/site-content-home-data.php`)

| # | Original | Revised | Rationale |
|---|----------|---------|-----------|
| 25 | Production control plane (hero lead) | Enterprise workspace | Same screens, calmer frame |
| 26 | Orchestration Platform & Workforce Intelligence | Workforce Program Operations Platform | Less defense-AI headline |
| 27 | operational intelligence (bullet) | operational visibility | Standard B2B ops |
| 28 | finance-grade events | finance events linked to placements | No false financial grade |
| 29 | Sovereign-grade orchestration infrastructure | Enterprise operations architecture | Keeps enterprise, drops sovereign |
| 30 | Shared control plane … event fabric | Shared application core … event delivery | Concrete software terms |
| 31 | Layered control plane | Platform layers | Consistency |
| 32 | Telemetry intelligence path | Field operations path | Consistency |
| 33 | Field telemetry intelligence (feature) | Field operations support | Consistency |
| 34 | Isolated production tenants on one control plane | Separate agency databases on one platform | Accurate tenancy story |
| 35 | Mission-critical programs | High-volume programs | Tier naming without contractor tone |
| 36 | finance-grade operations (footer) | integrated finance | Shorter, defensible |

## Home UI (`pages/home.php`, `ratib-enterprise-trust-home.php`)

| # | Original | Revised | Rationale |
|---|----------|---------|-----------|
| 37 | title Control plane / cp-me-01a | Sample workspace UI / ws-demo-01 | Sample UI honesty |
| 38 | Live (dash) | Sample | No fake live stream |
| 39 | Anti-spoof check (flow) | Signal check | Moderated path label |
| 40 | Control plane · tenant DBs (L1) | Platform config · tenant DBs | Diagram clarity |
| 41 | (new) dash illustrative line | Sample workspace UI · illustrative metrics | Matches analytics disclaimer |

## Security (`includes/ratib-security-compliance-data.php`)

| # | Original | Revised | Rationale |
|---|----------|---------|-----------|
| 42 | Defense-in-depth … control plane | Layered security … platform core | One strong security phrase |
| 43 | sovereign workforce programs | regulated workforce programs | Regulated = reviewable |
| 44 | immutable history (sub) | recorded history | Still audit-oriented, less absolute |
| 45 | Control plane vs program datastores | Platform core vs program datastores | Plain language |
| 46 | mission-critical programs | high-volume programs | See above |
| 47 | Workforce telemetry intelligence | Field operations monitoring | Trust center appropriate tone |

## Architecture (`includes/ratib-architecture-data.php`)

| # | Original | Revised | Rationale |
|---|----------|---------|-----------|
| 48 | control plane, telemetry intelligence (meta) | shared core, field operations | Meta description scanability |
| 49 | Multi-tenant … orchestration infrastructure | Multi-agency … platform | Less abstract |
| 50 | layered control plane (lead) | platform layers | Same story |
| 51 | intelligence (overview sub) | field operations | Reduced abstraction |
| 52 | Layered control plane (eyebrow) | Platform layers | Consistency |
| 53 | Control plane never stores… | Platform stores hold configuration… | Same boundary, clearer |
| 54 | Telemetry intelligence / Field intelligence | Field operations / Location-assisted operations | Section credibility |
| 55 | Anti-spoof logic | Signal validation | Feature honesty |
| 56 | Geofence intelligence | Geofence rules | Removes “intelligence” suffix |

## Procurement (`includes/ratib-procurement-legal-data.php` + sections)

| # | Original | Revised | Rationale |
|---|----------|---------|-----------|
| 57 | control plane layers | platform layers | Buyer comprehension |
| 58 | Layered control plane (link desc) | Platform layers … | Consistency |
| 59 | Control plane (boundary) | Platform core | Diagram + data alignment |
| 60 | telemetry intelligence (scope) | field-operations support | RFP-safe scope statement |

## Navigation & banners

| # | Original | Revised | Rationale |
|---|----------|---------|-----------|
| 61 | Layered control plane (mega nav) | Platform layers | First-touch moderation |
| 62 | workforce telemetry intelligence (mega nav) | field-operations support … agency workspace | Accurate nav blurbs |
| 63 | rate intelligence | rate limiting | Standard security feature name |
| 64 | Layered control plane documentation (arch banner) | Platform architecture documentation | Page intent clear |
| 65 | control plane (security diagram label) | platform core | Visual consistency |

---

## Tone target (achieved)

- **Sounds like:** enterprise workforce operations software vendor with architecture documentation for buyers.
- **Does not sound like:** classified intelligence platform, national control plane, or unaudited production war room.

---

## Related documents

- `enterprise-realism-audit.md` — scope and file list
- `procurement-defensibility-review.md` — reviewer Q&A and claim boundaries
- `overpositioning-reduction-report.md` — category analysis and visual pass
