<?php
declare(strict_types=1);

use App\Controllers\Http\TrackingController;
use App\Controllers\Http\WorkerController;
use App\Controllers\Http\WorkflowController;
use App\Controllers\Http\WorkflowTimelineController;
use App\Controllers\Http\MetricsController;
use App\Core\Container;
use App\Core\SystemAlertService;
use App\Core\SystemHealth;
use App\Middleware\AccessMiddleware;
use App\Middleware\SecurityMiddleware;

/** @return array<string, callable(array<string, mixed>):array> */
return static function (Container $container): array {
    /** @var AccessMiddleware $access */
    $access = $container->get(AccessMiddleware::class);
    /** @var SecurityMiddleware $security */
    $security = $container->get(SecurityMiddleware::class);

    return [
        'POST /workers' => fn (array $payload) => $access->handle(
            $access->resolveCurrentUser(),
            'workers.create',
            $payload,
            function (array $safePayload) use ($container, $security, $access): array {
                $security->enforce($access->resolveCurrentUser(), 'workers.create', (string) ($safePayload['__raw_body'] ?? ''));
                return $container->get(WorkerController::class)->store($safePayload);
            }
        ),
        'POST /tracking/move' => fn (array $payload) => $access->handle(
            $access->resolveCurrentUser(),
            'tracking.move',
            $payload,
            function (array $safePayload) use ($container, $security, $access): array {
                $security->enforce($access->resolveCurrentUser(), 'tracking.move', (string) ($safePayload['__raw_body'] ?? ''));
                return $container->get(TrackingController::class)->move($safePayload);
            }
        ),
        'GET /workflows/{id}/timeline' => fn (array $payload) => $access->handle(
            $access->resolveCurrentUser(),
            'workflow.timeline.view',
            $payload,
            function (array $safePayload) use ($container, $security, $access): array {
                $security->enforce($access->resolveCurrentUser(), 'workflow.timeline.view', '');
                $workflowId = (int) ($safePayload['_route_params']['id'] ?? 0);
                return $container->get(WorkflowTimelineController::class)->show($workflowId);
            }
        ),
        'GET /metrics/system-health' => fn (array $payload) => $access->handle(
            $access->resolveCurrentUser(),
            'metrics.system_health.view',
            $payload,
            function (array $safePayload) use ($container, $security, $access): array {
                $security->enforce($access->resolveCurrentUser(), 'metrics.system_health.view', '');
                return $container->get(MetricsController::class)->systemHealth();
            }
        ),
        'GET /metrics/workflow-stats' => fn (array $payload) => $access->handle(
            $access->resolveCurrentUser(),
            'metrics.workflow_stats.view',
            $payload,
            function (array $safePayload) use ($container, $security, $access): array {
                $security->enforce($access->resolveCurrentUser(), 'metrics.workflow_stats.view', '');
                return $container->get(MetricsController::class)->workflowStats();
            }
        ),
        'GET /metrics/failure-rates' => fn (array $payload) => $access->handle(
            $access->resolveCurrentUser(),
            'metrics.failure_rates.view',
            $payload,
            function (array $safePayload) use ($container, $security, $access): array {
                $security->enforce($access->resolveCurrentUser(), 'metrics.failure_rates.view', '');
                return $container->get(MetricsController::class)->failureRates();
            }
        ),
        'GET /system/health' => fn (array $payload) => $access->handle(
            $access->resolveCurrentUser(),
            'metrics.system_health.view',
            $payload,
            function (array $safePayload) use ($container, $security, $access): array {
                $security->enforce($access->resolveCurrentUser(), 'metrics.system_health.view', '');
                $snapshot = $container->get(SystemHealth::class)->snapshot();
                $alerts = $container->get(SystemAlertService::class)->evaluate($snapshot);
                $container->get(SystemAlertService::class)->dispatchFailSafe($alerts);
                $snapshot['alerts'] = $alerts;
                return $snapshot;
            }
        ),
    ];
};
