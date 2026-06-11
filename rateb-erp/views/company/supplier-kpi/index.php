<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? __('supplier_kpi')); ?></div>
    <div class="rateb-card-body">
        <?php Rateb\App\Core\View::partial('export-toolbar', ['exportRoute' => $exportRoute ?? '']); ?>
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $suppliers ?? [],
            'columns' => [
                ['name' => 'name', 'label' => 'suppliers'],
                ['name' => 'classification_name', 'label' => 'supplier_classifications'],
                ['name' => 'rating', 'label' => 'rating'],
                ['name' => 'avg_eval', 'label' => 'overall_score'],
                ['name' => 'po_count', 'label' => 'purchase_orders'],
                ['name' => 'performance_kpi', 'label' => 'performance_kpi'],
            ],
        ]); ?>
    </div>
</div>
