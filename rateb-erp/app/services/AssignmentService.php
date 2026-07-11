<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\RecruitmentAssignment;

final class AssignmentService
{
    /** @param array<string, mixed> $data */
    public function assign(int $candidateId, array $data): int
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        RecruitmentSupport::assertCandidate($candidateId, $companyId);
        $assignee = (int) ($data['assignee_user_id'] ?? 0);
        if ($assignee < 1) {
            throw new \InvalidArgumentException('assignee_required');
        }
        $id = (new RecruitmentAssignment())->create(array_merge([
            'public_uuid' => RecruitmentSupport::uuidV4(),
            'company_id' => $companyId,
            'candidate_id' => $candidateId,
            'assignee_user_id' => $assignee,
            'role_label' => trim((string) ($data['role_label'] ?? 'recruiter')) ?: 'recruiter',
            'status' => 'active',
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ], RecruitmentSupport::actorFields(true)));
        (new RecruitmentTimelineService())->record($candidateId, 'assignment', 'Recruiter assigned', null, 'assignment', $id);
        (new AuditService())->log('create', 'recruitment_assignment', $id, [
            'candidate_id' => $candidateId,
            'assignee_user_id' => $assignee,
        ]);

        return $id;
    }

    public function revoke(int $id): void
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        $row = (new RecruitmentAssignment())->queryOne(
            'SELECT * FROM rateb_recruitment_assignments WHERE id = :id AND company_id = :cid AND deleted_at IS NULL',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!$row) {
            throw new \RuntimeException('assignment_not_found');
        }
        (new RecruitmentAssignment())->update($id, array_merge([
            'status' => 'revoked',
            'deleted_at' => date('Y-m-d H:i:s'),
        ], RecruitmentSupport::actorFields(false)));
        (new AuditService())->log('delete', 'recruitment_assignment', $id, ['candidate_id' => $row['candidate_id']]);
    }
}

/**
 * ONLINE attachment metadata via existing DocumentService.
 * Does not implement offline attachment queues.
 */
final class RecruitmentDocumentMetaService
{
    public const ENTITY_CANDIDATE = 'recruitment_candidate';
    public const ENTITY_VISA = 'recruitment_visa';
    public const ENTITY_MEDICAL = 'recruitment_medical';
    public const ENTITY_CONTRACT = 'recruitment_contract';
    public const ENTITY_PASSPORT = 'recruitment_passport';
    public const ENTITY_INTERVIEW = 'recruitment_interview';

    /**
     * @param array<string, mixed> $file $_FILES element
     * @return array<string, mixed>
     */
    public function storeUpload(string $entityType, int $entityId, array $file, ?string $title = null): array
    {
        RecruitmentSupport::requireCompanyId();
        $allowed = [
            self::ENTITY_CANDIDATE, self::ENTITY_VISA, self::ENTITY_MEDICAL,
            self::ENTITY_CONTRACT, self::ENTITY_PASSPORT, self::ENTITY_INTERVIEW,
        ];
        if (!in_array($entityType, $allowed, true)) {
            throw new \InvalidArgumentException('invalid_attachment_entity');
        }
        $docs = new DocumentService();
        $result = $docs->storeUpload($file, $entityType, $entityId, (string) ($title ?? ''));
        (new AuditService())->log('create', 'recruitment_attachment', $entityId, [
            'entity_type' => $entityType,
            'title' => $title,
        ]);

        return is_array($result) ? $result : ['ok' => true];
    }

    /** @return list<array<string, mixed>> */
    public function listFor(string $entityType, int $entityId): array
    {
        $companyId = RecruitmentSupport::requireCompanyId();

        return (new DocumentService())->listForEntity($entityType, $entityId, $companyId);
    }
}
