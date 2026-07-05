<?php
declare(strict_types=1);

/** @var array<int, array<string, mixed>> $items */
?>
<div class="rateb-pos-page">
    <h1 class="h3 mb-3"><?php echo \Rateb\App\Pos\Support\PosView::escape($title ?? ''); ?></h1>
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th><?php echo __('pos_order_no'); ?></th>
                    <th><?php echo __('pos_order_type'); ?></th>
                    <th><?php echo __('status'); ?></th>
                    <th><?php echo __('pos_total'); ?></th>
                    <th><?php echo __('created_at'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($items === []): ?>
                    <tr><td colspan="6"><?php echo __('no_records'); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($item['order_no'] ?? '')); ?></td>
                            <td><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($item['order_type'] ?? '')); ?></td>
                            <td><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($item['status'] ?? '')); ?></td>
                            <td><?php echo number_format((float) ($item['total'] ?? 0), 2); ?></td>
                            <td><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($item['created_at'] ?? '')); ?></td>
                            <td>
                                <a class="btn btn-sm btn-outline-primary" href="<?php echo rateb_app_url('pos/orders/' . (int) ($item['id'] ?? 0)); ?>">
                                    <?php echo __('view'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<link href="<?php echo rateb_pos_asset('css/pos-module.css'); ?>" rel="stylesheet">
