<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\IVR\Enums;

enum IvrSessionStatus: string
{
    case Active = 'active';
    case WaitingInput = 'waiting_input';
    case Completed = 'completed';
    case Failed = 'failed';
    case Timeout = 'timeout';
}
