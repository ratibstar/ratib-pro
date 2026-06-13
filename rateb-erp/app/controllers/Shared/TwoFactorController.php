<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Shared;

use Rateb\App\Core\Auth;
use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\TwoFactorService;
use Rateb\App\Services\TotpHelper;

final class TwoFactorController extends Controller
{
    public function setup(): void
    {
        $user = Auth::user();
        if (!$user) {
            Response::redirect(rateb_url('login'));
        }
        $svc = new TwoFactorService();
        $pending = null;
        if ((int) ($user['two_factor_enabled'] ?? 0) !== 1) {
            if (!empty($_SESSION['_rateb_2fa_pending_secret'])) {
                $pending = [
                    'secret' => (string) $_SESSION['_rateb_2fa_pending_secret'],
                    'uri' => TotpHelper::provisioningUri((string) $user['email'], (string) $_SESSION['_rateb_2fa_pending_secret']),
                ];
            } else {
                try {
                    $pending = $svc->beginSetup((int) $user['id']);
                } catch (\Throwable $e) {
                    SessionManager::flash('error', $e->getMessage());
                }
            }
        }
        $this->view('shared/auth/two-factor-setup', [
            'title' => __('two_factor_setup'),
            'user' => $user,
            'pending' => $pending,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function enable(): void
    {
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('profile/2fa'));
        }
        $user = Auth::user();
        if (!$user) {
            Response::redirect(rateb_url('login'));
        }
        try {
            $codes = (new TwoFactorService())->confirmSetup((int) $user['id'], (string) $this->input('code', ''));
            SessionManager::flash('success', __('two_factor_enabled'));
            SessionManager::flash('2fa_backup_codes', implode(', ', $codes));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        Response::redirect(rateb_app_url('profile/2fa'));
    }

    public function disable(): void
    {
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_app_url('profile/2fa'));
        }
        $user = Auth::user();
        if (!$user) {
            Response::redirect(rateb_url('login'));
        }
        (new TwoFactorService())->disable((int) $user['id']);
        SessionManager::flash('success', __('two_factor_disabled'));
        Response::redirect(rateb_app_url('profile/2fa'));
    }
}
