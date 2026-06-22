<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Supervisor;

use Ratib\ContactCenter\App\Core\Database;

final class WfmAttendanceRepository
{
    /** @return list<array<string, mixed>> */
    public function listForDate(int $tenantId, string $workDate): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT att.*, a.display_name, a.extension, s.name AS shift_name, s.start_time, s.end_time
             FROM rcc_wfm_attendance att
             INNER JOIN rcc_agents a ON a.id = att.agent_id AND a.tenant_id = att.tenant_id
             LEFT JOIN rcc_wfm_shifts s ON s.id = att.scheduled_shift_id
             WHERE att.tenant_id = :tid AND att.work_date = :wd
             ORDER BY a.display_name'
        );
        $stmt->execute(['tid' => $tenantId, 'wd' => $workDate]);
        return $stmt->fetchAll() ?: [];
    }

    /** @return array<string, mixed>|null */
    public function findToday(int $tenantId, int $agentId, string $workDate): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rcc_wfm_attendance WHERE tenant_id=:tid AND agent_id=:aid AND work_date=:wd LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'aid' => $agentId, 'wd' => $workDate]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** @return array<string, mixed> */
    public function clockIn(int $tenantId, int $agentId, string $workDate, ?int $shiftId = null): array
    {
        $pdo = Database::connection();
        $existing = $this->findToday($tenantId, $agentId, $workDate);
        $now = gmdate('Y-m-d H:i:s');
        $status = 'present';
        if ($shiftId !== null) {
            $shift = $pdo->prepare('SELECT start_time, grace_minutes FROM rcc_wfm_shifts WHERE id=:id AND tenant_id=:tid');
            $shift->execute(['id' => $shiftId, 'tid' => $tenantId]);
            $s = $shift->fetch();
            if ($s !== false) {
                $scheduled = strtotime($workDate . ' ' . $s['start_time'] . ' UTC');
                $grace = ((int) $s['grace_minutes']) * 60;
                if ($scheduled !== false && time() > $scheduled + $grace) {
                    $status = 'late';
                }
            }
        }
        if ($existing !== null) {
            $upd = $pdo->prepare(
                'UPDATE rcc_wfm_attendance SET clock_in=COALESCE(clock_in, :cin), status=:st, scheduled_shift_id=COALESCE(scheduled_shift_id, :sid), updated_at=NOW()
                 WHERE id=:id'
            );
            $upd->execute(['cin' => $now, 'st' => $status, 'sid' => $shiftId, 'id' => $existing['id']]);
            return $this->findToday($tenantId, $agentId, $workDate) ?? [];
        }
        $ins = $pdo->prepare(
            'INSERT INTO rcc_wfm_attendance (tenant_id, agent_id, work_date, scheduled_shift_id, clock_in, status)
             VALUES (:tid, :aid, :wd, :sid, :cin, :st)'
        );
        $ins->execute(['tid' => $tenantId, 'aid' => $agentId, 'wd' => $workDate, 'sid' => $shiftId, 'cin' => $now, 'st' => $status]);
        return $this->findToday($tenantId, $agentId, $workDate) ?? [];
    }

    /** @return array<string, mixed> */
    public function clockOut(int $tenantId, int $agentId, string $workDate): array
    {
        $pdo = Database::connection();
        $now = gmdate('Y-m-d H:i:s');
        $upd = $pdo->prepare(
            'UPDATE rcc_wfm_attendance SET clock_out=:cout, updated_at=NOW()
             WHERE tenant_id=:tid AND agent_id=:aid AND work_date=:wd'
        );
        $upd->execute(['cout' => $now, 'tid' => $tenantId, 'aid' => $agentId, 'wd' => $workDate]);
        return $this->findToday($tenantId, $agentId, $workDate) ?? [];
    }
}
