<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Controllers;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\DatabaseErrorService;
use Rateb\App\Pos\Services\PosContextService;
use Rateb\App\Pos\Support\PosView;

abstract class PosBaseController extends Controller
{
    protected function posView(string $view, array $data = [], ?string $layout = 'pos-pages-shell'): void
    {
        PosView::render($view, $data, $layout);
    }

    protected function bootstrapPos(): void
    {
        (new PosContextService())->bootstrapTenant();
    }

    protected function companyId(): int
    {
        if (function_exists('rateb_require_ops_company')) {
            return rateb_require_ops_company();
        }
        return (int) (SessionManager::get('rateb_company_id') ?? 0);
    }

    protected function userId(): int
    {
        return (int) (SessionManager::get('rateb_user_id') ?? 0);
    }

    protected function guardPosView(string $resource): void
    {
        if (function_exists('rateb_can_view_entity') && rateb_can_view_entity($resource)) {
            return;
        }
        $this->denyAccess($resource);
    }

    protected function guardPosManage(string $resource): void
    {
        if (function_exists('rateb_can_manage_entity') && rateb_can_manage_entity($resource)) {
            return;
        }
        $this->denyAccess($resource);
    }

    protected function guardPosPermission(string $slug, string $resource = 'pos'): void
    {
        if ($slug === '' || (function_exists('rateb_can') && rateb_can($slug))) {
            return;
        }
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return;
        }
        $this->denyAccess($resource);
    }

    protected function denyAccess(string $resource): void
    {
        if ($this->isPosJsonRequest()) {
            Response::json(['ok' => false, 'error' => __('access_denied')], 403);
            exit;
        }
        SessionManager::flash('error', __('access_denied'));
        $this->redirect(rateb_app_url($resource === 'pos' ? 'pos/dashboard' : $resource));
    }

    protected function isPosJsonRequest(): bool
    {
        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        if (str_contains($path, '/api/')) {
            return true;
        }
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        return isset($_SERVER['HTTP_X_CSRF_TOKEN']) || isset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    /** @param callable():void $action */
    protected function runPosJsonAction(callable $action, string $logContext = 'pos-api'): void
    {
        try {
            $action();
        } catch (\Throwable $e) {
            error_log('RATEB POS ' . $logContext . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $message = class_exists(DatabaseErrorService::class)
                ? DatabaseErrorService::userMessage($e)
                : __('system_error_generic');
            $status = DatabaseErrorService::isSchemaIssue($e) ? 503 : 500;
            Response::json(['ok' => false, 'error' => $message], $status);
        }
    }

    protected function flashBack(string $type, string $message): void
    {
        SessionManager::flash($type, $message);
    }

    /** @return array<string, mixed> */
    protected function inputData(): array
    {
        return array_merge($_GET, $_POST);
    }

    /** @return array<string, mixed> */
    protected function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** Session JSON routes only — Bearer /api/v2/pos/* requests skip CSRF. */
    protected function requireSessionCsrfOrAbort(): void
    {
        if ($this->isBearerApiRequest()) {
            return;
        }

        if (!Csrf::validate($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            Response::json([
                'ok' => false,
                'success' => false,
                'error' => __('invalid_request'),
                'code' => 'CSRF_INVALID',
            ], 419);
            exit;
        }
    }

    protected function isBearerApiRequest(): bool
    {
        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');

        return (bool) preg_match('/Bearer\s+\S+/i', $header);
    }
}
