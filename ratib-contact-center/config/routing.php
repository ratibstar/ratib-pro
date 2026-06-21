<?php
declare(strict_types=1);

/**
 * AI Routing Engine — tenant-agnostic scoring rules (no logic in controllers).
 * Override per tenant via rcc_settings keys: routing.weights, routing.erp_boosts
 */
return [
    'agent_weights' => [
        'skill_match' => 0.30,
        'current_load' => 0.25,
        'availability' => 0.20,
        'erp_familiarity' => 0.15,
        'sla_risk_penalty' => 0.10,
    ],

    'erp_priority_boosts' => [
        'vip_customer' => 0.40,
        'open_sla_breach' => 0.30,
        'high_value_company' => 0.25,
        'repeat_caller' => 0.10,
    ],

    'ivr_skill_map' => [
        '1' => 'sales',
        '2' => 'support',
        '3' => 'billing',
    ],

    'queue_skill_map' => [
        'sales' => 'sales',
        'support' => 'support',
        'billing' => 'billing',
        'default' => 'support',
    ],

    'sla_thresholds' => [
        'yellow_ratio' => 0.70,
        'red_ratio' => 1.00,
    ],

    'queue_score_weights' => [
        'wait_penalty' => 0.35,
        'availability' => 0.30,
        'skill_match' => 0.20,
        'sla_risk' => 0.15,
    ],
];
