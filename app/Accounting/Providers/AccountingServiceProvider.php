<?php
declare(strict_types=1);

namespace App\Accounting\Providers;

use App\Accounting\Adapters\ControlPanelAccountingAdapter;
use App\Accounting\Adapters\LedgerAccountingAdapter;
use App\Accounting\Adapters\MainSiteAccountingAdapter;
use App\Accounting\Adapters\RatebErpAccountingAdapter;
use App\Accounting\Audit\AccountingAuditService;
use App\Accounting\Closing\AccountingPeriodCloser;
use App\Accounting\Consolidation\AccountingConsolidationEngine;
use App\Accounting\Core\AccountingEventValidator;
use App\Accounting\Core\AccountingGateway;
use App\Accounting\Core\AccountingIdempotency;
use App\Accounting\Drift\AccountingDriftDetector;
use App\Accounting\EventStore\AccountingEventRepository;
use App\Accounting\EventStore\AccountingEventStore;
use App\Accounting\Normalization\AccountingNormalizer;
use App\Accounting\Pipeline\AccountingEventPipeline;
use App\Accounting\Pipeline\AccountingProjectionHook;
use App\Accounting\Projections\AccountingProjectionEngine;
use App\Accounting\Projections\AccountingSnapshotRebuilder;
use App\Accounting\Projections\ProjectionRepository;
use App\Accounting\Replay\AccountingReplayEngine;
use App\Accounting\Reporting\AccountingReportService;
use Illuminate\Support\ServiceProvider;

final class AccountingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AccountingEventValidator::class);
        $this->app->singleton(AccountingEventRepository::class);
        $this->app->singleton(AccountingEventStore::class);
        $this->app->singleton(AccountingIdempotency::class);
        $this->app->singleton(AccountingAuditService::class);
        $this->app->singleton(AccountingNormalizer::class);
        $this->app->singleton(AccountingReportService::class);
        $this->app->singleton(ProjectionRepository::class);
        $this->app->singleton(AccountingProjectionEngine::class);
        $this->app->singleton(AccountingProjectionHook::class);
        $this->app->singleton(AccountingConsolidationEngine::class);
        $this->app->singleton(AccountingPeriodCloser::class);
        $this->app->singleton(AccountingDriftDetector::class);
        $this->app->singleton(AccountingSnapshotRebuilder::class);

        $this->app->singleton(AccountingGateway::class, static function (): AccountingGateway {
            return new AccountingGateway([
                new RatebErpAccountingAdapter(),
                new MainSiteAccountingAdapter(),
                new ControlPanelAccountingAdapter(),
                new LedgerAccountingAdapter(),
            ]);
        });

        $this->app->singleton(AccountingEventPipeline::class, static function ($app): AccountingEventPipeline {
            return new AccountingEventPipeline($app->make(AccountingGateway::class));
        });

        $this->app->singleton(AccountingReplayEngine::class);
    }

    public function boot(): void
    {
        $bootstrap = dirname(__DIR__) . '/Support/post_accounting_event.php';
        if (is_file($bootstrap)) {
            require_once $bootstrap;
        }
    }
}
