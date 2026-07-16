# P14-00 — Phase 14 Accounting Charter

**Status:** ACTIVE (implementation)  
**Architecture Freeze:** AF 2.1 + AF 2.1.1 ACTIVE  
**Module:** `accounting` (`RatebOfflineV2Accounting` `1.0.0-phase14`)

## Mandate

Implement Offline V2 **Accounting BusinessModule** only.

## Dependencies (mandatory)

- `identity >= 1.0.0`
- `inventory >= 1.0.0`

## Owns

- Chart of Accounts (`acct.account`)
- Journal Entries + Lines (`acct.journal`)
- Fiscal Periods (`acct.fiscal_period`)
- Cash Vouchers (`acct.voucher`)
- Cost Centers (`acct.cost_center`)
- AccountMap (`acct.account_map`)
- Tax Policy / Currency Policy
- PostingPort (`createPostedEntry`)
- Financial Reports (TB / BS / P&L)

## Must NOT

- Modify Platform / HCI / Runtime / Router / Shell / Sync / SDK / BM Framework
- Modify Identity / Inventory / Procurement / Sales / Offline V1
- Own or mutate inventory balances, batches, reservations, valuation
- Execute inventory SQL or read/write `inv.*`
- Store passwords, hashes, cookies, tokens, TOTP secrets
- Hardcode account codes inside PostingPort paths (use AccountMap)

## Integration

- Auth/RBAC: `module.identity.*` only
- Inventory reads: `module.inventory.*` (especially `valuation`) or published inventory events
- Sync: accounting business events only (`acct.*`)

## Phase boundary

STOP after Accounting Enterprise Complete. Do not start the next ERP module.
