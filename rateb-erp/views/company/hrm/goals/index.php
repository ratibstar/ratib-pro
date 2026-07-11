<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('hrm_goals')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <?php if (!empty($canCreate)): ?>
    <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('hrm/goals')), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 col-lg-10 mb-4">
        <?php if (function_exists('csrf_field')): ?>
            <?php echo csrf_field(); ?>
        <?php elseif (isset($csrf)): ?>
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8'); ?>">
        <?php else: ?>
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        <?php endif; ?>
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label"><?php echo htmlspecialchars(__('hrm_employees'), ENT_QUOTES, 'UTF-8'); ?></label>
                <select name="employee_profile_id" class="form-select" required>
                    <option value=""></option>
                    <?php foreach (($employees ?? []) as $emp): ?>
                    <option value="<?php echo (int) $emp['id']; ?>"><?php echo htmlspecialchars(trim((string) ($emp['code'] ?? '') . ' — ' . (string) ($emp['first_name'] ?? '') . ' ' . (string) ($emp['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo htmlspecialchars(__('title'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="text" name="title" required class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo htmlspecialchars(__('target_date'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="date" name="target_date" class="form-control">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
        </div>
    </form>
    <?php endif; ?>
    <div class="table-responsive border rounded">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?php echo htmlspecialchars(__('title'), ENT_QUOTES, 'UTF-8'); ?></th>
                    <th><?php echo htmlspecialchars(__('hrm_employees'), ENT_QUOTES, 'UTF-8'); ?></th>
                    <th><?php echo htmlspecialchars(__('target_date'), ENT_QUOTES, 'UTF-8'); ?></th>
                    <th><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach (($items ?? []) as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo (int) ($row['employee_profile_id'] ?? 0); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['target_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['goal_status'] ?? $row['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($items ?? []) === []): ?><tr><td colspan="4" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
