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
}
