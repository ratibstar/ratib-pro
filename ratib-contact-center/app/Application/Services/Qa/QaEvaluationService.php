<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Qa;

use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;

final class QaEvaluationService
{
    public function __construct(private readonly RccAuditService $audit = new RccAuditService())
    {
    }

    /** @return list<array<string, mixed>> */
    public function listForms(int $tenantId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM rcc_qa_forms WHERE tenant_id = :tid AND is_active = 1 ORDER BY name');
        $stmt->execute(['tid' => $tenantId]);
        return $stmt->fetchAll() ?: [];
    }

    /** @param array<string, mixed> $data */
    public function createReview(int $tenantId, array $data, ?int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO rcc_qa_reviews (tenant_id, form_id, agent_id, evaluator_user_id, channel, call_id, conversation_id, recording_id, status)
             VALUES (:tid, :fid, :aid, :uid, :ch, :call, :conv, :rec, \'draft\')'
        );
        $stmt->execute([
            'tid' => $tenantId,
            'fid' => (int) ($data['form_id'] ?? 0),
            'aid' => (int) ($data['agent_id'] ?? 0),
            'uid' => $userId,
            'ch' => (string) ($data['channel'] ?? 'call'),
            'call' => $data['call_id'] ?? null,
            'conv' => $data['conversation_id'] ?? null,
            'rec' => $data['recording_id'] ?? null,
        ]);
        $id = (int) Database::connection()->lastInsertId();
        $this->audit->log($tenantId, 'qa.review.create', $userId, 'qa_review', $id);
        EventBus::instance()->emit([
            'type' => EventType::QA_REVIEW_CREATED,
            'tenant_id' => $tenantId,
            'agent_id' => (int) ($data['agent_id'] ?? 0),
            'payload' => ['review_id' => $id],
        ]);
        return $this->findReview($tenantId, $id) ?? [];
    }

    /** @return array<string, mixed>|null */
    public function findReview(int $tenantId, int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM rcc_qa_reviews WHERE tenant_id = :tid AND id = :id LIMIT 1');
        $stmt->execute(['tid' => $tenantId, 'id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }
}
