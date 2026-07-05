<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\Enums;

/**
 * Supported POS V2 feature flags (env + settings_json + device_meta).
 */
enum PosV2FeatureFlag: string
{
    case Enabled = 'POS_V2_ENABLED';
    case Profile = 'POS_V2_PROFILE';
    case ScanMode = 'POS_V2_SCAN_MODE';
    case Offline = 'POS_V2_OFFLINE';
    case CardTerminal = 'POS_V2_CARD_TERMINAL';

    public function isBoolean(): bool
    {
        return $this !== self::Profile;
    }
}
