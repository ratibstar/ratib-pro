<?php use Rateb\App\Services\CmsService; ?>
<section class="rateb-mkt-page-hero"><div class="container"><h1><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1></div></section>
<section class="rateb-mkt-section"><div class="container col-lg-6 mx-auto">
<ul class="list-group rateb-mkt-status-list">
<?php foreach ($statusItems ?? [] as $item) {
    $status = (string) ($item['status'] ?? 'operational');
    ?>
<li class="list-group-item d-flex justify-content-between align-items-center">
<span><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($item, 'component')); ?></span>
<span class="badge bg-<?php echo $status === 'operational' ? 'success' : ($status === 'degraded' ? 'warning' : 'danger'); ?>"><?php echo Rateb\App\Core\View::escape($status); ?></span>
</li>
<?php } ?>
</ul>
</div></section>
