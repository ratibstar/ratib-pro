# Operational proof upgrade report

**Date:** 2026-05-18  
**Phase:** Operational maturity & credibility (post enterprise-realism pass)

---

## Objective

Shift public surfaces from **positioning language** to **operational proof**: diagrams, sanitized screens, workflow walkthroughs, and explicit sample-data labeling—without new buzzwords, AI/intelligence framing, or intensified architecture copy.

---

## Deliverables added

### Shared module

| File | Role |
|------|------|
| `includes/rateb-operational-proof-data.php` | Diagrams, screenshots, workflows, global disclaimer |
| `includes/rateb-operational-proof-render.php` | Renders `#operational-proof` section |
| `css/pages/operational-proof.css` | Calm grid layouts, sample badges |

### Diagram assets (`assets/images/diagrams/`)

| File | Purpose |
|------|---------|
| `workflow-lifecycle.svg` | Worker stage path (sample) |
| `onboarding-flow.svg` | Agency onboarding |
| `deployment-lifecycle.svg` | Program → field → handover → closure |
| `tenant-isolation.svg` | Platform core vs agency DBs |
| `event-processing.svg` | Emit → route → verify → commit |

Flat slate palette—no violet marketing gradients.

### Page integration

| URL | Content |
|-----|---------|
| `/` (home) | Full section after enterprise trust block |
| `/profile/` | Full section after architecture |
| `/architecture/` | Diagrams + workflows only (no screenshot grid duplicate) |

### CMS keys (`site-content-home-data.php`)

- `home.op_proof.eyebrow`, `title`, `sub`
- `home.ops.disclaimer`
- Shortened `home.how.sub`, `home.ent.sub`
- `home.analytics.illus` → “Sample operational data …”

---

## Sample-data labeling

| Surface | Label |
|---------|--------|
| Profile hero metrics | Illustrative sample metrics |
| Home hero dash | Sample workspace UI · illustrative metrics |
| Home analytics (all cards) | Sample operational data line |
| Home ops panel | Sample operational data pill + section disclaimer |
| Ops event tail | sample stream |
| Operational proof screens | Per-card badge (Illustrative interface / Sample operational data) |
| Diagrams block | Illustrative diagrams · not live system output |
| Telemetry event stream (profile) | Sample operational data · illustrative event stream |
| Global op-proof disclaimer | Screenshots/diagrams/metrics not live production |

---

## Workflow walkthroughs (5)

1. Worker onboarding  
2. Compliance review  
3. Deployment approval  
4. Finance reconciliation  
5. Partner coordination  

Each: 4 steps + outcome line—written for operators and procurement readers.

---

## Screenshots (sanitized references)

Uses existing marketing assets (`1.jpg`–`7.jpg`) with fictional-data disclaimer:

- Workforce pipeline, operations dashboard, finance ledger, audit/admin context, field map, partner portal.

Replace with production-sanitized captures when available; structure supports swap without layout changes.

---

## What we did not do

- No new certification or government-integration claims  
- No live metrics or “streaming production” framing on marketing UI  
- No additional architecture buzzword sections  

---

## Verification checklist

1. Home `#operational-proof` — 5 diagrams, 6 screens, 5 workflows, disclaimer.  
2. Profile — same block; jump nav includes “Operational proof”.  
3. Architecture — diagrams/workflows only.  
4. All metric/dashboard UIs show sample labeling.  
