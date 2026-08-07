<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $recent */
/** @var array<string,int> $board */
/** @var list<array<string,mixed>> $timeline */
/** @var array<string,mixed> $kpis */
$kpis = $kpis ?? [];
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('crm')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/leads/board')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('crm_lead_board'), ENT_QUOTES, 'UTF-8'); ?></a>
            <a class="btn btn-primary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/leads/create')), ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-plus"></i> <?php echo htmlspecialchars(__('crm_lead_create'), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl">
            <div class="border rounded p-3 h-100">
                <div class="text-muted small"><?php echo htmlspecialchars(__('crm_kpi_leads'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="fs-4 fw-semibold"><?php echo (int) ($kpis['leads_total'] ?? 0); ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <div class="border rounded p-3 h-100">
                <div class="text-muted small"><?php echo htmlspecialchars(__('crm_kpi_conversion_rate'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="fs-4 fw-semibold"><?php echo htmlspecialchars((string) ($kpis['conversion_rate'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>%</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <div class="border rounded p-3 h-100">
                <div class="text-muted small"><?php echo htmlspecialchars(__('crm_kpi_active_opportunities'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="fs-4 fw-semibold"><?php echo (int) ($kpis['opportunities_active'] ?? 0); ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <div class="border rounded p-3 h-100">
                <div class="text-muted small"><?php echo htmlspecialchars(__('crm_kpi_pending_quotations'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="fs-4 fw-semibold"><?php echo (int) ($kpis['quotations_pending'] ?? 0); ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <div class="border rounded p-3 h-100">
                <div class="text-muted small"><?php echo htmlspecialchars(__('crm_kpi_pipeline_value'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="fs-4 fw-semibold"><?php echo htmlspecialchars(number_format((float) ($kpis['pipeline_value'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <?php foreach (($board ?? []) as $st => $cnt): ?>
        <div class="col-6 col-md-3 col-xl">
            <div class="border rounded p-3 h-100">
                <div class="text-muted small"><?php echo htmlspecialchars((string) $st, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="fs-4 fw-semibold"><?php echo (int) $cnt; ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="row g-3">
        <div class="col-lg-7">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_leads'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead><tr><th><?php echo htmlspecialchars(__('record_id'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach (($recent ?? []) as $row): ?>
                        <tr>
                            <td><a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/leads') . '/' . (int) $row['id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($row['lead_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></td>
                            <td><?php echo htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['workflow_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (($recent ?? []) === []): ?><tr><td colspan="3" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-lg-5">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_timeline'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group list-group-flush border rounded">
                <?php foreach (($timeline ?? []) as $ev): ?>
                    <li class="list-group-item">
                        <div class="fw-semibold"><?php echo htmlspecialchars((string) ($ev['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="small text-muted"><?php echo htmlspecialchars((string) ($ev['event_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars((string) ($ev['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    </li>
                <?php endforeach; ?>
                <?php if (($timeline ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
