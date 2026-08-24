<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $fileArticles */
/** @var list<array<string,mixed>> $dbArticles */
/** @var string $helpHomeUrl */
/** @var string $csrf */

use Rateb\App\Core\View;
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/help-center.css'); ?>">
<div class="hc-page hc-admin-page">
    <?php View::partial('help/breadcrumb', [
        'crumbs' => [
            ['label' => __('help_center'), 'url' => $helpHomeUrl],
            ['label' => __('help_admin_title'), 'url' => null],
        ],
    ]); ?>

    <header class="hc-module-hero">
        <span class="hc-module-hero__icon" aria-hidden="true"><i class="fas fa-pen-to-square"></i></span>
        <div>
            <h2><?php echo View::escape(__('help_admin_title')); ?></h2>
            <p><?php echo View::escape(__('help_admin_intro')); ?></p>
        </div>
    </header>

    <div class="hc-admin-stats">
        <div class="hc-panel"><strong><?php echo (int) ($moduleCount ?? 0); ?></strong><span><?php echo View::escape(__('help_modules_title')); ?></span></div>
        <div class="hc-panel"><strong><?php echo (int) ($articleCount ?? 0); ?></strong><span><?php echo View::escape(__('help_articles')); ?></span></div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a class="btn btn-primary btn-sm" href="<?php echo rateb_url('admin/help/manage/create'); ?>"><i class="fas fa-plus"></i> <?php echo View::escape(__('help_admin_create')); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo rateb_url('admin/help/manage/analytics'); ?>"><i class="fas fa-chart-line"></i> <?php echo View::escape(__('help_admin_analytics')); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo View::escape($helpHomeUrl); ?>"><?php echo View::escape(__('help_center')); ?></a>
    </div>

    <section class="hc-panel">
        <h3><?php echo View::escape(__('help_admin_db_articles')); ?></h3>
        <?php if ($dbArticles === []) { ?>
        <p class="text-muted mb-0"><?php echo View::escape(__('help_admin_db_empty')); ?></p>
        <?php } else { ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th><?php echo View::escape(__('help_admin_slug')); ?></th><th><?php echo View::escape(__('title')); ?></th><th><?php echo View::escape(__('status')); ?></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($dbArticles as $row) { ?>
                    <tr>
                        <td><code><?php echo View::escape((string) ($row['slug'] ?? '')); ?></code></td>
                        <td><?php echo View::escape((string) ($row['title_ar'] ?? $row['title_en'] ?? '')); ?></td>
                        <td><?php echo View::escape((string) ($row['status'] ?? '')); ?></td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?php echo rateb_url('admin/help/manage/edit/' . (int) ($row['id'] ?? 0)); ?>"><?php echo View::escape(__('edit')); ?></a>
                            <form method="post" action="<?php echo rateb_url('admin/help/manage/archive/' . (int) ($row['id'] ?? 0)); ?>" class="d-inline">
                                <input type="hidden" name="_csrf" value="<?php echo View::escape($csrf); ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit"><?php echo View::escape(__('help_admin_archive')); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } ?>
    </section>

    <section class="hc-panel">
        <h3><?php echo View::escape(__('help_admin_file_catalog')); ?></h3>
        <p class="text-muted"><?php echo View::escape(__('help_admin_file_note')); ?></p>
        <p class="mb-0"><strong><?php echo count($fileArticles ?? []); ?></strong> <?php echo View::escape(__('help_articles')); ?></p>
    </section>
</div>
