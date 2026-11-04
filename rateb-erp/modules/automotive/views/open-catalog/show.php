<?php
declare(strict_types=1);
use Rateb\App\Core\View;
$detail = $detail ?? [];
$model = $detail['model'] ?? [];
$release = $detail['release'] ?? [];
?>
<h1 class="h4 mb-3"><?php echo View::escape((string) ($model['name_en'] ?? '')); ?></h1>
<p><?php echo View::escape(__('auto_open_kind_' . (string) ($model['kind'] ?? 'car'))); ?></p>
<p><?php echo View::escape((string) ($model['make_slug'] ?? '') . ' / ' . (string) ($model['model_slug'] ?? '')); ?></p>
<p><?php echo View::escape(__('auto_open_source')); ?>: <?php echo View::escape((string) ($model['source_record_id'] ?? '')); ?></p>
<p><?php echo View::escape((string) ($release['provider_code'] ?? '') . ' ' . (string) ($release['version_label'] ?? '')); ?></p>
<p><?php echo View::escape(__('auto_open_retrieved')); ?>: <?php echo View::escape((string) ($release['retrieved_on'] ?? '')); ?></p>
<p><?php echo View::escape((string) ($release['attribution'] ?? '')); ?></p>
<h2 class="h6"><?php echo View::escape(__('auto_open_license')); ?></h2>
<ul><?php foreach (($detail['licenses'] ?? []) as $license): ?><li><?php echo View::escape((string) $license); ?></li><?php endforeach; ?></ul>
<h2 class="h6"><?php echo View::escape(__('auto_open_market')); ?></h2>
<ul><?php foreach (($detail['markets'] ?? []) as $market): ?><li><?php echo View::escape((string) $market['market_code'] . ' ' . (string) $market['origin_code'] . ' ' . (string) $market['evidence']); ?></li><?php endforeach; ?></ul>
<?php if (!empty($can_select)): ?>
<form method="post" action="<?php echo rateb_app_url('automotive/open-catalog/select'); ?>">
    <?php echo rateb_csrf_field(); ?>
    <input type="hidden" name="model_id" value="<?php echo (int) ($model['id'] ?? 0); ?>">
    <button class="btn btn-primary" type="submit"><?php echo View::escape(__('auto_open_select')); ?></button>
</form>
<?php endif; ?>
