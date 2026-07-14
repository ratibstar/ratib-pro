<?php
declare(strict_types=1);

/**
 * Phase AG — temporary instrumented FIXED rateb_app_route (measure only; then use clean app.php).
 * Same once-init algorithm as production fix + per-call timings.
 */
if (!function_exists('rateb_app_route')) {
    function rateb_app_route(string $path): string
    {
        $t0 = hrtime(true);
        static $conflictLookup = null;
        static $conflictSize = 0;
        static $mergeTotalMs = 0.0;
        static $afInv = 0;

        if (!isset($GLOBALS['AG_RATEB_APP_ROUTE'])) {
            $GLOBALS['AG_RATEB_APP_ROUTE'] = [
                'calls' => [],
                'merge_calls' => 0,
                'initial_size' => null,
                'final_size' => null,
            ];
        }

        $path = ltrim(preg_replace('#^company/#', '', trim($path)), '/');

        $mergeMs = 0.0;
        if ($conflictLookup === null) {
            $tMerge0 = hrtime(true);
            $conflictRoots = [
                'inventory', 'suppliers', 'assets', 'contracts', 'stock-movements',
                'supplier-evaluations', 'workflows', 'medical-devices', 'reports',
                'notifications', 'accounting', 'chart-of-accounts', 'journal-entries',
                'cost-centers', 'cash-vouchers', 'fiscal-periods', 'bank-accounts',
                'rfq', 'quotations', 'purchase-requests', 'purchase-orders',
                'warehouses', 'warehouse-transfers', 'product-categories',
                'branches', 'branch-dashboard', 'branch-financial', 'branch-transfers',
                'inventory-batches', 'inventory-audits', 'inventory-forecast',
                'supplier-comms', 'supplier-classifications', 'supplier-kpi',
                'contract-renewals', 'tenders', 'asset-maintenance', 'asset-assignments',
                'asset-depreciation', 'device-maintenance', 'device-spare-parts', 'device-warranty',
                'documents', 'profile', 'pos',
            ];
            if (function_exists('rateb_company_access_routes_enabled') && rateb_company_access_routes_enabled()) {
                $conflictRoots = array_merge($conflictRoots, [
                    'access-control', 'users', 'roles', 'permissions',
                    'audit-logs', 'support-tickets', 'email-templates', 'sms-templates',
                ]);
            }
            $conflictLookup = array_fill_keys($conflictRoots, true);
            $conflictSize = count($conflictLookup);
            $mergeMs = (hrtime(true) - $tMerge0) / 1e6;
            $mergeTotalMs = $mergeMs;
            $GLOBALS['AG_RATEB_APP_ROUTE']['merge_calls'] = 1;
            $GLOBALS['AG_RATEB_APP_ROUTE']['initial_size'] = $conflictSize;
            $GLOBALS['AG_RATEB_APP_ROUTE']['unique_ok'] = count($conflictRoots) === $conflictSize;
        }

        $tExp0 = hrtime(true);
        $root = explode('/', $path)[0];
        $explodeMs = (hrtime(true) - $tExp0) / 1e6;

        $tIn0 = hrtime(true);
        $hit = isset($conflictLookup[$root]);
        $lookupMs = (hrtime(true) - $tIn0) / 1e6;

        $afInv++;
        $wallMs = (hrtime(true) - $t0) / 1e6;
        $GLOBALS['AG_RATEB_APP_ROUTE']['final_size'] = $conflictSize;
        $GLOBALS['AG_RATEB_APP_ROUTE']['sum_merge_ms'] = $mergeTotalMs;
        $GLOBALS['AG_RATEB_APP_ROUTE']['calls'][] = [
            'n' => $afInv,
            'size' => $conflictSize,
            'merge_ms' => round($mergeMs, 6),
            'explode_ms' => round($explodeMs, 6),
            'lookup_ms' => round($lookupMs, 6),
            'wall_ms' => round($wallMs, 6),
        ];

        if ($hit) {
            return 'admin/ops/' . $path;
        }

        return 'admin/' . $path;
    }
}
