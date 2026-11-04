<?php
declare(strict_types=1);
use Rateb\App\Core\View;
$page = max(1, (int) ($page ?? 1));
$pages = max(1, (int) ceil(((int) ($total ?? 0)) / 25));
$year = (string) ($year ?? '');
$make = (string) ($make ?? '');
$model = (string) ($model ?? '');
$fuel = (string) ($fuel ?? '');
$class = (string) ($class ?? '');
$base = rateb_app_url('automotive/us-catalog');
$link = static function (int $target) use ($base, $year, $make, $model, $fuel, $class): string {
    return $base . '?year=' . rawurlencode($year) . '&make=' . rawurlencode($make) . '&model=' . rawurlencode($model) . '&fuel=' . rawurlencode($fuel) . '&class=' . rawurlencode($class) . '&page=' . $target;
};
?>
<h1 class="h4 mb-2"><?php echo View::escape((string) ($title ?? '')); ?></h1>
<p class="text-muted"><?php echo View::escape(__('auto_us_scope')); ?></p>
<form method="get" action="<?php echo $base; ?>" class="card mb-3"><div class="card-body d-flex flex-wrap gap-2">
    <input class="form-control" style="max-width:6rem" name="year" value="<?php echo View::escape($year); ?>" placeholder="<?php echo View::escape(__('auto_us_year')); ?>">
    <input class="form-control" style="max-width:10rem" name="make" value="<?php echo View::escape($make); ?>" placeholder="<?php echo View::escape(__('auto_us_make')); ?>">
    <input class="form-control" style="max-width:12rem" name="model" value="<?php echo View::escape($model); ?>" placeholder="<?php echo View::escape(__('auto_us_model')); ?>">
    <input class="form-control" style="max-width:10rem" name="fuel" value="<?php echo View::escape($fuel); ?>" placeholder="<?php echo View::escape(__('auto_us_fuel')); ?>">
    <input class="form-control" style="max-width:10rem" name="class" value="<?php echo View::escape($class); ?>" placeholder="<?php echo View::escape(__('auto_us_class')); ?>">
    <button class="btn btn-primary" type="submit"><?php echo View::escape(__('auto_epc_search')); ?></button>
</div></form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
<thead><tr>
    <th><?php echo View::escape(__('auto_us_year')); ?></th>
    <th><?php echo View::escape(__('auto_us_make')); ?></th>
    <th><?php echo View::escape(__('auto_us_model')); ?></th>
    <th><?php echo View::escape(__('auto_us_fuel')); ?></th>
    <th><?php echo View::escape(__('auto_us_mpg')); ?></th>
</tr></thead>
<tbody>
<?php foreach (($rows ?? []) as $row): ?>
<tr>
    <td><?php echo (int) $row['model_year']; ?></td>
    <td><?php echo View::escape((string) $row['make_name']); ?></td>
    <td><a href="<?php echo rateb_app_url('automotive/us-catalog/' . (int) $row['id']); ?>"><?php echo View::escape((string) $row['model_name']); ?></a></td>
    <td><?php echo View::escape((string) $row['fuel_type1']); ?></td>
    <td><?php echo View::escape((string) $row['comb_mpg']); ?> MPG</td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
<div class="d-flex justify-content-between mt-3">
    <?php if ($page > 1): ?><a class="btn btn-sm btn-outline-secondary" href="<?php echo View::escape($link($page - 1)); ?>"><?php echo View::escape(__('auto_open_prev')); ?></a><?php else: ?><span></span><?php endif; ?>
    <span><?php echo View::escape(__('auto_open_page')); ?> <?php echo $page; ?> / <?php echo $pages; ?></span>
    <?php if ($page < $pages): ?><a class="btn btn-sm btn-outline-secondary" href="<?php echo View::escape($link($page + 1)); ?>"><?php echo View::escape(__('auto_open_next')); ?></a><?php endif; ?>
</div>
