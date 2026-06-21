<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Core\Events;

interface EventSubscriberInterface
{
    public function onEvent(RealtimeEvent $event): void;
}
