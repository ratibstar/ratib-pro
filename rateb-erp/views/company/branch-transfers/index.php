<?php declare(strict_types=1); ?>
<div class="rateb-page-header d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?php echo Rateb\App\Core\View::escape($title ?? __('branch_transfers')); ?></h1>
    <?php if (empty($schemaMissing) && function_exists('rateb_can') && rateb_can('branch.transfers.manage')) { ?>
    <a class="btn btn-primary btn-sm" href="<?php echo rateb_url(rateb_app_route('branch-transfers/create')); ?>"><?php echo __('create'); ?></a>
    <?php } ?>
</div>
<?php if (!empty($schemaMissing)) { ?>
<div class="alert alert-warning"><?php echo __('db_schema_outdated'); ?> — <code>129_inter_branch_transfers.sql</code></div>
<?php } ?>
<div class="table-responsive rateb-card">
    <table class="table table-sm mb-0">
        <thead><tr><th>#</th><th><?php echo __('type'); ?></th><th><?php echo __('from'); ?></th><th><?php echo __('to'); ?></th><th><?php echo __('status'); ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($items ?? [] as $item) { ?>
            <tr>
                <td><?php echo Rateb\App\Core\View::escape((string) ($item['transfer_no'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($item['transfer_type'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($item['source_name'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($item['dest_name'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($item['status'] ?? '')); ?></td>
                <td>
                    <?php if (($item['status'] ?? '') === 'pending' && function_exists('rateb_can') && rateb_can('branch.transfers.manage')) { ?>
                    <form method="post" action="<?php echo rateb_url(rateb_app_route('branch-transfers/' . (int) $item['id'] . '/approve')); ?>" class="d-inline">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
                        <button type="submit" class="btn btn-outline-success btn-sm"><?php echo __('approve'); ?></button>
                    </form>
                    <?php } ?>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div>
