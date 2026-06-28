<?php Rateb\App\Core\View::partial('admin-company-portal-banner'); ?>
<div class="row g-3">
    <?php Rateb\App\Core\View::partial('admin-oversight-filters', [
        'companies' => $companies ?? [],
        'filters' => $filters ?? [],
        'statusOptions' => $statusOptions ?? [],
        'formAction' => $formAction ?? rateb_url('admin/oversight/inventory'),
    ]); ?>
    <div class="col-12">
        <div class="rateb-card mb-3">
            <div class="rateb-card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><?php echo __('inventory_value'); ?>: <strong><?php echo number_format((float) ($total_value ?? 0), 2); ?></strong></span>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="rateb-card mb-3">
            <div class="rateb-card-header"><?php echo __('inventory'); ?></div>
            <div class="rateb-card-body p-0">
                <?php Rateb\App\Core\View::partial('crud-index', [
                    'title' => '',
                    'items' => $items ?? [],
                    'fields' => $itemFields ?? [],
                    'csrf' => $csrf,
                    'routePrefix' => $invRoutePrefix ?? rateb_app_route('inventory'),
                    'permissionResource' => 'inventory',
                    'bulkEnabled' => true,
                    'createEnabled' => false,
                    'actionsEnabled' => true,
                    'exportEnabled' => true,
                    'exportRoute' => rateb_url(($invRoutePrefix ?? rateb_app_route('inventory')) . '/export'),
                    'page' => $invPage ?? 1,
                    'total' => $invTotal ?? 0,
                    'limit' => $invLimit ?? 5,
                    'search' => $invSearch ?? '',
                    'listBaseUrl' => $listBaseUrl ?? '',
                    'pageKey' => $invPageKey ?? 'inv_page',
                    'perPageKey' => $invPerPageKey ?? 'inv_per_page',
                    'searchKey' => $invSearchKey ?? 'inv_q',
                    'perPageOptions' => $invPerPageOptions ?? rateb_list_per_page_options(),
                    'preserveQuery' => $invPreserveQuery ?? [],
                    'searchClearUrl' => $invSearchClearUrl ?? '',
                    'searchPreserve' => array_keys($invPreserveQuery ?? []),
                ]); ?>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo __('warehouses'); ?></div>
            <div class="rateb-card-body p-0">
                <?php Rateb\App\Core\View::partial('crud-index', [
                    'title' => '',
                    'items' => $warehouses ?? [],
                    'fields' => $warehouseFields ?? [],
                    'csrf' => $csrf,
                    'routePrefix' => $whRoutePrefix ?? rateb_app_route('warehouses'),
                    'permissionResource' => 'warehouses',
                    'bulkEnabled' => true,
                    'createEnabled' => false,
                    'actionsEnabled' => true,
                    'exportEnabled' => true,
                    'exportRoute' => rateb_url(($whRoutePrefix ?? rateb_app_route('warehouses')) . '/export'),
                    'page' => $whPage ?? 1,
                    'total' => $whTotal ?? 0,
                    'limit' => $whLimit ?? 5,
                    'search' => $whSearch ?? '',
                    'listBaseUrl' => $listBaseUrl ?? '',
                    'pageKey' => $whPageKey ?? 'wh_page',
                    'perPageKey' => $whPerPageKey ?? 'wh_per_page',
                    'searchKey' => $whSearchKey ?? 'wh_q',
                    'perPageOptions' => $whPerPageOptions ?? rateb_list_per_page_options(),
                    'preserveQuery' => $whPreserveQuery ?? [],
                    'searchClearUrl' => $whSearchClearUrl ?? '',
                    'searchPreserve' => array_keys($whPreserveQuery ?? []),
                ]); ?>
            </div>
        </div>
    </div>
</div>
