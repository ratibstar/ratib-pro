<?php
declare(strict_types=1);

namespace App\Core;

use App\Controllers\Http\TrackingController;
use App\Controllers\Http\WorkerController;
use App\Controllers\Http\WorkflowController;
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
use App\Repositories\EmployerRepository;
use App\Repositories\EventLogRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\TrackingLogRepository;
use App\Repositories\ViolationRepository;
use App\Repositories\WorkerRepository;
use App\Repositories\WorkflowRepository;
use App\Services\ComplianceService;
use App\Services\NotificationService;
use App\Services\TrackingService;
use App\Services\WorkerService;
use App\Services\WorkflowService;
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
        $container->singleton(WorkflowRepository::class, fn (Container $c) => new WorkflowRepository($c->get(PDO::class)));

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
            $c->get(WorkerOnboardingWorkflow::class),
            $c->get(FrozenExecutionContext::class),
            $c->get(EventDispatcher::class)
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
        $container->singleton(WorkflowEngine::class, fn (Container $c) => new WorkflowEngine($c->get(EventDispatcher::class)));

        $container->singleton(WorkerController::class, fn (Container $c) => new WorkerController($c->get(WorkerService::class)));
        $container->singleton(TrackingController::class, fn (Container $c) => new TrackingController(
            $c->get(TrackingService::class),
            $c->get(ComplianceService::class)
        ));
        $container->singleton(WorkflowController::class, fn (Container $c) => new WorkflowController($c->get(WorkflowService::class)));

        self::registerListeners($container);
        return $container;
    }

    private static function registerListeners(Container $container): void
    {
        $events = $container->get(EventDispatcher::class);
        $workflowLogger = new LogWorkflowExecutionEventListener($container->get(EventLogRepository::class));

        $events->listen(WorkerCreated::class, new LogWorkerCreatedListener($container->get(EventLogRepository::class)));
        $events->listen(WorkerMoved::class, new HandleWorkerMovedListener($container->get(EventLogRepository::class)));
        $events->listen(ViolationDetected::class, new NotifyViolationDetectedListener(
            $container->get(NotificationService::class),
            $container->get(EventLogRepository::class)
        ));
        $events->listen(WorkflowExecutionStarted::class, [$workflowLogger, 'onStarted']);
        $events->listen(WorkflowExecutionCompleted::class, [$workflowLogger, 'onCompleted']);
        $events->listen(WorkflowExecutionFailed::class, [$workflowLogger, 'onFailed']);
        // Step lifecycle events are emitted by engine and intentionally not re-dispatched from listeners.
        $events->listen(WorkflowStepLifecycle::class, static function (WorkflowStepLifecycle $event): void {
            // no-op by default; hooks can be attached without changing engine flow
        });
    }
}
