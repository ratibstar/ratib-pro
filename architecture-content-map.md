# Architecture Page — Content Map

Maps user-facing sections to PHP config keys and DOM anchors. Source of truth: `includes/rateb-architecture-data.php`.

---

## Meta

| Field | Value |
|-------|--------|
| Config path | `meta.title`, `meta.description` |
| Canonical | `/architecture/` |
| Page stamp | `architecture` |

---

## Hero (`#top`)

| Element | Config key |
|---------|------------|
| Eyebrow | `hero.eyebrow` |
| H1 | `hero.title` |
| Lead | `hero.lead` |
| Stack preview list | `hero.stack_preview[]` |
| Stack label | `hero.diagram_label` |

**Positioning phrase:** multi-tenant workforce program orchestration infrastructure

---

## 1. Architecture Overview (`#architecture-overview`)

| Block | Config |
|-------|--------|
| Section header | `overview.eyebrow`, `title`, `sub` |
| Point cards | `overview.points[]` → `label`, `body` |

**Points covered:** Primary role, Tenancy model, Integration surface, Review posture

---

## 2. Layered Control Plane (`#layered-control-plane`)

| Layer | Order | Key | Config fields |
|-------|-------|-----|---------------|
| Experience Layer | L7 | `experience` | `responsibilities`, `operational_role`, `boundaries` |
| Orchestration Layer | L6 | `orchestration` | same |
| Telemetry Layer | L5 | `telemetry` | same |
| Business Modules | L4 | `business` | same |
| Governance Layer | L3 | `governance` | same |
| Commercial Layer | L2 | `commercial` | same |
| Data Layer | L1 | `data` | same |

Rendered top-to-bottom (L7 → L1) via `layers.items[]`.

---

## 3. Multi-Tenant Isolation (`#multi-tenant-isolation`)

| Pillar | Config |
|--------|--------|
| Shared orchestration core | `isolation.pillars[0]` |
| Isolated tenant datastores | `isolation.pillars[1]` |
| Governance boundaries | `isolation.pillars[2]` |
| Scoped operations | `isolation.pillars[3]` |

**Visual:** `rateb-arch-topology__diagram` (core + Tenant A/B/C spokes)

---

## 4. Event-Driven Infrastructure (`#event-driven`)

| Capability | Config path |
|------------|-------------|
| Event fabric | `events.capabilities[]` |
| Webhooks | same |
| SSE streams | same |
| Orchestrated workflows | same |
| Replay-safe operations | same |
| Idempotency | same |

| Flow step | Config |
|-----------|--------|
| Emit | `events.flow[0]` |
| Route | `events.flow[1]` |
| Verify | `events.flow[2]` |
| Commit | `events.flow[3]` |

---

## 5. Telemetry Intelligence (`#telemetry-intelligence`)

| Item | Config `telemetry.items[]` |
|------|----------------------------|
| Geospatial telemetry | title + body |
| Offline synchronization | |
| Anti-spoof logic | |
| Geofence intelligence | |
| Operational escalation | |

---

## 6. Finance Infrastructure (`#finance-infrastructure`)

| Item | Config `finance.items[]` |
|------|--------------------------|
| Ledger subsystem | |
| AR / AP | |
| Transaction linkage | |
| Multi-currency support | |
| Registration / payment sync | |

Grid: 2 columns on wide viewports.

---

## 7. Operational Governance (`#operational-governance`)

| Item | Config `governance.items[]` |
|------|-----------------------------|
| RBAC | |
| Policy enforcement | |
| Audit history | |
| Country scopes | |
| Labor oversight support | |

---

## 8. Deployment Model (`#deployment-model`)

| Node | Tier | Config `deployment.nodes[]` |
|------|------|-----------------------------|
| Public edge | `edge` | `label`, `body` |
| Agency workspace | `client` | |
| Partner portals | `client` | |
| APIs | `gateway` | |
| Orchestration core | `core` | |
| Tenant databases | `data` | |

**Visual:** `rateb-arch-deploy-topology` rows + `rateb-arch-deploy-legend`

---

## Briefing (end of page)

| Field | Config |
|-------|--------|
| Title | `briefing.title` |
| Body | `briefing.body` |
| Primary CTA | `briefing.href` → mailto architecture review |
| Secondary | `briefing.secondary_href` → `/security-compliance/` |

---

## Jump Navigation

```
#top
#architecture-overview
#layered-control-plane
#multi-tenant-isolation
#event-driven
#telemetry-intelligence
#finance-infrastructure
#operational-governance
#deployment-model
```

---

## Navigation & Footer Keys

| Surface | href_key / URL |
|---------|----------------|
| Mega nav Company | `architecture` → `/architecture/` |
| Footer Legal | `home.footer.link.legal.architecture` |
| Resolver | `rateb_mega_nav_resolve_href('architecture', …)` |

---

## Content Editing

1. Edit copy in `includes/rateb-architecture-data.php` only.  
2. Adjust layout/markup in `includes/rateb-architecture-sections.php`.  
3. Adjust visuals in `css/pages/architecture.css`.  
4. No CMS DB keys for architecture body copy (static include pattern, same as security-compliance).
