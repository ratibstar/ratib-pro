<?php
declare(strict_types=1);

namespace Rateb\App\Subscription\Admin;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Subscription\SubscriptionModule;

/**
 * Platform ops console for tenant subscription lifecycle (no payment UI).
 */
final class SubscriptionAdminController extends Controller
{
    private SubscriptionAdminService $service;

    public function __construct()
    {
        $this->service = new SubscriptionAdminService();
    }

    public function index(): void
    {
        $this->assertCanView();

        $sync = $this->service->syncMissingCompanies();
        if (($sync['inserted'] ?? 0) > 0) {
            SessionManager::flash(
                'success',
                'Synced ' . (int) $sync['inserted'] . ' compan' . ((int) $sync['inserted'] === 1 ? 'y' : 'ies')
                . ' into subscription engine (from companies + billing dates).'
            );
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        if (isset($_GET['per_page'])) {
            $limit = max(1, min(100, (int) $_GET['per_page']));
        }
        $status = trim((string) ($_GET['status'] ?? 'all'));
        $search = trim((string) ($_GET['q'] ?? ''));

        $dashboard = $this->service->dashboard();
        $list = $this->service->listTenants($page, $limit, $status !== '' ? $status : 'all', $search);
        // Fan-out is session-throttled (once/day); still returns ops panel items every load.
        $adminAlerts = $this->service->fanOutAdminAlerts();

        $this->render('dashboard', [
            'title' => 'Subscription Engine Admin',
            'dashboard' => $dashboard,
            'tenants' => $list['items'],
            'total' => $list['total'],
            'page' => $list['page'],
            'limit' => $list['limit'],
            'statusFilter' => $list['status'],
            'search' => $list['search'],
            'canManage' => $this->service->canManage($this->actorId()),
            'syncInserted' => (int) ($sync['inserted'] ?? 0),
            'adminAlerts' => $adminAlerts,
            'csrf' => Csrf::token(),
        ]);
    }

    public function show(string $id): void
    {
        $this->assertCanView();
        $companyId = (int) $id;
        $detail = $this->service->tenantDetail($companyId);
        if ($detail === null) {
            SessionManager::flash('error', 'Subscription engine record not found for company #' . $companyId);
            Response::redirect(rateb_url('admin/subscription-engine'));
            return;
        }

        $this->render('detail', [
            'title' => 'Subscription — ' . (string) ($detail['tenant']['company_name'] ?? $companyId),
            'detail' => $detail,
            'companyId' => $companyId,
            'canManage' => $this->service->canManage($this->actorId()),
            'csrf' => Csrf::token(),
        ]);
    }

    public function renew(string $id): void
    {
        $this->assertCanManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            Response::redirect(rateb_url('admin/subscription-engine/' . (int) $id));
            return;
        }

        $companyId = (int) $id;
        $newExpiry = substr(trim((string) ($_POST['new_expiry_date'] ?? '')), 0, 10);
        $period = trim((string) ($_POST['renewal_period'] ?? 'manual'));
        $reference = trim((string) ($_POST['reference'] ?? ''));
        if ($period === '') {
            $period = 'manual';
        }

        $result = $this->service->renewManual(
            $companyId,
            $newExpiry,
            $period,
            $this->actorId(),
            $reference !== '' ? $reference : null
        );

        if ($result->success()) {
            SessionManager::flash(
                'success',
                'Renewed — ACTIVE until ' . (string) $result->newExpiryDate()
            );
        } else {
            SessionManager::flash('error', 'Renewal failed: ' . $result->message());
        }

        Response::redirect(rateb_url('admin/subscription-engine/' . $companyId));
    }

    public function extend(string $id): void
    {
        $this->assertCanManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            Response::redirect(rateb_url('admin/subscription-engine/' . (int) $id));
            return;
        }

        $companyId = (int) $id;
        $newExpiry = substr(trim((string) ($_POST['new_expiry_date'] ?? '')), 0, 10);
        $out = $this->service->extendExpiry($companyId, $newExpiry, $this->actorId());

        if ($out['success']) {
            SessionManager::flash('success', 'Expiry extended to ' . (string) $out['new_expiry']);
        } else {
            SessionManager::flash('error', 'Extend failed: ' . $out['message']);
        }

        Response::redirect(rateb_url('admin/subscription-engine/' . $companyId));
    }

    public function pushAgency(string $id): void
    {
        $this->assertCanManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            Response::redirect(rateb_url('admin/subscription-engine/' . (int) $id));
            return;
        }
        $companyId = (int) $id;
        $out = $this->service->pushToAgency($companyId, $this->actorId());
        SessionManager::flash($out['success'] ? 'success' : 'error', $out['message']);
        Response::redirect(rateb_url('admin/subscription-engine/' . $companyId));
    }

    public function create(): void
    {
        $this->assertCanManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            Response::redirect(rateb_url('admin/subscription-engine'));
            return;
        }

        $companyId = (int) ($_POST['company_id'] ?? 0);
        $start = substr(trim((string) ($_POST['subscription_start'] ?? '')), 0, 10);
        $end = substr(trim((string) ($_POST['subscription_end'] ?? '')), 0, 10);
        $seedAlert = !isset($_POST['seed_alert']) || (string) $_POST['seed_alert'] === '1';

        if ($start === '') {
            $start = gmdate('Y-m-d');
        }

        $out = $this->service->createTenant(
            $companyId,
            $start,
            $end,
            $this->actorId(),
            $seedAlert
        );

        if ($out['success']) {
            SessionManager::flash(
                'success',
                'Created engine row for company #' . $companyId
                . ($seedAlert ? ' (alert seeded if in warning window)' : '')
            );
            Response::redirect(rateb_url('admin/subscription-engine/' . $companyId));
            return;
        }

        SessionManager::flash('error', 'Create failed: ' . $out['message']);
        Response::redirect(rateb_url('admin/subscription-engine'));
    }

    private function assertCanView(): void
    {
        if (!function_exists('rateb_can')
            || (!rateb_can('subscriptions.view') && !rateb_can('subscriptions.manage'))) {
            SessionManager::flash('error', 'Permission denied');
            Response::redirect(rateb_url('admin'));
        }
    }

    private function assertCanManage(): void
    {
        if (!$this->service->canManage($this->actorId())) {
            SessionManager::flash('error', 'Permission denied — subscriptions.manage required');
            Response::redirect(rateb_url('admin/subscription-engine'));
        }
    }

    private function actorId(): int
    {
        return (int) SessionManager::get('rateb_user_id', 0);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $view, array $data): void
    {
        $viewFile = SubscriptionModule::rootPath() . '/admin/views/' . $view . '.php';
        if (!is_file($viewFile)) {
            $this->notFound();
            return;
        }

        extract($data, EXTR_SKIP);
        $pageContent = static function () use ($viewFile, $data): string {
            extract($data, EXTR_SKIP);
            ob_start();
            include $viewFile;
            return (string) ob_get_clean();
        };

        $layoutFile = RATEB_VIEWS_PATH . '/layouts/main.php';
        if (is_file($layoutFile)) {
            $pageContent = $pageContent();
            include $layoutFile;
            return;
        }

        echo $pageContent();
    }
}
