<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('hrm_employees')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if (!empty($canCreate)): ?>
        <a class="btn btn-primary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('hrm/employees/create')), ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-plus"></i> <?php echo htmlspecialchars(__('hrm_employee_create'), ENT_QUOTES, 'UTF-8'); ?></a>
        <?php endif; ?>
    </div>
    <form method="get" class="row g-2 mb-3">
        <div class="col-md-4"><input type="search" name="q" value="<?php echo htmlspecialchars((string) ($q ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="<?php echo htmlspecialchars(__('search'), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value=""><?php echo htmlspecialchars(__('all'), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php foreach (($statuses ?? []) as $st): ?>
                <option value="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($status ?? '') === $st) ? 'selected' : ''; ?>><?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-outline-secondary w-100" type="submit"><?php echo htmlspecialchars(__('filter'), ENT_QUOTES, 'UTF-8'); ?></button></div>
    </form>
    <div class="table-responsive border rounded">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?php echo htmlspecialchars(__('code'), ENT_QUOTES, 'UTF-8'); ?></th>
                    <th><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></th>
                    <th><?php echo htmlspecialchars(__('email'), ENT_QUOTES, 'UTF-8'); ?></th>
                    <th><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach (($items ?? []) as $row): ?>
                <tr>
                    <td><a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('hrm/employees') . '/' . (int) $row['id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($row['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></td>
                    <td><?php echo htmlspecialchars(trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><span class="badge text-bg-light"><?php echo htmlspecialchars((string) ($row['workflow_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($items ?? []) === []): ?><tr><td colspan="4" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
