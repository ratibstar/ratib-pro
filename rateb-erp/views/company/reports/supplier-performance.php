<div class="rateb-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></span>
        <?php Rateb\App\Core\View::partial('export-toolbar', ['exportRoute' => $exportRoute ?? '', 'exportEnabled' => $exportEnabled ?? true]); ?>
    </div>
    <div class="rateb-card-body">
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $rows ?? [],
            'columns' => [
                ['name' => 'code', 'label' => 'record_id', 'type' => 'id'],
                ['name' => 'name', 'label' => 'suppliers'],
                ['name' => 'classification_name', 'label' => 'supplier_classifications'],
                ['name' => 'rating', 'label' => 'rating'],
                ['name' => 'avg_eval', 'label' => 'overall_score', 'type' => 'money'],
                ['name' => 'po_count', 'label' => 'purchase_orders'],
                ['name' => 'performance_kpi', 'label' => 'performance_kpi', 'type' => 'money'],
            ],
        ]); ?>
    </div>
</div>
