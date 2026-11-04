<?php
declare(strict_types=1);
use Rateb\App\Core\View;
?>
<h1 class="h4 mb-3"><?php echo View::escape((string) ($title ?? '')); ?></h1>
<?php if (!empty($missing_release)): ?><p class="text-muted"><?php echo View::escape(__('auto_open_no_release')); ?></p><?php return; ?><?php endif; ?>
<ul>
<?php foreach (($kinds ?? []) as $kind): ?>
<li><a href="<?php echo rateb_app_url('automotive/open-catalog/makes'); ?>?kind=<?php echo View::escape((string) $kind['kind']); ?>"><?php echo View::escape(__('auto_open_kind_' . $kind['kind'])); ?> (<?php echo (int) $kind['models']; ?>)</a></li>
<?php endforeach; ?>
</ul>
