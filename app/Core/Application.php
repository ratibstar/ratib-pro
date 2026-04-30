<?php
declare(strict_types=1);

namespace App\Core;

use App\Controllers\Http\TrackingController;
use App\Controllers\Http\WorkerController;
use App\Controllers\Http\WorkflowController;
use App\Controllers\Http\WorkflowTimelineController;
use App\Controllers\Http\MetricsController;
use App\Domain\Contracts\EmployerRepositoryInterface;
use App\Domain\Contracts\NotificationRepositoryInterface;
use App\Domain\Contracts\TrackingLogRepositoryInterface;
use App\Domain\Contracts\ViolationRepositoryInterface;
use App\Domain\Contracts\WorkerRepositoryInterface;
use App\Events\ViolationDetected;
use App\Events\WorkflowExecutionCompleted;
use App\Events\WorkflowExecutionFailed;
use App\Events\WorkflowExecutionStarted;
use App\Events\WorkflowStepLifecycle;
use App\Events\WorkerCreated;
use App\Events\WorkerMoved;
use App\Listeners\HandleWorkerMovedListener;
use App\Listeners\LogWorkflowExecutionEventListener;
use App\Listeners\LogWorkerCreatedListener;
use App\Listeners\NotifyViolationDetectedListener;
use App\Listeners\ProcessAlertIntelligenceListener;
use App\Listeners\QueueWebhookListener;
use App\Listeners\WorkflowMetricsListener;
use App\Middleware\AccessMiddleware;
use App\Middleware\ExternalApiMiddleware;
use App\Middleware\SecurityMiddleware;
use App\Repositories\AlertRepository;
use App\Repositories\EmployerRepository;
use App\Repositories\EventLogRepository;
use App\Repositories\LoginAuditRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\SessionRepository;
use App\Repositories\TrackingLogRepository;
use App\Repositories\ViolationRepository;
use App\Repositories\WorkerRepository;
use App\Repositories\WorkflowRepository;
use App\Repositories\WorkflowStateRepository;
use App\Repositories\WebhookRepository;
use App\Services\ComplianceService;
use App\Services\NotificationService;
use App\Services\AuthorizationService;
use App\Services\AlertService;
use App\Services\AlertQueryService;
use App\Services\ApiTokenService;
use App\Services\IpRestrictionService;
use App\Services\RateLimiterService;
use App\Services\RequestSigningService;
use App\Services\TrackingService;
use App\Services\TwoFactorService;
use App\Services\WorkerService;
use App\Services\WorkerReadService;
use App\Services\WorkflowService;
use App\Services\WorkflowTimelineService;
use App\Services\MetricsService;
use App\Services\WebhookService;
use App\Workflows\Steps\AssignEmployerStep;
use App\Workflows\Steps\CreateWorkerStep;
use App\Workflows\Steps\SendNotificationStep;
use App\Workflows\Steps\StartTrackingStep;
use App\Workflows\Steps\ValidateWorkerStep;
use App\Workflows\WorkerOnboardingWorkflow;
use PDO;

final class Application
{
    public static function boot(array $config): Container
    {
        $container = new Container();

        $container->singleton(PDO::class, fn () => Database::connect($config['db']));
        $container->singleton(EventDispatcher::class, fn () => new EventDispatcher());
        $container->singleton(RealtimeServer::class, fn () => new RealtimeServer('worker-platform'));
        $container->singleton(ModeResolver::class, fn () => new ModeResolver($config['system_mode'] ?? []));
        $container->singleton(PolicyEngine::class, fn () => new PolicyEngine());
        $container->singleton(FrozenExecutionContext::class, fn (Container $c) => new FrozenExecutionContext(
            $c->get(ModeResolver::class)->resolve(),
            $c->get(PolicyEngine::class)->forMode($c->get(ModeResolver::class)->resolve())
        ));

        $container->singleton(WorkerRepositoryInterface::class, fn (Container $c) => new WorkerRepository($c->get(PDO::class)));
        $container->singleton(EmployerRepositoryInterface::class, fn (Container $c) => new EmployerRepository($c->get(PDO::class)));
        $container->singleton(TrackingLogRepositoryInterface::class, fn (Container $c) => new TrackingLogRepository($c->get(PDO::class)));
        $container->singleton(ViolationRepositoryInterface::class, fn (Container $c) => new ViolationRepository($c->get(PDO::class)));
        $container->singleton(NotificationRepositoryInterface::class, fn (Container $c) => new NotificationRepository($c->get(PDO::class)));
        $container->singleton(EventLogRepository::class, fn (Container $c) => new EventLogRepository($c->get(PDO::class)));
        $container->singleton(AlertRepository::class, fn (Container $c) => new AlertRepository($c->get(PDO::class)));
        $container->singleton(WorkflowRepository::class, fn (Container $c) => new WorkflowRepository($c->get(PDO::class)));
        $container->singleton(WorkflowStateRepository::class, fn (Container $c) => new WorkflowStateRepository($c->get(PDO::class)));
        $container->singleton(WebhookRepository::class, fn (Container $c) => new WebhookRepository($c->get(PDO::class)));
        $container->singleton(SessionRepository::class, fn (Container $c) => new SessionRepository($c->get(PDO::class)));
        $container->singleton(LoginAuditRepository::class, fn (Container $c) => new LoginAuditRepository($c->get(PDO::class)));
        $container->singleton(IdempotencyService::class, fn (Container $c) => new IdempotencyService($c->get(WorkflowRepository::class)));
        $container->singleton(WorkflowMetrics::class, fn (Container $c) => new WorkflowMetrics($c->get(WorkflowRepository::class)));
        $container->singleton(AuthorizationService::class, fn (Container $c) => new AuthorizationService($c->get(PDO::class)));
        $container->singleton(AlertService::class, fn (Container $c) => new AlertService($c->get(AlertRepository::class)));
        $container->singleton(WebhookService::class, fn (Container $c) => new WebhookService($c->get(WebhookRepository::class)));
        $container->singleton(AlertQueryService::class, fn (Container $c) => new AlertQueryService($c->get(AlertRepository::class)));
        $container->singleton(ApiTokenService::class, fn () => new ApiTokenService());
        $container->singleton(RateLimiterService::class, fn (Container $c) => new RateLimiterService($c->get(PDO::class)));
        $container->singleton(TwoFactorService::class, fn (Container $c) => new TwoFactorService($c->get(PDO::class)));
        $container->singleton(RequestSigningService::class, fn (Container $c) => new RequestSigningService());
        $container->singleton(IpRestrictionService::class, fn () => new IpRestrictionService());
        $container->singleton(SecurityMiddleware::class, fn (Container $c) => new SecurityMiddleware(
            $c->get(IpRestrictionService::class),
            $c->get(RateLimiterService::class),
            $c->get(TwoFactorService::class),
            $c->get(RequestSigningService::class)
        ));
        $container->singleton(AccessMiddleware::class, fn (Container $c) => new AccessMiddleware(
            $c->get(AuthorizationService::class),
            $c->get(SessionRepository::class)
        ));
        $container->singleton(ExternalApiMiddleware::class, fn (Container $c) => new ExternalApiMiddleware(
            $c->get(ApiTokenService::class),
            $c->get(RateLimiterService::class)
        ));

        $container->singleton(NotificationService::class, fn (Container $c) => new NotificationService($c->get(NotificationRepositoryInterface::class)));
        $container->singleton(WorkerService::class, fn (Container $c) => new WorkerService(
            $c->get(WorkerRepositoryInterface::class),
            $c->get(EmployerRepositoryInterface::class),
            $c->get(EventDispatcher::class)
        ));
        $container->singleton(TrackingService::class, fn (Container $c) => new TrackingService(
            $c->get(TrackingLogRepositoryInterface::class),
            $c->get(WorkerRepositoryInterface::class),
            $c->get(EventDispatcher::class)
        ));
        $container->singleton(ComplianceService::class, fn (Container $c) => new ComplianceService(
            $c->get(ViolationRepositoryInterface::class),
            $c->get(EventDispatcher::class)
        ));
        $container->singleton(WorkflowService::class, fn (Container $c) => new WorkflowService(
            $c->get(WorkflowEngine::class),
            $c->get(WorkflowRepository::class),
            $c->get(WorkflowStateRepository::class),
            $c->get(WorkerOnboardingWorkflow::class),
            $c->get(FrozenExecutionContext::class),
            $c->get(EventDispatcher::class),
            $c->get(IdempotencyService::class)
        ));
        $container->singleton(WorkerReadService::class, fn (Container $c) => new WorkerReadService(
            $c->get(WorkerRepositoryInterface::class)
        ));
        $container->singleton(WorkflowTimelineService::class, fn (Container $c) => new WorkflowTimelineService(
            $c->get(WorkflowRepository::class),
            $c->get(WorkflowStateRepository::class),
            $c->get(EventLogRepository::class)
        ));
        $container->singleton(MetricsService::class, fn (Container $c) => new MetricsService(
            $c->get(PDO::class),
            $c->get(WorkflowMetrics::class)
        ));

        $container->singleton(ValidateWorkerStep::class, fn () => new ValidateWorkerStep());
        $container->singleton(CreateWorkerStep::class, fn (Container $c) => new CreateWorkerStep($c->get(WorkerService::class)));
        $container->singleton(AssignEmployerStep::class, fn () => new AssignEmployerStep());
        $container->singleton(StartTrackingStep::class, fn (Container $c) => new StartTrackingStep($c->get(TrackingService::class)));
        $container->singleton(SendNotificationStep::class, fn (Container $c) => new SendNotificationStep($c->get(NotificationService::class)));

        $container->singleton(WorkerOnboardingWorkflow::class, fn (Container $c) => new WorkerOnboardingWorkflow(
            $c->get(ValidateWorkerStep::class),
            $c->get(CreateWorkerStep::class),
            $c->get(AssignEmployerStep::class),
            $c->get(StartTrackingStep::class),
            $c->get(SendNotificationStep::class)
        ));
        $container->singleton(WorkflowEngine::class, fn (Container $c) => new WorkflowEngine(
            $c->get(EventDispatcher::class),
            $c->get(WorkflowStateRepository::class)
        ));

        $container->singleton(WorkerController::class, fn (Container $c) => new WorkerController($c->get(WorkerService::class)));
        $container->singleton(TrackingController::class, fn (Container $c) => new TrackingController(
            $c->get(TrackingService::class),
            $c->get(ComplianceService::class)
        ));
        $container->singleton(WorkflowController::class, fn (Container $c) => new WorkflowController($c->get(WorkflowService::class)));
        $container->singleton(WorkflowTimelineController::class, fn (Container $c) => new WorkflowTimelineController(
            $c->get(WorkflowTimelineService::class)
        ));
        $container->singleton(MetricsController::class, fn (Container $c) => new MetricsController(
            $c->get(MetricsService::class)
        ));

        self::registerListeners($container);
        return $container;
    }

    private static function registerListeners(Container $container): void
    {
        $events = $container->get(EventDispatcher::class);
        $realtime = $container->get(RealtimeServer::class);
        $workflowLogger = new LogWorkflowExecutionEventListener($container->get(EventLogRepository::class));
        $metricsListener = new WorkflowMetricsListener($container->get(WorkflowMetrics::class));
        $alertIntelligence = new ProcessAlertIntelligenceListener($container->get(AlertService::class));
        $webhookQueue = new QueueWebhookListener($container->get(WebhookService::class));

        $events->listen(WorkerCreated::class, new LogWorkerCreatedListener($container->get(EventLogRepository::class)));
        $events->listen(WorkerCreated::class, [$webhookQueue, 'onWorkerCreated']);
        $events->listen(WorkerMoved::class, new HandleWorkerMovedListener($container->get(EventLogRepository::class)));
        $events->listen(ViolationDetected::class, new NotifyViolationDetectedListener(
            $container->get(NotificationService::class),
            $container->get(EventLogRepository::class)
        ));
        $events->listen(ViolationDetected::class, [$webhookQueue, 'onViolationDetected']);
        $events->listen(WorkflowExecutionStarted::class, [$workflowLogger, 'onStarted']);
        $events->listen(WorkflowExecutionCompleted::class, [$workflowLogger, 'onCompleted']);
        $events->listen(WorkflowExecutionCompleted::class, [$webhookQueue, 'onWorkflowCompleted']);
        $events->listen(WorkflowExecutionFailed::class, [$workflowLogger, 'onFailed']);
        $events->listen(WorkflowExecutionStarted::class, [$metricsListener, 'onStarted']);
        $events->listen(WorkflowExecutionCompleted::class, [$metricsListener, 'onCompleted']);
        $events->listen(WorkflowExecutionFailed::class, [$metricsListener, 'onFailed']);
        $events->listen(ViolationDetected::class, [$alertIntelligence, 'onViolationDetected']);
        $events->listen(WorkflowExecutionFailed::class, [$alertIntelligence, 'onWorkflowFailed']);
        $events->listen(WorkerMoved::class, static function (WorkerMoved $event) use ($realtime): void {
            $realtime->publish('worker.movement', [
                'worker_id' => $event->workerId,
                'latitude' => $event->latitude,
                'longitude' => $event->longitude,
            ]);
        });
        $events->listen(ViolationDetected::class, static function (ViolationDetected $event) use ($realtime): void {
            $realtime->publish('alerts', [
                'worker_id' => $event->workerId,
                'type' => $event->violationType,
                'severity' => $event->severity,
            ]);
        });
        $events->listen(WorkflowExecutionStarted::class, static function (WorkflowExecutionStarted $event) use ($realtime): void {
            $realtime->publish('workflow.status', [
                'workflow_id' => $event->workflowId,
                'workflow_name' => $event->workflowName,
                'status' => 'started',
                'mode' => $event->mode,
                'actor' => $event->actor,
                'sequence' => $event->sequenceNumber,
                'timestamp' => $event->timestamp,
            ]);
        });
        $events->listen(WorkflowExecutionCompleted::class, static function (WorkflowExecutionCompleted $event) use ($realtime): void {
            $realtime->publish('workflow.status', [
                'workflow_id' => $event->workflowId,
                'workflow_name' => $event->workflowName,
                'status' => 'completed',
                'mode' => $event->mode,
                'actor' => $event->actor,
                'sequence' => $event->sequenceNumber,
                'timestamp' => $event->timestamp,
            ]);
        });
        $events->listen(WorkflowExecutionFailed::class, static function (WorkflowExecutionFailed $event) use ($realtime): void {
            $realtime->publish('workflow.status', [
                'workflow_id' => $event->workflowId,
                'workflow_name' => $event->workflowName,
                'status' => 'failed',
                'mode' => $event->mode,
                'actor' => $event->actor,
                'sequence' => $event->sequenceNumber,
                'timestamp' => $event->timestamp,
                'error' => $event->error,
            ]);
        });
        // Step lifecycle events are emitted by engine and intentionally not re-dispatched from listeners.
        $events->listen(WorkflowStepLifecycle::class, static function (WorkflowStepLifecycle $event): void {
            // no-op by default; hooks can be attached without changing engine flow
        });
    }
}
