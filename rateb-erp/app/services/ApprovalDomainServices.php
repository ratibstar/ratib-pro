<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\EapAction;
use Rateb\App\Models\EapAudit;
use Rateb\App\Models\EapChain;
use Rateb\App\Models\EapChainStage;
use Rateb\App\Models\EapComment;
use Rateb\App\Models\EapDelegation;
use Rateb\App\Models\EapEscalation;
use Rateb\App\Models\EapNotificationMeta;
use Rateb\App\Models\EapRequest;
use Rateb\App\Models\EapRule;
use Rateb\App\Models\EapStage;
use Rateb\App\Models\EapTemplate;

/**
 * Phase 20A — Enterprise Approval Platform domain services (ONLINE).
 * Controllers must not embed business rules — call these services only.
 * Operates on rateb_eap_* — distinct from legacy WorkflowService / rateb_approval_*.
 */

final class ApprovalService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = '', ?string $status = null): array
    {
        return (new ApprovalRequestService())->list($limit, $offset, $search, $status);
    }

    /** @return array<string, int> */
    public function boardCounts(): array
    {
        $companyId = ApprovalSupport::requireCompanyId();
        $out = [];
        foreach (ApprovalWorkflowService::statuses() as $st) {
            $row = (new EapRequest())->queryOne(
                'SELECT COUNT(*) AS c FROM rateb_eap_requests
                 WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = :st',
                ['cid' => $companyId, 'st' => $st]
            );
            $out[$st] = (int) ($row['c'] ?? 0);
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function listPending(int $limit = 50): array
    {
        return (new ApprovalRequestService())->list($limit, 0, '', ApprovalWorkflowService::PENDING)['items'];
    }
}

final class ApprovalTemplateService
{
    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        $companyId = ApprovalSupport::requireCompanyId();
        $rows = (new EapTemplate())->query(
            'SELECT * FROM rateb_eap_templates WHERE company_id = :cid AND deleted_at IS NULL ORDER BY name ASC',
            ['cid' => $companyId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ApprovalSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = ApprovalSupport::nextCode('rateb_eap_templates', 'TPL', $companyId);
        }
        $id = (new EapTemplate())->create(array_merge([
            'public_uuid' => ApprovalSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ApprovalSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => ApprovalSupport::nullIfEmpty($input['name_ar'] ?? null),
            'module_key' => ApprovalSupport::nullIfEmpty($input['module_key'] ?? null),
            'description' => ApprovalSupport::nullIfEmpty($input['description'] ?? null),
            'status' => 'active',
            'version' => 1,
        ], ApprovalSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class ApprovalStageService
{
    /** @return list<array<string, mixed>> */
    public function listForTemplate(?int $templateId = null): array
    {
        $companyId = ApprovalSupport::requireCompanyId();
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($templateId !== null && $templateId > 0) {
            $where .= ' AND template_id = :tid';
            $params['tid'] = $templateId;
        }
        $rows = (new EapStage())->query(
            'SELECT * FROM rateb_eap_stages WHERE ' . $where . ' ORDER BY sort_order ASC, id ASC',
            $params
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ApprovalSupport::requireCompanyId();
        $templateId = (int) ($input['template_id'] ?? 0);
        if ($templateId < 1) {
            throw new \InvalidArgumentException('template_id_required');
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = ApprovalSupport::nextCode('rateb_eap_stages', 'STG', $companyId);
        }
        $id = (new EapStage())->create(array_merge([
            'public_uuid' => ApprovalSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ApprovalSupport::branchId(),
            'template_id' => $templateId,
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 160),
            'sort_order' => (int) ($input['sort_order'] ?? 0),
            'approver_role' => ApprovalSupport::nullIfEmpty($input['approver_role'] ?? null),
            'min_approvals' => max(1, (int) ($input['min_approvals'] ?? 1)),
            'sla_hours' => ApprovalSupport::intOrNull($input['sla_hours'] ?? null),
            'status' => 'active',
            'version' => 1,
        ], ApprovalSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class ApprovalRuleService
{
    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        $companyId = ApprovalSupport::requireCompanyId();
        $rows = (new EapRule())->query(
            'SELECT * FROM rateb_eap_rules WHERE company_id = :cid AND deleted_at IS NULL ORDER BY priority DESC, id DESC',
            ['cid' => $companyId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ApprovalSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = ApprovalSupport::nextCode('rateb_eap_rules', 'RUL', $companyId);
        }
        $condition = $input['condition_json'] ?? null;
        if (is_array($condition)) {
            $encoded = json_encode($condition, JSON_UNESCAPED_UNICODE);
            $condition = $encoded !== false ? $encoded : null;
        }
        $id = (new EapRule())->create(array_merge([
            'public_uuid' => ApprovalSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ApprovalSupport::branchId(),
            'template_id' => ApprovalSupport::intOrNull($input['template_id'] ?? null),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 160),
            'rule_type' => substr(trim((string) ($input['rule_type'] ?? 'amount')), 0, 40) ?: 'amount',
            'condition_json' => $condition,
            'priority' => (int) ($input['priority'] ?? 0),
            'status' => 'active',
            'version' => 1,
        ], ApprovalSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class ApprovalChainService
{
    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        $companyId = ApprovalSupport::requireCompanyId();
        $rows = (new EapChain())->query(
            'SELECT * FROM rateb_eap_chains WHERE company_id = :cid AND deleted_at IS NULL ORDER BY name ASC',
            ['cid' => $companyId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ApprovalSupport::requireCompanyId();
        $templateId = (int) ($input['template_id'] ?? 0);
        if ($templateId < 1) {
            throw new \InvalidArgumentException('template_id_required');
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = ApprovalSupport::nextCode('rateb_eap_chains', 'CHN', $companyId);
        }
        $id = (new EapChain())->create(array_merge([
            'public_uuid' => ApprovalSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ApprovalSupport::branchId(),
            'template_id' => $templateId,
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 160),
            'status' => 'active',
            'version' => 1,
        ], ApprovalSupport::actorFields(true)));

        $stageIds = $input['stage_ids'] ?? [];
        if (is_array($stageIds)) {
            $sort = 0;
            foreach ($stageIds as $sid) {
                $stageId = (int) $sid;
                if ($stageId < 1) {
                    continue;
                }
                (new EapChainStage())->create(array_merge([
                    'public_uuid' => ApprovalSupport::uuidV4(),
                    'company_id' => $companyId,
                    'chain_id' => (int) $id,
                    'stage_id' => $stageId,
                    'sort_order' => $sort++,
                ], ApprovalSupport::actorFields(true)));
            }
        }

        return ['id' => (int) $id];
    }
}

final class ApprovalRequestService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = '', ?string $status = null): array
    {
        $companyId = ApprovalSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($status !== null && $status !== '') {
            $where .= ' AND workflow_status = :st';
            $params['st'] = $status;
        }
        if ($search !== '') {
            $where .= ' AND (title LIKE :q OR request_no LIKE :q2)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
        }
        $totalRow = (new EapRequest())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_eap_requests WHERE ' . $where,
            $params
        );
        $items = (new EapRequest())->query(
            'SELECT * FROM rateb_eap_requests WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return ApprovalSupport::findRequest($id, ApprovalSupport::requireCompanyId());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, request_no: string}
     */
    public function create(array $input): array
    {
        $companyId = ApprovalSupport::requireCompanyId();
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $requestNo = trim((string) ($input['request_no'] ?? ''));
        if ($requestNo === '') {
            $requestNo = ApprovalSupport::nextRequestNo($companyId);
        }
        $id = (new EapRequest())->create(array_merge([
            'public_uuid' => ApprovalSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ApprovalSupport::intOrNull($input['branch_id'] ?? null) ?? ApprovalSupport::branchId(),
            'request_no' => substr($requestNo, 0, 40),
            'title' => substr($title, 0, 190),
            'template_id' => ApprovalSupport::intOrNull($input['template_id'] ?? null),
            'chain_id' => ApprovalSupport::intOrNull($input['chain_id'] ?? null),
            'current_stage_id' => ApprovalSupport::intOrNull($input['current_stage_id'] ?? null),
            'related_module' => ApprovalSupport::nullIfEmpty($input['related_module'] ?? null),
            'related_type' => ApprovalSupport::nullIfEmpty($input['related_type'] ?? null),
            'related_id' => ApprovalSupport::intOrNull($input['related_id'] ?? null),
            'workflow_status' => ApprovalWorkflowService::DRAFT,
            'priority' => in_array(($input['priority'] ?? 'normal'), ['low', 'normal', 'high', 'urgent'], true)
                ? $input['priority'] : 'normal',
            'amount' => ApprovalSupport::floatOrNull($input['amount'] ?? null),
            'currency_code' => ApprovalSupport::nullIfEmpty($input['currency_code'] ?? null),
            'requester_user_id' => ApprovalSupport::intOrNull($input['requester_user_id'] ?? null) ?? ApprovalSupport::userId(),
            'status' => 'active',
            'notes' => ApprovalSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ApprovalSupport::actorFields(true)));

        (new ApprovalTimelineService())->record(
            'request_created',
            'Approval request created: ' . $title,
            null,
            (int) $id
        );
        (new ApprovalAuditService())->log('request_created', 'Request created', (int) $id);

        return ['id' => (int) $id, 'request_no' => $requestNo];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ApprovalSupport::requireCompanyId();
        $req = ApprovalSupport::assertRequest($id, $companyId);
        if (isset($input['expected_version']) && (int) $input['expected_version'] !== (int) ($req['version'] ?? 1)) {
            throw new \RuntimeException('version_conflict');
        }
        $patch = ApprovalSupport::actorFields(false);
        foreach (['title', 'notes', 'currency_code', 'related_module', 'related_type', 'priority'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'title'
                    ? substr(trim((string) $input[$f]), 0, 190)
                    : ApprovalSupport::nullIfEmpty($input[$f]);
            }
        }
        foreach (['template_id', 'chain_id', 'current_stage_id', 'related_id', 'branch_id', 'requester_user_id'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = ApprovalSupport::intOrNull($input[$f]);
            }
        }
        if (array_key_exists('amount', $input)) {
            $patch['amount'] = ApprovalSupport::floatOrNull($input['amount']);
        }
        if (isset($patch['title']) && $patch['title'] === '') {
            throw new \InvalidArgumentException('title_required');
        }
        unset($patch['workflow_status']);
        $patch['version'] = (int) ($req['version'] ?? 1) + 1;
        (new EapRequest())->update($id, $patch);
        (new ApprovalTimelineService())->record('request_updated', 'Approval request updated', null, $id);
    }

    public function softDelete(int $id): void
    {
        $companyId = ApprovalSupport::requireCompanyId();
        ApprovalSupport::assertRequest($id, $companyId);
        (new EapRequest())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'archived',
        ], ApprovalSupport::actorFields(false)));
    }
}

final class ApprovalActionService
{
    /** @return list<array<string, mixed>> */
    public function listForRequest(int $requestId): array
    {
        $companyId = ApprovalSupport::requireCompanyId();
        ApprovalSupport::assertRequest($requestId, $companyId);
        $rows = (new EapAction())->query(
            'SELECT * FROM rateb_eap_actions WHERE company_id = :cid AND request_id = :rid AND deleted_at IS NULL
             ORDER BY acted_at DESC, id DESC',
            ['cid' => $companyId, 'rid' => $requestId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function record(array $input): array
    {
        $companyId = ApprovalSupport::requireCompanyId();
        $requestId = (int) ($input['request_id'] ?? 0);
        ApprovalSupport::assertRequest($requestId, $companyId);
        $actionType = (string) ($input['action_type'] ?? '');
        if (!in_array($actionType, ['approve', 'reject', 'delegate', 'escalate', 'comment', 'submit', 'cancel'], true)) {
            throw new \InvalidArgumentException('invalid_action_type');
        }
        $id = (new EapAction())->create(array_merge([
            'public_uuid' => ApprovalSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ApprovalSupport::branchId(),
            'request_id' => $requestId,
            'stage_id' => ApprovalSupport::intOrNull($input['stage_id'] ?? null),
            'action_type' => $actionType,
            'actor_user_id' => ApprovalSupport::intOrNull($input['actor_user_id'] ?? null) ?? ApprovalSupport::userId(),
            'comment' => ApprovalSupport::nullIfEmpty($input['comment'] ?? null),
            'acted_at' => date('Y-m-d H:i:s'),
            'version' => 1,
        ], ApprovalSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class ApprovalDelegationService
{
    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ApprovalSupport::requireCompanyId();
        $from = (int) ($input['from_user_id'] ?? 0);
        $to = (int) ($input['to_user_id'] ?? 0);
        if ($from < 1 || $to < 1) {
            throw new \InvalidArgumentException('delegation_users_required');
        }
        $requestId = ApprovalSupport::intOrNull($input['request_id'] ?? null);
        if ($requestId !== null) {
            ApprovalSupport::assertRequest($requestId, $companyId);
        }
        $id = (new EapDelegation())->create(array_merge([
            'public_uuid' => ApprovalSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ApprovalSupport::branchId(),
            'request_id' => $requestId,
            'from_user_id' => $from,
            'to_user_id' => $to,
            'starts_at' => ApprovalSupport::nullIfEmpty($input['starts_at'] ?? null) ?? date('Y-m-d H:i:s'),
            'ends_at' => ApprovalSupport::nullIfEmpty($input['ends_at'] ?? null),
            'status' => 'active',
            'notes' => ApprovalSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ApprovalSupport::actorFields(true)));

        if ($requestId !== null) {
            (new ApprovalActionService())->record([
                'request_id' => $requestId,
                'action_type' => 'delegate',
                'comment' => 'Delegated to user ' . $to,
            ]);
            (new ApprovalTimelineService())->record('delegated', 'Approval delegated', null, $requestId);
        }

        return ['id' => (int) $id];
    }

    /** @return list<array<string, mixed>> */
    public function listActive(int $limit = 50): array
    {
        $companyId = ApprovalSupport::requireCompanyId();
        $safe = max(1, min(200, $limit));
        $rows = (new EapDelegation())->query(
            'SELECT * FROM rateb_eap_delegations WHERE company_id = :cid AND deleted_at IS NULL AND status = \'active\'
             ORDER BY id DESC LIMIT ' . $safe,
            ['cid' => $companyId]
        );

        return is_array($rows) ? $rows : [];
    }
}

final class ApprovalEscalationService
{
    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ApprovalSupport::requireCompanyId();
        $requestId = (int) ($input['request_id'] ?? 0);
        ApprovalSupport::assertRequest($requestId, $companyId);
        $id = (new EapEscalation())->create(array_merge([
            'public_uuid' => ApprovalSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ApprovalSupport::branchId(),
            'request_id' => $requestId,
            'stage_id' => ApprovalSupport::intOrNull($input['stage_id'] ?? null),
            'escalate_to_user_id' => ApprovalSupport::intOrNull($input['escalate_to_user_id'] ?? null),
            'reason' => ApprovalSupport::nullIfEmpty($input['reason'] ?? null),
            'escalated_at' => date('Y-m-d H:i:s'),
            'status' => 'open',
            'version' => 1,
        ], ApprovalSupport::actorFields(true)));

        (new ApprovalActionService())->record([
            'request_id' => $requestId,
            'action_type' => 'escalate',
            'comment' => ApprovalSupport::nullIfEmpty($input['reason'] ?? null),
        ]);
        (new ApprovalTimelineService())->record('escalated', 'Approval escalated', null, $requestId);

        return ['id' => (int) $id];
    }
}

final class ApprovalCommentService
{
    /** @return list<array<string, mixed>> */
    public function listForRequest(int $requestId): array
    {
        $companyId = ApprovalSupport::requireCompanyId();
        ApprovalSupport::assertRequest($requestId, $companyId);
        $rows = (new EapComment())->query(
            'SELECT * FROM rateb_eap_comments WHERE company_id = :cid AND request_id = :rid AND deleted_at IS NULL
             ORDER BY created_at DESC, id DESC',
            ['cid' => $companyId, 'rid' => $requestId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ApprovalSupport::requireCompanyId();
        $requestId = (int) ($input['request_id'] ?? 0);
        ApprovalSupport::assertRequest($requestId, $companyId);
        $body = trim((string) ($input['body'] ?? ''));
        if ($body === '') {
            throw new \InvalidArgumentException('body_required');
        }
        $id = (new EapComment())->create(array_merge([
            'public_uuid' => ApprovalSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ApprovalSupport::branchId(),
            'request_id' => $requestId,
            'body' => $body,
            'version' => 1,
        ], ApprovalSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class ApprovalAuditService
{
    /**
     * @param array<string, mixed>|null $meta
     */
    public function log(string $eventCode, string $message, ?int $requestId = null, ?array $meta = null): int
    {
        $companyId = ApprovalSupport::requireCompanyId();
        $metaJson = null;
        if ($meta !== null) {
            $encoded = json_encode($meta, JSON_UNESCAPED_UNICODE);
            $metaJson = $encoded !== false ? $encoded : null;
        }

        return (int) (new EapAudit())->create([
            'public_uuid' => ApprovalSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ApprovalSupport::branchId(),
            'request_id' => $requestId,
            'event_code' => substr($eventCode, 0, 60),
            'message' => substr($message, 0, 255),
            'meta_json' => $metaJson,
            'created_by' => ApprovalSupport::userId(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function listRecent(int $limit = 50): array
    {
        $companyId = ApprovalSupport::requireCompanyId();
        $safe = max(1, min(200, $limit));
        $rows = (new EapAudit())->query(
            'SELECT * FROM rateb_eap_audit WHERE company_id = :cid ORDER BY created_at DESC, id DESC LIMIT ' . $safe,
            ['cid' => $companyId]
        );

        return is_array($rows) ? $rows : [];
    }
}

/**
 * Notification metadata only. Actual sending via NotificationService remains ONLINE ONLY.
 */
final class ApprovalNotificationMetaService
{
    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ApprovalSupport::requireCompanyId();
        $requestId = (int) ($input['request_id'] ?? 0);
        ApprovalSupport::assertRequest($requestId, $companyId);
        $id = (new EapNotificationMeta())->create(array_merge([
            'public_uuid' => ApprovalSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ApprovalSupport::branchId(),
            'request_id' => $requestId,
            'channel' => substr(trim((string) ($input['channel'] ?? 'in_app')), 0, 40) ?: 'in_app',
            'recipient_user_id' => ApprovalSupport::intOrNull($input['recipient_user_id'] ?? null),
            'subject' => ApprovalSupport::nullIfEmpty($input['subject'] ?? null),
            'status' => 'queued',
            'notification_id' => ApprovalSupport::intOrNull($input['notification_id'] ?? null),
            'version' => 1,
        ], ApprovalSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}
