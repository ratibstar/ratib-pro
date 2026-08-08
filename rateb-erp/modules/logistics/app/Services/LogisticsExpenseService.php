<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Logistics\Contracts\AccountingPoster;
use Rateb\App\Logistics\Policies\LogisticsStatusPolicy;
use Rateb\App\Logistics\Repositories\LogisticsExpenseRepository;
use Rateb\App\Logistics\Services\Integration\ErpAccountingPoster;

/**
 * Posts logistics expenses through AccountingService::createPostedEntry.
 * source_type = logistics_expense; journal_entry_id stored on the expense row.
 */
final class LogisticsExpenseService
{
    public const SOURCE_TYPE = 'logistics_expense';

    public function __construct(
        private LogisticsExpenseRepository $expenses = new LogisticsExpenseRepository(),
        private AccountingPoster $accounting = new ErpAccountingPoster(),
        private LogisticsStatusService $status = new LogisticsStatusService(),
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function listForCompany(int $companyId, int $limit = 200, int $offset = 0): array
    {
        return $this->expenses->listForCompany($companyId, $limit, $offset);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $companyId): ?array
    {
        return $this->expenses->find($id, $companyId);
    }

    /** @param array<string, mixed> $data */
    public function create(int $companyId, array $data): int
    {
        if ($companyId < 1) {
            throw new \RuntimeException(__('select_company_ops'));
        }
        TenantContext::setCompanyId($companyId);
        $payload = $this->normalize($companyId, $data);
        $payload['status'] = 'draft';
        $payload['created_by'] = $this->userId();

        return $this->expenses->create($companyId, $payload);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, int $companyId, array $data): bool
    {
        $existing = $this->expenses->find($id, $companyId);
        if ($existing === null) {
            throw new \RuntimeException(__('no_records'));
        }
        if ((string) ($existing['status'] ?? '') !== 'draft') {
            throw new \RuntimeException(__('logistics_expense_locked'));
        }
        $payload = $this->normalize($companyId, $data);
        $payload['updated_by'] = $this->userId();
        unset($payload['status']);

        return $this->expenses->update($id, $companyId, $payload);
    }

    public function delete(int $id, int $companyId): bool
    {
        $existing = $this->expenses->find($id, $companyId);
        if ($existing === null) {
            throw new \RuntimeException(__('no_records'));
        }
        if ((string) ($existing['status'] ?? '') !== 'draft') {
            throw new \RuntimeException(__('logistics_expense_locked'));
        }

        return $this->expenses->delete($id, $companyId);
    }

    /**
     * @return array{ok:bool,expense_id:int,journal_entry_id:int}
     */
    public function post(int $expenseId, int $companyId): array
    {
        if ($companyId < 1 || $expenseId < 1) {
            throw new \InvalidArgumentException('logistics_invalid_context');
        }
        TenantContext::setCompanyId($companyId);

        $expense = $this->expenses->find($expenseId, $companyId);
        if ($expense === null) {
            throw new \RuntimeException(__('no_records'));
        }

        $status = (string) ($expense['status'] ?? 'draft');
        if ($status === 'posted' || (int) ($expense['journal_entry_id'] ?? 0) > 0) {
            throw new \RuntimeException(__('logistics_expense_already_posted'));
        }
        if ($status === 'cancelled') {
            throw new \RuntimeException(__('logistics_expense_cancelled'));
        }
        if ($this->accounting->journalExistsForSource(self::SOURCE_TYPE, $expenseId)) {
            throw new \RuntimeException(__('logistics_expense_already_posted'));
        }

        $amount = round((float) ($expense['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new \RuntimeException(__('logistics_expense_amount_invalid'));
        }

        $this->accounting->ensureDefaultAccounts($companyId);
        $expenseAccountId = $this->accountForType($companyId, (string) ($expense['expense_type'] ?? 'other'));
        $cashAccountId = $this->accounting->accountIdByCode($companyId, '1100')
            ?? $this->accounting->ensureCompanyCoaCode($companyId, '1100');
        if ($expenseAccountId === null || $cashAccountId === null) {
            throw new \RuntimeException(__('logistics_expense_accounts_missing'));
        }

        $date = (string) ($expense['expense_date'] ?? date('Y-m-d'));
        $type = (string) ($expense['expense_type'] ?? 'other');
        $desc = trim((string) ($expense['description'] ?? ''));
        if ($desc === '') {
            $desc = 'Logistics expense #' . $expenseId . ' (' . $type . ')';
        }

        $entryId = $this->accounting->createPostedEntry(
            $companyId,
            self::SOURCE_TYPE,
            $expenseId,
            [
                ['account_id' => $expenseAccountId, 'debit' => $amount, 'credit' => 0.0, 'memo' => $desc],
                ['account_id' => $cashAccountId, 'debit' => 0.0, 'credit' => $amount, 'memo' => $desc],
            ],
            $desc,
            $desc,
            $date
        );
        if ($entryId === null || $entryId < 1) {
            throw new \RuntimeException(__('logistics_expense_post_failed'));
        }

        $this->expenses->update($expenseId, $companyId, [
            'journal_entry_id' => $entryId,
            'updated_by' => $this->userId(),
        ]);
        $this->status->transition(
            $companyId,
            LogisticsStatusPolicy::ENTITY_EXPENSE,
            $expenseId,
            'posted',
            'accounting_post'
        );

        return [
            'ok' => true,
            'expense_id' => $expenseId,
            'journal_entry_id' => $entryId,
        ];
    }

    private function accountForType(int $companyId, string $expenseType): ?int
    {
        $code = match ($expenseType) {
            'driver_payment' => '5300',
            'fuel', 'maintenance', 'transport_cost', 'other' => '5800',
            default => '5800',
        };

        return $this->accounting->accountIdByCode($companyId, $code);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function normalize(int $companyId, array $data): array
    {
        $type = (string) ($data['expense_type'] ?? 'other');
        if (!in_array($type, ['fuel', 'maintenance', 'driver_payment', 'transport_cost', 'other'], true)) {
            $type = 'other';
        }
        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new \RuntimeException(__('logistics_expense_amount_invalid'));
        }
        $date = trim((string) ($data['expense_date'] ?? date('Y-m-d')));
        if ($date === '') {
            $date = date('Y-m-d');
        }

        return [
            'company_id' => $companyId,
            'branch_id' => ((int) ($data['branch_id'] ?? 0)) ?: null,
            'trip_id' => ((int) ($data['trip_id'] ?? 0)) ?: null,
            'vehicle_id' => ((int) ($data['vehicle_id'] ?? 0)) ?: null,
            'driver_id' => ((int) ($data['driver_id'] ?? 0)) ?: null,
            'expense_type' => $type,
            'amount' => $amount,
            'currency' => strtoupper(trim((string) ($data['currency'] ?? 'SAR'))) ?: 'SAR',
            'expense_date' => $date,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
        ];
    }

    private function userId(): ?int
    {
        $id = (int) (SessionManager::get('rateb_user_id') ?? 0);

        return $id > 0 ? $id : null;
    }
}
