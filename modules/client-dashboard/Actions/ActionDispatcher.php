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
        $obs = new Ratib_ClientDashboard_ObservabilityHub();
        $ctx = new Ratib_ClientDashboard_Action_Context($conn, $uid, $verb, $targetId, $input, $obs);

        /** @var Ratib_ClientDashboard_Action_Interface $handler */
        $class = $map[$verb];
        $handler = new $class();

        try {
            $result = $handler->execute($ctx);
            $ok = !empty($result['ok']);
            $ctx->obs->recordAction($verb, $ok, ['target' => $targetId]);
            Ratib_ClientDashboard_AuditLogger::log($conn, 'action', [
                'action' => $verb,
                'target_id' => $targetId,
                'ok' => $ok,
                'code' => $result['code'] ?? null,
            ]);

            return array_merge(
                [
                    'observability' => $ctx->obs->snapshotSlice(),
                ],
                $result
            );
        } catch (Throwable $e) {
            $ctx->obs->recordAction($verb, false, ['error' => $e->getMessage()]);
            Ratib_ClientDashboard_AuditLogger::log($conn, 'action_error', [
                'action' => $verb,
                'target_id' => $targetId,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'code' => 'exception',
                'message' => 'Action handler failed safely',
                'meta' => ['detail' => 'logged'],
                'observability' => $ctx->obs->snapshotSlice(),
            ];
        }
    }
}
