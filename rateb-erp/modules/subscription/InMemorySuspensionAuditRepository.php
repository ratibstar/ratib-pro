<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * In-memory suspension audit for shadow-mode unit tests.
 */
final class InMemorySuspensionAuditRepository extends SuspensionAuditRepository
{
    /** @var list<array<string, mixed>> */
    private array $rows = [];
    private int $seq = 0;

    public function record(SuspensionDecision $decision): int
    {
        if ($decision->companyId() < 1) {
            return 0;
        }
        $this->seq++;
        $this->rows[] = [
            'id' => $this->seq,
            'company_id' => $decision->companyId(),
            'decision' => $decision->isEligible() ? 'eligible' : 'not_eligible',
            'reason' => $decision->reason(),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];
        return $this->seq;
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->rows;
    }
}
