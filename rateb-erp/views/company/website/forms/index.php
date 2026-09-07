<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $forms */
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(rateb_asset('css/website-builder.css'), ENT_QUOTES, 'UTF-8'); ?>">
<div class="container-fluid py-3 wb-admin">
    <div class="d-flex justify-content-between mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('website_forms')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <a class="btn btn-primary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/forms/create')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('website_create_form'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <table class="table table-striped">
        <thead><tr><th><?php echo htmlspecialchars(__('slug'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></th><th>CRM</th><th></th></tr></thead>
        <tbody>
        <?php foreach (($forms ?? []) as $f) { ?>
            <tr>
                <td><?php echo htmlspecialchars((string) ($f['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars(rateb_website_pick($f, 'name_en', 'name_ar'), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo !empty($f['crm_enabled']) ? htmlspecialchars(__('website_yes'), ENT_QUOTES, 'UTF-8') : htmlspecialchars(__('website_no'), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/forms/' . (int) $f['id'] . '/edit')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('edit'), ENT_QUOTES, 'UTF-8'); ?></a></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div>
