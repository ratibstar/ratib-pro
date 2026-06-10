<?php
declare(strict_types=1);

define('RATEB_ROOT', dirname(__DIR__));

require_once RATEB_ROOT . '/app/Core/Bootstrap.php';

Rateb\App\Core\Bootstrap::init(RATEB_ROOT);

Rateb\App\Core\Auth::bootstrapFromSession();

$router = new Rateb\App\Core\Router();

require RATEB_ROOT . '/routes/web.php';
require RATEB_ROOT . '/routes/company.php';
require RATEB_ROOT . '/routes/api.php';

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$base = RATEB_BASE_URL;
if ($base !== '' && strpos($uri, $base) === 0) {
    $uri = substr($uri, strlen($base)) ?: '/';
}

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $uri);
