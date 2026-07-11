<?php

declare(strict_types=1);

/**
 * Module tier + operation classification registry (Phase 5).
 * Tier 1 adapters: Inventory, HR, Procurement (all flag-gated).
 */
return [
    'tiers' => [
        'T0' => ['pos'],
        'T1' => ['inventory', 'hr', 'procurement', 'recruitment'],
        'T2' => ['erp_shell'],
        'T3' => ['platform'],
    ],

    /** Phase 11: + platform auth unlock (flag-gated). Phase 15B: + recruitment. */
    'active_modules' => ['offline_meta', 'pos', 'inventory', 'hr', 'procurement', 'recruitment', 'erp_shell', 'platform'],

    'operations' => [
        'offline.ping' => ['class' => 'OC', 'module' => 'offline_meta'],
        'offline.ack' => ['class' => 'RS', 'module' => 'offline_meta'],
        'offline.shell.ping' => ['class' => 'OC', 'module' => 'erp_shell'],
        'offline.auth.unlock' => ['class' => 'OC', 'module' => 'platform'],
        'offline.rbac.cache' => ['class' => 'OC', 'module' => 'platform'],
        'offline.master_data' => ['class' => 'OC', 'module' => 'platform'],
        'pos.checkout' => ['class' => 'RS', 'module' => 'pos'],
        'pos.process_return' => ['class' => 'RS', 'module' => 'pos'],
        'pos.process_exchange' => ['class' => 'RS', 'module' => 'pos'],
        'inventory.stock_movement' => ['class' => 'RS', 'module' => 'inventory'],
        'inventory.stock_count' => ['class' => 'RS', 'module' => 'inventory'],
        'inventory.warehouse_transfer' => ['class' => 'RS', 'module' => 'inventory'],
        'hr.attendance' => ['class' => 'RS', 'module' => 'hr'],
        'hr.attendance.bulk' => ['class' => 'RS', 'module' => 'hr'],
        'hr.leave_draft' => ['class' => 'RS', 'module' => 'hr'],
        'procurement.purchase_request.draft' => ['class' => 'RS', 'module' => 'procurement'],
        'procurement.rfq.draft' => ['class' => 'RS', 'module' => 'procurement'],
        'procurement.purchase_order.draft' => ['class' => 'RS', 'module' => 'procurement'],
        'procurement.goods_receipt.receive' => ['class' => 'RS', 'module' => 'procurement'],
        'recruitment.candidate.create' => ['class' => 'RS', 'module' => 'recruitment'],
        'recruitment.candidate.update' => ['class' => 'RS', 'module' => 'recruitment'],
        'recruitment.workflow.transition' => ['class' => 'RS', 'module' => 'recruitment'],
        'recruitment.assignment.create' => ['class' => 'RS', 'module' => 'recruitment'],
        'recruitment.interview.create' => ['class' => 'RS', 'module' => 'recruitment'],
        'recruitment.visa.create' => ['class' => 'RS', 'module' => 'recruitment'],
        'recruitment.medical.create' => ['class' => 'RS', 'module' => 'recruitment'],
        'recruitment.passport.create' => ['class' => 'RS', 'module' => 'recruitment'],
        'recruitment.passport.update' => ['class' => 'RS', 'module' => 'recruitment'],
        'recruitment.contract.create' => ['class' => 'RS', 'module' => 'recruitment'],
        'recruitment.note.create' => ['class' => 'RS', 'module' => 'recruitment'],
    ],
];
