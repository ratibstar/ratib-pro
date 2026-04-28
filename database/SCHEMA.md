# Enterprise Accounting Database Schema

## Entity Relationship Overview

```
countries (1) ──< agencies (n)
agencies (1) ──< customers (n)
agencies (1) ──< wallets (n)
agencies (1) ──< ledger_accounts (n)
agencies (1) ──< ledger_journals (n)
agencies (1) ──< settlements (n)
agencies (1) ──< commissions (n)

subscription_plans (1) ──< subscriptions (n)
customers (1) ──< subscriptions (n)
customers (1) ──< wallets (n) [holder_type='customer']
customers (1) ──< transactions (n)
wallets (1) ──< transactions (n)
transactions (1) ──< commissions (n)

ledger_accounts (1) ──< ledger_accounts (n) [parent]
ledger_accounts (1) ──< ledger_entries (n)
ledger_journals (1) ──< ledger_entries (n)
```

## Tables

| Table | Purpose | Soft Deletes | Immutable |
|-------|---------|--------------|-----------|
| countries | ISO 3166 countries | ✓ | |
| agencies | Per-country agencies | ✓ | |
| customers | Agency customers | ✓ | |
| subscription_plans | Global plans | ✓ | |
| subscriptions | Customer subscriptions | ✓ | |
| wallets | Customer/agency balances | ✓ | |
| transactions | Payment transactions | ✓ | |
| commissions | 10% agency commission | ✓ | |
| ledger_accounts | Chart of accounts | ✓ | |
| ledger_journals | Journal header | | |
| ledger_entries | Double-entry lines | | ✓ |
| settlements | Agency payouts | ✓ | |

## Financial Rules

- **Agency commission:** 10% (config: `config/commission.php`)
- **Money fields:** `decimal(15,2)`
- **Double entry:** `ledger_journals` + `ledger_entries` (sum debits = sum credits per journal)
- **Immutable ledger:** `ledger_entries` has no `updated_at`; use reversal entries

## Wallets (holder_type)

- `customer` — customer balance (holder_id = customer_id)
- `commission` — agency commission wallet (holder_id = agency_id)
