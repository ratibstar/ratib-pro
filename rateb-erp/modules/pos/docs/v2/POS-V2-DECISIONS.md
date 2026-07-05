# RATEB POS V2 — Architecture Decision Records (ADR)

**Version:** 1.0.0  
**Format:** MADR-inspired

---

## ADR-001: Additive V2 Parallel Stack

| Field | Value |
|-------|-------|
| **ID** | ADR-001 |
| **Status** | Accepted |
| **Context** | V1 POS is in production; full rewrite risks regression |
| **Decision** | Build V2 as parallel namespace (`V2/`, `v2/` assets, `/api/v2/`) with `POS_V2_ENABLED` flag |
| **Alternatives** | (A) Fork module (B) In-place refactor (C) Microservice |
| **Consequences** | Temporary duplication; clean rollback; longer initial build |
| **Future review** | After 90% traffic on V2, deprecate V1 views (not APIs) |

---

## ADR-002: Bridge Pattern for ERP Integration

| Field | Value |
|-------|-------|
| **ID** | ADR-002 |
| **Status** | Accepted |
| **Context** | Inventory, GL, CRM owned by ERP modules |
| **Decision** | V2 UseCases call existing `Pos*BridgeService` adapters only |
| **Alternatives** | Direct model access; duplicate inventory logic in POS |
| **Consequences** | Performance tied to bridge; no duplicated business rules |
| **Future review** | If bridge becomes bottleneck, add read replicas |

---

## ADR-003: UseCase-per-Operation Application Layer

| Field | Value |
|-------|-------|
| **ID** | ADR-003 |
| **Status** | Accepted |
| **Context** | Controllers and V1 services mix concerns |
| **Decision** | One `*UseCase` class per business operation |
| **Alternatives** | Fat services; command bus only |
| **Consequences** | More classes; excellent testability and traceability |
| **Future review** | Phase 3 — optional command bus if count > 80 |

---

## ADR-004: Typed DTOs — No Array Passing

| Field | Value |
|-------|-------|
| **ID** | ADR-004 |
| **Status** | Accepted |
| **Context** | V1 uses associative arrays; runtime errors common |
| **Decision** | All UseCase I/O via immutable DTOs |
| **Alternatives** | Array + validation rules only |
| **Consequences** | Boilerplate; strong contracts; OpenAPI alignment |
| **Future review** | Consider DTO codegen from OpenAPI |

---

## ADR-005: Event Dispatch After DB Commit

| Field | Value |
|-------|-------|
| **ID** | ADR-005 |
| **Status** | Accepted |
| **Context** | Premature events cause inconsistent integrations |
| **Decision** | Domain events only after successful transaction commit |
| **Alternatives** | Event sourcing; in-transaction events |
| **Consequences** | Requires transaction-aware dispatcher |
| **Future review** | Outbox pattern if scale demands |

---

## ADR-006: Feature Flag at Company + Terminal Level

| Field | Value |
|-------|-------|
| **ID** | ADR-006 |
| **Status** | Accepted |
| **Context** | Gradual rollout per store |
| **Decision** | `rateb_pos_settings.settings_json.v2.enabled` + terminal override |
| **Alternatives** | Global env only; A/B at CDN |
| **Consequences** | Per-store pilot possible |
| **Future review** | Remove flag when V1 UI retired |

---

## ADR-007: Retail MVP Before Restaurant Profile

| Field | Value |
|-------|-------|
| **ID** | ADR-007 |
| **Status** | Accepted |
| **Context** | Restaurant adds table/kitchen complexity |
| **Decision** | Phase 1 retail only; restaurant Phase 4 |
| **Alternatives** | Build both parallel; restaurant first |
| **Consequences** | Faster time-to-value; restaurant customers wait |
| **Future review** | After retail MVP sign-off |

---

## ADR-008: Offline Default — Banner Only (Phase 1)

| Field | Value |
|-------|-------|
| **ID** | ADR-008 |
| **Status** | Accepted |
| **Context** | Offline sales create inventory/accounting conflicts |
| **Decision** | Phase 1: connectivity banner, block complete when offline |
| **Alternatives** | Full offline queue day one |
| **Consequences** | No offline revenue during outage Phase 1 |
| **Future review** | Phase 3 emergency cash-only mode |

---

## ADR-009: Manager Approval via Short-Lived Token

| Field | Value |
|-------|-------|
| **ID** | ADR-009 |
| **Status** | Accepted |
| **Context** | Manager must authorize without taking over session |
| **Decision** | 60s `ApprovalToken` in header retry pattern |
| **Alternatives** | Manager login swap; permanent elevation |
| **Consequences** | Secure, auditable; two-step UX |
| **Future review** | NFC badge speed vs PIN |

---

## ADR-010: Plugin Hardware SDK Extension

| Field | Value |
|-------|-------|
| **ID** | ADR-010 |
| **Status** | Accepted |
| **Context** | Many printer/terminal vendors in MENA |
| **Decision** | Extend existing `PosHardwareManager` with driver registry |
| **Alternatives** | Single vendor; browser-only print |
| **Consequences** | Driver maintenance burden; flexibility |
| **Future review** | Local print agent vs server-side drivers |

---

## ADR-011: Extension SDK — Hooks Not Core Edits

| Field | Value |
|-------|-------|
| **ID** | ADR-011 |
| **Status** | Accepted |
| **Context** | Customers need custom payment/loyalty |
| **Decision** | Hook registry + `/ext/` API namespace |
| **Alternatives** | Fork per customer; WordPress-style core hacks |
| **Consequences** | Disciplined extension surface |
| **Future review** | Marketplace governance model |

---

## ADR-012: JSON Schema for Settings

| Field | Value |
|-------|-------|
| **ID** | ADR-012 |
| **Status** | Accepted |
| **Context** | `settings_json` unstructured in V1 |
| **Decision** | Versioned JSON Schema under `v2` key |
| **Alternatives** | Relational settings tables only |
| **Consequences** | Easy migration; validation at save |
| **Future review** | Admin UI schema-driven forms |

---

## ADR-013: OpenAPI 3.1 as API Contract Source

| Field | Value |
|-------|-------|
| **ID** | ADR-013 |
| **Status** | Accepted |
| **Context** | V1 APIs undocumented in machine-readable form |
| **Decision** | `POS-V2-OPENAPI.yaml` is contract; CI diff check |
| **Alternatives** | Postman only; codegen from code |
| **Consequences** | Doc drift if not enforced |
| **Future review** | Auto-generate from attributes |

---

## ADR-014: Queue-Backed Integration Side Effects

| Field | Value |
|-------|-------|
| **ID** | ADR-014 |
| **Status** | Accepted |
| **Context** | Print, accounting, inventory post must not block UI |
| **Decision** | Integration events → Laravel queues with DLQ |
| **Alternatives** | Sync everything; external Kafka |
| **Consequences** | Requires Redis/worker ops |
| **Future review** | Kafka if multi-region |

---

## ADR-015: Preserve data-pos-* Hooks

| Field | Value |
|-------|-------|
| **ID** | ADR-015 |
| **Status** | Accepted |
| **Context** | V1 JS and extensions rely on DOM hooks |
| **Decision** | V2 views must emit same `data-pos-*` attributes |
| **Alternatives** | Clean break |
| **Consequences** | Slight markup constraint |
| **Future review** | Deprecate hooks in V3 |

---

## ADR-016: No Inline CSS/JS

| Field | Value |
|-------|-------|
| **ID** | ADR-016 |
| **Status** | Accepted |
| **Context** | V1 has large monolithic assets; hard to maintain |
| **Decision** | Strict separation; modular assets |
| **Alternatives** | Inline for speed |
| **Consequences** | More files; better caching and CSP |
| **Future review** | Bundle strategy (Vite) |

---

## ADR-017: Pharmacy Regulatory Scope — SA-SFDA First

| Field | Value |
|-------|-------|
| **ID** | ADR-017 |
| **Status** | Proposed |
| **Context** | Pharmacy rules vary by country |
| **Decision** | Initial pharmacy profile targets SA-SFDA; extensible region config |
| **Alternatives** | Generic pharmacy; defer pharmacy |
| **Consequences** | Other regions need extension |
| **Future review** | Legal sign-off before Phase 5 |

---

## ADR-018: Card Terminal via N-Genius Driver First

| Field | Value |
|-------|-------|
| **ID** | ADR-018 |
| **Status** | Proposed |
| **Context** | Card payments block retail go-live for many clients |
| **Decision** | First terminal driver: `ngenius.terminal.1` |
| **Alternatives** | Geidea first; manual card entry |
| **Consequences** | Geidea clients wait or use cash |
| **Future review** | After N-Genius UAT |

---

## ADR-019: Audit Table Append-Only

| Field | Value |
|-------|-------|
| **ID** | ADR-019 |
| **Status** | Accepted |
| **Context** | Compliance requires immutable audit |
| **Decision** | `rateb_pos_audit_events` append-only; no UPDATE/DELETE |
| **Alternatives** | Log files only |
| **Consequences** | Storage growth; archival job needed |
| **Future review** | Cold storage after 2 years |

---

## ADR-020: Idempotency on Complete Sale

| Field | Value |
|-------|-------|
| **ID** | ADR-020 |
| **Status** | Accepted |
| **Context** | Double-tap Charge / network retry duplicates orders |
| **Decision** | Required `idempotency_key` on CompleteSale |
| **Alternatives** | Client-side debounce only |
| **Consequences** | Client must generate UUID |
| **Future review** | Extend to returns |

---

*End of POS-V2-DECISIONS.md*
