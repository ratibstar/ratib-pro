<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\RecruitmentActivity;
use Rateb\App\Models\RecruitmentTimeline;

/** Timeline + activity feed for candidates — reusable by all recruitment services. */
final class RecruitmentTimelineService
{
    public function record(
        int $candidateId,
        string $eventType,
        string $title,
        ?string $body = null,
        ?string $relatedEntity = null,
        ?int $relatedId = null
    ): int {
        $companyId = RecruitmentSupport::requireCompanyId();
        RecruitmentSupport::assertCandidate($candidateId, $companyId);
        $id = (new RecruitmentTimeline())->create([
            'company_id' => $companyId,
            'candidate_id' => $candidateId,
            'event_type' => substr($eventType, 0, 64),
            'title' => substr($title, 0, 190),
            'body' => $body,
            'related_entity' => $relatedEntity,
            'related_id' => $relatedId,
            'created_by' => RecruitmentSupport::userId(),
        ]);

        return $id;
    }

    public function activity(int $candidateId, string $type, string $title, ?string $body = null, ?array $meta = null): int
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        RecruitmentSupport::assertCandidate($candidateId, $companyId);

        return (new RecruitmentActivity())->create([
            'company_id' => $companyId,
            'candidate_id' => $candidateId,
            'activity_type' => substr($type, 0, 64),
            'title' => substr($title, 0, 190),
            'body' => $body,
            'meta_json' => $meta !== null ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'created_by' => RecruitmentSupport::userId(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function listForCandidate(int $candidateId, int $limit = 100): array
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        RecruitmentSupport::assertCandidate($candidateId, $companyId);

        return (new RecruitmentTimeline())->query(
            'SELECT * FROM rateb_recruitment_timeline
             WHERE company_id = :cid AND candidate_id = :cand
             ORDER BY id DESC LIMIT ' . max(1, min(500, $limit)),
            ['cid' => $companyId, 'cand' => $candidateId]
        );
    }
}
