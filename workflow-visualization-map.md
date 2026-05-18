# Workflow visualization map

**Purpose:** Information architecture for operational proof content—where each artifact appears and what it explains.

---

## Site map (operational proof)

```
Home (/)
└── #operational-proof
    ├── Reference diagrams (5)
    ├── Platform screens (6)
    └── Workflow walkthroughs (5)

Profile (/profile/)
└── #operational-proof  (same module, full)
    └── Jump nav link

Architecture (/architecture/)
└── #operational-proof  (diagrams + workflows only)
```

---

## Diagram → question answered

| Diagram ID | File | Procurement / ops question |
|------------|------|----------------------------|
| `workflow-lifecycle` | `workflow-lifecycle.svg` | How does a worker move through stages? |
| `onboarding-flow` | `onboarding-flow.svg` | How does an agency get from sale to production? |
| `deployment-lifecycle` | `deployment-lifecycle.svg` | What happens between program setup and closure? |
| `tenant-isolation` | `tenant-isolation.svg` | Where is our data vs other agencies? |
| `event-processing` | `event-processing.svg` | How do stage changes reach integrations safely? |

**Label on all:** Illustrative diagrams · not live system output

---

## Screenshot → module

| Screen title | Asset | Module |
|--------------|-------|--------|
| Workforce pipeline | `7.jpg` | Stage graph / pipeline |
| Operations dashboard | `1.jpg` | Agency workspace |
| Finance ledger | `4.jpg` | Accounting |
| Audit history | `5.jpg` | Admin / history context |
| Field operations map | `3.jpg` | Location checkpoints |
| Partner portal | `6.jpg` | Partner access |

**Label on all:** Illustrative interface or Sample operational data

---

## Workflow walkthrough → process

| Workflow ID | Anchor | Steps (summary) | Outcome |
|-------------|--------|-----------------|---------|
| `worker-onboarding` | `#worker-onboarding` | Record → docs → verification → stage advance | Deployment-ready file |
| `compliance-review` | `#compliance-review` | Open file → policy compare → finding/hold → log | Attributed decision |
| `deployment-approval` | `#deployment-approval` | Program slot → HITL gate → event → partner package | Active placement |
| `finance-reconciliation` | `#finance-reconciliation` | Link fees → ledger post → match milestones → export | Traceable finance |
| `partner-coordination` | `#partner-coordination` | Scoped portal → documents → webhook/API → closure sync | Bounded partner access |

---

## Relationship to existing sections

| Existing section | Operational proof complements |
|------------------|------------------------------|
| Home `#how-it-works` | High-level 7-step onboarding; proof section adds **diagrams + screens** |
| Home `#enterprise-infrastructure` | Layer/topo summary; proof adds **tenant + event diagrams** |
| Home `#operational` | Sample ops panel; proof adds **full screen gallery + workflows** |
| Profile `#architecture` | Layer cards + SVG stack; proof adds **downloadable-style reference diagrams** |
| `/architecture/` page | Long-form technical copy; proof adds **scannable visuals** at bottom |

---

## Disclaimer placement

| Level | Text intent |
|-------|-------------|
| Section (op-proof) | No live production / audited metrics / universal gov integrations |
| Diagram block | Illustrative diagrams |
| Screenshot block | Fictional names/figures |
| Metrics/dashboards sitewide | Sample operational data / illustrative interface |

---

## Content density guideline (applied)

- Section intros: **≤ 2 sentences**  
- Workflow steps: **4 bullets + 1 outcome**  
- Diagram captions: **title + one line**  
- Prefer grids over long paragraphs  

---

## File reference (implementation)

```
includes/ratib-operational-proof-data.php    # source of truth
includes/ratib-operational-proof-render.php  # renderer
css/pages/operational-proof.css
assets/images/diagrams/*.svg
```
