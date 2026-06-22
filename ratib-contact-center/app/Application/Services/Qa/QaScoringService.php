<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Qa;

use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;

final class QaScoringService
{
    public function __construct(
        private readonly QaEvaluationService $evaluations = new QaEvaluationService(),
        private readonly RccAuditService $audit = new RccAuditService()
    ) {
    }

    /** @param list<array{question_id:int,score:float,max_score:float,comment?:string}> $scores */
    public function submitScores(int $tenantId, int $reviewId, array $scores, ?int $userId): array
    {
        $pdo = Database::connection();
        $total = 0.0;
        $max = 0.0;
        foreach ($scores as $s) {
            $qid = (int) $s['question_id'];
            $score = (float) $s['score'];
            $maxScore = (float) $s['max_score'];
            $total += $score;
            $max += $maxScore;
            $stmt = $pdo->prepare(
                'INSERT INTO rcc_qa_scores (tenant_id, review_id, question_id, score, max_score, comment)
                 VALUES (:tid, :rid, :qid, :score, :max, :comment)
                 ON DUPLICATE KEY UPDATE score = VALUES(score), max_score = VALUES(max_score), comment = VALUES(comment)'
            );
            $stmt->execute([
                'tid' => $tenantId,
                'rid' => $reviewId,
                'qid' => $qid,
                'score' => $score,
                'max' => $maxScore,
                'comment' => $s['comment'] ?? null,
            ]);
        }
        $pct = $max > 0 ? round(($total / $max) * 100, 2) : 0;
        $pdo->prepare(
            'UPDATE rcc_qa_reviews SET total_score = :score, status = \'completed\', completed_at = NOW() WHERE tenant_id = :tid AND id = :id'
        )->execute(['tid' => $tenantId, 'id' => $reviewId, 'score' => $pct]);
        $this->audit->log($tenantId, 'qa.review.complete', $userId, 'qa_review', $reviewId, ['score' => $pct]);
        EventBus::instance()->emit([
            'type' => EventType::QA_REVIEW_COMPLETED,
            'tenant_id' => $tenantId,
            'payload' => ['review_id' => $reviewId, 'score' => $pct],
        ]);
        EventBus::instance()->emit([
            'type' => EventType::QA_SCORE_UPDATED,
            'tenant_id' => $tenantId,
            'payload' => ['review_id' => $reviewId, 'score' => $pct],
        ]);
        return $this->evaluations->findReview($tenantId, $reviewId) ?? [];
    }
}
