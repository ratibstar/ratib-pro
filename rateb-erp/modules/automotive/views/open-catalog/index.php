<?php
declare(strict_types=1);
use Rateb\App\Core\View;
$release = $release ?? [];
$kinds = $kinds ?? [];
$total = 0;
foreach ($kinds as $kind) {
    $total += (int) $kind['models'];
}
?>
<h1 class="h4 mb-3"><?php echo View::escape((string) ($title ?? '')); ?></h1>
<?php if (!empty($missing_release)): ?><p class="text-muted"><?php echo View::escape(__('auto_open_no_release')); ?></p><?php return; ?><?php endif; ?>
<p><?php echo View::escape((string) ($release['provider_code'] ?? '') . ' ' . (string) ($release['version_label'] ?? '')); ?></p>
<p><?php echo View::escape(__('auto_open_retrieved')); ?>: <?php echo View::escape((string) ($release['retrieved_on'] ?? '')); ?></p>
<p><?php echo View::escape((string) ($release['attribution'] ?? '')); ?></p>
<p><?php echo View::escape((string) $total); ?></p>
<p><a href="<?php echo rateb_app_url('automotive/open-catalog/types'); ?>"><?php echo View::escape(__('auto_open_types')); ?></a></p>
