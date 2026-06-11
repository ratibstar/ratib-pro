<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Shared;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\PasswordResetService;

final class PasswordResetController extends Controller
{
    public function showForgot(): void
    {
        $portal = strpos((string) ($_SERVER['REQUEST_URI'] ?? ''), 'company') !== false ? 'company' : 'admin';
        $this->view('auth/forgot-password', [
            'title' => __('password_reset'),
            'portal' => $portal,
            'csrf' => Csrf::token(),
        ], 'auth');
    }

    public function sendLink(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url('password/forgot'));
        }
        $email = trim((string) $this->input('email', ''));
        (new PasswordResetService())->createTokenForEmail($email);
        SessionManager::flash('success', __('password_reset_sent'));
        Response::redirect(rateb_url('password/forgot'));
    }

    public function showReset(array $params): void
    {
        $token = (string) ($params['token'] ?? '');
        if (!(new PasswordResetService())->validateToken($token)) {
            SessionManager::flash('error', __('password_reset_invalid'));
            Response::redirect(rateb_url('password/forgot'));
        }
        $this->view('auth/reset-password', [
            'title' => __('password_reset'),
            'token' => $token,
            'csrf' => Csrf::token(),
        ], 'auth');
    }

    public function reset(array $params): void
    {
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_url('password/forgot'));
        }
        $token = (string) ($params['token'] ?? '');
        $password = (string) $this->input('password', '');
        $confirm = (string) $this->input('password_confirm', '');
        if ($password === '' || $password !== $confirm) {
            SessionManager::flash('error', __('password_mismatch'));
            Response::redirect(rateb_url('password/reset/' . $token));
        }
        if (!(new PasswordResetService())->resetWithToken($token, $password)) {
            SessionManager::flash('error', __('password_reset_invalid'));
            Response::redirect(rateb_url('password/forgot'));
        }
        SessionManager::flash('success', __('password_reset_done'));
        Response::redirect(rateb_url('company/login'));
    }
}
