<?php
declare(strict_types=1);
$forecast = $forecast ?? [];
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('crm_enterprise_forecast')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if (!empty($canManage)): ?>
        <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/forecast/snapshot')), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="period_type" value="<?php echo htmlspecialchars((string) ($period_type ?? 'month'), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="pipeline_id" value="<?php echo (int) ($pipeline_id ?? 0); ?>">
            <input type="hidden" name="team_id" value="<?php echo (int) ($team_id ?? 0); ?>">
            <input type="hidden" name="user_id" value="<?php echo (int) ($user_id ?? 0); ?>">
            <button class="btn btn-outline-primary" type="submit"><?php echo htmlspecialchars(__('crm_forecast_snapshot'), ENT_QUOTES, 'UTF-8'); ?></button>
        </form>
        <?php endif; ?>
    </div>
    <form method="get" class="row g-2 mb-3">
        <div class="col-md-2">
            <select class="form-select" name="period_type">
                <option value="month" <?php echo (($period_type ?? '') === 'month') ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('crm_monthly'), ENT_QUOTES, 'UTF-8'); ?></option>
                <option value="quarter" <?php echo (($period_type ?? '') === 'quarter') ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('crm_quarterly'), ENT_QUOTES, 'UTF-8'); ?></option>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select" name="pipeline_id">
                <option value="0"><?php echo htmlspecialchars(__('crm_pipeline'), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php foreach (($pipelines ?? []) as $p): ?>
                <option value="<?php echo (int) $p['id']; ?>" <?php echo ((int) ($pipeline_id ?? 0) === (int) $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><input class="form-control" type="number" name="team_id" placeholder="<?php echo htmlspecialchars(__('crm_team_id'), ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo (int) ($team_id ?? 0) ?: ''; ?>"></div>
        <div class="col-md-2"><input class="form-control" type="number" name="user_id" placeholder="rep_id" value="<?php echo (int) ($user_id ?? 0) ?: ''; ?>"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100" type="submit"><?php echo htmlspecialchars(__('apply'), ENT_QUOTES, 'UTF-8'); ?></button></div>
    </form>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_weighted_pipeline'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo htmlspecialchars(number_format((float) ($forecast['weighted_amount'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_forecast_confidence'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo htmlspecialchars((string) ($forecast['confidence_score'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>%</div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_scope'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-5"><?php echo htmlspecialchars((string) ($forecast['forecast_scope'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars((string) ($forecast['period_key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
    </div>
    <div class="row g-3">
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_team_forecast'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th><?php echo htmlspecialchars(__('crm_team'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('crm_weighted'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('crm_open'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('crm_members'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead><tbody>
            <?php foreach (($forecast['team_rollup'] ?? []) as $row): ?>
                <tr><td>#<?php echo (int) $row['team_id']; ?></td><td><?php echo htmlspecialchars(number_format($row['weighted_amount'], 2), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars(number_format($row['open_amount'], 2), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo (int) $row['member_count']; ?></td></tr>
            <?php endforeach; ?>
            <?php if (($forecast['team_rollup'] ?? []) === []): ?><tr><td colspan="4" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_forecast_changes'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group">
                <?php foreach (($history ?? []) as $h): ?>
                <li class="list-group-item small">
                    <?php echo htmlspecialchars((string) (($h['period_key'] ?? '') . ' · ' . ($h['change_type'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                    · <?php echo htmlspecialchars((string) (($h['from_weighted'] ?? '—') . ' → ' . ($h['to_weighted'] ?? '—')), ENT_QUOTES, 'UTF-8'); ?>
                </li>
                <?php endforeach; ?>
                <?php if (($history ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
