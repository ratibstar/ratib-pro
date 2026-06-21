<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\IVR\NodeExecutors;

use Ratib\ContactCenter\App\Application\Contracts\PbxCommandGatewayInterface;
use Ratib\ContactCenter\App\Application\Contracts\QueueGatewayInterface;
use Ratib\ContactCenter\App\Application\Contracts\TicketGatewayInterface;
use Ratib\ContactCenter\App\Domain\IVR\Enums\IvrNodeType;
use Ratib\ContactCenter\App\Domain\IVR\IvrNode;
use Ratib\ContactCenter\App\Domain\IVR\IvrSession;
use Ratib\ContactCenter\App\Domain\IVR\NodeExecutionResult;

final class RouteCallExecutor implements NodeExecutorInterface
{
    public function __construct(
        private readonly ?QueueGatewayInterface $queueGateway = null,
        private readonly ?TicketGatewayInterface $ticketGateway = null
    ) {
    }

    public function supports(string $nodeType): bool
    {
        return $nodeType === IvrNodeType::RouteCall->value;
    }

    public function execute(
        IvrSession $session,
        IvrNode $node,
        PbxCommandGatewayInterface $pbx
    ): NodeExecutionResult {
        $channelId = $session->channelId ?? '';
        $input = $session->lastInput();
        $routes = $node->payload['routes'] ?? [];
        $defaultRoute = $node->payload['default'] ?? null;

        $matched = $this->matchRoute($routes, $input, $session, $defaultRoute);
        if ($matched === null) {
            if ($node->fallbackNodeId !== null) {
                return NodeExecutionResult::advance($node->fallbackNodeId, [
                    'route_miss' => true,
                    'last_input' => $input,
                ]);
            }
            return NodeExecutionResult::fail(['error' => 'no_route_match', 'input' => $input]);
        }

        $action = (string) ($matched['action'] ?? '');
        $statePatch = [
            'routed_action' => $action,
            'routed_input' => $input,
            'route_label' => $matched['label'] ?? $action,
        ];

        switch ($action) {
            case 'queue':
                $queueCode = (string) ($matched['queue_code'] ?? 'default');
                $decision = null;
                if ($channelId !== '' && $this->queueGateway !== null) {
                    $decision = $this->queueGateway->enqueueCaller(
                        $session->tenantId,
                        $session->callId,
                        $queueCode,
                        $channelId,
                        [
                            'ivr_input' => $input,
                        ]
                    );
                }
                if ($channelId !== '') {
                    if (is_array($decision) && !empty($decision['escalated']) && !empty($decision['agent_extension'])) {
                        $pbx->routeToExtension(
                            $channelId,
                            (string) $decision['agent_extension'],
                            $session->tenantId
                        );
                    } elseif (is_array($decision)) {
                        $pbx->routeToQueue(
                            $channelId,
                            (string) ($decision['selected_queue_code'] ?? $queueCode),
                            $session->tenantId,
                            isset($decision['selected_agent_id']) ? (int) $decision['selected_agent_id'] : null
                        );
                    } else {
                        $pbx->routeToQueue($channelId, $queueCode, $session->tenantId);
                    }
                }
                $nextNode = isset($matched['next_node_id']) ? (int) $matched['next_node_id'] : $node->nextNodeId;
                return NodeExecutionResult::advance($nextNode, $statePatch);

            case 'extension':
            case 'operator':
                $extension = (string) ($matched['extension'] ?? '0');
                if ($channelId !== '') {
                    $pbx->routeToExtension($channelId, $extension, $session->tenantId);
                }
                return NodeExecutionResult::complete($statePatch);

            case 'create_ticket':
                $subject = (string) ($matched['ticket_subject'] ?? 'IVR Support Request');
                $desc = (string) ($matched['ticket_description'] ?? 'Created from IVR input: ' . ($input ?? ''));
                $ticketId = 0;
                if ($this->ticketGateway !== null) {
                    $ticketId = $this->ticketGateway->createFromIvr(
                        $session->tenantId,
                        $session->callId,
                        $subject,
                        $desc,
                        ['input' => $input, 'locale' => $session->locale]
                    );
                }
                $statePatch['ticket_id'] = $ticketId;
                $nextNode = isset($matched['next_node_id']) ? (int) $matched['next_node_id'] : $node->nextNodeId;
                return NodeExecutionResult::advance($nextNode, $statePatch);

            case 'language':
                $lang = (string) ($matched['locale'] ?? 'ar');
                if (!in_array($lang, ['en', 'ar'], true)) {
                    $lang = 'ar';
                }
                $statePatch['locale'] = $lang;
                $nextNode = isset($matched['next_node_id']) ? (int) $matched['next_node_id'] : $node->nextNodeId;
                return NodeExecutionResult::advance($nextNode, $statePatch);

            case 'next_node':
                $nextNode = isset($matched['next_node_id']) ? (int) $matched['next_node_id'] : $node->nextNodeId;
                return NodeExecutionResult::advance($nextNode, $statePatch);

            default:
                return NodeExecutionResult::advance($node->nextNodeId, $statePatch);
        }
    }

    /**
     * @param list<array<string, mixed>> $routes
     * @param array<string, mixed>|null $defaultRoute
     * @return array<string, mixed>|null
     */
    private function matchRoute(array $routes, ?string $input, IvrSession $session, ?array $defaultRoute): ?array
    {
        foreach ($routes as $route) {
            if (!is_array($route)) {
                continue;
            }
            $dtmf = isset($route['dtmf']) ? (string) $route['dtmf'] : null;
            if ($dtmf !== null && $input !== null && $dtmf === $input) {
                return $route;
            }
            if (isset($route['tenant_locale']) && $route['tenant_locale'] === $session->locale) {
                return $route;
            }
        }

        if ($defaultRoute !== null && is_array($defaultRoute)) {
            return $defaultRoute;
        }

        return null;
    }
}
