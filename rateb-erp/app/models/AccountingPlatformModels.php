<?php

declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

/** Phase 16A — additive accounting platform models (online foundation). */

final class AccountingCurrency extends Model
{
    protected string $table = 'rateb_accounting_currencies';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'symbol',
        'decimal_places', 'is_base', 'status', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class AccountingExchangeRate extends Model
{
    protected string $table = 'rateb_accounting_exchange_rates';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'from_currency', 'to_currency', 'rate',
        'rate_date', 'source', 'status', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class AccountingTaxCode extends Model
{
    protected string $table = 'rateb_accounting_tax_codes';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'rate_percent',
        'tax_type', 'recoverable', 'account_id', 'status', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class AccountingProfitCenter extends Model
{
    protected string $table = 'rateb_accounting_profit_centers';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'parent_id',
        'status', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class AccountingRecurringJournal extends Model
{
    protected string $table = 'rateb_accounting_recurring_journals';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'description',
        'frequency', 'next_run_date', 'end_date', 'currency_code', 'status',
        'last_generated_entry_id', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class AccountingRecurringJournalLine extends Model
{
    protected string $table = 'rateb_accounting_recurring_journal_lines';
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'company_id', 'recurring_journal_id', 'account_id', 'cost_center_id', 'profit_center_id',
        'debit', 'credit', 'memo', 'sort_order',
    ];
}

final class AccountingStatusHistory extends Model
{
    protected string $table = 'rateb_accounting_status_history';
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'company_id', 'journal_entry_id', 'from_status', 'to_status', 'reason', 'created_by',
    ];
}

final class AccountingActivity extends Model
{
    protected string $table = 'rateb_accounting_activities';
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'company_id', 'journal_entry_id', 'entity_type', 'entity_id', 'action', 'summary', 'created_by',
    ];
}

final class AccountingDocumentLink extends Model
{
    protected string $table = 'rateb_accounting_document_links';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'journal_entry_id', 'entity_type', 'entity_id',
        'document_id', 'file_name', 'mime_type', 'notes', 'created_by', 'deleted_at',
    ];
}
