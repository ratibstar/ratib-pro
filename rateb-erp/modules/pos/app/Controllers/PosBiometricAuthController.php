<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Controllers;

use Rateb\App\Core\Csrf;
use Rateb\App\Services\BiometricAuthService;

final class PosBiometricAuthController extends PosBaseController
{
    public function gate(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');

        $userId = $this->userId();
        $bio = new BiometricAuthService();
        if ($bio->isPosVerified($userId)) {
            $this->redirect(rateb_app_url('pos/register'));
            return;
        }

        $display = (string) (\Rateb\App\Core\SessionManager::get('rateb_user_display')
            ?? \Rateb\App\Core\SessionManager::get('rateb_user_email') ?? '—');

        $this->posView('auth/biometric-gate', [
            'title' => __('pos_biometric_gate_title'),
            'cashierLabel' => $display,
            'hasEnrollment' => $bio->hasEnrollment($userId),
            'registerConfig' => [
                'locale' => rateb_locale(),
                'rtl' => rateb_is_rtl(),
                'csrf' => Csrf::token(),
                'userId' => $userId,
                'api' => [
                    'start' => rateb_app_url('pos/api/biometric/start'),
                    'finish' => rateb_app_url('pos/api/biometric/finish'),
                    'registerStart' => rateb_app_url('pos/api/biometric/register-start'),
                    'registerFinish' => rateb_app_url('pos/api/biometric/register-finish'),
                    'face' => rateb_app_url('pos/api/biometric/face'),
                    'status' => rateb_app_url('pos/api/biometric/status'),
                ],
                'urls' => ['register' => rateb_app_url('pos/register')],
                'i18n' => [
                    'pos_biometric_gate_title' => __('pos_biometric_gate_title'),
                    'pos_biometric_scan' => __('pos_biometric_scan'),
                    'pos_biometric_face' => __('pos_biometric_face'),
                    'pos_biometric_not_enrolled' => __('pos_biometric_not_enrolled'),
                    'pos_biometric_register' => __('pos_biometric_register'),
                    'pos_biometric_register_success' => __('pos_biometric_register_success'),
                    'pos_biometric_register_loading' => __('pos_biometric_register_loading'),
                    'pos_biometric_register_settings_hint' => __('pos_biometric_register_settings_hint'),
                    'pos_biometric_success' => __('pos_biometric_success'),
                    'pos_biometric_failed' => __('pos_biometric_failed'),
                    'pos_biometric_camera_denied' => __('pos_biometric_camera_denied'),
                ],
            ],
        ], 'pos-shell');
    }

    public function status(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        $bio = new BiometricAuthService();
        $userId = $this->userId();
        $this->json([
            'ok' => true,
            'verified' => $bio->isPosVerified($userId),
            'enrolled' => $bio->hasEnrollment($userId),
        ]);
    }

    public function start(): void
    {
        $this->runPosJsonAction(function (): void {
            $this->bootstrapPos();
            $this->guardPosView('pos/register');
            $this->requireSessionCsrfOrAbort();

            $body = $this->jsonBody();
            $supervisor = !empty($body['supervisor']);
            $userId = $supervisor ? 0 : $this->userId();

            $result = (new BiometricAuthService())->startWebAuthn($userId, $supervisor);
            if (!$result['ok']) {
                $this->json(['ok' => false, 'error' => (string) ($result['error'] ?? __('invalid_request'))], 400);
                return;
            }
            $this->json($this->biometricSuccessPayload($result));
        }, 'biometric-start');
    }

    public function finish(): void
    {
        $this->runPosJsonAction(function (): void {
            $this->bootstrapPos();
            $this->guardPosView('pos/register');
            $this->requireSessionCsrfOrAbort();

            $body = $this->jsonBody();
            $supervisor = !empty($body['supervisor']);
            $bio = new BiometricAuthService();
            $result = $bio->finishWebAuthn(
                $body,
                $supervisor ? 0 : $this->userId(),
                $supervisor
            );
            if (!$result['ok']) {
                $this->json(['ok' => false, 'error' => (string) ($result['error'] ?? __('pos_biometric_failed'))], 403);
                return;
            }

            if (!$supervisor) {
                $bio->markPosVerified((int) ($result['user_id'] ?? $this->userId()));
            }

            $this->json(['ok' => true, 'user_id' => (int) ($result['user_id'] ?? 0)]);
        }, 'biometric-finish');
    }

    public function registerStart(): void
    {
        $this->runPosJsonAction(function (): void {
            $this->bootstrapPos();
            $this->guardPosView('pos/register');
            $this->requireSessionCsrfOrAbort();

            $bio = new BiometricAuthService();
            $result = $bio->registerStartWebAuthn($this->userId());
            if (!$result['ok']) {
                $this->json(['ok' => false, 'error' => (string) ($result['error'] ?? __('invalid_request'))], 400);
                return;
            }
            $this->json($this->biometricSuccessPayload($result));
        }, 'biometric-register-start');
    }

    public function registerFinish(): void
    {
        $this->runPosJsonAction(function (): void {
            $this->bootstrapPos();
            $this->guardPosView('pos/register');
            $this->requireSessionCsrfOrAbort();

            $body = $this->jsonBody();
            $result = (new BiometricAuthService())->registerFinishWebAuthn($body, $this->userId());
            if (!$result['ok']) {
                $error = (string) ($result['error'] ?? __('pos_biometric_failed'));
                $status = str_contains($error, (string) __('db_schema_outdated')) ? 503 : 403;
                $this->json(['ok' => false, 'error' => $error], $status);
                return;
            }
            $this->json(['ok' => true, 'user_id' => (int) ($result['user_id'] ?? $this->userId())]);
        }, 'biometric-register-finish');
    }

    public function face(): void
    {
        $this->runPosJsonAction(function (): void {
            $this->bootstrapPos();
            $this->guardPosView('pos/register');
            $this->requireSessionCsrfOrAbort();

            $body = $this->jsonBody();
            $bio = new BiometricAuthService();
            $result = $bio->verifyFace($this->userId(), $body);
            if (!$result['ok']) {
                $this->json(['ok' => false, 'error' => (string) ($result['error'] ?? __('pos_biometric_failed'))], 403);
                return;
            }
            $bio->markPosVerified($this->userId());
            $this->json(['ok' => true]);
        }, 'biometric-face');
    }

    /** @param array<string, mixed> $result */
    private function biometricSuccessPayload(array $result): array
    {
        $payload = ['ok' => true] + $result;
        if (isset($result['publicKey']) && !isset($payload['options'])) {
            $payload['options'] = ['publicKey' => $result['publicKey']];
        }

        return $payload;
    }
}
