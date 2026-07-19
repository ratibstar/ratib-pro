<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;

/**
 * HR Mobile Development Console — launcher / diagnostics only.
 * No HR SQL, models, or business logic.
 */
final class HrMobileDevController extends Controller
{
    public function console(): void
    {
        if (!rateb_hr_mobile_dev_console_enabled()) {
            http_response_code(404);
            echo '404';
            return;
        }

        $cfg = rateb_hr_mobile_dev_config();
        $userId = (int) (SessionManager::get('rateb_user_id') ?? 0);
        $userName = (string) (SessionManager::get('rateb_user_display')
            ?? SessionManager::get('rateb_user_email')
            ?? '');

        $this->view('admin/hr-mobile-dev/console', [
            'title' => __('hr_mobile_dev_console'),
            'cfg' => $cfg,
            'authSignedIn' => $userId > 0,
            'authLabel' => $userId > 0
                ? ($userName !== '' ? $userName : ('#' . $userId))
                : __('hr_mobile_dev_auth_none'),
            'healthUrl' => rateb_url('admin/hr-mobile/health'),
        ], 'main');
    }

    public function health(): void
    {
        if (!rateb_hr_mobile_dev_console_enabled()) {
            Response::json(['ok' => false, 'message' => 'disabled'], 404);
            return;
        }

        $cfg = rateb_hr_mobile_dev_config();
        $url = (string) ($cfg['web_url'] ?? '');
        if ($url === '') {
            Response::json([
                'ok' => false,
                'message' => 'RATEB_HR_MOBILE_WEB_URL is not configured',
                'status' => null,
            ]);
            return;
        }

        $status = null;
        $ok = false;
        $error = '';
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if (isset($http_response_header[0]) && is_string($http_response_header[0])) {
            if (preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
                $status = (int) $m[1];
            }
        }
        if ($body !== false && $status !== null && $status >= 200 && $status < 400) {
            $ok = true;
        } elseif ($body === false) {
            $error = 'unreachable';
        }

        Response::json([
            'ok' => $ok,
            'status' => $status,
            'url' => $url,
            'message' => $ok ? 'reachable' : ($error !== '' ? $error : 'unexpected_status'),
        ]);
    }
}
