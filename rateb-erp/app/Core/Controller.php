<?php
declare(strict_types=1);

namespace Rateb\App\Core;

abstract class Controller
{
    protected function view(string $view, array $data = [], ?string $layout = 'main'): void
    {
        View::render($view, $data, $layout);
    }

    protected function json(array $payload, int $status = 200): void
    {
        Response::json($payload, $status);
    }

    protected function redirect(string $url): void
    {
        Response::redirect($url);
    }

    protected function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    protected function validateCsrf(): bool
    {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return Csrf::validate((string) $token);
    }

    /** HTTP 404 — never fatal (Website LOCK certification). */
    protected function notFound(): void
    {
        http_response_code(404);
        if (class_exists(View::class) && defined('RATEB_ROOT') && is_file(RATEB_ROOT . '/views/errors/404.php')) {
            try {
                View::render('errors/404', [
                    'title' => '404',
                    'message' => 'Not found',
                ], class_exists(\Rateb\App\Website\WebsiteContext::class) && \Rateb\App\Website\WebsiteContext::current() !== null
                    ? 'marketing'
                    : 'main');
                return;
            } catch (\Throwable $e) {
                // fall through to plain body
            }
        }
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Not found';
        exit;
    }
}
