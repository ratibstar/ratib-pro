<?php
declare(strict_types=1);

/**
 * Phase 3 — Event-driven accounting core examples (documentation / admin CLI).
 */

require_once dirname(__DIR__) . '/Support/post_accounting_event.php';
require_once dirname(__DIR__, 3) . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register(dirname(__DIR__, 2));

use App\Accounting\Replay\AccountingReplayEngine;
use App\Accounting\Reporting\AccountingReportService;

// -------------------------------------------------------------------------
// REPLAY EXAMPLE (admin only — requires ACCOUNTING_REPLAY_ENABLED=1)
// -------------------------------------------------------------------------
/*
$engine = new AccountingReplayEngine();
$result = $engine->replay([
    'source_system' => 'rateb-erp',
    'event_type' => 'journal',
    'company_id' => 12,
    'from_date' => '2026-01-01',
    'to_date' => '2026-07-04',
    'status' => 'failed',  // optional: only replay failed events
    'force' => false,      // true = clear idempotency and reprocess processed events
]);
print_r($result->toArray());
*/

// -------------------------------------------------------------------------
// UNIFIED REPORT QUERY FLOW (read-only)
// -------------------------------------------------------------------------
/*
$reports = new AccountingReportService();

$trialBalance = $reports->trialBalance([
    'company_id' => 12,
    'from_date' => '2026-01-01',
    'to_date' => '2026-07-04',
]);
// Returns rows from rateb_*, financial_*, control_*, ledger_* normalized to AccountingReportRow

$pl = $reports->profitAndLoss(['company_id' => 12, 'from_date' => '2026-01-01']);
$bs = $reports->balanceSheet(['company_id' => 12]);
$cf = $reports->cashFlow(['company_id' => 12, 'from_date' => '2026-01-01']);
*/

// -------------------------------------------------------------------------
// EVENT STORE FLOW (when ACCOUNTING_EVENT_STORE_ENABLED=1)
// -------------------------------------------------------------------------
/*
define('ACCOUNTING_GATEWAY_ENABLED', true);
define('ACCOUNTING_EVENT_STORE_ENABLED', true);

postAccountingEvent([
    'source_system' => 'rateb-erp',
    'event_type' => 'journal',
    'company_id' => 12,
    'branch_id' => null,
    'amount' => 250.00,
    'currency' => 'SAR',
    'debit_account' => '1100',
    'credit_account' => '4100',
    'reference_type' => 'manual',
    'reference_id' => 'gw-001',
    'metadata' => ['description' => 'Gateway-first with event store'],
]);
// Flow: validate → persist accounting_events → idempotency check → adapter → status=processed → audit log
*/
