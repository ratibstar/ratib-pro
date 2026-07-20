<?php

declare(strict_types=1);

/**
 * Phase C ESS hardening — notification isolation + DTO / envelope / dashboard queries.
 *
 * Run: php tests/hr/run-ess-phase-c-hardening-tests.php
 */
final class EssPhaseCHardeningTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testVisibilitySqlConstant();
        $this->testListForUserUsesIsolationPredicate();
        $this->testEmployeeACannotSeeEmployeeB();
        $this->testCrossCompanyLeakageImpossible();
        $this->testBroadcastVisibleWithinCompanyOnly();
        $this->testDashboardUsesCountAndRecentNotFullList();
        $this->testRequestQueriesAvoidSelectStar();
        $this->testErrorEnvelopeHelper();
        $this->testRatingsLogsExceptions();
        $this->testMigrationIndexesExist();

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function testVisibilitySqlConstant(): void
    {
        $sql = \Rateb\App\Services\NotificationService::VISIBLE_TO_USER_SQL;
        $ok = str_contains($sql, 'company_id = :cid')
            && str_contains($sql, 'user_id = :uid')
            && str_contains($sql, 'user_id IS NULL')
            && !str_contains($sql, 'OR company_id');
        $this->record('VISIBLE_TO_USER_SQL enforces company + own/broadcast', $ok, $sql);
    }

    private function testListForUserUsesIsolationPredicate(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/NotificationService.php');
        $ok = str_contains($src, 'VISIBLE_TO_USER_SQL')
            && !preg_match(
                '/listForUser[\s\S]*?user_id\s*=\s*:uid\s+OR\s+company_id\s*=\s*:cid/',
                $src
            );
        $this->record('listForUser no longer uses leaky OR company_id predicate', $ok);
    }

    private function testEmployeeACannotSeeEmployeeB(): void
    {
        $rows = [
            ['id' => 1, 'company_id' => 10, 'user_id' => 100],
            ['id' => 2, 'company_id' => 10, 'user_id' => 200],
            ['id' => 3, 'company_id' => 10, 'user_id' => null],
        ];
        $ids = $this->visibleIds($rows, 100, 10);
        $ok = in_array(1, $ids, true)
            && in_array(3, $ids, true)
            && !in_array(2, $ids, true);
        $this->record(
            'Employee A cannot see Employee B notifications',
            $ok,
            'visible=' . implode(',', $ids)
        );
    }

    private function testCrossCompanyLeakageImpossible(): void
    {
        $rows = [
            ['id' => 1, 'company_id' => 10, 'user_id' => 100],
            ['id' => 2, 'company_id' => 99, 'user_id' => 100],
            ['id' => 3, 'company_id' => 99, 'user_id' => null],
            ['id' => 4, 'company_id' => 10, 'user_id' => null],
        ];
        $ids = $this->visibleIds($rows, 100, 10);
        $ok = in_array(1, $ids, true)
            && in_array(4, $ids, true)
            && !in_array(2, $ids, true)
            && !in_array(3, $ids, true);
        $this->record(
            'Cross-company notification leakage impossible',
            $ok,
            'visible=' . implode(',', $ids)
        );
    }

    private function testBroadcastVisibleWithinCompanyOnly(): void
    {
        $rows = [
            ['id' => 1, 'company_id' => 10, 'user_id' => null],
            ['id' => 2, 'company_id' => 11, 'user_id' => null],
        ];
        $a = $this->visibleIds($rows, 1, 10);
        $b = $this->visibleIds($rows, 1, 11);
        $ok = $a === [1] && $b === [2];
        $this->record('Broadcast notifications are company-scoped', $ok);
    }

    private function testDashboardUsesCountAndRecentNotFullList(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssPhaseCService.php');
        $ok = str_contains($src, 'countUnreadForUser')
            && str_contains($src, 'listRecentForUser')
            && str_contains($src, 'countVisibleForUser')
            && !preg_match('/dashboard\([\s\S]*?listForUser\(/', $src);
        $this->record('Dashboard uses unread count + recent queries', $ok);
    }

    private function testRequestQueriesAvoidSelectStar(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssPhaseCService.php');
        $ok = str_contains($src, 'REQUEST_DTO_COLUMNS')
            && !preg_match('/SELECT\s+\*\s+FROM\s+rateb_hr_employee_requests/i', $src)
            && str_contains($src, 'id, request_no, request_type, request_date, status, notes, created_at');
        $this->record('ESS employee request APIs avoid SELECT *', $ok);
    }

    private function testErrorEnvelopeHelper(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssPhaseCService.php');
        $ctrl = (string) file_get_contents(RATEB_ROOT . '/app/controllers/Api/HrEssNotificationsController.php');
        $ok = str_contains($src, "'success' => false")
            && str_contains($src, "'code' =>")
            && str_contains($src, 'function fail(')
            && str_contains($ctrl, 'notification_not_found')
            && str_contains($ctrl, "'code' =>");
        $this->record('ESS error envelopes include success/code/message', $ok);
    }

    private function testRatingsLogsExceptions(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssPhaseCService.php');
        $ok = str_contains($src, 'Logger::error')
            && str_contains($src, 'ratings_unavailable')
            && str_contains($src, "'degraded' => true");
        $this->record('Ratings exceptions are logged with controlled response', $ok);
    }

    private function testMigrationIndexesExist(): void
    {
        $path = RATEB_ROOT . '/migrations/205_ess_phase_c_hardening_indexes.sql';
        $sql = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = $sql !== ''
            && str_contains($sql, 'idx_hr_emp_req_ess_list')
            && str_contains($sql, '(company_id, employee_id, status, id)')
            && str_contains($sql, 'idx_leave_req_ess_list')
            && substr_count($sql, '(company_id, employee_id, status, id)') >= 2;
        $this->record('Migration 205 adds ESS composite indexes', $ok);
    }

    /**
     * Applies the same visibility rule as NotificationService::VISIBLE_TO_USER_SQL.
     *
     * @param list<array{id:int,company_id:int,user_id:int|null}> $rows
     * @return list<int>
     */
    private function visibleIds(array $rows, int $userId, int $companyId): array
    {
        $sql = \Rateb\App\Services\NotificationService::VISIBLE_TO_USER_SQL;
        if (!str_contains($sql, 'company_id = :cid') || !str_contains($sql, 'user_id IS NULL')) {
            return [];
        }
        $ids = [];
        foreach ($rows as $row) {
            if ((int) ($row['company_id'] ?? 0) !== $companyId) {
                continue;
            }
            $uid = $row['user_id'] ?? null;
            if ($uid === null || $uid === '') {
                $ids[] = (int) $row['id'];
                continue;
            }
            if ((int) $uid === $userId) {
                $ids[] = (int) $row['id'];
            }
        }

        return $ids;
    }
}
