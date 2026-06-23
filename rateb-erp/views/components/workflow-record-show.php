<?php
/** @var array<string, mixed> $item */
/** @var array<int, array{name:string,label:string,type?:string}> $columns */
/** @var string $routePrefix */
/** @var string $csrf */
/** @var bool $canManage */
/** @var bool $canApprove */
/** @var bool $exportEnabled */
$item = $item ?? [];
$columns = $columns ?? [];
$id = (int) ($item['id'] ?? 0);
$approval = (string) ($item['manager_approval_raw'] ?? 'pending');
if (str_starts_with($approval, 'manager_approval_')) {
    $approval = substr($approval, strlen('manager_approval_'));
}
?>
<div class="rateb-card mb-4">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></span>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('back'); ?></a>
            <a href="<?php echo rateb_url($routePrefix . '/' . $id . '/print'); ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                <i class="fas fa-print"></i> <?php echo __('print'); ?>
            </a>
            <?php if (!empty($exportEnabled)) { ?>
            <a href="<?php echo rateb_url_query(rateb_url($routePrefix . '/' . $id . '/download'), ['format' => 'pdf']); ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                <i class="fas fa-file-pdf"></i> <?php echo __('print_save_pdf'); ?>
            </a>
            <a href="<?php echo rateb_url_query(rateb_url($routePrefix . '/' . $id . '/download'), ['format' => 'excel']); ?>" class="btn btn-sm btn-outline-success">
                <i class="fas fa-download"></i> Excel
            </a>
            <?php } ?>
        </div>
    </div>
    <div class="rateb-card-body">
        <dl class="row mb-0">
            <?php foreach ($columns as $col) {
                $type = (string) ($col['type'] ?? '');
                if ($type === 'action_link') {
                    continue;
                }
                $name = (string) ($col['name'] ?? '');
                if ($name === '') {
                    continue;
                }
                $val = $item[$name] ?? '';
                if ($type === 'status' || $name === 'manager_approval') {
                    $meta = rateb_table_cell_meta($val, $col);
                    $display = (string) ($meta['display'] ?? $val);
                } elseif ($type === 'money') {
                    $display = number_format((float) $val, 2);
                } else {
                    $display = (string) $val;
                }
                if ($display === '' || $display === '0000-00-00') {
                    $display = '—';
                }
                ?>
            <dt class="col-sm-4"><?php echo Rateb\App\Core\View::escape(rateb_label((string) ($col['label'] ?? $name))); ?></dt>
            <dd class="col-sm-8"><?php echo $type === 'notes' ? nl2br(Rateb\App\Core\View::escape($display)) : Rateb\App\Core\View::escape($display); ?></dd>
            <?php } ?>
            <?php if (!empty($item['approved_by_name']) || !empty($item['approved_at'])) { ?>
            <dt class="col-sm-4"><?php echo __('approved_by'); ?></dt>
            <dd class="col-sm-8"><?php echo Rateb\App\Core\View::escape((string) ($item['approved_by_name'] ?? '—')); ?>
                <?php if (!empty($item['approved_at'])) { ?>
                <span class="text-muted small rateb-ltr-num"> — <?php echo Rateb\App\Core\View::escape((string) $item['approved_at']); ?></span>
                <?php } ?>
            </dd>
            <?php } ?>
        </dl>
        <div class="mt-4 d-flex flex-wrap gap-2">
            <?php if (!empty($canApprove) && $approval === 'pending') { ?>
            <form method="post" action="<?php echo rateb_url($routePrefix . '/' . $id . '/approve'); ?>" class="d-inline">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> <?php echo __('approve'); ?></button>
            </form>
            <form method="post" action="<?php echo rateb_url($routePrefix . '/' . $id . '/reject'); ?>" class="d-inline" data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('confirm_reject')); ?>">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <button type="submit" class="btn btn-outline-danger"><i class="fas fa-times"></i> <?php echo __('reject'); ?></button>
            </form>
            <?php } ?>
        </div>
    </div>
</div>
