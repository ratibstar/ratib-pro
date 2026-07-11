<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('project_issues')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="get" class="mb-3" style="max-width:360px">
        <select name="project_id" class="form-select" onchange="this.form.submit()">
            <option value=""><?php echo htmlspecialchars(__('select_project'), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php foreach (($projects ?? []) as $p): ?>
            <option value="<?php echo (int) $p['id']; ?>" <?php echo ((int) ($project_id ?? 0) === (int) $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php if (!empty($canCreate) && (int) ($project_id ?? 0) > 0): ?>
    <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('projects/issues')), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 mb-3">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="project_id" value="<?php echo (int) $project_id; ?>">
        <input class="form-control mb-2" name="title" required placeholder="<?php echo htmlspecialchars(__('title'), ENT_QUOTES, 'UTF-8'); ?>">
        <button class="btn btn-primary btn-sm" type="submit"><?php echo htmlspecialchars(__('add'), ENT_QUOTES, 'UTF-8'); ?></button>
    </form>
    <?php endif; ?>
    <div class="table-responsive border rounded">
        <table class="table mb-0">
            <thead><tr><th><?php echo htmlspecialchars(__('record_id'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('title'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead>
            <tbody>
            <?php foreach (($items ?? []) as $row): ?>
                <tr><td><?php echo htmlspecialchars((string) ($row['issue_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <?php endforeach; ?>
            <?php if (($items ?? []) === []): ?><tr><td colspan="3" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
