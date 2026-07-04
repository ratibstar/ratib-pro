<?php
declare(strict_types=1);

/**
 * Example usage — documentation only, not executed in production.
 *
 * Enable gateway: define('ACCOUNTING_GATEWAY_ENABLED', true);
 * or ACCOUNTING_GATEWAY_ENABLED=1 in environment.
 */

require_once dirname(__DIR__) . '/Support/post_accounting_event.php';

// -------------------------------------------------------------------------
// 1. rateb-erp — after AccountingService::createPostedEntry (legacy hook)
// -------------------------------------------------------------------------
/*
postAccountingEvent([
    'source_system' => 'rateb-erp',
    'event_type' => 'invoice',
    'company_id' => 12,
    'branch_id' => 3,
    'amount' => 1500.00,
    'currency' => 'SAR',
    'debit_account' => '1100',
    'credit_account' => '4100',
    'reference_type' => 'invoice',
    'reference_id' => 884,
    'metadata' => [
        'legacy_write' => true,
        'journal_entry_id' => 5012,
        'entry_date' => '2026-07-04',
        'description' => 'Invoice #884 posted',
    ],
]);
*/

// -------------------------------------------------------------------------
// 2. main-site — after api/accounting/journal-entries.php insert
// -------------------------------------------------------------------------
/*
postAccountingEvent([
    'source_system' => 'main-site',
    'event_type' => 'journal',
    'company_id' => 7,
    'branch_id' => null,
    'amount' => 500.00,
    'currency' => 'SAR',
    'debit_account' => '1010',
    'credit_account' => '2010',
    'reference_type' => 'journal_entry',
    'reference_id' => 991,
    'metadata' => [
        'legacy_write' => true,
        'journal_entry_id' => 991,
    ],
]);
*/

// -------------------------------------------------------------------------
// 3. control-panel — after journal_entry_create
// -------------------------------------------------------------------------
/*
postAccountingEvent([
    'source_system' => 'control-panel',
    'event_type' => 'journal',
    'company_id' => 2,
    'branch_id' => null,
    'amount' => 250.00,
    'currency' => 'SAR',
    'debit_account' => '1000',
    'credit_account' => '4000',
    'reference_type' => 'control_journal_entry',
    'reference_id' => 44,
    'metadata' => [
        'legacy_write' => true,
        'journal_entry_id' => 44,
        'reference' => 'GL-00044',
        'country_id' => 2,
    ],
]);
*/

// -------------------------------------------------------------------------
// 4. Modules/Ledger — after LedgerService::recordEntry
// -------------------------------------------------------------------------
/*
postAccountingEvent([
    'source_system' => 'ledger',
    'event_type' => 'payment',
    'company_id' => 9,
    'branch_id' => null,
    'amount' => 99.00,
    'currency' => 'SAR',
    'debit_account' => 'wallet_cash',
    'credit_account' => 'revenue_subscriptions',
    'reference_type' => 'payment',
    'reference_id' => 12001,
    'metadata' => [
        'legacy_write' => true,
        'ledger_journal_id' => 330,
        'agency_id' => 9,
    ],
]);
*/

// -------------------------------------------------------------------------
// 5. Gateway-first write (future — no legacy_write flag)
// -------------------------------------------------------------------------
/*
$result = postAccountingEvent([
    'source_system' => 'rateb-erp',
    'event_type' => 'journal',
    'company_id' => 12,
    'branch_id' => null,
    'amount' => 100.00,
    'currency' => 'SAR',
    'debit_account' => '1100',
    'credit_account' => '4100',
    'reference_type' => 'manual_adjustment',
    'reference_id' => 'adj-2026-0704-001',
    'metadata' => [
        'entry_date' => '2026-07-04',
        'description' => 'Manual adjustment via gateway',
    ],
]);
*/

// -------------------------------------------------------------------------
// 6. Laravel container resolution
// -------------------------------------------------------------------------
/*
use App\Accounting\Core\AccountingGateway;

$gateway = app(AccountingGateway::class);
$result = $gateway->post([...]);
*/
