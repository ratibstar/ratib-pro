<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/** Shared GET filters for admin oversight list pages. */
final class OversightFilterService
{
    /** @return array{company_id: int, status: string, date_from: string, date_to: string} */
    public function parse(): array
    {
        return [
            'company_id' => max(0, (int) ($_GET['company_id'] ?? 0)),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ];
    }

    /** @param array{company_id: int, status: string, date_from: string, date_to: string} $filters */
    public function applyCompany(string &$sql, array &$params, string $column, array $filters): void
    {
        if ($filters['company_id'] > 0) {
            $sql .= ' AND ' . $column . ' = :_of_cid';
            $params['_of_cid'] = $filters['company_id'];
        }
    }

    /** @param array{company_id: int, status: string, date_from: string, date_to: string} $filters */
    public function applyStatus(string &$sql, array &$params, string $column, array $filters): void
    {
        if ($filters['status'] !== '') {
            $sql .= ' AND ' . $column . ' = :_of_st';
            $params['_of_st'] = $filters['status'];
        }
    }

    /** @param array{company_id: int, status: string, date_from: string, date_to: string} $filters */
    public function applyDateRange(string &$sql, array &$params, string $dateColumn, array $filters): void
    {
        if ($filters['date_from'] !== '') {
            $sql .= ' AND ' . $dateColumn . ' >= :_of_df';
            $params['_of_df'] = $filters['date_from'];
        }
        if ($filters['date_to'] !== '') {
            $sql .= ' AND ' . $dateColumn . ' <= :_of_dt';
            $params['_of_dt'] = $filters['date_to'];
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function companies(): array
    {
        return (new BillingService())->companyOptions();
    }
}
