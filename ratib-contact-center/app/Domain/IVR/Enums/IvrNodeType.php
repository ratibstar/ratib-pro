<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\IVR\Enums;

enum IvrNodeType: string
{
    case PlayMessage = 'play_message';
    case CollectInput = 'collect_input';
    case RouteCall = 'route_call';
    case Hangup = 'hangup';
}
