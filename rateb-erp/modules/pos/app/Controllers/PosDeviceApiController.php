<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Controllers;

use Rateb\App\Pos\Services\PosOfflineDeviceService;

/** POS client device register + heartbeat APIs. */
final class PosDeviceApiController extends PosBaseController
{
    public function register(): void
    {
        $this->runPosJsonAction(function (): void {
            $this->bootstrapPos();
            $this->guardPosView('pos/register');
            $this->requireSessionCsrfOrAbort();

            $body = array_merge($this->inputData(), $this->jsonBody());
            $result = (new PosOfflineDeviceService())->register(
                $this->companyId(),
                $this->userId(),
                $body
            );
            if (empty($result['ok'])) {
                $this->json([
                    'ok' => false,
                    'error' => (string) ($result['error'] ?? __('invalid_request')),
                    'code' => (string) ($result['code'] ?? 'REGISTER_FAILED'),
                ], 422);
                return;
            }

            $this->json([
                'ok' => true,
                'device' => $result['device'] ?? null,
                'created' => (bool) ($result['created'] ?? false),
            ]);
        }, 'device-register');
    }

    public function heartbeat(): void
    {
        $this->runPosJsonAction(function (): void {
            $this->bootstrapPos();
            $this->guardPosView('pos/register');
            $this->requireSessionCsrfOrAbort();

            $body = array_merge($this->inputData(), $this->jsonBody());
            $result = (new PosOfflineDeviceService())->heartbeat(
                $this->companyId(),
                $this->userId(),
                $body
            );
            if (empty($result['ok'])) {
                $code = (string) ($result['code'] ?? 'HEARTBEAT_FAILED');
                $status = $code === 'NOT_FOUND' ? 404 : 422;
                $this->json([
                    'ok' => false,
                    'error' => (string) ($result['error'] ?? __('invalid_request')),
                    'code' => $code,
                ], $status);
                return;
            }

            $this->json([
                'ok' => true,
                'device' => $result['device'] ?? null,
            ]);
        }, 'device-heartbeat');
    }
}
