<?php use Rateb\App\Services\CmsService; ?>
<section class="rateb-career-section">
    <div class="container rateb-career-portal">
        <h1><?php echo __('applications') ?: 'Applications'; ?></h1>
        <table class="rateb-career-table">
            <thead><tr><th><?php echo __('job') ?: 'Job'; ?></th><th><?php echo __('status') ?: 'Status'; ?></th><th><?php echo __('date') ?: 'Date'; ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($applications ?? [] as $app) { ?>
            <tr>
                <td><a href="<?php echo rateb_url('site/careers/job/' . rawurlencode((string) ($app['slug'] ?? ''))); ?>"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($app, 'title')); ?></a></td>
                <td><span class="rateb-career-status rateb-career-status--<?php echo Rateb\App\Core\View::escape((string) ($app['status'] ?? '')); ?>"><?php echo Rateb\App\Core\View::escape((string) ($app['status'] ?? '')); ?></span></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($app['created_at'] ?? '')); ?></td>
                <td>
                    <?php if ((string) ($app['status'] ?? '') === 'submitted') { ?>
                    <form method="post" action="<?php echo rateb_url('site/candidate/withdraw/' . (int) ($app['id'] ?? 0)); ?>" class="rateb-career-inline-form">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
                        <button type="submit" class="rateb-career-btn rateb-career-btn--ghost"><?php echo __('withdraw') ?: 'Withdraw'; ?></button>
                    </form>
                    <?php } ?>
                </td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</section>
