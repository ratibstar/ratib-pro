<?php
$pageKey = (string) ($pageKey ?? 'page');
$perPageKey = (string) ($perPageKey ?? 'per_page');
$page = max(1, (int) ($page ?? 1));
$total = max(0, (int) ($total ?? 0));
$limit = max(1, (int) ($limit ?? rateb_list_per_page()));
$pages = $limit > 0 ? max(1, (int) ceil($total / $limit)) : 1;
$routePrefix = (string) ($routePrefix ?? '');
$baseUrl = (string) ($baseUrl ?? '');
$pageUrl = static function (array $query) use ($routePrefix, $baseUrl): string {
    if ($baseUrl !== '') {
        return rateb_url_query($baseUrl, $query);
    }
    return rateb_list_url($routePrefix, $query);
};
$perPageOptions = $perPageOptions ?? (function_exists('rateb_list_per_page_options') ? rateb_list_per_page_options() : [5, 10, 25, 50, 100]);
$from = $total > 0 ? (($page - 1) * $limit) + 1 : 0;
$to = $total > 0 ? min($total, $page * $limit) : 0;
$preserveQuery = is_array($preserveQuery ?? null) ? $preserveQuery : [];
$queryBase = $preserveQuery !== [] ? $preserveQuery : rateb_list_query_except([$pageKey, $perPageKey]);
?>
<?php if ($total > 0 || $pages > 1) { ?>
<div class="rateb-pagination-bar d-flex flex-wrap align-items-center justify-content-between gap-2 px-2 py-2">
    <div class="rateb-pagination-meta text-muted small">
        <?php if ($total > 0) { ?>
        <?php echo __('pagination_showing', ['from' => $from, 'to' => $to, 'total' => $total]); ?>
        <?php } ?>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="rateb-per-page d-flex align-items-center gap-1">
            <span class="text-muted small"><?php echo __('per_page'); ?>:</span>
            <?php foreach ($perPageOptions as $opt) {
                $opt = (int) $opt;
                $active = $opt === $limit;
                $href = $pageUrl(array_merge($queryBase, [$perPageKey => $opt, $pageKey => 1]));
                ?>
            <a href="<?php echo Rateb\App\Core\View::escape($href); ?>"
               data-rateb-full-nav="1"
               class="btn btn-sm <?php echo $active ? 'btn-primary' : 'btn-outline-secondary'; ?>"><?php echo $opt; ?></a>
            <?php } ?>
        </div>
        <?php if ($pages > 1) { ?>
        <nav class="rateb-pagination" aria-label="Pagination">
            <ul class="pagination pagination-sm mb-0">
                <?php
                $prevDisabled = $page <= 1;
                $nextDisabled = $page >= $pages;
                $prevHref = $prevDisabled ? '#' : $pageUrl(array_merge($queryBase, [$perPageKey => $limit, $pageKey => $page - 1]));
                $nextHref = $nextDisabled ? '#' : $pageUrl(array_merge($queryBase, [$perPageKey => $limit, $pageKey => $page + 1]));
                ?>
                <li class="page-item<?php echo $prevDisabled ? ' disabled' : ''; ?>">
                    <a class="page-link" data-rateb-full-nav="1" href="<?php echo Rateb\App\Core\View::escape($prevHref); ?>"<?php echo $prevDisabled ? ' tabindex="-1" aria-disabled="true"' : ''; ?> aria-label="<?php echo Rateb\App\Core\View::escape(rateb_locale() === 'en' ? 'Previous' : 'السابق'); ?>">‹</a>
                </li>
                <li class="page-item disabled">
                    <span class="page-link"><?php echo (int) $page; ?> / <?php echo (int) $pages; ?></span>
                </li>
                <li class="page-item<?php echo $nextDisabled ? ' disabled' : ''; ?>">
                    <a class="page-link" data-rateb-full-nav="1" href="<?php echo Rateb\App\Core\View::escape($nextHref); ?>"<?php echo $nextDisabled ? ' tabindex="-1" aria-disabled="true"' : ''; ?> aria-label="<?php echo Rateb\App\Core\View::escape(__('next')); ?>">›</a>
                </li>
            </ul>
        </nav>
        <?php } ?>
    </div>
</div>
<?php } ?>
