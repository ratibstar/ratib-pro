<?php
/** @var array<int, array<string, mixed>> $items */
/** @var string $routePrefix */
/** @var bool $canManage */
/** @var string $search */
/** @var int $total */
/** @var int $page */
/** @var int $limit */
$canManage = !empty($canManage);
$items = is_array($items ?? null) ? $items : [];
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/company-permissions.css'); ?>">

<section class="rateb-cp-page" aria-labelledby="rateb-cp-title">
    <header class="rateb-cp-hero">
        <div class="rateb-cp-hero-text">
            <h1 id="rateb-cp-title" class="rateb-cp-title"><?php echo Rateb\App\Core\View::escape($title ?? __('company_permissions')); ?></h1>
            <p class="rateb-cp-lead"><?php echo __('company_permissions_help'); ?></p>
        </div>
        <div class="rateb-cp-hero-actions">
            <a href="<?php echo rateb_url('admin/companies'); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-building"></i> <?php echo __('companies'); ?>
            </a>
        </div>
    </header>

    <form method="get" action="<?php echo rateb_url($routePrefix); ?>" class="rateb-cp-search" role="search">
        <label class="visually-hidden" for="rateb-cp-q"><?php echo __('search'); ?></label>
        <input id="rateb-cp-q" type="search" name="q" class="form-control" value="<?php echo Rateb\App\Core\View::escape($search ?? ''); ?>"
               placeholder="<?php echo Rateb\App\Core\View::escape(__('company_permissions_search_ph')); ?>">
        <button type="submit" class="btn btn-primary"><?php echo __('search'); ?></button>
        <?php if (trim((string) ($search ?? '')) !== '') { ?>
        <a class="btn btn-outline-secondary" href="<?php echo rateb_url($routePrefix); ?>"><?php echo __('clear'); ?></a>
        <?php } ?>
    </form>

    <?php if ($items === []) { ?>
    <div class="rateb-cp-empty">
        <i class="fas fa-building-circle-xmark" aria-hidden="true"></i>
        <p><?php echo __('no_records'); ?></p>
    </div>
    <?php } else { ?>
    <div class="rateb-cp-list" role="list">
        <?php foreach ($items as $row) {
            $cid = (int) ($row['id'] ?? 0);
            $name = (string) ($row['name'] ?? '');
            $status = (string) ($row['status'] ?? 'pending');
            $statusBadge = match ($status) {
                'active' => 'is-active',
                'suspended' => 'is-suspended',
                default => 'is-pending',
            };
            $modCount = (int) ($row['modules_count'] ?? 0);
            $modTotal = max(1, (int) ($row['modules_total'] ?? 0));
            $pct = max(0, min(100, (int) ($row['modules_pct'] ?? 0)));
            $labels = is_array($row['modules_labels'] ?? null) ? $row['modules_labels'] : [];
            $showLabels = array_slice($labels, 0, 8);
            $extraLabels = max(0, count($labels) - count($showLabels));
            $editUrl = rateb_url($routePrefix . '/' . $cid);
            ?>
        <article class="rateb-cp-card" role="listitem">
            <div class="rateb-cp-card-top">
                <div class="rateb-cp-identity">
                    <span class="rateb-cp-id" title="<?php echo __('id'); ?>">#<?php echo $cid; ?></span>
                    <h2 class="rateb-cp-name"><?php echo Rateb\App\Core\View::escape($name); ?></h2>
                    <span class="rateb-cp-status <?php echo $statusBadge; ?>"><?php echo __($status); ?></span>
                </div>
                <?php if ($canManage) { ?>
                <a class="btn btn-primary rateb-cp-manage" href="<?php echo $editUrl; ?>">
                    <i class="fas fa-toggle-on" aria-hidden="true"></i>
                    <?php echo __('company_permissions_manage'); ?>
                </a>
                <?php } ?>
            </div>

            <div class="rateb-cp-meter" aria-label="<?php echo Rateb\App\Core\View::escape(__('company_permissions_modules_count')); ?>">
                <div class="rateb-cp-meter-meta">
                    <strong><?php echo sprintf(__('company_permissions_count_of'), $modCount, $modTotal); ?></strong>
                    <span><?php echo $pct; ?>%</span>
                </div>
                <div class="rateb-cp-meter-track" role="progressbar" aria-valuenow="<?php echo $pct; ?>" aria-valuemin="0" aria-valuemax="100">
                    <span class="rateb-cp-meter-fill" style="width: <?php echo $pct; ?>%"></span>
                </div>
            </div>

            <div class="rateb-cp-chips">
                <?php if ($showLabels === []) { ?>
                <span class="rateb-cp-chip is-empty"><?php echo __('company_permissions_none'); ?></span>
                <?php } else {
                    foreach ($showLabels as $label) { ?>
                <span class="rateb-cp-chip"><?php echo Rateb\App\Core\View::escape((string) $label); ?></span>
                <?php }
                    if ($extraLabels > 0) { ?>
                <span class="rateb-cp-chip is-more">+<?php echo $extraLabels; ?></span>
                <?php }
                } ?>
            </div>
        </article>
        <?php } ?>
    </div>
    <?php } ?>
</section>

<?php Rateb\App\Core\View::partial('pagination', [
    'page' => $page ?? 1,
    'total' => $total ?? 0,
    'limit' => $limit ?? rateb_list_per_page(),
    'routePrefix' => $routePrefix ?? '',
    'preserveQuery' => array_filter(['q' => $search ?? '']),
]); ?>
