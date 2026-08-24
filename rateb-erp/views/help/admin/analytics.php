<?php
declare(strict_types=1);

/** @var array<string,mixed> $report */
/** @var string $helpHomeUrl */
/** @var string $csrf */

use Rateb\App\Core\View;

$totals = is_array($report['totals'] ?? null) ? $report['totals'] : [];
$locales = is_array($report['locales'] ?? null) ? $report['locales'] : [];
$topQueries = is_array($report['top_queries'] ?? null) ? $report['top_queries'] : [];
$topArticles = is_array($report['top_articles'] ?? null) ? $report['top_articles'] : [];
$unanswered = is_array($report['unanswered'] ?? null) ? $report['unanswered'] : [];
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/help-center.css'); ?>">
<div class="hc-page">
    <?php View::partial('help/breadcrumb', [
        'crumbs' => [
            ['label' => __('help_center'), 'url' => $helpHomeUrl],
            ['label' => __('help_admin_title'), 'url' => rateb_url('admin/help/manage')],
            ['label' => __('help_admin_analytics'), 'url' => null],
        ],
    ]); ?>

    <div class="hc-admin-stats">
        <div class="hc-panel"><strong><?php echo (int) ($totals['asks'] ?? 0); ?></strong><span><?php echo View::escape(__('help_admin_asks')); ?></span></div>
        <div class="hc-panel"><strong><?php echo (int) ($totals['opens'] ?? 0); ?></strong><span><?php echo View::escape(__('help_admin_opens')); ?></span></div>
        <div class="hc-panel"><strong><?php echo (int) ($totals['unanswered'] ?? 0); ?></strong><span><?php echo View::escape(__('help_admin_unanswered')); ?></span></div>
        <div class="hc-panel"><strong>AR <?php echo (int) ($locales['ar'] ?? 0); ?> / EN <?php echo (int) ($locales['en'] ?? 0); ?></strong><span><?php echo View::escape(__('language')); ?></span></div>
    </div>

    <section class="hc-panel">
        <h3><?php echo View::escape(__('help_admin_unanswered_report')); ?></h3>
        <?php if ($unanswered === []) { ?>
        <p class="text-muted mb-0"><?php echo View::escape(__('help_admin_unanswered_empty')); ?></p>
        <?php } else { ?>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th><?php echo View::escape(__('help_admin_question')); ?></th><th><?php echo View::escape(__('help_admin_hits')); ?></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($unanswered as $row) { ?>
                    <tr>
                        <td><?php echo View::escape((string) ($row['question'] ?? '')); ?></td>
                        <td><?php echo (int) ($row['hit_count'] ?? 0); ?></td>
                        <td>
                            <form method="post" action="<?php echo rateb_url('admin/help/manage/unanswered/' . (int) ($row['id'] ?? 0)); ?>" class="d-inline">
                                <input type="hidden" name="_csrf" value="<?php echo View::escape($csrf); ?>">
                                <input type="hidden" name="status" value="resolved">
                                <button class="btn btn-sm btn-outline-success" type="submit"><?php echo View::escape(__('help_admin_resolve')); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } ?>
    </section>

    <div class="row g-3">
        <div class="col-md-6">
            <section class="hc-panel">
                <h3><?php echo View::escape(__('help_admin_top_queries')); ?></h3>
                <ul class="mb-0">
                    <?php foreach ($topQueries as $row) { ?>
                    <li><?php echo View::escape((string) ($row['query_text'] ?? '')); ?> <span class="text-muted">(<?php echo (int) ($row['c'] ?? 0); ?>)</span></li>
                    <?php } ?>
                </ul>
            </section>
        </div>
        <div class="col-md-6">
            <section class="hc-panel">
                <h3><?php echo View::escape(__('help_admin_top_articles')); ?></h3>
                <ul class="mb-0">
                    <?php foreach ($topArticles as $row) { ?>
                    <li><code><?php echo View::escape((string) ($row['article_slug'] ?? '')); ?></code> <span class="text-muted">(<?php echo (int) ($row['c'] ?? 0); ?>)</span></li>
                    <?php } ?>
                </ul>
            </section>
        </div>
    </div>
</div>
