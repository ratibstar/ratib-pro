<?php Rateb\App\Core\View::partial('admin-oversight-filters', [
    'companies' => $companies ?? [],
    'filters' => $filters ?? [],
    'statusOptions' => $statusOptions ?? [],
    'formAction' => $formAction ?? rateb_url('admin/supplier-evaluations'),
]); ?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? __('supplier_evaluations')); ?></div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                <tr>
                    <th><?php echo __('companies'); ?></th>
                    <th><?php echo __('suppliers'); ?></th>
                    <th><?php echo __('evaluation_date'); ?></th>
                    <th><?php echo __('overall_score'); ?></th>
                    <th><?php echo __('quality_score'); ?></th>
                    <th><?php echo __('delivery_score'); ?></th>
                    <th><?php echo __('status'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($items)) { ?>
                <tr><td colspan="7" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else { foreach ($items as $row) { ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape($row['company_name'] ?? ''); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($row['supplier_name'] ?? ''); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($row['evaluation_date'] ?? ''); ?></td>
                    <td><strong><?php echo Rateb\App\Core\View::escape($row['overall_score'] ?? ''); ?></strong></td>
                    <td><?php echo Rateb\App\Core\View::escape($row['quality_score'] ?? ''); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($row['delivery_score'] ?? ''); ?></td>
                    <td><?php echo __( (string) ($row['status'] ?? '')); ?></td>
                </tr>
                <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
