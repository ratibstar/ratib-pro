<?php
declare(strict_types=1);
/** @var array<string, list<array<string,mixed>>> $columns */
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('project_kanban')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="get" class="mb-3" style="max-width:360px">
        <select name="project_id" class="form-select" onchange="this.form.submit()">
            <option value=""><?php echo htmlspecialchars(__('all_projects'), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php foreach (($projects ?? []) as $p): ?>
            <option value="<?php echo (int) $p['id']; ?>" <?php echo ((int) ($project_id ?? 0) === (int) $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <div class="row g-3 flex-nowrap overflow-auto pb-2">
        <?php foreach (($statuses ?? []) as $st): ?>
        <div class="col-10 col-md-4 col-xl-2">
            <div class="border rounded h-100">
                <div class="px-3 py-2 border-bottom fw-semibold"><?php echo htmlspecialchars((string) $st, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="p-2">
                    <?php foreach (($columns[$st] ?? []) as $t): ?>
                    <div class="border rounded p-2 mb-2 bg-white">
                        <div class="small text-muted"><?php echo htmlspecialchars((string) ($t['task_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div><?php echo htmlspecialchars((string) ($t['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
