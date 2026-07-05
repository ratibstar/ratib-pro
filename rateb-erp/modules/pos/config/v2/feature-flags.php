<?php

declare(strict_types=1);

/**
 * POS V2 feature flag definitions — JSON paths and hardcoded defaults.
 *
 * Storage layers (priority high → low):
 *   terminal device_meta.v2 → branch settings_json → company settings_json → env → defaults
 */
return [
    'defaults' => [
        'POS_V2_ENABLED' => false,
        'POS_V2_PROFILE' => 'retail',
        'POS_V2_SCAN_MODE' => false,
        'POS_V2_OFFLINE' => false,
        'POS_V2_CARD_TERMINAL' => false,
    ],

    'env' => [
        'POS_V2_ENABLED' => 'POS_V2_ENABLED',
        'POS_V2_PROFILE' => 'POS_V2_PROFILE',
        'POS_V2_SCAN_MODE' => 'POS_V2_SCAN_MODE',
        'POS_V2_OFFLINE' => 'POS_V2_OFFLINE',
        'POS_V2_CARD_TERMINAL' => 'POS_V2_CARD_TERMINAL',
    ],

    'profiles' => [
        'retail',
        'restaurant',
        'pharmacy',
        'fashion',
        'enterprise',
    ],
];
