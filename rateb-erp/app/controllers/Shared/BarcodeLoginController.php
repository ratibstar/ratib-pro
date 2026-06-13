<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Shared;

use Rateb\App\Core\Auth;
use Rateb\App\Core\Controller;
use Rateb\App\Core\RateLimiter;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\User;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\BarcodeLoginService;
use Rateb\App\Services\LoginActivityService;

final class BarcodeLoginController extends Controller
{
    public function pairApi(): void
    {
        $input = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }
        $action = strtolower(trim((string) ($input['action'] ?? '')));
        $svc = new BarcodeLoginService();

        if ($action === 'create') {
            if (!RateLimiter::attempt('erp_barcode_pair_' . ($_SERVER['REMOTE_ADDR'] ?? 'x'), 20, 300)) {
                $this->json(['success' => false, 'message' => __('too_many_attempts')], 429);
            }
            $created = $svc->pairCreate();
            $this->json(['success' => true, 'token' => $created['token'] ?? '']);
        }

        if ($action === 'poll') {
            $token = (string) ($input['token'] ?? '');
            $poll = $svc->pairPoll($token);
            if (!$poll['ok']) {
                $this->json(['success' => true, 'status' => 'expired']);
            }
            $this->json(['success' => true, 'status' => $poll['status'] ?? 'pending']);
        }

        if ($action === 'submit') {
            $token = (string) ($input['token'] ?? '');
            $barcode = (string) ($input['barcode'] ?? '');
            if (!RateLimiter::attempt('erp_barcode_submit_' . md5($token), 10, 300)) {
                $this->json(['success' => false, 'message' => __('too_many_attempts')], 429);
            }
            $result = $svc->pairSubmit($token, $barcode);
            if (!$result['ok']) {
                $this->json(['success' => false, 'message' => $result['message'] ?? __('barcode_invalid')], 400);
            }
            $this->json(['success' => true, 'message' => __('barcode_pair_ok')]);
        }

        $this->json(['success' => false, 'message' => __('invalid_request')], 400);
    }

    public function showScan(): void
    {
        $token = preg_replace('/[^a-f0-9]/', '', strtolower((string) ($_GET['token'] ?? ''))) ?? '';
        $valid = strlen($token) === 32;
        $svc = new BarcodeLoginService();
        if ($valid) {
            $poll = $svc->pairPoll($token);
            $valid = $poll['ok'] && ($poll['status'] ?? '') === 'pending';
            if ($valid) {
                $svc->setPairCookie($token);
            }
        }
        $autoBadge = trim((string) ($_GET['d'] ?? $_GET['badge'] ?? ''));
        $this->view('shared/auth/login-scan', [
            'title' => __('barcode_scan_title'),
            'token' => $token,
            'tokenValid' => $valid,
            'autoBadge' => $autoBadge,
        ], null);
    }

    public function loginBarcode(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url('login'));
        }
        $barcode = (string) $this->input('barcode', '');
        $key = 'erp_barcode_login_' . md5($barcode . ($_SERVER['REMOTE_ADDR'] ?? ''));
        if (!RateLimiter::attempt($key, 8, 300)) {
            SessionManager::flash('error', __('too_many_attempts'));
            Response::redirect(rateb_url('login'));
        }

        $user = (new BarcodeLoginService())->findUserByBarcode($barcode);
        (new LoginActivityService())->record($user ? (int) $user['id'] : null, 'barcode:' . substr($barcode, 0, 20), $user !== null);

        if (!$user || !Auth::loginUser($user)) {
            SessionManager::flash('error', __('barcode_invalid'));
            Response::redirect(rateb_url('login'));
        }

        $this->finishLogin($user, 'barcode');
        Response::redirect(rateb_url(Auth::homePath()));
    }

    /** @param array<string, mixed> $user */
    private function finishLogin(array $user, string $method): void
    {
        if (!empty($user['locale']) && in_array($user['locale'], RATEB_SUPPORTED_LOCALES, true)) {
            $_SESSION['rateb_locale'] = $user['locale'];
        }
        (new User())->updateLastLogin((int) $user['id']);
        (new AuditService())->log('login', 'user', (int) $user['id'], ['method' => $method]);
    }
}
