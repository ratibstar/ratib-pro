<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $pages */
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(rateb_asset('css/website-builder.css'), ENT_QUOTES, 'UTF-8'); ?>">
<div class="container-fluid py-3 wb-admin">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? 'Pages'), ENT_QUOTES, 'UTF-8'); ?></h1>
        <a class="btn btn-primary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/pages/create')), ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-plus"></i> <?php echo htmlspecialchars(__('create') ?: 'Create', ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead><tr><th>Slug</th><th>Title</th><th>Status</th><th>Template</th><th></th></tr></thead>
            <tbody>
            <?php foreach (($pages ?? []) as $p) { $id = (int) ($p['id'] ?? 0); ?>
                <tr>
                    <td><?php echo htmlspecialchars((string) ($p['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($p['title_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($p['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($p['template'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/builder') . '?page_id=' . $id), ENT_QUOTES, 'UTF-8'); ?>">Builder</a>
                        <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/pages/' . $id . '/edit')), ENT_QUOTES, 'UTF-8'); ?>">SEO</a>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
