<?php
declare(strict_types=1);
/** @var array<string,mixed> $item */
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><?php echo htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="text-muted"><?php echo htmlspecialchars((string) ($item['project_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars((string) ($item['workflow_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('projects') . '/' . (int) $item['id'] . '/edit'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('edit'), ENT_QUOTES, 'UTF-8'); ?></a>
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('projects/tasks/kanban') . '?project_id=' . (int) $item['id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('project_kanban'), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-lg-8">
            <h2 class="h5"><?php echo htmlspecialchars(__('project_tasks'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="table-responsive border rounded mb-3">
                <table class="table mb-0 align-middle">
                    <thead><tr><th><?php echo htmlspecialchars(__('record_id'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach (($tasks ?? []) as $t): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) ($t['task_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($t['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($t['workflow_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (($tasks ?? []) === []): ?><tr><td colspan="3" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($canTasks)): ?>
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('projects/tasks')), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 mb-3">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="project_id" value="<?php echo (int) $item['id']; ?>">
                <div class="row g-2 align-items-end">
                    <div class="col-md-8"><label class="form-label"><?php echo htmlspecialchars(__('project_task_title'), ENT_QUOTES, 'UTF-8'); ?></label><input class="form-control" name="title" required></div>
                    <div class="col-md-4"><button class="btn btn-primary w-100" type="submit"><?php echo htmlspecialchars(__('add'), ENT_QUOTES, 'UTF-8'); ?></button></div>
                </div>
            </form>
            <?php endif; ?>
            <h2 class="h5"><?php echo htmlspecialchars(__('project_comments'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group mb-3">
                <?php foreach (($comments ?? []) as $c): ?>
                    <li class="list-group-item"><div><?php echo nl2br(htmlspecialchars((string) ($c['body'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></div><div class="small text-muted"><?php echo htmlspecialchars((string) ($c['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></li>
                <?php endforeach; ?>
            </ul>
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('projects') . '/' . (int) $item['id'] . '/comments'), ENT_QUOTES, 'UTF-8'); ?>" class="mb-3">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <textarea name="body" class="form-control mb-2" rows="2" required></textarea>
                <button class="btn btn-outline-primary btn-sm" type="submit"><?php echo htmlspecialchars(__('add_comment'), ENT_QUOTES, 'UTF-8'); ?></button>
            </form>
        </div>
        <div class="col-lg-4">
            <?php if (!empty($canWorkflow) && ($transitions ?? []) !== []): ?>
            <div class="border rounded p-3 mb-3">
                <h2 class="h6"><?php echo htmlspecialchars(__('workflow'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('projects') . '/' . (int) $item['id'] . '/transition'), ENT_QUOTES, 'UTF-8'); ?>">
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
                <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('projects') . '/' . (int) $item['id'] . '/assign'), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="number" name="assignee_user_id" class="form-control mb-2" placeholder="User ID" required>
                    <button class="btn btn-outline-primary btn-sm" type="submit"><?php echo htmlspecialchars(__('assign'), ENT_QUOTES, 'UTF-8'); ?></button>
                </form>
            </div>
            <?php endif; ?>
            <h2 class="h6"><?php echo htmlspecialchars(__('project_timeline'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group list-group-flush border rounded">
                <?php foreach (($timeline ?? []) as $ev): ?>
                    <li class="list-group-item"><div class="fw-semibold"><?php echo htmlspecialchars((string) ($ev['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div><div class="small text-muted"><?php echo htmlspecialchars((string) ($ev['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
