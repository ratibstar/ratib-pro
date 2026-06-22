<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Supervisor;

use Ratib\ContactCenter\App\Core\Database;

final class WfmShiftRepository
{
    /** @return list<array<string, mixed>> */
    public function listShifts(int $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rcc_wfm_shifts WHERE tenant_id = :tid ORDER BY start_time, code'
        );
        $stmt->execute(['tid' => $tenantId]);
        return $stmt->fetchAll() ?: [];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function saveShift(int $tenantId, array $data): array
    {
        $pdo = Database::connection();
        $id = (int) ($data['id'] ?? 0);
        $params = [
            'code' => (string) ($data['code'] ?? ''),
            'name' => (string) ($data['name'] ?? ''),
            'name_ar' => $data['name_ar'] ?? null,
            'start' => (string) ($data['start_time'] ?? '09:00:00'),
            'end' => (string) ($data['end_time'] ?? '17:00:00'),
            'break' => (int) ($data['break_minutes'] ?? 30),
            'grace' => (int) ($data['grace_minutes'] ?? 5),
            'st' => (string) ($data['status'] ?? 'active'),
            'tid' => $tenantId,
        ];
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE rcc_wfm_shifts SET code=:code, name=:name, name_ar=:name_ar, start_time=:start, end_time=:end,
                 break_minutes=:break, grace_minutes=:grace, status=:st, updated_at=NOW()
                 WHERE id=:id AND tenant_id=:tid'
            );
            $stmt->execute($params + ['id' => $id]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO rcc_wfm_shifts (tenant_id, code, name, name_ar, start_time, end_time, break_minutes, grace_minutes, status)
                 VALUES (:tid, :code, :name, :name_ar, :start, :end, :break, :grace, :st)'
            );
            $stmt->execute($params);
            $id = (int) $pdo->lastInsertId();
        }
        $row = $pdo->prepare('SELECT * FROM rcc_wfm_shifts WHERE id=:id AND tenant_id=:tid');
        $row->execute(['id' => $id, 'tid' => $tenantId]);
        return $row->fetch() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listAssignments(int $tenantId, string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT a.*, s.code AS shift_code, s.name AS shift_name, s.start_time, s.end_time,
                    ag.display_name AS agent_name, ag.extension
             FROM rcc_wfm_shift_assignments a
             INNER JOIN rcc_wfm_shifts s ON s.id = a.shift_id AND s.tenant_id = a.tenant_id
             INNER JOIN rcc_agents ag ON ag.id = a.agent_id AND ag.tenant_id = a.tenant_id
             WHERE a.tenant_id = :tid AND a.work_date BETWEEN :from AND :to
             ORDER BY a.work_date, s.start_time, ag.display_name'
        );
        $stmt->execute(['tid' => $tenantId, 'from' => $from, 'to' => $to]);
        return $stmt->fetchAll() ?: [];
    }

    public function assignShift(int $tenantId, int $shiftId, int $agentId, string $workDate): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO rcc_wfm_shift_assignments (tenant_id, shift_id, agent_id, work_date)
             VALUES (:tid, :sid, :aid, :wd)
             ON DUPLICATE KEY UPDATE shift_id = VALUES(shift_id)'
        );
        $stmt->execute(['tid' => $tenantId, 'sid' => $shiftId, 'aid' => $agentId, 'wd' => $workDate]);
    }
}
