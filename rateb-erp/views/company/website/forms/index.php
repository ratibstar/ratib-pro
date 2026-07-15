<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $forms */
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(rateb_asset('css/website-builder.css'), ENT_QUOTES, 'UTF-8'); ?>">
<div class="container-fluid py-3 wb-admin">
    <div class="d-flex justify-content-between mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? 'Forms'), ENT_QUOTES, 'UTF-8'); ?></h1>
        <a class="btn btn-primary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/forms/create')), ENT_QUOTES, 'UTF-8'); ?>">Create form</a>
    </div>
    <table class="table table-striped">
        <thead><tr><th>Slug</th><th>Name</th><th>CRM</th><th></th></tr></thead>
        <tbody>
        <?php foreach (($forms ?? []) as $f) { ?>
            <tr>
                <td><?php echo htmlspecialchars((string) ($f['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) ($f['name_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo !empty($f['crm_enabled']) ? 'Yes' : 'No'; ?></td>
                <td><a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/forms/' . (int) $f['id'] . '/edit')), ENT_QUOTES, 'UTF-8'); ?>">Edit</a></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div>
