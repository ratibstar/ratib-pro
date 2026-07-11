<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('eam_maintenance')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="row g-3">
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('eam_plans'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="table-responsive border rounded mb-3">
                <table class="table mb-0 align-middle">
                    <thead><tr><th><?php echo htmlspecialchars(__('record_id'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('due_date'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach (($plans ?? []) as $p): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) ($p['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($p['next_due_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (($plans ?? []) === []): ?><tr><td colspan="3" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($canCreate)): ?>
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eam/maintenance/plans')), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="mb-2"><input class="form-control" name="name" required placeholder="<?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                <div class="mb-2"><input class="form-control" name="asset_id" type="number" placeholder="Asset ID"></div>
                <div class="mb-2"><input class="form-control" name="next_due_date" type="date"></div>
                <button class="btn btn-primary btn-sm" type="submit"><?php echo htmlspecialchars(__('add'), ENT_QUOTES, 'UTF-8'); ?></button>
            </form>
            <?php endif; ?>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('eam_requests'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="table-responsive border rounded">
                <table class="table mb-0 align-middle">
                    <thead><tr><th><?php echo htmlspecialchars(__('record_id'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach (($requests ?? []) as $r): ?>
                        <tr>
                            <td><a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eam/requests') . '/' . (int) $r['id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($r['request_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></td>
                            <td><?php echo htmlspecialchars((string) ($r['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($r['workflow_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (($requests ?? []) === []): ?><tr><td colspan="3" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
