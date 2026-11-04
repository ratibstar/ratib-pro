<?php
declare(strict_types=1);
use Rateb\App\Core\View;
$detail = $detail ?? [];
$model = $detail['model'] ?? [];
$release = $detail['release'] ?? [];
$dir = function_exists('rateb_is_rtl') && rateb_is_rtl() ? 'rtl' : 'ltr';
?>
<div dir="<?php echo View::escape($dir); ?>">
    <h1 class="h4 mb-3"><?php echo View::escape((string) ($model['name_en'] ?? '')); ?></h1>
    <p class="text-muted"><?php echo View::escape(__('auto_open_note')); ?></p>
    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table mb-0">
                <tbody>
                    <tr><th><?php echo View::escape(__('auto_open_types')); ?></th><td><?php echo View::escape(__('auto_open_kind_' . (string) ($model['kind'] ?? 'car'))); ?></td></tr>
                    <tr><th><?php echo View::escape(__('auto_open_slug')); ?></th><td><?php echo View::escape((string) ($model['make_slug'] ?? '') . ' / ' . (string) ($model['model_slug'] ?? '')); ?></td></tr>
                    <tr><th><?php echo View::escape(__('auto_open_name')); ?></th><td><?php echo View::escape((string) ($model['name_en'] ?? '')); ?></td></tr>
                    <tr><th><?php echo View::escape(__('auto_open_source')); ?></th><td><?php echo View::escape((string) ($model['source_record_id'] ?? '')); ?></td></tr>
                    <tr><th><?php echo View::escape(__('auto_open_retrieved')); ?></th><td><?php echo View::escape((string) ($release['provider_code'] ?? '') . ' ' . (string) ($release['version_label'] ?? '') . ' — ' . (string) ($release['retrieved_on'] ?? '')); ?></td></tr>
                    <tr><th><?php echo View::escape(__('auto_open_sources')); ?></th><td><?php echo View::escape((string) ($release['attribution'] ?? '')); ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6"><?php echo View::escape(__('auto_open_sources')); ?></h2>
                <ul class="mb-0"><?php foreach (($detail['sources'] ?? []) as $source): ?><li><?php echo View::escape((string) ($source['origin_code'] ?? '')); ?><?php if ((string) ($source['license_code'] ?? '') !== ''): ?> — <?php echo View::escape((string) $source['license_code']); ?><?php endif; ?></li><?php endforeach; ?></ul>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6"><?php echo View::escape(__('auto_open_license')); ?></h2>
                <ul class="mb-0"><?php foreach (($detail['licenses'] ?? []) as $license): ?><li><?php echo View::escape((string) $license); ?></li><?php endforeach; ?></ul>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6"><?php echo View::escape(__('auto_open_market')); ?></h2>
                <ul class="mb-0"><?php foreach (($detail['markets'] ?? []) as $market): ?><li><?php echo View::escape((string) $market['market_code'] . ' — ' . (string) $market['origin_code'] . ' — ' . (string) $market['evidence']); ?></li><?php endforeach; ?></ul>
            </div></div>
        </div>
    </div>
    <?php if (!empty($can_select)): ?>
    <form class="mt-3" method="post" action="<?php echo rateb_app_url('automotive/open-catalog/select'); ?>">
        <?php echo rateb_csrf_field(); ?>
        <input type="hidden" name="model_id" value="<?php echo (int) ($model['id'] ?? 0); ?>">
        <button class="btn btn-primary" type="submit"><?php echo View::escape(__('auto_open_select')); ?></button>
    </form>
    <?php endif; ?>
</div>
