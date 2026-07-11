<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('project_milestones')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="get" class="mb-3" style="max-width:360px">
        <select name="project_id" class="form-select" onchange="this.form.submit()">
            <option value=""><?php echo htmlspecialchars(__('select_project'), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php foreach (($projects ?? []) as $p): ?>
            <option value="<?php echo (int) $p['id']; ?>" <?php echo ((int) ($project_id ?? 0) === (int) $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php if (!empty($canCreate) && (int) ($project_id ?? 0) > 0): ?>
    <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('projects/milestones')), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 mb-3">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="project_id" value="<?php echo (int) $project_id; ?>">
        <div class="row g-2">
            <div class="col-md-8"><input class="form-control" name="name" required placeholder="<?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div class="col-md-4"><input type="date" class="form-control" name="due_date"></div>
        </div>
        <button class="btn btn-primary btn-sm mt-2" type="submit"><?php echo htmlspecialchars(__('add'), ENT_QUOTES, 'UTF-8'); ?></button>
    </form>
    <?php endif; ?>
    <ul class="list-group">
        <?php foreach (($items ?? []) as $row): ?>
        <li class="list-group-item d-flex justify-content-between"><span><?php echo htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span><span class="text-muted"><?php echo htmlspecialchars((string) ($row['due_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></li>
        <?php endforeach; ?>
        <?php if (($items ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
    </ul>
</div>
