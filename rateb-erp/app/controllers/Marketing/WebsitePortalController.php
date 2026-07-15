<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Marketing;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\CmsService;
use Rateb\App\Website\Portal\PortalAppointmentService;
use Rateb\App\Website\Portal\PortalAuthService;
use Rateb\App\Website\Portal\PortalBookingService;
use Rateb\App\Website\Portal\PortalContactService;
use Rateb\App\Website\Portal\PortalContractService;
use Rateb\App\Website\Portal\PortalDashboardService;
use Rateb\App\Website\Portal\PortalDocumentService;
use Rateb\App\Website\Portal\PortalFinanceService;
use Rateb\App\Website\Portal\OnlineServiceService;
use Rateb\App\Website\Portal\PortalRateLimit;
use Rateb\App\Website\Portal\PortalRecruitmentService;
use Rateb\App\Website\Portal\PortalRequestService;
use Rateb\App\Website\Portal\PortalSupportService;
use Rateb\App\Website\Portal\PortalWorkflowService;
use Rateb\App\Website\WebsiteContext;

/**
 * Phase WEBSITE-07 — Employer / Customer / Partner self-service portals.
 */
final class WebsitePortalController extends Controller
{
    private function ensureWebsite(): bool
    {
        if (!class_exists(WebsiteContext::class)) {
            $this->notFound();
            return false;
        }
        if (WebsiteContext::current() === null) {
            WebsiteContext::bootFromRequest();
        }

        return true;
    }

    private function resolvePortalType(string $type = ''): string
    {
        if (PortalAuthService::isValidType($type)) {
            return $type;
        }
        $path = '';
        if (isset($_GET['route']) && is_string($_GET['route'])) {
            $path = '/' . trim($_GET['route'], '/');
        } else {
            $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
        }
        if (preg_match('#/(employer|customer|partner)(/|$)#', $path, $m)) {
            return $m[1];
        }

        return '';
    }

    private function requireUser(string $type): ?array
    {
        $user = (new PortalAuthService())->currentUser($type);
        if ($user === null) {
            Response::redirect(rateb_url('site/' . $type . '/login'));

            return null;
        }

        return $user;
    }

    public function showLogin(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || !PortalAuthService::isValidType($type)) {
            $this->notFound();
            return;
        }
        if ((new PortalAuthService())->isLoggedIn($type)) {
            Response::redirect(rateb_url('site/' . $type));
            return;
        }
        $this->renderAuth($type, 'login');
    }

    public function login(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || !PortalAuthService::isValidType($type)) {
            $this->notFound();
            return;
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url('site/' . $type . '/login'));
            return;
        }
        $result = (new PortalAuthService())->login(
            $type,
            (string) $this->input('email', ''),
            (string) $this->input('password', '')
        );
        if (!($result['ok'] ?? false)) {
            SessionManager::flash('error', __('invalid_credentials') ?: 'Invalid credentials');
            Response::redirect(rateb_url('site/' . $type . '/login'));
            return;
        }
        Response::redirect(rateb_url('site/' . $type));
    }

    public function showRegister(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || !PortalAuthService::isValidType($type)) {
            $this->notFound();
            return;
        }
        if ((new PortalAuthService())->isLoggedIn($type)) {
            Response::redirect(rateb_url('site/' . $type));
            return;
        }
        $this->renderAuth($type, 'register');
    }

    public function register(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || !PortalAuthService::isValidType($type)) {
            $this->notFound();
            return;
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url('site/' . $type . '/register'));
            return;
        }
        $result = (new PortalAuthService())->register($type, $_POST);
        if (!($result['ok'] ?? false)) {
            SessionManager::flash('error', (string) ($result['error'] ?? 'register_failed'));
            Response::redirect(rateb_url('site/' . $type . '/register'));
            return;
        }
        SessionManager::flash('success', __('register_ok') ?: 'Account created');
        Response::redirect(rateb_url('site/' . $type));
    }

    public function logout(string $type = ''): void
    {
        $type = $this->resolvePortalType($type) ?: 'employer';
        (new PortalAuthService())->logout();
        Response::redirect(rateb_url('site/' . $type . '/login'));
    }

    public function dashboard(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || !PortalAuthService::isValidType($type)) {
            $this->notFound();
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $dash = new PortalDashboardService();
        $data = match ($type) {
            PortalAuthService::TYPE_EMPLOYER => $dash->employer($user),
            PortalAuthService::TYPE_PARTNER => $dash->partner($user),
            default => (new \Rateb\App\Website\Portal\CustomerWorkspaceService())->workspace($user),
        };
        $view = $type === PortalAuthService::TYPE_CUSTOMER ? 'workspace' : 'dashboard';
        $this->renderPortal($type, $view, $data);
    }

    public function requests(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || !PortalAuthService::isValidType($type)) {
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $this->renderPortal($type, 'requests', [
            'user' => $user,
            'requests' => (new PortalRequestService())->listForUser((int) $user['id']),
        ]);
    }

    public function createRequest(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || !$this->validateCsrf()) {
            Response::redirect(rateb_url('site/' . ($type ?: 'customer') . '/requests'));
            return;
        }
        if (!PortalRateLimit::allow('create_request', 20, 60)) {
            SessionManager::flash('error', __('rate_limited') ?: 'Too many requests');
            Response::redirect(rateb_url('site/' . $type . '/requests'));
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $rtype = (string) $this->input('request_type', 'service');
        $result = (new PortalRequestService())->create($user, $rtype, $_POST);
        if (($result['ok'] ?? false) && class_exists(\Rateb\App\Services\AuditService::class)) {
            (new \Rateb\App\Services\AuditService())->log('portal.request', 'website_portal_request', (int) ($result['id'] ?? 0), [
                'portal_type' => $type,
            ]);
        }
        SessionManager::flash(($result['ok'] ?? false) ? 'success' : 'error', ($result['ok'] ?? false) ? __('request_submitted') : ($result['error'] ?? 'failed'));
        Response::redirect(rateb_url('site/' . $type . '/requests'));
    }

    public function updateRequest(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || !$this->validateCsrf()) {
            Response::redirect(rateb_url('site/' . ($type ?: 'customer') . '/requests'));
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $submit = !empty($_POST['submit_request']);
        $result = (new PortalRequestService())->updateDraft($user, (int) $this->input('request_id', 0), $_POST, $submit);
        SessionManager::flash(($result['ok'] ?? false) ? 'success' : 'error', ($result['ok'] ?? false) ? __('saved') : ($result['error'] ?? 'failed'));
        Response::redirect(rateb_url('site/' . $type . '/requests'));
    }

    public function contracts(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || $type !== PortalAuthService::TYPE_CUSTOMER) {
            $this->notFound();
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $this->renderPortal($type, 'contracts', [
            'user' => $user,
            'contracts' => (new PortalContractService())->listActive(20, $page),
            'page' => $page,
        ]);
    }

    public function pipeline(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || $type !== PortalAuthService::TYPE_CUSTOMER) {
            $this->notFound();
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $this->renderPortal($type, 'pipeline', [
            'user' => $user,
            'pipeline' => (new PortalRecruitmentService())->pipelineSummary(),
        ]);
    }

    public function downloadInvoice(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite()) {
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        if (!PortalRateLimit::allow('invoice_download', 40, 60)) {
            SessionManager::flash('error', __('rate_limited') ?: 'Too many requests');
            Response::redirect(rateb_url('site/' . $type . '/finance'));
            return;
        }
        $invoiceId = (int) ($_GET['id'] ?? $this->input('id', 0));
        $invoice = (new PortalFinanceService())->findInvoice($invoiceId);
        if ($invoice === null) {
            $this->notFound();
            return;
        }
        $path = trim((string) ($invoice['document_path'] ?? ''));
        if ($path === '') {
            SessionManager::flash('error', __('document_unavailable') ?: 'PDF not available');
            Response::redirect(rateb_url('site/' . $type . '/finance'));
            return;
        }
        $full = (defined('RATEB_ROOT') ? RATEB_ROOT . '/' : '') . ltrim(str_replace('..', '', $path), '/');
        if (!is_file($full) && defined('RATEB_STORAGE_PATH')) {
            $alt = RATEB_STORAGE_PATH . '/' . ltrim($path, '/');
            $full = is_file($alt) ? $alt : $full;
        }
        if (!is_file($full)) {
            // Attempt project-relative path
            $full = dirname(__DIR__, 3) . '/' . ltrim($path, '/');
        }
        if (!is_file($full)) {
            SessionManager::flash('error', __('document_unavailable') ?: 'PDF not available');
            Response::redirect(rateb_url('site/' . $type . '/finance'));
            return;
        }
        if (class_exists(\Rateb\App\Services\AuditService::class)) {
            (new \Rateb\App\Services\AuditService())->log('portal.invoice_download', 'invoice', $invoiceId, [
                'portal_user_id' => (int) $user['id'],
            ]);
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . rawurlencode((string) ($invoice['invoice_no'] ?? 'invoice')) . '.pdf"');
        header('X-Content-Type-Options: nosniff');
        readfile($full);
        exit;
    }

    public function finance(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite()) {
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $this->renderPortal($type, 'finance', array_merge(
            ['user' => $user],
            (new PortalFinanceService())->statement($user)
        ));
    }

    public function documents(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite()) {
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $this->renderPortal($type, 'documents', [
            'user' => $user,
            'documents' => (new PortalDocumentService())->listForUser((int) $user['id']),
        ]);
    }

    public function uploadDocument(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || !$this->validateCsrf()) {
            Response::redirect(rateb_url('site/' . ($type ?: 'customer') . '/documents'));
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $file = isset($_FILES['document']) && is_array($_FILES['document']) ? $_FILES['document'] : [];
        $result = (new PortalDocumentService())->upload(
            $user,
            $file,
            (string) $this->input('doc_category', 'attachment'),
            (string) $this->input('title', '')
        );
        SessionManager::flash(($result['ok'] ?? false) ? 'success' : 'error', ($result['ok'] ?? false) ? __('upload_ok') : ($result['error'] ?? 'failed'));
        Response::redirect(rateb_url('site/' . $type . '/documents'));
    }

    public function support(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite()) {
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $svc = new PortalSupportService();
        $tickets = $svc->ticketsForUser((int) $user['id']);
        $ticketId = (int) ($_GET['ticket_id'] ?? 0);
        $this->renderPortal($type, 'support', [
            'user' => $user,
            'tickets' => $tickets,
            'replies' => $ticketId > 0 ? $svc->repliesForTicket((int) $user['id'], $ticketId) : [],
            'activeTicketId' => $ticketId,
        ]);
    }

    public function createTicket(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || !$this->validateCsrf()) {
            Response::redirect(rateb_url('site/' . ($type ?: 'customer') . '/support'));
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $result = (new PortalSupportService())->createTicket($user, $_POST);
        SessionManager::flash(($result['ok'] ?? false) ? 'success' : 'error', ($result['ok'] ?? false) ? __('ticket_created') : ($result['error'] ?? 'failed'));
        Response::redirect(rateb_url('site/' . $type . '/support'));
    }

    public function appointments(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite()) {
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $this->renderPortal($type, 'appointments', [
            'user' => $user,
            'appointments' => (new PortalAppointmentService())->listForUser((int) $user['id']),
        ]);
    }

    public function bookAppointment(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || !$this->validateCsrf()) {
            Response::redirect(rateb_url('site/' . ($type ?: 'customer') . '/appointments'));
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $result = (new PortalAppointmentService())->book($user, $_POST);
        SessionManager::flash(($result['ok'] ?? false) ? 'success' : 'error', ($result['ok'] ?? false) ? __('appointment_booked') : ($result['error'] ?? 'failed'));
        Response::redirect(rateb_url('site/' . $type . '/appointments'));
    }

    public function recruitment(string $type = ''): void
    {
        $type = $this->resolvePortalType($type) ?: PortalAuthService::TYPE_EMPLOYER;
        if (!$this->ensureWebsite() || $type !== PortalAuthService::TYPE_EMPLOYER) {
            $this->notFound();
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $q = trim((string) ($_GET['q'] ?? ''));
        $this->renderPortal($type, 'recruitment', [
            'user' => $user,
            'search' => $q,
            'candidates' => (new PortalRecruitmentService())->searchCandidates($q),
            'shortlists' => (new PortalRecruitmentService())->shortlistsForUser((int) $user['id']),
        ]);
    }

    public function shortlistCandidate(string $type = ''): void
    {
        $type = PortalAuthService::TYPE_EMPLOYER;
        if (!$this->ensureWebsite() || !$this->validateCsrf()) {
            Response::redirect(rateb_url('site/employer/recruitment'));
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $ok = (new PortalRecruitmentService())->shortlist($user, (int) $this->input('candidate_id', 0));
        SessionManager::flash($ok ? 'success' : 'error', $ok ? __('shortlisted') : __('invalid_request'));
        Response::redirect(rateb_url('site/employer/recruitment'));
    }

    public function decideShortlist(string $type = ''): void
    {
        $type = PortalAuthService::TYPE_EMPLOYER;
        if (!$this->ensureWebsite() || !$this->validateCsrf()) {
            Response::redirect(rateb_url('site/employer/recruitment'));
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $ok = (new PortalRecruitmentService())->decide(
            $user,
            (int) $this->input('shortlist_id', 0),
            (string) $this->input('decision', ''),
            (string) $this->input('notes', '')
        );
        SessionManager::flash($ok ? 'success' : 'error', $ok ? __('saved') : __('invalid_request'));
        Response::redirect(rateb_url('site/employer/recruitment'));
    }

    public function approvals(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite()) {
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $this->renderPortal($type, 'approvals', [
            'user' => $user,
            'approvals' => (new PortalWorkflowService())->pendingForCompany(),
        ]);
    }

    public function decideApproval(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || !$this->validateCsrf()) {
            Response::redirect(rateb_url('site/' . ($type ?: 'customer') . '/approvals'));
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $wf = new PortalWorkflowService();
        $id = (int) $this->input('instance_id', 0);
        $action = (string) $this->input('action', '');
        $ok = $action === 'approve' ? $wf->approve($id) : ($action === 'reject' ? $wf->reject($id) : false);
        SessionManager::flash($ok ? 'success' : 'error', $ok ? __('saved') : __('invalid_request'));
        Response::redirect(rateb_url('site/' . $type . '/approvals'));
    }

    public function updateProfile(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || !$this->validateCsrf()) {
            Response::redirect(rateb_url('site/' . ($type ?: 'customer') . '/profile'));
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        try {
            (new PortalAuthService())->updateProfile((int) $user['id'], $_POST);
            SessionManager::flash('success', __('portal_profile_saved') ?: 'Profile saved');
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        Response::redirect(rateb_url('site/' . $type . '/profile'));
    }

    public function replyTicket(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || !$this->validateCsrf()) {
            Response::redirect(rateb_url('site/' . ($type ?: 'customer') . '/support'));
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $file = isset($_FILES['attachment']) && is_array($_FILES['attachment']) ? $_FILES['attachment'] : null;
        $result = (new PortalSupportService())->addReply(
            $user,
            (int) $this->input('ticket_id', 0),
            (string) $this->input('body', ''),
            $file
        );
        SessionManager::flash(($result['ok'] ?? false) ? 'success' : 'error', ($result['ok'] ?? false) ? __('saved') : ($result['error'] ?? 'failed'));
        Response::redirect(rateb_url('site/' . $type . '/support'));
    }

    public function addContact(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || !$this->validateCsrf()) {
            Response::redirect(rateb_url('site/' . ($type ?: 'customer') . '/profile'));
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $result = (new PortalContactService())->add($user, $_POST);
        SessionManager::flash(($result['ok'] ?? false) ? 'success' : 'error', ($result['ok'] ?? false) ? __('saved') : ($result['error'] ?? 'failed'));
        Response::redirect(rateb_url('site/' . $type . '/profile'));
    }

    /** Phase WEBSITE-09 */
    public function services(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || $type !== PortalAuthService::TYPE_CUSTOMER) {
            $this->notFound();
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $svc = new OnlineServiceService();
        $this->renderPortal($type, 'services', [
            'user' => $user,
            'services' => $svc->listForUser((int) $user['id'], $page),
            'packages' => $svc->packages(),
            'page' => $page,
        ]);
    }

    public function serviceNew(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || $type !== PortalAuthService::TYPE_CUSTOMER) {
            $this->notFound();
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $svc = new OnlineServiceService();
        $this->renderPortal($type, 'service-new', [
            'user' => $user,
            'packages' => $svc->packages(),
            'prefill_type' => (string) ($_GET['type'] ?? 'recruitment'),
            'prefill_package' => (string) ($_GET['package'] ?? ''),
        ]);
    }

    public function serviceCreate(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || !$this->validateCsrf()) {
            Response::redirect(rateb_url('site/customer/services'));
            return;
        }
        if (!PortalRateLimit::allow('service_create', 15, 60)) {
            SessionManager::flash('error', __('rate_limited') ?: 'Too many requests');
            Response::redirect(rateb_url('site/customer/services/new'));
            return;
        }
        $user = $this->requireUser($type ?: 'customer');
        if ($user === null) {
            return;
        }
        $result = (new OnlineServiceService())->submitRequest($user, $_POST);
        if ($result['ok'] ?? false) {
            SessionManager::flash('success', __('request_submitted') ?: 'Submitted');
            Response::redirect(rateb_url('site/customer/services/track?id=' . (int) ($result['id'] ?? 0)));
            return;
        }
        SessionManager::flash('error', (string) ($result['error'] ?? 'failed'));
        Response::redirect(rateb_url('site/customer/services/new'));
    }

    public function serviceTrack(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || $type !== PortalAuthService::TYPE_CUSTOMER) {
            $this->notFound();
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $id = (int) ($_GET['id'] ?? 0);
        $tracked = (new OnlineServiceService())->track($id, (int) $user['id']);
        if ($tracked === null) {
            $this->notFound();
            return;
        }
        $this->renderPortal($type, 'service-track', [
            'user' => $user,
            'service' => $tracked,
            'timeline' => $tracked['timeline'] ?? [],
            'appointments' => $tracked['appointments'] ?? [],
        ]);
    }

    public function serviceMessage(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || !$this->validateCsrf()) {
            Response::redirect(rateb_url('site/customer/services'));
            return;
        }
        if (!PortalRateLimit::allow('service_message', 30, 60)) {
            SessionManager::flash('error', __('rate_limited') ?: 'Too many requests');
            Response::redirect(rateb_url('site/customer/services'));
            return;
        }
        $user = $this->requireUser($type ?: 'customer');
        if ($user === null) {
            return;
        }
        $id = (int) $this->input('service_id', 0);
        $result = (new OnlineServiceService())->addCustomerMessage($user, $id, (string) $this->input('message', ''));
        SessionManager::flash(($result['ok'] ?? false) ? 'success' : 'error', ($result['ok'] ?? false) ? __('saved') : ($result['error'] ?? 'failed'));
        Response::redirect(rateb_url('site/customer/services/track?id=' . $id));
    }

    public function serviceAgreement(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || !$this->validateCsrf()) {
            Response::redirect(rateb_url('site/customer/services'));
            return;
        }
        $user = $this->requireUser($type ?: 'customer');
        if ($user === null) {
            return;
        }
        $id = (int) $this->input('service_id', 0);
        $result = (new OnlineServiceService())->acceptAgreement($user, $id);
        SessionManager::flash(($result['ok'] ?? false) ? 'success' : 'error', ($result['ok'] ?? false) ? __('saved') : ($result['error'] ?? 'failed'));
        Response::redirect(rateb_url('site/customer/services/track?id=' . $id));
    }

    public function serviceBook(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || $type !== PortalAuthService::TYPE_CUSTOMER) {
            $this->notFound();
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $svc = new OnlineServiceService();
        $this->renderPortal($type, 'service-book', [
            'user' => $user,
            'services' => $svc->listForUser((int) $user['id'], 1, 50),
            'appointments' => (new PortalBookingService())->appointmentsForUser((int) $user['id']),
            'prefill_service_id' => (int) ($_GET['service_id'] ?? 0),
        ]);
    }

    public function serviceBookSubmit(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || !$this->validateCsrf()) {
            Response::redirect(rateb_url('site/customer/services/book'));
            return;
        }
        if (!PortalRateLimit::allow('service_book', 20, 60)) {
            SessionManager::flash('error', __('rate_limited') ?: 'Too many requests');
            Response::redirect(rateb_url('site/customer/services/book'));
            return;
        }
        $user = $this->requireUser($type ?: 'customer');
        if ($user === null) {
            return;
        }
        $id = (int) $this->input('service_id', 0);
        $result = (new OnlineServiceService())->bookAppointment($user, $id, $_POST);
        SessionManager::flash(($result['ok'] ?? false) ? 'success' : 'error', ($result['ok'] ?? false) ? __('saved') : ($result['error'] ?? 'failed'));
        Response::redirect(rateb_url('site/customer/services/track?id=' . $id));
    }

    public function servicePay(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || !$this->validateCsrf()) {
            Response::redirect(rateb_url('site/customer/services'));
            return;
        }
        if (!PortalRateLimit::allow('service_pay', 10, 60)) {
            SessionManager::flash('error', __('rate_limited') ?: 'Too many requests');
            Response::redirect(rateb_url('site/customer/services'));
            return;
        }
        $user = $this->requireUser($type ?: 'customer');
        if ($user === null) {
            return;
        }
        $id = (int) $this->input('service_id', 0);
        $result = (new OnlineServiceService())->startPayment($user, $id);
        if (!($result['ok'] ?? false)) {
            SessionManager::flash('error', (string) ($result['error'] ?? 'failed'));
            Response::redirect(rateb_url('site/customer/services/track?id=' . $id));
            return;
        }
        $token = (string) ($result['payment_token'] ?? '');
        Response::redirect(rateb_url('site/customer/services/payment/callback') . '?id=' . $id . '&token=' . rawurlencode($token) . '&ref=SIM-' . $id);
    }

    public function servicePaymentCallback(string $type = ''): void
    {
        if (!$this->ensureWebsite()) {
            $this->notFound();
            return;
        }
        if (!PortalRateLimit::allow('service_pay_cb', 40, 60)) {
            SessionManager::flash('error', __('rate_limited') ?: 'Too many requests');
            Response::redirect(rateb_url('site/customer/services'));
            return;
        }
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
        $ref = (string) ($_GET['ref'] ?? $_POST['ref'] ?? '');
        $result = (new OnlineServiceService())->completePaymentCallback($id, $token, $ref);
        SessionManager::flash(($result['ok'] ?? false) ? 'success' : 'error', ($result['ok'] ?? false) ? (__('payment_ok') ?: 'Payment confirmed') : ($result['error'] ?? 'failed'));
        $user = (new PortalAuthService())->currentUser('customer');
        if ($user !== null) {
            Response::redirect(rateb_url('site/customer/services/track?id=' . $id));
            return;
        }
        Response::redirect(rateb_url('site/customer/login'));
    }

    public function profile(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite()) {
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $this->renderPortal($type, 'profile', [
            'user' => $user,
            'contacts' => (new PortalContactService())->listForUser((int) $user['id']),
        ]);
    }

    public function notifications(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite()) {
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        $this->renderPortal($type, 'notifications', [
            'user' => $user,
            'notifications' => (new \Rateb\App\Website\Portal\PortalNotificationService())->listInApp(),
        ]);
    }

    private function renderAuth(string $type, string $section): void
    {
        $cms = new CmsService();
        $title = ucfirst($type) . ' ' . ucfirst($section);
        $this->view('marketing/portals/auth/' . $section, [
            'title' => $title,
            'portalType' => $type,
            'meta' => $cms->metaTags($type . '-portal', $title),
            'menuItems' => $cms->menuItems(),
            'theme' => $cms->theme(),
            'analytics' => $cms->analytics(),
            'csrf' => Csrf::token(),
            'isPortalPage' => true,
            'hidePortalNav' => true,
        ], 'marketing-portals');
    }

    /** @param array<string, mixed> $extra */
    private function renderPortal(string $type, string $section, array $extra = []): void
    {
        $cms = new CmsService();
        $title = ucfirst($type) . ' — ' . ucfirst($section);
        $this->view('marketing/portals/' . $section, array_merge([
            'title' => $title,
            'portalType' => $type,
            'portalSection' => $section,
            'meta' => $cms->metaTags($type . '-portal', $title),
            'menuItems' => $cms->menuItems(),
            'theme' => $cms->theme(),
            'analytics' => $cms->analytics(),
            'csrf' => Csrf::token(),
            'isPortalPage' => true,
        ], $extra), 'marketing-portals');
    }
}
