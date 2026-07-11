<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $items */
/** @var list<array<string,mixed>> $projects */
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('project_tasks')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('projects/tasks/kanban')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('project_kanban'), ENT_QUOTES, 'UTF-8'); ?></a>
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('projects/tasks/gantt')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('project_gantt'), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
    </div>
    <form method="get" class="row g-2 mb-3">
        <div class="col-md-4">
            <select name="project_id" class="form-select" onchange="this.form.submit()">
                <option value=""><?php echo htmlspecialchars(__('all_projects'), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php foreach (($projects ?? []) as $p): ?>
                <option value="<?php echo (int) $p['id']; ?>" <?php echo ((int) ($project_id ?? 0) === (int) $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
    <?php if (!empty($canCreate)): ?>
    <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('projects/tasks')), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 mb-3">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="row g-2">
            <div class="col-md-4">
                <select name="project_id" class="form-select" required>
                    <option value=""><?php echo htmlspecialchars(__('project'), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php foreach (($projects ?? []) as $p): ?>
                    <option value="<?php echo (int) $p['id']; ?>"><?php echo htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5"><input class="form-control" name="title" required placeholder="<?php echo htmlspecialchars(__('project_task_title'), ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div class="col-md-3"><button class="btn btn-primary w-100" type="submit"><?php echo htmlspecialchars(__('add'), ENT_QUOTES, 'UTF-8'); ?></button></div>
        </div>
    </form>
    <?php endif; ?>
    <div class="table-responsive border rounded">
        <table class="table mb-0 align-middle">
            <thead><tr><th><?php echo htmlspecialchars(__('record_id'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('due_date'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead>
            <tbody>
            <?php foreach (($items ?? []) as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string) ($row['task_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?><?php if (!empty($row['parent_task_id'])): ?> <span class="badge text-bg-light"><?php echo htmlspecialchars(__('subtask'), ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['workflow_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['due_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($items ?? []) === []): ?><tr><td colspan="4" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
