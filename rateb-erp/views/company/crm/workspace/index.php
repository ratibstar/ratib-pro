<?php
declare(strict_types=1);
$filters = $filters ?? [];
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('crm_workspace')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <div class="d-flex gap-2">
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/intelligence/refresh')), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <button class="btn btn-outline-primary" type="submit"><?php echo htmlspecialchars(__('crm_refresh_intelligence'), ENT_QUOTES, 'UTF-8'); ?></button>
            </form>
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/dashboards')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('crm_advanced_dashboards'), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
    </div>

    <form method="get" class="row g-2 mb-3">
        <div class="col-md-3"><input class="form-control" type="number" name="user_id" placeholder="<?php echo htmlspecialchars(__('crm_user_id'), ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo (int) ($filters['user_id'] ?? 0) ?: ''; ?>"></div>
        <div class="col-md-3">
            <select class="form-select" name="team_id">
                <option value="0"><?php echo htmlspecialchars(__('crm_sales_teams'), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php foreach (($teams ?? []) as $t): ?>
                <option value="<?php echo (int) $t['id']; ?>" <?php echo ((int) ($filters['team_id'] ?? 0) === (int) $t['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($t['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select" name="territory_id">
                <option value="0"><?php echo htmlspecialchars(__('crm_territories'), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php foreach (($territories ?? []) as $t): ?>
                <option value="<?php echo (int) $t['id']; ?>" <?php echo ((int) ($filters['territory_id'] ?? 0) === (int) $t['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($t['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3"><button class="btn btn-primary w-100" type="submit"><?php echo htmlspecialchars(__('apply'), ENT_QUOTES, 'UTF-8'); ?></button></div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <h2 class="h6"><?php echo htmlspecialchars(__('crm_daily_actions'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group">
                <?php foreach (($daily_sales_actions ?? []) as $a): ?>
                <li class="list-group-item">
                    <span class="badge text-bg-secondary me-1"><?php echo htmlspecialchars((string) ($a['type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php echo htmlspecialchars((string) ($a['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </li>
                <?php endforeach; ?>
                <?php if (($daily_sales_actions ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
        <div class="col-lg-4">
            <h2 class="h6"><?php echo htmlspecialchars(__('crm_follow_ups_due'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group">
                <?php foreach (($follow_ups_due ?? []) as $t): ?>
                <li class="list-group-item"><?php echo htmlspecialchars((string) ($t['subject'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    <div class="small text-muted"><?php echo htmlspecialchars((string) ($t['due_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                </li>
                <?php endforeach; ?>
                <?php if (($follow_ups_due ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
        <div class="col-lg-4">
            <h2 class="h6"><?php echo htmlspecialchars(__('crm_pipeline_changes'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group">
                <?php foreach (($pipeline_changes ?? []) as $c): ?>
                <li class="list-group-item small">Opp #<?php echo (int) ($c['opportunity_id'] ?? 0); ?>:
                    <?php echo (int) ($c['from_stage_id'] ?? 0); ?> → <?php echo (int) ($c['to_stage_id'] ?? 0); ?>
                    <div class="text-muted"><?php echo htmlspecialchars((string) ($c['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                </li>
                <?php endforeach; ?>
                <?php if (($pipeline_changes ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <h2 class="h6"><?php echo htmlspecialchars(__('crm_my_leads'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group">
                <?php foreach (($my_leads ?? []) as $row): ?>
                <li class="list-group-item"><a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/leads') . '/' . (int) $row['id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($row['title'] ?? $row['lead_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a>
                    <div class="small text-muted"><?php echo htmlspecialchars(rateb_enum_label((string) ($row['workflow_status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div></li>
                <?php endforeach; ?>
                <?php if (($my_leads ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
        <div class="col-lg-4">
            <h2 class="h6"><?php echo htmlspecialchars(__('crm_my_opportunities'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group">
                <?php foreach (($my_opportunities ?? []) as $row): ?>
                <li class="list-group-item">
                    <a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/opportunities') . '/' . (int) $row['id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a>
                    <div class="small text-muted">
                        <?php echo htmlspecialchars((string) ($row['amount'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>
                        · <?php echo htmlspecialchars(__('crm_score'), ENT_QUOTES, 'UTF-8'); ?> <?php echo (int) ($row['intelligence_score'] ?? 0); ?>
                        · <?php echo htmlspecialchars((string) ($row['risk_level'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                        <?php if (!empty($row['is_stale'])): ?> · <?php echo htmlspecialchars(__('crm_stale'), ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                    </div>
                    <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/opportunities') . '/' . (int) $row['id'] . '/score'), ENT_QUOTES, 'UTF-8'); ?>" class="mt-1">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <button class="btn btn-sm btn-outline-secondary" type="submit"><?php echo htmlspecialchars(__('crm_score'), ENT_QUOTES, 'UTF-8'); ?></button>
                    </form>
                </li>
                <?php endforeach; ?>
                <?php if (($my_opportunities ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
        <div class="col-lg-4">
            <h2 class="h6"><?php echo htmlspecialchars(__('crm_my_tasks'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group">
                <?php foreach (($my_tasks ?? []) as $row): ?>
                <li class="list-group-item"><?php echo htmlspecialchars((string) ($row['subject'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    <div class="small text-muted"><?php echo htmlspecialchars(rateb_ui((string) ($row['priority'] ?? '')) . ' · ' . (string) ($row['due_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></li>
                <?php endforeach; ?>
                <?php if (($my_tasks ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
