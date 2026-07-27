<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\LoginActivity;
use Rateb\App\Services\AutomationHealthService;
use Rateb\App\Services\MailDiagnosticsService;
use Rateb\App\Services\QueueWorkerService;

final class AutomationDashboardController extends Controller
{
    public function index(): void
    {
        $health = (new AutomationHealthService())->dashboard();
        $this->view('admin/automation/index', [
            'title' => __('automation_health'),
            'health' => $health,
            'csrf' => Csrf::token(),
        ], 'main');
    }
}

final class LoginActivityController extends Controller
{
    public function index(): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;
        $failedOnly = isset($_GET['failed']) ? 1 : 0;
        $sql = 'SELECT la.*, u.name AS user_name FROM rateb_login_activity la LEFT JOIN rateb_users u ON u.id = la.user_id WHERE 1=1';
        $params = [];
        if ($failedOnly) {
            $sql .= ' AND la.success = 0';
        }
        $sql .= ' ORDER BY la.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
        $items = (new LoginActivity())->query($sql, $params);
        $totalRow = (new LoginActivity())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_login_activity' . ($failedOnly ? ' WHERE success = 0' : '')
        );
        $this->view('admin/login-activity/index', [
            'title' => __('login_activity'),
            'items' => $items,
            'total' => (int) ($totalRow['c'] ?? 0),
            'page' => $page,
            'failedOnly' => $failedOnly,
            'csrf' => Csrf::token(),
        ], 'main');
    }
}

final class QueueMonitorController extends Controller
{
    public function index(): void
    {
        $status = trim((string) ($_GET['status'] ?? 'failed'));
        if (!in_array($status, ['pending', 'failed', 'sent', 'dead'], true)) {
            $status = 'failed';
        }
        $db = \Rateb\App\Core\Database::connection();
        if ($status === 'dead') {
            $items = $db->query(
                'SELECT * FROM rateb_notification_queue WHERE dead_letter_at IS NOT NULL ORDER BY id DESC LIMIT 100'
            )->fetchAll();
        } else {
            $stmt = $db->prepare('SELECT * FROM rateb_notification_queue WHERE status = :st ORDER BY id DESC LIMIT 100');
            $stmt->execute(['st' => $status === 'dead' ? 'failed' : $status]);
            $items = $stmt->fetchAll();
        }
        $this->view('admin/queue-monitor/index', [
            'title' => __('queue_monitor'),
            'items' => $items,
            'status' => $status,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function retry(): void
    {
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_url('admin/queue-monitor'));
        }
        $count = (new QueueWorkerService())->retryFailed(50);
        SessionManager::flash('success', __('queue_retried', ['count' => (string) $count]));
        Response::redirect(rateb_url('admin/queue-monitor?status=failed'));
    }
}

final class EmailDiagnosticsController extends Controller
{
    public function index(): void
    {
        if (!function_exists('rateb_email_diagnostics_accessible') || !rateb_email_diagnostics_accessible()) {
            Response::redirect(rateb_url('admin/settings'));
            return;
        }

        $service = new MailDiagnosticsService();
        $data = $service->collect();
        $data['overall'] = $service->overall($data);

        $this->view('admin/email-diagnostics/index', [
            'title' => __('email_diagnostics_title'),
            'data' => $data,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function runTest(): void
    {
        if (!function_exists('rateb_email_diagnostics_accessible') || !rateb_email_diagnostics_accessible()) {
            Response::redirect(rateb_url('admin/settings'));
            return;
        }

        if (!$this->validateCsrf()) {
            Response::redirect(rateb_url('admin/email-diagnostics'));
            return;
        }

        $service = new MailDiagnosticsService();
        $to = trim((string) $this->input('test_to', 'info@rateb.sa'));
        if ($to === '') {
            $to = 'info@rateb.sa';
        }

        $data = $service->collect();
        $data['test'] = $service->runTestEmail($to);
        $data['overall'] = $service->overall($data);

        SessionManager::flash(
            ($data['test']['level'] ?? 'error') === 'success' ? 'success' : 'error',
            (string) ($data['test']['message'] ?? __('email_diagnostics_test_failed'))
        );

        $this->view('admin/email-diagnostics/index', [
            'title' => __('email_diagnostics_title'),
            'data' => $data,
            'testTo' => $to,
            'csrf' => Csrf::token(),
        ], 'main');
    }
}
