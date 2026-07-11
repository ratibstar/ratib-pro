<?php

declare(strict_types=1);

/**
 * Enterprise offline feature flags (Phase 2A + Phase 14 pilot).
 * Master default is OFF — no behavior change until explicitly enabled.
 */
return [
    'defaults' => [
        'offline.enabled' => false,
        'offline.pos.complete' => true,
        'offline.inventory.movements' => false,
        'offline.hr.attendance' => false,
        'offline.procurement' => false,
        /** Phase 14.2 — PO goods receipt (GRN) via ProcurementService::receiveOrder (requires procurement). */
        'offline.procurement.goods_receipt' => false,
        /** Phase 15B — Recruitment Tier-1 (requires master). */
        'offline.recruitment' => false,
        'offline.recruitment.candidates' => false,
        'offline.recruitment.workflow' => false,
        'offline.recruitment.assignment' => false,
        'offline.monitoring' => false,
        'offline.read_cache' => false,
        'offline.auth.unlock' => false,
        'offline.rbac.cache' => false,
        'offline.master_data' => false,
        /** Phase 14 — allowlisted ops page snapshots (requires master + read_cache). */
        'offline.pilot.ops_pages' => false,
    ],

    /** Env var overrides (string "1"/"true"/"yes" → true). */
    'env' => [
        'offline.enabled' => 'RATEB_OFFLINE_ENABLED',
        'offline.pos.complete' => 'RATEB_OFFLINE_POS_COMPLETE',
        'offline.inventory.movements' => 'RATEB_OFFLINE_INVENTORY_MOVEMENTS',
        'offline.hr.attendance' => 'RATEB_OFFLINE_HR_ATTENDANCE',
        'offline.procurement' => 'RATEB_OFFLINE_PROCUREMENT',
        'offline.procurement.goods_receipt' => 'RATEB_OFFLINE_PROCUREMENT_GRN',
        'offline.recruitment' => 'RATEB_OFFLINE_RECRUITMENT',
        'offline.recruitment.candidates' => 'RATEB_OFFLINE_RECRUITMENT_CANDIDATES',
        'offline.recruitment.workflow' => 'RATEB_OFFLINE_RECRUITMENT_WORKFLOW',
        'offline.recruitment.assignment' => 'RATEB_OFFLINE_RECRUITMENT_ASSIGNMENT',
        'offline.monitoring' => 'RATEB_OFFLINE_MONITORING',
        'offline.read_cache' => 'RATEB_OFFLINE_READ_CACHE',
        'offline.auth.unlock' => 'RATEB_OFFLINE_AUTH_UNLOCK',
        'offline.rbac.cache' => 'RATEB_OFFLINE_RBAC_CACHE',
        'offline.master_data' => 'RATEB_OFFLINE_MASTER_DATA',
        'offline.pilot.ops_pages' => 'RATEB_OFFLINE_PILOT_OPS_PAGES',
    ],
];
