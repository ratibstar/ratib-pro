<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Controllers;

use Rateb\App\Automotive\Services\AutomotiveGulfFoundationService;
use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;

final class AutomotiveGulfFoundationController extends AutomotiveBaseController
{
    public function __construct(private ?AutomotiveGulfFoundationService $service = null)
    {
    }

    public function index(): void
    {
        $this->bootstrapAutomotive();
        $this->guardView('automotive_reference');
        $service = $this->service();
        $this->automotiveView('gulf/index', [
            'title' => __('auto_gulf_title'),
            'markets' => $service->gulfMarkets(),
            'providers' => $service->providers(),
            'rights' => $service->rights(),
            'coverage' => $service->coverage(),
            'can_manage' => function_exists('rateb_can') && rateb_can('automotive.reference.manage'),
            'can_import' => function_exists('rateb_can') && rateb_can('automotive.reference.import'),
        ]);
    }

    public function storeProvider(): void
    {
        $this->post(function (AutomotiveGulfFoundationService $service): void {
            if (!function_exists('rateb_can') || !rateb_can('automotive.reference.manage')) {
                throw new \RuntimeException('forbidden');
            }
            $markets = (array) $this->input('markets', []);
            $categories = (array) $this->input('categories', []);
            $service->saveProvider([
                'code' => (string) $this->input('code', ''),
                'provider_type' => (string) $this->input('provider_type', ''),
                'name_en' => (string) $this->input('name_en', ''),
                'name_ar' => (string) $this->input('name_ar', ''),
                'website' => (string) $this->input('website', ''),
                'contact' => (string) $this->input('contact', ''),
                'status' => (string) $this->input('status', 'pending'),
                'source_note' => (string) $this->input('source_note', ''),
                'review_note' => (string) $this->input('review_note', ''),
            ], $markets, $categories);
        });
    }

    public function storeRights(): void
    {
        $this->post(function (AutomotiveGulfFoundationService $service): void {
            if (!function_exists('rateb_can') || !rateb_can('automotive.reference.manage')) {
                throw new \RuntimeException('forbidden');
            }
            $service->saveRights(
                (int) $this->input('provider_id', 0),
                (string) $this->input('evidence', ''),
                [
                    'store_local' => (string) $this->input('store_local', 'unknown'),
                    'process_internal' => (string) $this->input('process_internal', 'unknown'),
                    'commercial_display' => (string) $this->input('commercial_display', 'unknown'),
                    'saas_multi' => (string) $this->input('saas_multi', 'unknown'),
                    'redistribute' => (string) $this->input('redistribute', 'unknown'),
                    'diagrams' => (string) $this->input('diagrams', 'unknown'),
                    'access_mode' => (string) $this->input('access_mode', 'unknown'),
                ],
                (string) $this->input('territory_codes', ''),
                $this->dateOrNull('expires_on'),
                $this->dateOrNull('terminated_on'),
                $this->dateOrNull('effective_on'),
                (string) $this->input('data_category', '')
            );
        });
    }

    public function storeCoverage(): void
    {
        $this->post(function (AutomotiveGulfFoundationService $service): void {
            if (!function_exists('rateb_can') || !rateb_can('automotive.reference.manage')) {
                throw new \RuntimeException('forbidden');
            }
            $service->saveCoverage([
                'market_code' => (string) $this->input('market_code', ''),
                'provider_id' => (int) $this->input('provider_id', 0),
                'brand_name' => (string) $this->input('brand_name', ''),
                'model_name' => (string) $this->input('model_name', ''),
                'year_from' => $this->input('year_from', '') !== '' ? (int) $this->input('year_from') : null,
                'year_to' => $this->input('year_to', '') !== '' ? (int) $this->input('year_to') : null,
                'vehicle_category' => (string) $this->input('vehicle_category', ''),
                'data_category' => (string) $this->input('data_category', ''),
                'verification' => (string) $this->input('verification', ''),
                'license_status' => (string) $this->input('license_status', ''),
            ]);
        });
    }

    public function dryRun(): void
    {
        $this->post(function (AutomotiveGulfFoundationService $service): void {
            if (!function_exists('rateb_can') || !rateb_can('automotive.reference.import')) {
                throw new \RuntimeException('forbidden');
            }
            $service->dryRun(
                (int) $this->input('rights_id', 0),
                (string) $this->input('market_code', ''),
                (string) $this->input('payload', ''),
                \Rateb\App\Automotive\Services\AutomotiveGulfFoundationService::businessDate()
            );
        });
    }

    private function post(callable $action): void
    {
        $this->bootstrapAutomotive();
        $this->guardView('automotive_reference');
        $back = rateb_url(rateb_app_route('automotive/gulf'));
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('auto_gulf_csrf'));
            $this->redirect($back);
        }
        try {
            $action($this->service());
            SessionManager::flash('success', __('auto_epc_saved'));
        } catch (\Throwable $e) {
            $code = $e->getMessage();
            $safe = in_array($code, ['invalid_provider', 'invalid_rights', 'invalid_coverage', 'invalid_fixture', 'rights_blocked', 'rights_conflict', 'checksum_mismatch', 'forbidden'], true)
                ? __($code === 'forbidden' ? 'auto_gulf_forbidden' : 'auto_gulf_' . $code)
                : __('auto_gulf_failed');
            SessionManager::flash('error', $safe);
        }
        $this->redirect($back);
    }

    private function dateOrNull(string $key): ?string
    {
        $value = trim((string) $this->input($key, ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    private function service(): AutomotiveGulfFoundationService
    {
        return $this->service ?? new AutomotiveGulfFoundationService(Database::connection());
    }
}
