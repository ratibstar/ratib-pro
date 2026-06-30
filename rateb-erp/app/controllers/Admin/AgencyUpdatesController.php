<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Services\AgencyErpMigrationService;

final class AgencyUpdatesController extends Controller
{
    public function index(): void
    {
        if (!rateb_is_super_admin()) {
            Response::redirect(rateb_url('admin'));
        }
        $svc = new AgencyErpMigrationService();
        $opsCompanyId = function_exists('rateb_resolve_ops_company_id') ? rateb_resolve_ops_company_id() : 0;
        $this->view('admin/agency-updates/index', [
            'title' => __('agency_erp_push_title'),
            'agencies' => $svc->listAgencies(false),
            'platformDb' => function_exists('rateb_erp_database_name') ? rateb_erp_database_name() : '',
            'suggestedAgencyId' => $svc->suggestedAgencyIdForCompany($opsCompanyId),
            'opsCompanyId' => $opsCompanyId,
            'csrf' => Csrf::token(),
            'pushUrl' => rateb_url('admin/agency-updates/push'),
        ], 'main');
    }

    public function push(): void
    {
        if (!rateb_is_super_admin()) {
            Response::json(['success' => false, 'message' => __('access_denied')], 403);
        }
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => __('csrf_invalid')], 403);
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '{}', true);
        if (!is_array($data)) {
            $data = $_POST;
        }

        try {
            $payload = (new AgencyErpMigrationService())->push([
                'scope' => (string) ($data['scope'] ?? ''),
                'include_platform' => !empty($data['include_platform']),
                'agency_ids' => $data['agency_ids'] ?? ($data['ids'] ?? []),
            ]);
            Response::json($payload, empty($payload['success']) ? 422 : 200);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
