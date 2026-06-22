<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Supervisor;

use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Supervisor\WfmAttendanceRepository;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Supervisor\WfmBreakRepository;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Supervisor\WfmShiftRepository;

final class SupervisorWfmService
{
    public function __construct(
        private readonly WfmShiftRepository $shifts = new WfmShiftRepository(),
        private readonly WfmAttendanceRepository $attendance = new WfmAttendanceRepository(),
        private readonly WfmBreakRepository $breaks = new WfmBreakRepository(),
        private readonly SupervisorAuditService $audit = new SupervisorAuditService()
    ) {
    }

    /** @return array<string, mixed> */
    public function overview(int $tenantId): array
    {
        $today = gmdate('Y-m-d');
        return [
            'shifts' => count($this->shifts->listShifts($tenantId)),
            'assignments_today' => count($this->shifts->listAssignments($tenantId, $today, $today)),
            'attendance_today' => $this->attendance->listForDate($tenantId, $today),
            'active_breaks' => $this->breaks->listActive($tenantId),
            'occupancy' => $this->occupancy($tenantId),
            'adherence' => $this->adherence($tenantId, $today),
            'timestamp' => gmdate('c'),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listShifts(int $tenantId): array
    {
        return $this->shifts->listShifts($tenantId);
    }

    /** @param array<string, mixed> $data */
    public function saveShift(int $tenantId, array $data, ?int $userId): array
    {
        $row = $this->shifts->saveShift($tenantId, $data);
        $this->audit->log($tenantId, 'supervisor.shift.save', $userId, 'shift', (int) ($row['id'] ?? 0), $data);
        EventBus::instance()->emit([
            'type' => EventType::SUPERVISOR_SHIFT_UPDATED,
            'tenant_id' => $tenantId,
            'payload' => ['shift_id' => $row['id'] ?? null],
        ]);
        return $row;
    }

    /** @return list<array<string, mixed>> */
    public function listAssignments(int $tenantId, string $from, string $to): array
    {
        return $this->shifts->listAssignments($tenantId, $from, $to);
    }

    public function assignShift(int $tenantId, int $shiftId, int $agentId, string $workDate, ?int $userId): void
    {
        $this->shifts->assignShift($tenantId, $shiftId, $agentId, $workDate);
        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO rcc_wfm_attendance (tenant_id, agent_id, work_date, scheduled_shift_id, status)
             VALUES (:tid, :aid, :wd, :sid, \'scheduled\')
             ON DUPLICATE KEY UPDATE scheduled_shift_id = VALUES(scheduled_shift_id)'
        )->execute(['tid' => $tenantId, 'aid' => $agentId, 'wd' => $workDate, 'sid' => $shiftId]);
        $this->audit->log($tenantId, 'supervisor.shift.assign', $userId, 'agent', $agentId, [
            'shift_id' => $shiftId, 'work_date' => $workDate,
        ]);
        EventBus::instance()->emit([
            'type' => EventType::SUPERVISOR_SHIFT_UPDATED,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'payload' => ['shift_id' => $shiftId, 'work_date' => $workDate],
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function attendanceForDate(int $tenantId, string $workDate): array
    {
        return $this->attendance->listForDate($tenantId, $workDate);
    }

    /** @return array<string, mixed> */
    public function clockIn(int $tenantId, int $agentId, ?int $shiftId, ?int $userId): array
    {
        $row = $this->attendance->clockIn($tenantId, $agentId, gmdate('Y-m-d'), $shiftId);
        $this->audit->log($tenantId, 'supervisor.attendance.clock_in', $userId, 'agent', $agentId);
        EventBus::instance()->emit([
            'type' => EventType::SUPERVISOR_ATTENDANCE_UPDATED,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'payload' => $row,
        ]);
        return $row;
    }

    /** @return array<string, mixed> */
    public function clockOut(int $tenantId, int $agentId, ?int $userId): array
    {
        $row = $this->attendance->clockOut($tenantId, $agentId, gmdate('Y-m-d'));
        $this->audit->log($tenantId, 'supervisor.attendance.clock_out', $userId, 'agent', $agentId);
        EventBus::instance()->emit([
            'type' => EventType::SUPERVISOR_ATTENDANCE_UPDATED,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'payload' => $row,
        ]);
        return $row;
    }

    /** @return array<string, mixed> */
    public function startBreak(int $tenantId, int $agentId, string $breakType, ?string $reason, ?int $userId): array
    {
        $row = $this->breaks->startBreak($tenantId, $agentId, $breakType, $reason);
        $this->audit->log($tenantId, 'supervisor.break.start', $userId, 'agent', $agentId, ['type' => $breakType]);
        EventBus::instance()->emit([
            'type' => EventType::SUPERVISOR_BREAK_STARTED,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'payload' => $row,
        ]);
        return $row;
    }

    /** @return array<string, mixed>|null */
    public function endBreak(int $tenantId, int $agentId, ?int $userId): ?array
    {
        $row = $this->breaks->endBreak($tenantId, $agentId);
        if ($row !== null) {
            $this->audit->log($tenantId, 'supervisor.break.end', $userId, 'agent', $agentId);
            EventBus::instance()->emit([
                'type' => EventType::SUPERVISOR_BREAK_ENDED,
                'tenant_id' => $tenantId,
                'agent_id' => $agentId,
                'payload' => $row,
            ]);
        }
        return $row;
    }

    /** @return list<array<string, mixed>> */
    public function activeBreaks(int $tenantId): array
    {
        $this->alerts->evaluateLongBreaks($tenantId);
        return $this->breaks->listActive($tenantId);
    }

    /** @return array<string, mixed> */
    public function occupancy(int $tenantId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT a.id AS agent_id, a.display_name,
                    als.status,
                    (SELECT COUNT(*) FROM rcc_softphone_calls sc
                     WHERE sc.agent_id = a.id AND sc.tenant_id = a.tenant_id AND sc.status = 'connected') AS live_calls,
                    (SELECT TIMESTAMPDIFF(SECOND, b.started_at, NOW()) FROM rcc_wfm_breaks b
                     WHERE b.agent_id = a.id AND b.tenant_id = a.tenant_id AND b.ended_at IS NULL LIMIT 1) AS break_seconds
             FROM rcc_agents a
             LEFT JOIN rcc_agent_live_state als ON als.agent_id = a.id AND als.tenant_id = a.tenant_id
             WHERE a.tenant_id = :tid AND a.status = 'active'"
        );
        $stmt->execute(['tid' => $tenantId]);
        $agents = $stmt->fetchAll() ?: [];
        $occupied = 0;
        $total = count($agents);
        foreach ($agents as $ag) {
            if (in_array($ag['status'] ?? '', ['busy', 'wrapup'], true) || (int) ($ag['live_calls'] ?? 0) > 0) {
                $occupied++;
            }
        }
        $pct = $total > 0 ? (int) round(($occupied / $total) * 100) : 0;
        $data = [
            'occupancy_pct' => $pct,
            'occupied_agents' => $occupied,
            'total_agents' => $total,
            'agents' => $agents,
            'timestamp' => gmdate('c'),
        ];
        EventBus::instance()->emit([
            'type' => EventType::SUPERVISOR_OCCUPANCY_UPDATED,
            'tenant_id' => $tenantId,
            'payload' => ['occupancy_pct' => $pct],
        ]);
        return $data;
    }

    /** @return array<string, mixed> */
    public function adherence(int $tenantId, string $workDate): array
    {
        $rows = $this->attendance->listForDate($tenantId, $workDate);
        $scheduled = count($rows);
        $onTime = 0;
        $late = 0;
        $absent = 0;
        foreach ($rows as $r) {
            match ($r['status'] ?? '') {
                'present' => $onTime++,
                'late' => $late++,
                'absent' => $absent++,
                default => null,
            };
        }
        $pct = $scheduled > 0 ? (int) round((($onTime + $late) / $scheduled) * 100) : 100;
        $data = [
            'work_date' => $workDate,
            'scheduled' => $scheduled,
            'present' => $onTime,
            'late' => $late,
            'absent' => $absent,
            'adherence_pct' => $pct,
            'records' => $rows,
        ];
        EventBus::instance()->emit([
            'type' => EventType::SUPERVISOR_ADHERENCE_UPDATED,
            'tenant_id' => $tenantId,
            'payload' => ['adherence_pct' => $pct, 'work_date' => $workDate],
        ]);
        return $data;
    }
}
