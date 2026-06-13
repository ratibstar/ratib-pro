<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Shared;

use Rateb\App\Core\Auth;
use Rateb\App\Core\Controller;
use Rateb\App\Core\RateLimiter;
use Rateb\App\Models\User;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\BarcodeLoginService;
use Rateb\App\Services\LoginActivityService;

final class QrLoginController extends Controller
{
    public function api(): void
    {
        $input = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }
        $action = strtolower(trim((string) ($input['action'] ?? '')));
        if ($action !== 'validate' && $action !== 'submit') {
            $this->json(['success' => false, 'message' => __('invalid_request'), 'code' => 'invalid'], 400);
        }

        $payload = trim((string) ($input['qr_payload'] ?? $input['barcode'] ?? ''));
        $pairToken = preg_replace('/[^a-f0-9]/', '', strtolower((string) ($input['pair_token'] ?? ''))) ?? '';
        if ($pairToken !== '' && strlen($pairToken) !== 32) {
            $pairToken = '';
        }
        if ($pairToken === '') {
            $pairToken = (new BarcodeLoginService())->readPairCookie();
        }

        if ($payload === '') {
            $this->json(['success' => false, 'message' => __('barcode_invalid'), 'code' => 'invalid'], 400);
        }

        $svc = new BarcodeLoginService();
        if ($svc->isPairingQr($payload)) {
            $this->json([
                'success' => false,
                'message' => __('barcode_pairing_qr_error'),
                'code' => 'pairing_qr',
            ], 400);
        }

        $key = 'erp_qr_validate_' . md5(($pairToken ?: 'direct') . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'x'));
        if (!RateLimiter::attempt($key, 12, 300)) {
            $this->json(['success' => false, 'message' => __('too_many_attempts'), 'code' => 'rate_limit'], 429);
        }

        $user = $svc->findUserByBarcode($payload);
        (new LoginActivityService())->record($user ? (int) $user['id'] : null, 'qr:' . substr($payload, 0, 24), $user !== null);

        if (!$user || (string) ($user['status'] ?? '') !== 'active') {
            $this->json(['success' => false, 'message' => __('barcode_invalid'), 'code' => 'invalid'], 401);
        }

        if ($pairToken !== '') {
            $approved = $svc->pairApprove($pairToken, (int) $user['id']);
            if (!$approved['ok']) {
                $this->json([
                    'success' => false,
                    'message' => $approved['message'] ?? __('barcode_pair_expired'),
                    'code' => 'pair_failed',
                ], 400);
            }
            $this->json([
                'success' => true,
                'message' => __('barcode_pair_ok'),
                'paired' => true,
                'username' => (string) ($user['name'] ?? $user['email'] ?? ''),
            ]);
        }

        if (!Auth::loginUser($user)) {
            $this->json(['success' => false, 'message' => __('barcode_invalid'), 'code' => 'inactive'], 401);
        }
        $this->finishLogin($user, 'barcode');
        $this->json([
            'success' => true,
            'message' => 'OK',
            'paired' => false,
            'username' => (string) ($user['name'] ?? $user['email'] ?? ''),
            'redirect' => rateb_url(Auth::homePath()),
        ]);
    }

    public function showBadge(): void
    {
        $payload = trim((string) ($_GET['d'] ?? $_GET['badge'] ?? ''));
        $pairToken = (new BarcodeLoginService())->readPairCookie();
        $this->view('shared/auth/login-badge', [
            'title' => __('login_badge'),
            'payload' => $payload,
            'pairToken' => $pairToken,
            'directLogin' => $pairToken === '',
        ], null);
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
