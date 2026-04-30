<?php
declare(strict_types=1);

use App\Core\Application;
use App\Core\Autoloader;
use App\Core\RealtimeServer;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$isAuthed = (!empty($_SESSION['logged_in']) && (int) ($_SESSION['user_id'] ?? 0) > 0)
    || (!empty($_SESSION['control_logged_in']) && (int) ($_SESSION['control_user_id'] ?? 0) > 0);
if (!$isAuthed) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$projectRoot = dirname(__DIR__, 2);
Autoloader::register($projectRoot . DIRECTORY_SEPARATOR . 'app');

$config = require $projectRoot . '/config/worker_tracking.php';
$container = Application::boot($config);
/** @var RealtimeServer $realtime */
$realtime = $container->get(RealtimeServer::class);

$lastEventId = 0;
if (isset($_SERVER['HTTP_LAST_EVENT_ID']) && ctype_digit((string) $_SERVER['HTTP_LAST_EVENT_ID'])) {
    $lastEventId = (int) $_SERVER['HTTP_LAST_EVENT_ID'];
} elseif (isset($_GET['last_event_id']) && ctype_digit((string) $_GET['last_event_id'])) {
    $lastEventId = (int) $_GET['last_event_id'];
}

$realtime->streamSse($lastEventId);
