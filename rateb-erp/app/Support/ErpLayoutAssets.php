<?php
declare(strict_types=1);

namespace Rateb\App\Support;

/** Resolve which ERP layout assets to load per route (faster first paint). */
final class ErpLayoutAssets
{
    /**
     * @return array{
     *   charts: bool,
     *   lineItems: bool,
     *   formHybrid: bool,
     *   fiscalYear: bool,
     *   inventoryBatch: bool,
     *   contractRenewal: bool,
     *   cmsAdmin: bool,
     *   entityDocuments: bool,
     *   tableTools: bool,
     *   bulkDelete: bool,
     *   dateInputs: bool,
     *   defer: list<string>
     * }
     */
    public static function resolve(string $erpRoute): array
    {
        $route = trim($erpRoute, '/');
        $isCreateEdit = (bool) preg_match('#/(create|edit)(/|$)#', $route);
        $isLeanDashboard = in_array($route, ['admin', 'admin/executive-dashboard'], true);

        $charts = $isLeanDashboard
            || $route === 'admin/ops/accounting'
            || str_ends_with($route, '/accounting')
            || str_contains($route, 'cfo-dashboard');

        $lineItems = $isCreateEdit && (bool) preg_match(
            '#(purchase-requests|purchase-orders|quotations|journal-entries|cash-vouchers|invoices|supplier-payments)(/|$)#',
            $route
        );

        $formHybrid = $isCreateEdit;

        $fiscalYear = $isCreateEdit && (bool) preg_match(
            '#(fiscal-periods|journal-entries|cash-vouchers)(/|$)#',
            $route
        );

        $inventoryBatch = str_contains($route, 'inventory-batches');

        $contractRenewal = str_contains($route, 'contract-renewals');

        $cmsAdmin = str_starts_with($route, 'admin/cms');

        $entityDocuments = !str_starts_with($route, 'admin/cms')
            && (bool) preg_match('#^(admin/ops/|admin/oversight/|hr/)#', $route);

        // Dashboard has no data tables / bulk actions — skip heavy UI helpers.
        $tableTools = !$isLeanDashboard;
        $bulkDelete = !$isLeanDashboard;
        $dateInputs = !$isLeanDashboard;

        $defer = [];
        if ($entityDocuments) {
            $defer[] = 'entity-documents-modal.js';
        }
        if ($charts) {
            $defer[] = 'charts.js';
        }

        return [
            'charts' => $charts,
            'lineItems' => $lineItems,
            'formHybrid' => $formHybrid,
            'fiscalYear' => $fiscalYear,
            'inventoryBatch' => $inventoryBatch,
            'contractRenewal' => $contractRenewal,
            'cmsAdmin' => $cmsAdmin,
            'entityDocuments' => $entityDocuments,
            'tableTools' => $tableTools,
            'bulkDelete' => $bulkDelete,
            'dateInputs' => $dateInputs,
            'defer' => $defer,
        ];
    }
}
