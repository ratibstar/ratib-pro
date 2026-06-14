<?php
/** @var array<int, array<string, mixed>> $tree */
/** @var string $routePrefix */
/** @var string $csrf */
/** @var bool $actionsEnabled */
$actionsEnabled = $actionsEnabled ?? false;
$editUrl = static function (int $id) use ($routePrefix): string {
    if (strpos($routePrefix, 'admin/ops/') === 0) {
        $short = preg_replace('#^admin/ops/#', '', $routePrefix);
        return rateb_app_url($short . '/' . $id . '/edit');
    }
    return rateb_url($routePrefix . '/' . $id . '/edit');
};
$createUrl = strpos($routePrefix, 'admin/ops/') === 0
    ? rateb_app_url(preg_replace('#^admin/ops/#', '', $routePrefix) . '/create')
    : rateb_url($routePrefix . '/create');
$render = static function (array $nodes, int $depth = 0) use (&$render, $editUrl, $actionsEnabled, $csrf, $routePrefix): void {
    foreach ($nodes as $node) {
        $name = rateb_locale() === 'ar' && !empty($node['name_ar']) ? $node['name_ar'] : ($node['name'] ?? '');
        $type = (string) ($node['account_type'] ?? '');
        $balance = (float) ($node['balance'] ?? 0);
        $isGroup = !empty($node['children']);
        ?>
        <tr class="<?php echo $isGroup ? 'rateb-coa-group' : ''; ?>" data-depth="<?php echo $depth; ?>">
            <td class="rateb-coa-name" style="padding-inline-start: <?php echo (12 + $depth * 20); ?>px">
                <?php if ($isGroup) { ?><i class="fas fa-folder-open text-warning me-1"></i><?php } else { ?><i class="fas fa-file-invoice text-muted me-1"></i><?php } ?>
                <span class="fw-semibold"><?php echo Rateb\App\Core\View::escape($node['code'] ?? ''); ?></span>
                <span class="ms-1"><?php echo Rateb\App\Core\View::escape($name); ?></span>
            </td>
            <td><span class="badge bg-secondary-subtle text-secondary"><?php echo __($type); ?></span></td>
            <td class="text-end"><?php echo number_format((float) ($node['total_debit'] ?? 0), 2); ?></td>
            <td class="text-end"><?php echo number_format((float) ($node['total_credit'] ?? 0), 2); ?></td>
            <td class="text-end fw-semibold"><?php echo number_format($balance, 2); ?></td>
            <?php if ($actionsEnabled) { ?>
            <td class="text-end">
                <a href="<?php echo $editUrl((int) $node['id']); ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
            </td>
            <?php } ?>
        </tr>
        <?php
        if ($isGroup) {
            $render($node['children'], $depth + 1);
        }
    }
};
?>
<div class="rateb-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-sitemap me-2"></i><?php echo __('coa_tree'); ?></span>
        <?php if ($createEnabled ?? false) { ?>
        <a href="<?php echo $createUrl; ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> <?php echo __('add_account'); ?>
        </a>
        <?php } ?>
    </div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table rateb-coa-tree mb-0">
                <thead>
                <tr>
                    <th><?php echo __('account'); ?></th>
                    <th><?php echo __('account_type'); ?></th>
                    <th class="text-end"><?php echo __('debit'); ?></th>
                    <th class="text-end"><?php echo __('credit'); ?></th>
                    <th class="text-end"><?php echo __('balance'); ?></th>
                    <?php if ($actionsEnabled) { ?><th></th><?php } ?>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($tree)) { ?>
                <tr><td colspan="<?php echo $actionsEnabled ? 6 : 5; ?>" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else {
                    $render($tree);
                } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
