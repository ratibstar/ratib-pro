<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Models\Company;
use Rateb\App\Services\AgencyErpMigrationService;
use Rateb\App\Services\AgencyFileSyncService;

final class AgencyUpdatesController extends Controller
{
    public function index(): void
    {
        if (!rateb_is_super_admin()) {
            Response::redirect(rateb_url('admin'));
        }
        $svc = new AgencyErpMigrationService();
        $opsCompanyId = function_exists('rateb_resolve_ops_company_id') ? rateb_resolve_ops_company_id() : 0;
        $queryCompanyId = (int) ($_GET['company_id'] ?? 0);
        if ($queryCompanyId > 0) {
            $opsCompanyId = $queryCompanyId;
        }
        $suggestedAgencyId = $svc->suggestedAgencyIdForCompany($opsCompanyId);
        $opsCompanyName = '';
        if ($opsCompanyId > 0) {
            $row = (new Company())->find($opsCompanyId);
            $opsCompanyName = trim((string) ($row['name'] ?? ''));
        }
        $companyNames = $svc->platformCompanyNames();
        $fileSyncSvc = new AgencyFileSyncService();
        $syncPreview = $fileSyncSvc->previewForSiteUrl('https://test.rateb.sa/');
        $this->view('admin/agency-updates/index', [
            'title' => __('agency_erp_push_title'),
            'agencies' => $svc->listAgencies(false),
            'platformDb' => function_exists('rateb_erp_database_name') ? rateb_erp_database_name() : '',
            'suggestedAgencyId' => $suggestedAgencyId,
            'opsCompanyId' => $opsCompanyId,
            'opsCompanyName' => $opsCompanyName,
            'companyNames' => $companyNames,
            'syncSource' => (string) ($syncPreview['source'] ?? ''),
            'syncTargetExample' => (string) ($syncPreview['target'] ?? ''),
            'csrf' => Csrf::token(),
            'pushUrl' => rateb_url('admin/agency-updates/push'),
            'linkUrl' => rateb_url('admin/agency-updates/link'),
            'syncUrl' => rateb_url('admin/agency-updates/sync-files'),
            'restoreAdminUrl' => rateb_url('admin/agency-updates/restore-admin'),
            'resetDataUrl' => rateb_url('admin/agency-updates/reset-data'),
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

    public function link(): void
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
        $agencyId = (int) ($data['agency_id'] ?? 0);
        $companyId = (int) ($data['company_id'] ?? 0);

        try {
            (new AgencyErpMigrationService())->linkAgencyToCompany($agencyId, $companyId);
            Response::json([
                'success' => true,
                'agency_id' => $agencyId,
                'company_id' => $companyId,
                'message' => __('agency_erp_push_link_ok'),
            ]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function syncFiles(): void
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
            $payload = (new AgencyFileSyncService())->sync([
                'scope' => (string) ($data['scope'] ?? ''),
                'confirm' => (string) ($data['confirm'] ?? ''),
                'agency_ids' => $data['agency_ids'] ?? ($data['ids'] ?? []),
            ]);
            Response::json($payload, empty($payload['success']) ? 422 : 200);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function restoreAdmin(): void
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
            $payload = (new AgencyErpMigrationService())->restoreSuperAdmins([
                'scope' => (string) ($data['scope'] ?? ''),
                'agency_ids' => $data['agency_ids'] ?? ($data['ids'] ?? []),
            ]);
            Response::json($payload, empty($payload['success']) ? 422 : 200);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function resetData(): void
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
            $payload = (new AgencyErpMigrationService())->resetAgencyDataBulk([
                'scope' => (string) ($data['scope'] ?? ''),
                'confirm' => (string) ($data['confirm'] ?? ''),
                'agency_ids' => $data['agency_ids'] ?? ($data['ids'] ?? []),
                'platform_company_id' => (int) ($data['platform_company_id'] ?? 0),
            ]);
            Response::json($payload, empty($payload['success']) ? 422 : 200);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
