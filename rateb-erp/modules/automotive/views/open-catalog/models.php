<?php
declare(strict_types=1);
use Rateb\App\Core\View;
$page = max(1, (int) ($page ?? 1));
$pages = max(1, (int) ceil(((int) ($total ?? 0)) / 25));
$kind = (string) ($kind ?? '');
$make = (string) ($make ?? '');
$market = (string) ($market ?? '');
$q = (string) ($q ?? '');
$base = rateb_app_url('automotive/open-catalog/models');
$link = static function (int $target) use ($base, $kind, $make, $market, $q): string {
    return $base . '?kind=' . rawurlencode($kind) . '&make=' . rawurlencode($make) . '&market=' . rawurlencode($market) . '&q=' . rawurlencode($q) . '&page=' . $target;
};
?>
<h1 class="h4 mb-3"><?php echo View::escape((string) ($title ?? '')); ?></h1>
<?php if (!empty($missing_release)): ?><p class="text-muted"><?php echo View::escape(__('auto_open_no_release')); ?></p><?php return; ?><?php endif; ?>
<form method="get" action="<?php echo $base; ?>" class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2">
        <input type="hidden" name="kind" value="<?php echo View::escape($kind); ?>">
        <input type="hidden" name="make" value="<?php echo View::escape($make); ?>">
        <input class="form-control" style="max-width: 16rem" name="q" value="<?php echo View::escape($q); ?>" placeholder="<?php echo View::escape(__('auto_open_name')); ?>">
        <input class="form-control" style="max-width: 8rem" name="market" value="<?php echo View::escape($market); ?>" placeholder="<?php echo View::escape(__('auto_open_market')); ?>">
        <button class="btn btn-primary" type="submit"><?php echo View::escape(__('auto_epc_search')); ?></button>
    </div>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th><?php echo View::escape(__('auto_open_slug')); ?></th>
                    <th><?php echo View::escape(__('auto_open_name')); ?></th>
                    <th><?php echo View::escape(__('auto_open_source')); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach (($rows ?? []) as $row): ?>
                <tr>
                    <td><?php echo View::escape((string) $row['make_slug'] . ' / ' . (string) $row['model_slug']); ?></td>
                    <td><a href="<?php echo rateb_app_url('automotive/open-catalog/models/' . (int) $row['id']); ?>"><?php echo View::escape((string) $row['name_en']); ?></a></td>
                    <td><?php echo View::escape((string) $row['source_record_id']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if (($rows ?? []) === []): ?><p class="text-muted mt-3"><?php echo View::escape(__('auto_open_empty')); ?></p><?php endif; ?>
<div class="d-flex justify-content-between align-items-center mt-3">
    <?php if ($page > 1): ?><a class="btn btn-outline-secondary btn-sm" href="<?php echo View::escape($link($page - 1)); ?>"><?php echo View::escape(__('auto_open_prev')); ?></a><?php else: ?><span></span><?php endif; ?>
    <span><?php echo View::escape(__('auto_open_page')); ?> <?php echo $page; ?> / <?php echo $pages; ?></span>
    <?php if ($page < $pages): ?><a class="btn btn-outline-secondary btn-sm" href="<?php echo View::escape($link($page + 1)); ?>"><?php echo View::escape(__('auto_open_next')); ?></a><?php else: ?><span></span><?php endif; ?>
</div>
