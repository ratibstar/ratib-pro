# RATEB POS V2 — Documentation Index

Pre-implementation engineering documentation for RATEB POS V2.

## Documents

| # | Document | Description |
|---|----------|-------------|
| 1 | [POS-V2-ARCHITECTURE.md](./POS-V2-ARCHITECTURE.md) | Domain architecture, bounded contexts, flows |
| 2 | [POS-V2-DOMAIN-MODEL.md](./POS-V2-DOMAIN-MODEL.md) | DDD tactical design per domain |
| 3 | [POS-V2-EVENT-ARCHITECTURE.md](./POS-V2-EVENT-ARCHITECTURE.md) | Domain, integration, hardware, audit events |
| 4 | [POS-V2-QUEUE-ARCHITECTURE.md](./POS-V2-QUEUE-ARCHITECTURE.md) | Async queues, retry, DLQ |
| 5 | [POS-V2-HARDWARE-SDK.md](./POS-V2-HARDWARE-SDK.md) | Plugin-based hardware drivers |
| 6 | [POS-V2-EXTENSION-SDK.md](./POS-V2-EXTENSION-SDK.md) | Third-party extension model |
| 7 | [POS-V2-CONFIGURATION-SCHEMA.md](./POS-V2-CONFIGURATION-SCHEMA.md) | JSON Schema for `rateb_pos_settings` |
| 8 | [POS-V2-USECASE-CATALOG.md](./POS-V2-USECASE-CATALOG.md) | All business use cases |
| 9 | [POS-V2-DTO-CATALOG.md](./POS-V2-DTO-CATALOG.md) | Request/Response DTO definitions |
| 10 | [POS-V2-OPENAPI.yaml](./POS-V2-OPENAPI.yaml) | OpenAPI 3.1 REST contract |
| 11 | [POS-V2-CODE-STANDARDS.md](./POS-V2-CODE-STANDARDS.md) | Mandatory coding standards |
| 12 | [POS-V2-DECISIONS.md](./POS-V2-DECISIONS.md) | Architecture Decision Records |
| 13 | [FINAL ARCHITECTURE AUDIT.md](./FINAL%20ARCHITECTURE%20AUDIT.md) | Audit, risks, implementation order |

## Phase 1 Implementation Planning

| Document | Description |
|----------|-------------|
| [PHASE1_IMPLEMENTATION_AUDIT.md](./implementation/PHASE1_IMPLEMENTATION_AUDIT.md) | Repository audit — V1 inventory |
| [PHASE1_TASK_BREAKDOWN.md](./implementation/PHASE1_TASK_BREAKDOWN.md) | 72 atomic tasks |
| [PHASE1_FOLDER_STRUCTURE.md](./implementation/PHASE1_FOLDER_STRUCTURE.md) | Planned file tree (~152 files) |
| [COMPATIBILITY_REPORT.md](./implementation/COMPATIBILITY_REPORT.md) | V1 compatibility verification |
| [IMPLEMENTATION_ORDER.md](./implementation/IMPLEMENTATION_ORDER.md) | 4-sprint delivery plan |

## Related

- [schema-proposal.sql](../schema-proposal.sql) — Reference only (core tables exist in migrations 154–168)

## Rules

- Additive to V1 POS only
- No production code until Phase 1 kickoff sign-off
- V1 APIs and schema preserved
