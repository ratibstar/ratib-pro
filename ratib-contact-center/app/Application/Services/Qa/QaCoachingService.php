<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Qa;

use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\Database;

final class QaCoachingService
{
    public function __construct(private readonly RccAuditService $audit = new RccAuditService())
    {
    }

    public function saveCoachingNotes(int $tenantId, int $reviewId, string $notes, ?int $userId): void
    {
        Database::connection()->prepare(
            'UPDATE rcc_qa_reviews SET coaching_notes = :notes, updated_at = NOW() WHERE tenant_id = :tid AND id = :id'
        )->execute(['tid' => $tenantId, 'id' => $reviewId, 'notes' => $notes]);
        $this->audit->log($tenantId, 'qa.coaching.save', $userId, 'qa_review', $reviewId);
    }

    /** @return list<array<string, mixed>> */
    public function agentScores(int $tenantId, int $agentId, int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, channel, total_score, completed_at, coaching_notes FROM rcc_qa_reviews
             WHERE tenant_id = :tid AND agent_id = :aid AND status = \'completed\'
             ORDER BY completed_at DESC LIMIT ' . max(1, min(200, $limit))
        );
        $stmt->execute(['tid' => $tenantId, 'aid' => $agentId]);
        return $stmt->fetchAll() ?: [];
    }
}
