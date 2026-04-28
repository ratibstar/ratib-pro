<?php
declare(strict_types=1);

use App\Core\EventDispatcher;

if (!function_exists('event')) {
    function event(object $event): void
    {
        $dispatcher = $GLOBALS['worker_platform_event_dispatcher'] ?? null;
        if ($dispatcher instanceof EventDispatcher) {
            $dispatcher->event($event);
        }
    }
}
