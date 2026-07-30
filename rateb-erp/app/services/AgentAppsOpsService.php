<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;

/**
 * Platform Agent Apps console — read/write over ESS / HR / notification / mobile config data.
 */
final class AgentAppsOpsService
{
    private function companyScopeSql(string $alias = ''): array
    {
        $col = $alias !== '' ? "{$alias}.company_id" : 'company_id';
        if (TenantContext::isSuperAdmin()
            || (function_exists('rateb_is_super_admin') && rateb_is_super_admin())) {
            return ['', []];
        }
        $cid = (int) (TenantContext::companyId() ?? 0);
        if ($cid < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $cid = (int) rateb_resolve_ops_company_id();
        }
        if ($cid < 1) {
            $cid = (int) SessionManager::get('rateb_company_id', 0);
        }
        if ($cid < 1) {
            return ['', []];
        }

        return [" AND {$col} = :ops_cid", ['ops_cid' => $cid]];
    }

    /**
     * @return array{items:list<array<string,mixed>>,total:int,pending:int}
     */
    public function listComplaints(int $limit = 50, int $offset = 0, string $status = '', string $type = ''): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        [$scopeSql, $scopeParams] = $this->companyScopeSql('r');
        $params = $scopeParams;
        $where = "r.request_type IN ('inquiry','complaint')" . $scopeSql;
        if ($status !== '' && in_array($status, ['pending', 'approved', 'rejected', 'cancelled'], true)) {
            $where .= ' AND r.status = :st';
            $params['st'] = $status;
        }
        if ($type !== '' && in_array($type, ['inquiry', 'complaint'], true)) {
            $where .= ' AND r.request_type = :rtype';
            $params['rtype'] = $type;
        }

        try {
            $pdo = Database::connection();
            $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM rateb_hr_employee_requests r WHERE {$where}");
            $totalStmt->execute($params);
            $total = (int) $totalStmt->fetchColumn();

            $pendingParams = $scopeParams;
            $pendingWhere = "r.request_type IN ('inquiry','complaint') AND r.status = 'pending'" . $scopeSql;
            $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM rateb_hr_employee_requests r WHERE {$pendingWhere}");
            $pendingStmt->execute($pendingParams);
            $pending = (int) $pendingStmt->fetchColumn();

            $sql = "SELECT r.id, r.company_id, r.request_no, r.employee_id, r.request_type, r.request_date,
                           r.status, r.notes, r.created_at, r.processed_at,
                           c.name AS company_name,
                           e.name AS employee_name
                    FROM rateb_hr_employee_requests r
                    LEFT JOIN rateb_companies c ON c.id = r.company_id
                    LEFT JOIN rateb_employees e ON e.id = r.employee_id
                    WHERE {$where}
                    ORDER BY r.id DESC
                    LIMIT {$limit} OFFSET {$offset}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return ['items' => $items, 'total' => $total, 'pending' => $pending];
        } catch (\Throwable $e) {
            error_log('AgentAppsOpsService::listComplaints: ' . $e->getMessage());

            return ['items' => [], 'total' => 0, 'pending' => 0];
        }
    }

    public function setComplaintStatus(int $id, string $action, int $userId): bool
    {
        if ($id < 1 || !in_array($action, ['approve', 'reject'], true)) {
            return false;
        }
        $state = $action === 'approve' ? 'approved' : 'rejected';
        [$scopeSql, $scopeParams] = $this->companyScopeSql('');
        $params = array_merge($scopeParams, [
            'st' => $state,
            'uid' => $userId > 0 ? $userId : null,
            'id' => $id,
            'pending' => 'pending',
        ]);
        $sql = 'UPDATE rateb_hr_employee_requests
                SET status = :st, processed_by = :uid, processed_at = NOW()
                WHERE id = :id AND status = :pending
                  AND request_type IN (\'inquiry\',\'complaint\')' . $scopeSql;
        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute($params);

            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            error_log('AgentAppsOpsService::setComplaintStatus: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * @return array{items:list<array<string,mixed>>,total:int,avg:string}
     */
    public function listRatings(int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        [$scopeSql, $scopeParams] = $this->companyScopeSql('r');
        $params = $scopeParams;
        $where = 'r.deleted_at IS NULL' . $scopeSql;

        try {
            $pdo = Database::connection();
            $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM rateb_hrm_performance_reviews r WHERE {$where}");
            $totalStmt->execute($params);
            $total = (int) $totalStmt->fetchColumn();

            $avgStmt = $pdo->prepare(
                "SELECT AVG(r.overall_score) FROM rateb_hrm_performance_reviews r
                 WHERE {$where} AND r.overall_score IS NOT NULL"
            );
            $avgStmt->execute($params);
            $avg = (float) $avgStmt->fetchColumn();

            $sql = "SELECT r.id, r.company_id, r.code, r.overall_score, r.workflow_status, r.summary,
                           r.updated_at, r.created_at, c.name AS company_name
                    FROM rateb_hrm_performance_reviews r
                    LEFT JOIN rateb_companies c ON c.id = r.company_id
                    WHERE {$where}
                    ORDER BY r.updated_at DESC, r.id DESC
                    LIMIT {$limit} OFFSET {$offset}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return [
                'items' => $items,
                'total' => $total,
                'avg' => number_format($avg, 1) . '/5',
            ];
        } catch (\Throwable $e) {
            error_log('AgentAppsOpsService::listRatings: ' . $e->getMessage());

            return ['items' => [], 'total' => 0, 'avg' => '0/5'];
        }
    }

    /**
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function listNotifications(int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        [$scopeSql, $scopeParams] = $this->companyScopeSql('n');
        $params = $scopeParams;
        $where = '1=1' . $scopeSql;

        try {
            $pdo = Database::connection();
            $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM rateb_notifications n WHERE {$where}");
            $totalStmt->execute($params);
            $total = (int) $totalStmt->fetchColumn();

            $sql = "SELECT n.id, n.company_id, n.user_id, n.title, n.message, n.type, n.is_read, n.created_at,
                           c.name AS company_name
                    FROM rateb_notifications n
                    LEFT JOIN rateb_companies c ON c.id = n.company_id
                    WHERE {$where}
                    ORDER BY n.id DESC
                    LIMIT {$limit} OFFSET {$offset}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return ['items' => $items, 'total' => $total];
        } catch (\Throwable $e) {
            error_log('AgentAppsOpsService::listNotifications: ' . $e->getMessage());

            return ['items' => [], 'total' => 0];
        }
    }

    /**
     * Mobile salary / payment feature matrix per company.
     *
     * @return list<array<string,mixed>>
     */
    public function listPaymentFeatureMatrix(): array
    {
        $svc = new MobileAppConfigService();
        $rows = $svc->listCompaniesWithConfig();
        $out = [];
        foreach ($rows as $row) {
            $features = $svc->decodeFeatures($row['enabled_features'] ?? null);
            $out[] = [
                'company_id' => (int) ($row['company_id'] ?? 0),
                'company_name' => (string) ($row['company_name'] ?? ''),
                'app_name' => (string) ($row['app_name'] ?? ''),
                'mobile_active' => (string) ($row['mobile_status'] ?? '') === MobileAppConfigService::STATUS_ACTIVE,
                'payroll' => !empty($features['payroll']),
                'payslips' => !empty($features['payslips']),
                'payments' => !empty($features['payments']),
            ];
        }

        return $out;
    }

    public function countPendingComplaints(): int
    {
        return $this->listComplaints(1, 0, 'pending')['pending'];
    }

    public function notificationCount(): int
    {
        return $this->listNotifications(1, 0)['total'];
    }

    public function ratingsAvgLabel(): string
    {
        return $this->listRatings(1, 0)['avg'];
    }
}
