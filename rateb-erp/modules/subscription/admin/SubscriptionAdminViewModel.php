<?php
declare(strict_types=1);

namespace Rateb\App\Subscription\Admin;

/**
 * View-model helpers for subscription admin list / detail screens.
 * Pure mapping — no DB / no ERP modules.
 */
final class SubscriptionAdminViewModel
{
    public const EXPIRING_SOON_DAYS = 14;

    /**
     * Map an engine (+ optional company name) row to a list-table row.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function mapTenantRow(array $row, string $todayYmd): array
    {
        $end = self::ymd($row['subscription_end'] ?? null);
        $start = self::ymd($row['subscription_start'] ?? null);
        $status = strtoupper(trim((string) ($row['current_status'] ?? 'ACTIVE')));
        $suspendedAt = trim((string) ($row['suspended_at'] ?? ''));
        $isSuspended = $status === 'SUSPENDED' || $suspendedAt !== '';

        $daysRemaining = null;
        if ($end !== null) {
            $endTs = strtotime($end . ' 00:00:00');
            $todayTs = strtotime($todayYmd . ' 00:00:00');
            if ($endTs !== false && $todayTs !== false) {
                $daysRemaining = (int) floor(($endTs - $todayTs) / 86400);
            }
        }

        $graceStart = self::ymd($row['grace_started_at'] ?? null);
        $graceEnd = self::ymd($row['grace_end_at'] ?? null);
        $inGrace = false;
        if ($status === 'GRACE') {
            $inGrace = true;
        } elseif ($graceStart !== null && $graceEnd !== null) {
            $inGrace = $todayYmd >= $graceStart && $todayYmd <= $graceEnd && !$isSuspended;
        } elseif ($end !== null && $daysRemaining !== null && $daysRemaining < 0 && !$isSuspended) {
            // Derived grace window when columns empty — status may already be GRACE/SUSPENSION_PENDING.
            $inGrace = in_array($status, ['GRACE', 'SUSPENSION_PENDING'], true) === false
                ? false
                : ($status === 'GRACE');
            if ($status === 'GRACE') {
                $inGrace = true;
            }
        }

        $graceLabel = 'none';
        if ($isSuspended) {
            $graceLabel = 'n/a';
        } elseif ($status === 'SUSPENSION_PENDING') {
            $graceLabel = 'expired';
        } elseif ($inGrace || $status === 'GRACE') {
            $graceLabel = 'active';
        }

        $suspensionLabel = $isSuspended ? 'suspended' : 'clear';
        if (!$isSuspended && $status === 'SUSPENSION_PENDING') {
            $suspensionLabel = 'eligible';
        }

        return [
            'company_id' => (int) ($row['company_id'] ?? 0),
            'company_name' => (string) ($row['company_name'] ?? ('#' . (int) ($row['company_id'] ?? 0))),
            'status' => $status,
            'subscription_start' => $start,
            'subscription_end' => $end,
            'days_remaining' => $daysRemaining,
            'grace_status' => $graceLabel,
            'suspension_status' => $suspensionLabel,
            'last_renewal' => self::ymd($row['renewed_at'] ?? null) ?? self::nullableDatetime($row['renewed_at'] ?? null),
            'renewed_at' => self::nullableDatetime($row['renewed_at'] ?? null),
            'suspended_at' => self::nullableDatetime($row['suspended_at'] ?? null),
            'grace_started_at' => $graceStart,
            'grace_end_at' => $graceEnd,
            'expiring_soon' => !$isSuspended
                && $daysRemaining !== null
                && $daysRemaining >= 0
                && $daysRemaining <= self::EXPIRING_SOON_DAYS,
        ];
    }

    /**
     * Build a chronological lifecycle timeline from engine + audit rows.
     *
     * @param array<string, mixed> $engineRow
     * @param list<array<string, mixed>> $lifecycleAudits
     * @param list<array<string, mixed>> $renewals
     * @param list<array<string, mixed>> $suspensions
     * @return list<array<string, mixed>>
     */
    public static function buildTimeline(
        array $engineRow,
        array $lifecycleAudits,
        array $renewals,
        array $suspensions
    ): array {
        $events = [];

        $created = self::nullableDatetime($engineRow['created_at'] ?? null);
        if ($created !== null) {
            $events[] = [
                'at' => $created,
                'type' => 'created',
                'label' => 'Subscription record created',
                'meta' => [
                    'start' => self::ymd($engineRow['subscription_start'] ?? null),
                    'end' => self::ymd($engineRow['subscription_end'] ?? null),
                ],
            ];
        }

        foreach ($lifecycleAudits as $a) {
            $events[] = [
                'at' => self::nullableDatetime($a['created_at'] ?? null) ?? '',
                'type' => strtoupper((string) ($a['action'] ?? 'EVENT')),
                'label' => strtoupper((string) ($a['action'] ?? 'EVENT')),
                'meta' => [
                    'old_status' => (string) ($a['old_status'] ?? ''),
                    'new_status' => (string) ($a['new_status'] ?? ''),
                    'actor_id' => (int) ($a['actor_id'] ?? 0),
                ],
            ];
        }

        foreach ($renewals as $r) {
            $events[] = [
                'at' => self::nullableDatetime($r['created_at'] ?? null) ?? '',
                'type' => 'RENEWAL_HISTORY',
                'label' => 'Renewal history',
                'meta' => [
                    'previous_expiry' => self::ymd($r['previous_expiry_date'] ?? null),
                    'new_expiry' => self::ymd($r['new_expiry_date'] ?? null),
                    'period' => (string) ($r['period'] ?? ''),
                    'reference' => (string) ($r['reference'] ?? ''),
                    'actor_id' => (int) ($r['actor_id'] ?? 0),
                ],
            ];
        }

        foreach ($suspensions as $s) {
            $events[] = [
                'at' => self::nullableDatetime($s['created_at'] ?? null) ?? '',
                'type' => 'SUSPENSION_AUDIT',
                'label' => 'Suspension audit: ' . (string) ($s['decision'] ?? ''),
                'meta' => [
                    'decision' => (string) ($s['decision'] ?? ''),
                    'reason' => (string) ($s['reason'] ?? ''),
                ],
            ];
        }

        usort($events, static function (array $a, array $b): int {
            return strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? ''));
        });

        return $events;
    }

    /**
     * Clamp pagination inputs.
     *
     * @return array{page:int,limit:int,offset:int}
     */
    public static function pagination(int $page, int $limit, int $maxLimit = 100): array
    {
        $page = max(1, $page);
        $limit = max(1, min($maxLimit, $limit));
        return [
            'page' => $page,
            'limit' => $limit,
            'offset' => ($page - 1) * $limit,
        ];
    }

    private static function ymd(mixed $raw): ?string
    {
        $v = trim((string) ($raw ?? ''));
        if ($v === '') {
            return null;
        }
        $ymd = substr($v, 0, 10);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) === 1 ? $ymd : null;
    }

    private static function nullableDatetime(mixed $raw): ?string
    {
        $v = trim((string) ($raw ?? ''));
        return $v !== '' ? $v : null;
    }
}
