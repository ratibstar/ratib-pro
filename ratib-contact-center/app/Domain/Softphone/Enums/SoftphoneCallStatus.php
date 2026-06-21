<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\Softphone\Enums;

enum SoftphoneCallStatus: string
{
    case Ringing = 'ringing';
    case Connected = 'connected';
    case Held = 'held';
    case Transferred = 'transferred';
    case Ended = 'ended';
}

enum SoftphoneDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
}
