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
                <div class="small text-muted"><?php echo htmlspecialchars((string) (($d['role_key'] ?? '') . ' · shared=' . ((int) ($d['is_shared'] ?? 0))), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (($dashboards ?? []) === []): ?><p class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
            <?php if (!empty($canManage)): ?>
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/reporting-center/dashboards')), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 mt-2">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input class="form-control form-control-sm mb-2" name="name" placeholder="Dashboard name" required>
                <select class="form-select form-select-sm mb-2" name="role_key">
                    <option value="executive">executive</option>
                    <option value="manager">manager</option>
                    <option value="rep">rep</option>
                </select>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_shared" value="1"><label class="form-check-label">Shared</label></div>
                <button class="btn btn-sm btn-primary" type="submit"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
            </form>
            <?php endif; ?>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_scheduled_reports'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php foreach (($schedules ?? []) as $s): ?>
            <div class="border rounded p-3 mb-2 small">
                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($s['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <div><?php echo htmlspecialchars((string) (($s['report_key'] ?? '') . ' · ' . ($s['frequency'] ?? '') . ' · ' . ($s['last_status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="text-muted">next: <?php echo htmlspecialchars((string) ($s['next_run_at'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (($schedules ?? []) === []): ?><p class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
            <?php if (!empty($canManage)): ?>
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/reporting-center/schedules')), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 mt-2">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input class="form-control form-control-sm mb-2" name="name" placeholder="Schedule name" required>
                <select class="form-select form-select-sm mb-2" name="report_key">
                    <option value="funnel">funnel</option>
                    <option value="performance">performance</option>
                    <option value="activity">activity</option>
                    <option value="velocity">velocity</option>
                </select>
                <select class="form-select form-select-sm mb-2" name="frequency">
                    <option value="daily">daily</option>
                    <option value="weekly" selected>weekly</option>
                    <option value="monthly">monthly</option>
                </select>
                <button class="btn btn-sm btn-primary" type="submit"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
