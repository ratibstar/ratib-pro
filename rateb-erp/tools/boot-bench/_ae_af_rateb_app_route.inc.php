<?php
declare(strict_types=1);

/**
 * Phase AF — temporary instrumented replacement for rateb_app_route.
 * Same algorithm as config/app.php; records growth timings. DO NOT leave in production.
 */
if (!function_exists('rateb_app_route')) {
    function rateb_app_route(string $path): string
    {
        $t0 = hrtime(true);
        static $conflictRoots = [
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
        static $afInv = 0;
        if (!isset($GLOBALS['AF_RATEB_APP_ROUTE'])) {
            $GLOBALS['AF_RATEB_APP_ROUTE'] = [
                'calls' => [],
                'company_access' => null,
                'initial_size_before_merge' => null,
            ];
        }

        $path = ltrim(preg_replace('#^company/#', '', trim($path)), '/');

        $sizeBefore = count($conflictRoots);
        if ($GLOBALS['AF_RATEB_APP_ROUTE']['initial_size_before_merge'] === null) {
            $GLOBALS['AF_RATEB_APP_ROUTE']['initial_size_before_merge'] = $sizeBefore;
        }

        $tMerge0 = hrtime(true);
        $didMerge = false;
        if (function_exists('rateb_company_access_routes_enabled') && rateb_company_access_routes_enabled()) {
            $conflictRoots = array_merge($conflictRoots, [
                'access-control', 'users', 'roles', 'permissions',
                'audit-logs', 'support-tickets', 'email-templates', 'sms-templates',
            ]);
            $didMerge = true;
        }
        $mergeMs = (hrtime(true) - $tMerge0) / 1e6;
        $sizeAfter = count($conflictRoots);

        $tExp0 = hrtime(true);
        $root = explode('/', $path)[0];
        $explodeMs = (hrtime(true) - $tExp0) / 1e6;

        $tIn0 = hrtime(true);
        $hit = in_array($root, $conflictRoots, true);
        $inArrayMs = (hrtime(true) - $tIn0) / 1e6;

        $afInv++;
        $wallMs = (hrtime(true) - $t0) / 1e6;
        $GLOBALS['AF_RATEB_APP_ROUTE']['company_access'] = $didMerge;
        $GLOBALS['AF_RATEB_APP_ROUTE']['calls'][] = [
            'n' => $afInv,
            'size_before' => $sizeBefore,
            'size_after' => $sizeAfter,
            'did_merge' => $didMerge,
            'merge_ms' => round($mergeMs, 6),
            'explode_ms' => round($explodeMs, 6),
            'in_array_ms' => round($inArrayMs, 6),
            'wall_ms' => round($wallMs, 6),
        ];

        if ($hit) {
            return 'admin/ops/' . $path;
        }

        return 'admin/' . $path;
    }
}
