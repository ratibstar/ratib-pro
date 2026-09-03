<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('crm_reporting_center')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if (!empty($canManage)): ?>
        <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/reporting-center/run-due')), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
            <button class="btn btn-outline-primary" type="submit"><?php echo htmlspecialchars(__('crm_run_due_reports'), ENT_QUOTES, 'UTF-8'); ?></button>
        </form>
        <?php endif; ?>
    </div>
    <div class="row g-3">
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_saved_dashboards'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php foreach (($dashboards ?? []) as $d): ?>
            <div class="border rounded p-3 mb-2">
                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($d['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="small text-muted"><?php echo htmlspecialchars(rateb_ui((string) ($d['role_key'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                    · <?php echo htmlspecialchars(__('crm_shared'), ENT_QUOTES, 'UTF-8'); ?>: <?php echo !empty($d['is_shared']) ? htmlspecialchars(__('yes'), ENT_QUOTES, 'UTF-8') : htmlspecialchars(__('no'), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (($dashboards ?? []) === []): ?><p class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
            <?php if (!empty($canManage)): ?>
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/reporting-center/dashboards')), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 mt-2">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input class="form-control form-control-sm mb-2" name="name" placeholder="<?php echo htmlspecialchars(__('crm_dashboard_name'), ENT_QUOTES, 'UTF-8'); ?>" required>
                <select class="form-select form-select-sm mb-2" name="role_key">
                    <option value="executive"><?php echo htmlspecialchars(__('executive'), ENT_QUOTES, 'UTF-8'); ?></option>
                    <option value="manager"><?php echo htmlspecialchars(__('manager'), ENT_QUOTES, 'UTF-8'); ?></option>
                    <option value="rep"><?php echo htmlspecialchars(__('rep'), ENT_QUOTES, 'UTF-8'); ?></option>
                </select>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_shared" value="1"><label class="form-check-label"><?php echo htmlspecialchars(__('crm_shared'), ENT_QUOTES, 'UTF-8'); ?></label></div>
                <button class="btn btn-sm btn-primary" type="submit"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
            </form>
            <?php endif; ?>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_scheduled_reports'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php foreach (($schedules ?? []) as $s): ?>
            <div class="border rounded p-3 mb-2 small">
                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($s['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <div><?php echo htmlspecialchars(rateb_ui((string) ($s['report_key'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                    · <?php echo htmlspecialchars(rateb_ui((string) ($s['frequency'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                    · <?php echo htmlspecialchars(rateb_ui((string) ($s['last_status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="text-muted"><?php echo htmlspecialchars(__('crm_next'), ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars((string) ($s['next_run_at'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (($schedules ?? []) === []): ?><p class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
            <?php if (!empty($canManage)): ?>
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/reporting-center/schedules')), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 mt-2">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input class="form-control form-control-sm mb-2" name="name" placeholder="<?php echo htmlspecialchars(__('crm_schedule_name'), ENT_QUOTES, 'UTF-8'); ?>" required>
                <select class="form-select form-select-sm mb-2" name="report_key">
                    <option value="funnel"><?php echo htmlspecialchars(__('funnel'), ENT_QUOTES, 'UTF-8'); ?></option>
                    <option value="performance"><?php echo htmlspecialchars(__('performance'), ENT_QUOTES, 'UTF-8'); ?></option>
                    <option value="activity"><?php echo htmlspecialchars(__('activity'), ENT_QUOTES, 'UTF-8'); ?></option>
                    <option value="velocity"><?php echo htmlspecialchars(__('velocity'), ENT_QUOTES, 'UTF-8'); ?></option>
                </select>
                <select class="form-select form-select-sm mb-2" name="frequency">
                    <option value="daily"><?php echo htmlspecialchars(__('daily'), ENT_QUOTES, 'UTF-8'); ?></option>
                    <option value="weekly" selected><?php echo htmlspecialchars(__('weekly'), ENT_QUOTES, 'UTF-8'); ?></option>
                    <option value="monthly"><?php echo htmlspecialchars(__('monthly'), ENT_QUOTES, 'UTF-8'); ?></option>
                </select>
                <button class="btn btn-sm btn-primary" type="submit"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
