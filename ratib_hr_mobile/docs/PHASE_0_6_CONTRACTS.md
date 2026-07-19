# Phase 0.6 — Architecture Contracts

Interface lock completed. **No implementations. No ERP connection. No Login.**

See:

- `lib/core/contracts/`
- `lib/core/env/`
- `lib/core/di/`
- `lib/core/errors/`
- `lib/core/adapters/` (empty — Phase 1+)

## Isolation

| Rule | Status |
|------|--------|
| Feature → contracts only | Required |
| Feature → feature | Forbidden |
| Flutter → ERP PHP/JS | Forbidden |
| Flutter → ERP | Via adapters implementing contracts only |
