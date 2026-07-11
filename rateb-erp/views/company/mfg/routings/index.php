<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('mfg_routings')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <?php if (!empty($canCreate)): ?>
    <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('mfg/routings')), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 col-lg-8 mb-4">
        <?php if (function_exists('csrf_field')): ?>
            <?php echo csrf_field(); ?>
        <?php elseif (isset($csrf)): ?>
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8'); ?>">
        <?php else: ?>
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        <?php endif; ?>
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label"><?php echo htmlspecialchars(__('code'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="text" name="code" class="form-control">
            </div>
            <div class="col-md-5">
                <label class="form-label"><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="text" name="name" required class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">product_id</label>
                <input type="number" name="product_id" class="form-control" min="1">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
        </div>
    </form>
    <?php endif; ?>
    <form method="get" class="row g-2 mb-3">
        <div class="col-md-4"><input type="search" name="q" value="<?php echo htmlspecialchars((string) ($q ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="<?php echo htmlspecialchars(__('search'), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value=""><?php echo htmlspecialchars(__('all'), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php foreach (($statuses ?? []) as $st): ?>
                <option value="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($status ?? '') === $st) ? 'selected' : ''; ?>><?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-outline-secondary w-100" type="submit"><?php echo htmlspecialchars(__('filter'), ENT_QUOTES, 'UTF-8'); ?></button></div>
    </form>
    <div class="table-responsive border rounded">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?php echo htmlspecialchars(__('code'), ENT_QUOTES, 'UTF-8'); ?></th>
                    <th><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></th>
                    <th><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach (($items ?? []) as $row): ?>
                <tr>
                    <td><a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('mfg/routings') . '/' . (int) $row['id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($row['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></td>
                    <td><?php echo htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><span class="badge text-bg-light"><?php echo htmlspecialchars((string) ($row['workflow_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($items ?? []) === []): ?><tr><td colspan="3" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
