<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('project_budget')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="get" class="mb-3" style="max-width:360px">
        <select name="project_id" class="form-select" onchange="this.form.submit()">
            <option value=""><?php echo htmlspecialchars(__('select_project'), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php foreach (($projects ?? []) as $p): ?>
            <option value="<?php echo (int) $p['id']; ?>" <?php echo ((int) ($project_id ?? 0) === (int) $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php if (!empty($canCreate) && (int) ($project_id ?? 0) > 0): ?>
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('projects/budget')), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="project_id" value="<?php echo (int) $project_id; ?>">
                <h2 class="h6"><?php echo htmlspecialchars(__('budget_line'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <input class="form-control mb-2" name="category" value="general">
                <input class="form-control mb-2" type="number" step="0.01" name="planned_amount" required>
                <button class="btn btn-primary btn-sm" type="submit"><?php echo htmlspecialchars(__('add'), ENT_QUOTES, 'UTF-8'); ?></button>
            </form>
        </div>
        <div class="col-md-6">
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('projects/budget/costs')), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="project_id" value="<?php echo (int) $project_id; ?>">
                <h2 class="h6"><?php echo htmlspecialchars(__('cost_entry'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <input class="form-control mb-2" type="date" name="cost_date" value="<?php echo date('Y-m-d'); ?>">
                <input class="form-control mb-2" type="number" step="0.01" name="amount" required>
                <button class="btn btn-outline-primary btn-sm" type="submit"><?php echo htmlspecialchars(__('add'), ENT_QUOTES, 'UTF-8'); ?></button>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <div class="row g-3">
        <div class="col-md-6">
            <h2 class="h6"><?php echo htmlspecialchars(__('budgets'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group">
                <?php foreach (($budgets ?? []) as $b): ?>
                <li class="list-group-item d-flex justify-content-between"><span><?php echo htmlspecialchars((string) ($b['category'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span><span><?php echo htmlspecialchars((string) ($b['planned_amount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></li>
                <?php endforeach; ?>
                <?php if (($budgets ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
        <div class="col-md-6">
            <h2 class="h6"><?php echo htmlspecialchars(__('costs'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group">
                <?php foreach (($costs ?? []) as $c): ?>
                <li class="list-group-item d-flex justify-content-between"><span><?php echo htmlspecialchars((string) ($c['cost_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span><span><?php echo htmlspecialchars((string) ($c['amount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></li>
                <?php endforeach; ?>
                <?php if (($costs ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
