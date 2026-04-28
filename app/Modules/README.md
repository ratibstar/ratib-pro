# Accounting SaaS - Modular Structure

## Modules

| Module | Purpose |
|--------|---------|
| **Core** | Shared utilities, config, base classes |
| **Agency** | Agency management, multi-tenant context |
| **Subscription** | Plans, billing cycles |
| **Payment** | Payment processing, gateways |
| **Commission** | Commission rules, calculations |
| **Wallet** | Wallet balances, transactions |
| **Ledger** | Double-entry accounting, journal entries |
| **Settlement** | Payouts, reconciliation |
| **Reporting** | Reports, exports |
| **Admin** | Admin panel, system config |

## Structure per Module

- `Controllers/` - HTTP request handlers
- `Models/` - Eloquent models
- `Services/` - Business logic
- `Repositories/` - Data access
- `Providers/` - Module service provider

## Multi-country / Multi-currency

Config: `config/currencies.php`, `config/countries.php`
