<?php
declare(strict_types=1);
/** @var array<string,mixed> $item */
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><?php echo htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="text-muted"><?php echo htmlspecialchars((string) ($item['asset_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars((string) ($item['workflow_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eam/assets') . '/' . (int) $item['id'] . '/edit'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('edit'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="row g-3">
        <div class="col-lg-8">
            <h2 class="h5"><?php echo htmlspecialchars(__('eam_assignments'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="table-responsive border rounded mb-3">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>User</th><th><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('date'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach (($assignments ?? []) as $a): ?>
                        <tr>
                            <td><?php echo (int) ($a['assignee_user_id'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars((string) ($a['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($a['assigned_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (($assignments ?? []) === []): ?><tr><td colspan="3" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <h2 class="h5"><?php echo htmlspecialchars(__('comments'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group mb-3">
                <?php foreach (($comments ?? []) as $c): ?>
                    <li class="list-group-item"><div><?php echo nl2br(htmlspecialchars((string) ($c['body'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></div><div class="small text-muted"><?php echo htmlspecialchars((string) ($c['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></li>
                <?php endforeach; ?>
            </ul>
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eam/assets') . '/' . (int) $item['id'] . '/comments'), ENT_QUOTES, 'UTF-8'); ?>" class="mb-3">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <textarea name="body" class="form-control mb-2" rows="2" required></textarea>
                <button class="btn btn-outline-primary btn-sm" type="submit"><?php echo htmlspecialchars(__('add_comment'), ENT_QUOTES, 'UTF-8'); ?></button>
            </form>
        </div>
        <div class="col-lg-4">
            <?php if (!empty($canWorkflow) && ($transitions ?? []) !== []): ?>
            <div class="border rounded p-3 mb-3">
                <h2 class="h6"><?php echo htmlspecialchars(__('workflow'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eam/assets') . '/' . (int) $item['id'] . '/transition'), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="expected_version" value="<?php echo (int) ($item['version'] ?? 1); ?>">
                    <select name="to_status" class="form-select mb-2">
                        <?php foreach (($transitions ?? []) as $tr): ?>
                        <option value="<?php echo htmlspecialchars($tr, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($tr, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="reason" class="form-control mb-2" placeholder="<?php echo htmlspecialchars(__('reason'), ENT_QUOTES, 'UTF-8'); ?>">
                    <button class="btn btn-primary btn-sm" type="submit"><?php echo htmlspecialchars(__('apply'), ENT_QUOTES, 'UTF-8'); ?></button>
                </form>
            </div>
            <?php endif; ?>
            <?php if (!empty($canAssign)): ?>
            <div class="border rounded p-3 mb-3">
                <h2 class="h6"><?php echo htmlspecialchars(__('assign'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eam/assets') . '/' . (int) $item['id'] . '/assign'), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="number" name="assignee_user_id" class="form-control mb-2" placeholder="User ID" required>
                    <button class="btn btn-outline-primary btn-sm" type="submit"><?php echo htmlspecialchars(__('assign'), ENT_QUOTES, 'UTF-8'); ?></button>
                </form>
            </div>
            <?php endif; ?>
            <?php if (!empty($canTransfer)): ?>
            <div class="border rounded p-3 mb-3">
                <h2 class="h6"><?php echo htmlspecialchars(__('eam_transfer'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eam/assets') . '/' . (int) $item['id'] . '/transfer'), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="number" name="to_location_id" class="form-control mb-2" placeholder="Location ID" required>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="complete_now" value="1" id="complete_now" checked>
                        <label class="form-check-label" for="complete_now"><?php echo htmlspecialchars(__('completed'), ENT_QUOTES, 'UTF-8'); ?></label>
                    </div>
                    <button class="btn btn-outline-primary btn-sm" type="submit"><?php echo htmlspecialchars(__('eam_transfer'), ENT_QUOTES, 'UTF-8'); ?></button>
                </form>
            </div>
            <?php endif; ?>
            <h2 class="h6"><?php echo htmlspecialchars(__('eam_timeline'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group list-group-flush border rounded">
                <?php foreach (($timeline ?? []) as $ev): ?>
                    <li class="list-group-item"><div class="fw-semibold"><?php echo htmlspecialchars((string) ($ev['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div><div class="small text-muted"><?php echo htmlspecialchars((string) ($ev['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
