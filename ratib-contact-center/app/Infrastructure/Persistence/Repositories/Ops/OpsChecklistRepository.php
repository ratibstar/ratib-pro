<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Ops;

use Ratib\ContactCenter\App\Core\Database;

final class OpsChecklistRepository
{
    /** @return list<array<string, mixed>> */
    public function steps(): array
    {
        $stmt = Database::connection()->query(
            'SELECT slug, category, title, title_ar, description, sort_order, verify_action, is_required
             FROM rcc_ops_checklist_steps ORDER BY sort_order ASC, slug ASC'
        );
        return $stmt ? ($stmt->fetchAll() ?: []) : [];
    }

    /** @return list<array<string, mixed>> */
    public function statusForTenant(int $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT s.slug, s.category, s.title, s.title_ar, s.description, s.sort_order,
                    s.verify_action, s.is_required,
                    COALESCE(st.status, \'pending\') AS status,
                    st.evidence_json, st.verified_at, st.notes
             FROM rcc_ops_checklist_steps s
             LEFT JOIN rcc_ops_checklist_status st
               ON st.step_slug = s.slug AND st.tenant_id = :tid
             ORDER BY s.sort_order ASC, s.slug ASC'
        );
        $stmt->execute(['tid' => $tenantId]);
        return $stmt->fetchAll() ?: [];
    }

    public function updateStatus(
        int $tenantId,
        string $stepSlug,
        string $status,
        ?int $userId = null,
        ?array $evidence = null,
        ?string $notes = null
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO rcc_ops_checklist_status (tenant_id, step_slug, status, evidence_json, verified_by_user_id, verified_at, notes, updated_at)
             VALUES (:tid, :slug, :status, :evidence, :uid, :verified_at, :notes, NOW())
             ON DUPLICATE KEY UPDATE
               status = VALUES(status),
               evidence_json = VALUES(evidence_json),
               verified_by_user_id = VALUES(verified_by_user_id),
               verified_at = VALUES(verified_at),
               notes = VALUES(notes),
               updated_at = NOW()'
        );
        $verifiedAt = in_array($status, ['pass', 'fail'], true) ? gmdate('Y-m-d H:i:s') : null;
        $stmt->execute([
            'tid' => $tenantId,
            'slug' => $stepSlug,
            'status' => $status,
            'evidence' => $evidence !== null ? json_encode($evidence, JSON_UNESCAPED_UNICODE) : null,
            'uid' => $userId,
            'verified_at' => $verifiedAt,
            'notes' => $notes,
        ]);
    }

    /** @return array{required:int,pass:int,fail:int,pending:int,ready:bool} */
    public function summary(int $tenantId): array
    {
        $rows = $this->statusForTenant($tenantId);
        $required = 0;
        $pass = 0;
        $fail = 0;
        $pending = 0;
        foreach ($rows as $row) {
            if (!(bool) ($row['is_required'] ?? true)) {
                continue;
            }
            $required++;
            $st = (string) ($row['status'] ?? 'pending');
            if ($st === 'pass') {
                $pass++;
            } elseif ($st === 'fail') {
                $fail++;
            } else {
                $pending++;
            }
        }
        return [
            'required' => $required,
            'pass' => $pass,
            'fail' => $fail,
            'pending' => $pending,
            'ready' => $required > 0 && $pass === $required && $fail === 0,
        ];
    }
}
