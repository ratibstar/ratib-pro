<?php
$page = (int) ($page ?? 1);
$total = (int) ($total ?? 0);
$limit = (int) ($limit ?? 20);
$pages = $limit > 0 ? (int) ceil($total / $limit) : 1;
$routePrefix = $routePrefix ?? '';
?>
<?php if ($pages > 1) { ?>
<nav class="rateb-pagination" aria-label="Pagination">
    <ul class="pagination pagination-sm mb-0">
        <?php for ($i = 1; $i <= $pages; $i++) { ?>
        <li class="page-item<?php echo $i === $page ? ' active' : ''; ?>">
            <a class="page-link" href="<?php echo rateb_list_url($routePrefix, array_merge(rateb_list_query_except([]), ['page' => $i])); ?>"><?php echo $i; ?></a>
        </li>
        <?php } ?>
    </ul>
</nav>
<?php } ?>
