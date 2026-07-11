<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('approval_chains')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <?php if (!empty($canCreate)): ?>
    <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('approvals/chains')), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 mb-3">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="row g-2">
            <div class="col-md-4"><input class="form-control" name="name" required placeholder="<?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div class="col-md-4">
                <select name="template_id" class="form-select" required>
                    <option value=""><?php echo htmlspecialchars(__('approval_templates'), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php foreach (($templates ?? []) as $t): ?>
                    <option value="<?php echo (int) $t['id']; ?>"><?php echo htmlspecialchars((string) ($t['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4"><button class="btn btn-primary w-100" type="submit"><?php echo htmlspecialchars(__('add'), ENT_QUOTES, 'UTF-8'); ?></button></div>
        </div>
    </form>
    <?php endif; ?>
    <div class="table-responsive border rounded">
        <table class="table align-middle mb-0">
            <thead><tr><th><?php echo htmlspecialchars(__('record_id'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead>
            <tbody>
            <?php foreach (($items ?? []) as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string) ($row['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($items ?? []) === []): ?><tr><td colspan="2" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
