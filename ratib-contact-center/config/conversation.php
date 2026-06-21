<?php
declare(strict_types=1);

/**
 * Unified conversation priority — channel urgency + ERP + routing SLA.
 */
return [
    'channel_urgency' => [
        'voice' => 1.0,
        'whatsapp' => 0.75,
        'chat' => 0.70,
        'email' => 0.45,
        'social' => 0.50,
        'system' => 0.20,
    ],

    'priority_thresholds' => [
        'vip' => 85,
        'high' => 70,
        'medium' => 40,
    ],

    'sla_status_penalty' => [
        'green' => 0,
        'yellow' => 15,
        'red' => 30,
    ],
];
