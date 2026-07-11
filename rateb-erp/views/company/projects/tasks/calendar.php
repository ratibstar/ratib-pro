<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('project_calendar')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="get" class="mb-3" style="max-width:360px">
        <select name="project_id" class="form-select" onchange="this.form.submit()">
            <option value=""><?php echo htmlspecialchars(__('all_projects'), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php foreach (($projects ?? []) as $p): ?>
            <option value="<?php echo (int) $p['id']; ?>" <?php echo ((int) ($project_id ?? 0) === (int) $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <div class="row g-3">
        <div class="col-lg-7">
            <h2 class="h6"><?php echo htmlspecialchars(__('project_tasks'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group">
                <?php foreach (($items ?? []) as $t): ?>
                <li class="list-group-item d-flex justify-content-between">
                    <span><?php echo htmlspecialchars((string) ($t['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="text-muted"><?php echo htmlspecialchars((string) ($t['due_date'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span>
                </li>
                <?php endforeach; ?>
                <?php if (($items ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
        <div class="col-lg-5">
            <h2 class="h6"><?php echo htmlspecialchars(__('project_milestones'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group">
                <?php foreach (($milestones ?? []) as $m): ?>
                <li class="list-group-item d-flex justify-content-between">
                    <span><?php echo htmlspecialchars((string) ($m['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="text-muted"><?php echo htmlspecialchars((string) ($m['due_date'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span>
                </li>
                <?php endforeach; ?>
                <?php if (($milestones ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
