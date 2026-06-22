<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Controllers\Api;

use Ratib\ContactCenter\App\Application\Services\RealtimeOrchestrator;
use Ratib\ContactCenter\App\Application\Services\Tickets\TicketAssignmentEngine;
use Ratib\ContactCenter\App\Application\Services\Tickets\TicketEscalationService;
use Ratib\ContactCenter\App\Application\Services\Tickets\TicketSlaService;
use Ratib\ContactCenter\App\Application\Services\Tickets\TicketWorkflowService;
use Ratib\ContactCenter\App\Core\Security\AuthContext;
use Ratib\ContactCenter\App\Core\TenantContext;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Tickets\TicketRepository;

final class TicketsApiController
{
    public function __construct(
        private readonly TicketRepository $tickets = new TicketRepository(),
        private readonly TicketWorkflowService $workflow = new TicketWorkflowService(),
        private readonly TicketAssignmentEngine $assignment = new TicketAssignmentEngine(),
        private readonly TicketEscalationService $escalation = new TicketEscalationService(),
        private readonly TicketSlaService $sla = new TicketSlaService()
    ) {
    }

    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            RealtimeOrchestrator::boot();
            $action = (string) ($_GET['action'] ?? '');
            $input = array_merge($this->parseJsonBody(), $_GET);
            echo json_encode($this->handleAction($action, $input), JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /** @return array<string, mixed> */
    public function handleAction(string $action, array $input): array
    {
        AuthContext::requirePermission('rcc.tickets.view');
        $tenantId = $this->resolveTenantId($input);
        $userId = AuthContext::userId();
        $agentId = AuthContext::agentIdOrZero();
        TenantContext::set($tenantId);

        return match ($action) {
            'ticket_list' => $this->ok(['tickets' => $this->tickets->list(
                $tenantId,
                isset($input['status']) ? (string) $input['status'] : null,
                isset($input['assigned_agent_id']) ? (int) $input['assigned_agent_id'] : null
            )]),
            'ticket_get' => $this->ok([
                'ticket' => $this->tickets->find($tenantId, (int) ($input['ticket_id'] ?? 0)),
                'comments' => $this->tickets->comments($tenantId, (int) ($input['ticket_id'] ?? 0), AuthContext::can('rcc.tickets.admin')),
                'sla' => $this->sla->status($tenantId, (int) ($input['ticket_id'] ?? 0)),
            ]),
            'ticket_create' => $this->runPerm('rcc.tickets.create', fn () => $this->ok([
                'ticket' => $this->workflow->create($tenantId, $input, $userId),
            ])),
            'ticket_assign' => $this->runPerm('rcc.tickets.assign', fn () => $this->ok([
                'ticket' => $this->assignment->assign($tenantId, (int) ($input['ticket_id'] ?? 0), (int) ($input['agent_id'] ?? 0), $userId),
            ])),
            'ticket_auto_assign' => $this->runPerm('rcc.tickets.assign', fn () => $this->ok([
                'ticket' => $this->assignment->autoAssign($tenantId, (int) ($input['ticket_id'] ?? 0), $userId),
            ])),
            'ticket_escalate' => $this->runPerm('rcc.tickets.escalate', fn () => $this->ok([
                'ticket' => $this->escalation->escalate(
                    $tenantId,
                    (int) ($input['ticket_id'] ?? 0),
                    isset($input['supervisor_agent_id']) ? (int) $input['supervisor_agent_id'] : null,
                    $userId
                ),
            ])),
            'ticket_reopen' => $this->runPerm('rcc.tickets.create', fn () => $this->ok([
                'ticket' => $this->workflow->reopen($tenantId, (int) ($input['ticket_id'] ?? 0), $userId),
            ])),
            'ticket_resolve' => $this->runPerm('rcc.tickets.create', fn () => $this->ok([
                'ticket' => $this->workflow->resolve($tenantId, (int) ($input['ticket_id'] ?? 0), $userId),
            ])),
            'ticket_merge' => $this->runPerm('rcc.tickets.merge', function () use ($tenantId, $input, $userId) {
                $this->workflow->merge($tenantId, (int) ($input['source_id'] ?? 0), (int) ($input['target_id'] ?? 0), $userId);
                return $this->ok(['merged' => true]);
            }),
            'ticket_split' => $this->runPerm('rcc.tickets.merge', fn () => $this->ok([
                'ticket' => $this->workflow->split(
                    $tenantId,
                    (int) ($input['ticket_id'] ?? 0),
                    (string) ($input['subject'] ?? 'Split ticket'),
                    (string) ($input['description'] ?? ''),
                    $userId
                ),
            ])),
            'ticket_comment' => $this->runPerm('rcc.tickets.create', fn () => $this->ok([
                'comment_id' => $this->workflow->addComment(
                    $tenantId,
                    (int) ($input['ticket_id'] ?? 0),
                    (string) ($input['body'] ?? ''),
                    $userId,
                    $agentId > 0 ? $agentId : null,
                    !empty($input['internal'])
                ),
            ])),
            'ticket_sla' => $this->ok($this->sla->status($tenantId, (int) ($input['ticket_id'] ?? 0))),
            default => ['ok' => false, 'error' => 'Unknown action: ' . $action],
        };
    }

    /** @param array<string, mixed> $input */
    private function resolveTenantId(array $input): int
    {
        $tenantId = AuthContext::tenantId();
        if (AuthContext::can('rcc.tenants.manage')) {
            $requested = (int) ($input['tenant_id'] ?? 0);
            if ($requested > 0) {
                return $requested;
            }
        }
        return $tenantId;
    }

    /** @return array<string, mixed>|null */
    private function requirePerm(string $perm): ?array
    {
        if (!AuthContext::can($perm)) {
            return ['ok' => false, 'error' => 'Permission denied: ' . $perm];
        }
        return null;
    }

    /** @return array<string, mixed> */
    private function runPerm(string $perm, callable $fn): array
    {
        $denied = $this->requirePerm($perm);
        return $denied ?? $fn();
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function ok(array $data): array
    {
        return ['ok' => true] + $data;
    }

    /** @return array<string, mixed> */
    private function parseJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
