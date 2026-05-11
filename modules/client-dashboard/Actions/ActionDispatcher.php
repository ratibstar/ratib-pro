<?php
declare(strict_types=1);

final class Ratib_ClientDashboard_Action_Dispatcher
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function dispatch(string $verb, string $targetId, array $input): array
    {
        require_once dirname(__DIR__) . '/Observability/ObservabilityHub.php';
        require_once dirname(__DIR__) . '/Observability/AuditLogger.php';
        require_once dirname(__DIR__) . '/Actions/ActionInterface.php';
        require_once dirname(__DIR__) . '/Actions/ActionContext.php';
        require_once dirname(__DIR__) . '/Actions/RenewAction.php';
        require_once dirname(__DIR__) . '/Actions/SuspendAction.php';
        require_once dirname(__DIR__) . '/Actions/RestartAction.php';
        require_once dirname(__DIR__) . '/Actions/UpgradeAction.php';
        require_once dirname(__DIR__) . '/Actions/CancelAction.php';
        require_once dirname(__DIR__) . '/Actions/RetryPaymentAction.php';
        require_once dirname(__DIR__) . '/Actions/OpenTicketAction.php';
        require_once dirname(__DIR__) . '/Tenant/TenantScope.php';
        require_once dirname(__DIR__) . '/Policy/PolicyEngine.php';
        require_once dirname(__DIR__) . '/Reliability/ActionReliabilityLayer.php';
        require_once dirname(__DIR__) . '/Events/InternalEventBus.php';
        require_once dirname(__DIR__) . '/Async/AsyncCoordinationLayer.php';
        require_once dirname(__DIR__) . '/Lifecycle/ServiceLifecycleCoordinator.php';
        require_once dirname(__DIR__) . '/Governance/GovernanceFacade.php';

        $map = [
            'renew' => Ratib_ClientDashboard_Action_Renew::class,
            'suspend' => Ratib_ClientDashboard_Action_Suspend::class,
            'restart' => Ratib_ClientDashboard_Action_Restart::class,
            'upgrade' => Ratib_ClientDashboard_Action_Upgrade::class,
            'cancel' => Ratib_ClientDashboard_Action_Cancel::class,
            'retry_payment' => Ratib_ClientDashboard_Action_RetryPayment::class,
            'open_ticket' => Ratib_ClientDashboard_Action_OpenTicket::class,
        ];

        if (!isset($map[$verb])) {
            return [
                'ok' => false,
                'code' => 'unknown_action',
                'message' => 'Unsupported action',
                'meta' => [],
            ];
        }

        $conn = isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli ? $GLOBALS['conn'] : null;
        $uid = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        $tenant = Ratib_ClientDashboard_TenantScope::fromSession();

        $correlationId = isset($input['correlation_id']) ? trim((string) $input['correlation_id']) : '';
        if ($correlationId === '') {
            $correlationId = bin2hex(random_bytes(8));
        }

        $policy = new Ratib_ClientDashboard_PolicyEngine();
        $gate = $policy->assertMutationAllowed($verb);
        if (!$gate['allowed']) {
            return self::wrapDenial($verb, $targetId, $correlationId, $gate['code'], $gate['message']);
        }

        $cached = Ratib_ClientDashboard_ActionReliabilityLayer::tryReplay($conn, $tenant, $verb, $targetId, $input);
        if ($cached !== null) {
            $lifecycle = new Ratib_ClientDashboard_ServiceLifecycleCoordinator();
            $replayObs = new Ratib_ClientDashboard_ObservabilityHub();
            $replayObs->setCorrelationId($correlationId);
            $clean = $cached;
            unset($clean['governance'], $clean['observability']);

            return Ratib_ClientDashboard_GovernanceFacade::augmentActionResponse(
                array_merge($clean, [
                    'meta' => array_merge((array) ($clean['meta'] ?? []), ['idempotent_replay' => true]),
                    'observability' => $replayObs->snapshotSlice(),
                ]),
                $correlationId,
                $replayObs->traceId(),
                null,
                $lifecycle,
                $verb
            );
        }

        $obs = new Ratib_ClientDashboard_ObservabilityHub();
        $obs->setCorrelationId($correlationId);
        $ctx = new Ratib_ClientDashboard_Action_Context($conn, $uid, $verb, $targetId, $input, $obs);

        $lifecycle = new Ratib_ClientDashboard_ServiceLifecycleCoordinator();
        $lcGate = $lifecycle->mapActionToLifecycle($verb, null);
        if (!$lcGate['allowed']) {
            return self::wrapDenial($verb, $targetId, $correlationId, 'lifecycle_denied', $lcGate['notes']);
        }

        /** @var Ratib_ClientDashboard_Action_Interface $handler */
        $class = $map[$verb];
        $handler = new $class();

        try {
            $result = $handler->execute($ctx);
            $ok = !empty($result['ok']);
            $ctx->obs->recordAction($verb, $ok, ['target' => $targetId, 'correlation_id' => $correlationId]);
            Ratib_ClientDashboard_AuditLogger::log($conn, 'action', [
                'action' => $verb,
                'target_id' => $targetId,
                'ok' => $ok,
                'code' => $result['code'] ?? null,
                'correlation_id' => $correlationId,
                'trace_id' => $ctx->obs->traceId(),
            ]);

            Ratib_ClientDashboard_InternalEventBus::publish($conn, 'action.completed', [
                'verb' => $verb,
                'target_id' => $targetId,
                'ok' => $ok,
                'tenant' => $tenant->toMeta(),
                'correlation_id' => $correlationId,
            ]);

            $asyncEnv = null;
            if ($ok && ($policy->preferAsyncQueue() || in_array($verb, ['renew', 'retry_payment'], true))) {
                $asyncEnv = Ratib_ClientDashboard_AsyncCoordinationLayer::enqueue(
                    $conn,
                    $verb,
                    $targetId,
                    $tenant,
                    $correlationId,
                    ['code' => $result['code'] ?? null]
                );
            }

            $merged = array_merge(
                [
                    'observability' => $ctx->obs->snapshotSlice(),
                ],
                $result
            );

            $merged = Ratib_ClientDashboard_GovernanceFacade::augmentActionResponse(
                $merged,
                $correlationId,
                $ctx->obs->traceId(),
                $asyncEnv,
                $lifecycle,
                $verb
            );

            Ratib_ClientDashboard_ActionReliabilityLayer::remember($conn, $tenant, $verb, $targetId, $input, $merged);

            return $merged;
        } catch (\Throwable $e) {
            $ctx->obs->recordAction($verb, false, ['error' => $e->getMessage(), 'correlation_id' => $correlationId]);
            Ratib_ClientDashboard_AuditLogger::log($conn, 'action_error', [
                'action' => $verb,
                'target_id' => $targetId,
                'error' => $e->getMessage(),
                'correlation_id' => $correlationId,
            ]);
            Ratib_ClientDashboard_InternalEventBus::publish($conn, 'action.failed', [
                'verb' => $verb,
                'target_id' => $targetId,
                'tenant' => $tenant->toMeta(),
                'correlation_id' => $correlationId,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'code' => 'exception',
                'message' => 'Action handler failed safely',
                'meta' => ['detail' => 'logged'],
                'observability' => $ctx->obs->snapshotSlice(),
                'governance' => [
                    'correlation_id' => $correlationId,
                    'trace_id' => $ctx->obs->traceId(),
                    'lifecycle' => $lifecycle->mapActionToLifecycle($verb, null),
                    'async' => null,
                ],
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function wrapDenial(string $verb, string $targetId, string $correlationId, string $code, string $message): array
    {
        $obs = new Ratib_ClientDashboard_ObservabilityHub();
        $obs->setCorrelationId($correlationId);
        $lifecycle = new Ratib_ClientDashboard_ServiceLifecycleCoordinator();

        return [
            'ok' => false,
            'code' => $code,
            'message' => $message,
            'meta' => ['target_id' => $targetId],
            'observability' => $obs->snapshotSlice(),
            'governance' => [
                'correlation_id' => $correlationId,
                'trace_id' => $obs->traceId(),
                'lifecycle' => $lifecycle->mapActionToLifecycle($verb, null),
                'async' => null,
            ],
        ];
    }
}
