<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\SupplierCommunication;

final class SupplierCommService
{
    /** @return array{total:int,this_month:int,pending_followups:int,by_supplier:int,distinct_suppliers:int} */
    public function companyStats(int $companyId, int $supplierId = 0): array
    {
        if ($companyId < 1) {
            return ['total' => 0, 'this_month' => 0, 'pending_followups' => 0, 'by_supplier' => 0, 'distinct_suppliers' => 0];
        }
        $model = new SupplierCommunication();
        $base = 'FROM rateb_supplier_communications WHERE company_id = :cid AND is_archived = 0';
        $params = ['cid' => $companyId];
        if ($supplierId > 0) {
            $base .= ' AND supplier_id = :sid';
            $params['sid'] = $supplierId;
        }
        $total = (int) ($model->queryOne('SELECT COUNT(*) AS c ' . $base, $params)['c'] ?? 0);
        $monthParams = $params;
        $monthSql = 'SELECT COUNT(*) AS c ' . $base . ' AND (
            (comm_date IS NOT NULL AND YEAR(comm_date) = YEAR(CURDATE()) AND MONTH(comm_date) = MONTH(CURDATE()))
            OR (comm_date IS NULL AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE()))
        )';
        $thisMonth = (int) ($model->queryOne($monthSql, $monthParams)['c'] ?? 0);
        $followSql = 'SELECT COUNT(*) AS c ' . $base . ' AND follow_up_date IS NOT NULL
            AND follow_up_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            AND comm_status IN (\'new\', \'follow_up\')';
        $pendingFollowups = (int) ($model->queryOne($followSql, $params)['c'] ?? 0);
        $bySupplier = $supplierId > 0 ? $total : 0;
        $distinctSuppliers = (int) ($model->queryOne(
            'SELECT COUNT(DISTINCT supplier_id) AS c ' . $base,
            $params
        )['c'] ?? 0);
        return [
            'total' => $total,
            'this_month' => $thisMonth,
            'pending_followups' => $pendingFollowups,
            'by_supplier' => $bySupplier,
            'distinct_suppliers' => $distinctSuppliers,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function upcomingFollowUps(int $companyId, int $limit = 10): array
    {
        if ($companyId < 1) {
            return [];
        }
        return (new SupplierCommunication())->query(
            'SELECT c.*, s.name AS supplier_name
             FROM rateb_supplier_communications c
             LEFT JOIN rateb_suppliers s ON s.id = c.supplier_id
             WHERE c.company_id = :cid AND c.is_archived = 0
               AND c.follow_up_date IS NOT NULL
               AND c.follow_up_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
               AND c.comm_status IN (\'new\', \'follow_up\')
             ORDER BY c.follow_up_date ASC, c.id DESC
             LIMIT ' . max(1, min(20, $limit)),
            ['cid' => $companyId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function historyForSupplier(int $companyId, int $supplierId, int $excludeId = 0, int $limit = 15): array
    {
        if ($companyId < 1 || $supplierId < 1) {
            return [];
        }
        $sql = 'SELECT c.id, c.subject, c.channel, c.comm_date, c.comm_time, c.comm_status,
                       c.follow_up_date, c.created_at, c.is_archived
                FROM rateb_supplier_communications c
                WHERE c.company_id = :cid AND c.supplier_id = :sid';
        $params = ['cid' => $companyId, 'sid' => $supplierId];
        if ($excludeId > 0) {
            $sql .= ' AND c.id != :xid';
            $params['xid'] = $excludeId;
        }
        $sql .= ' ORDER BY COALESCE(c.comm_date, DATE(c.created_at)) DESC, c.id DESC LIMIT ' . max(1, min(30, $limit));
        return (new SupplierCommunication())->query($sql, $params);
    }

    /** @return list<array{supplier_id:int,supplier_name:string,cnt:int}> */
    public function topSuppliersByComms(int $companyId, int $limit = 5): array
    {
        if ($companyId < 1) {
            return [];
        }
        return (new SupplierCommunication())->query(
            'SELECT c.supplier_id, s.name AS supplier_name, COUNT(*) AS cnt
             FROM rateb_supplier_communications c
             LEFT JOIN rateb_suppliers s ON s.id = c.supplier_id
             WHERE c.company_id = :cid AND c.is_archived = 0
             GROUP BY c.supplier_id, s.name
             ORDER BY cnt DESC
             LIMIT ' . max(1, min(10, $limit)),
            ['cid' => $companyId]
        );
    }

    public function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'closed' => 'secondary',
            'follow_up' => 'warning',
            default => 'info',
        };
    }

    public function priorityBadgeClass(string $priority): string
    {
        return match ($priority) {
            'high' => 'danger',
            'low' => 'secondary',
            default => 'primary',
        };
    }
}
