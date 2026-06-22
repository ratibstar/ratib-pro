<?php
declare(strict_types=1);

return [
    'host' => getenv('RCC_AMI_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('RCC_AMI_PORT') ?: 5038),
    'username' => getenv('RCC_AMI_USER') ?: 'rcc',
    'secret' => getenv('RCC_AMI_PASS') ?: '',
    'connect_timeout' => (float) (getenv('RCC_AMI_CONNECT_TIMEOUT') ?: 5),
    'read_timeout' => (float) (getenv('RCC_AMI_READ_TIMEOUT') ?: 0.5),
    'reconnect_delay_seconds' => (int) (getenv('RCC_AMI_RECONNECT_DELAY') ?: 5),
    'events' => [
        'on',
        'Newchannel',
        'DTMF',
        'BridgeEnter',
        'BridgeLeave',
        'Hangup',
        'QueueCallerJoin',
        'QueueCallerLeave',
        'QueueMemberStatus',
        'AgentConnect',
        'BlindTransfer',
        'AttendedTransfer',
    ],
    'context_prefix' => getenv('RCC_ASTERISK_CONTEXT_PREFIX') ?: 'rcc',
    'pjsip_endpoint_prefix' => getenv('RCC_PJSIP_ENDPOINT_PREFIX') ?: 'agent',
];
