<?php
declare(strict_types=1);

return [
    'default_wss_uri' => getenv('RCC_SIP_WSS_URI') ?: 'wss://pbx.ratib.sa:8089/ws',
    'default_domain' => getenv('RCC_SIP_DOMAIN') ?: 'pbx.ratib.sa',
    'ice_servers' => [
        ['urls' => 'stun:stun.l.google.com:19302'],
    ],
    'register_expires' => 300,
    'session_ping_seconds' => 30,
];
