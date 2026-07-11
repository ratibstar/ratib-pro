<?php
declare(strict_types=1);
/** @var array<string, array<string, int>> $board */
/** @var array<string, mixed> $spend */
/** @var list<array<string, mixed>> $timeline */
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('procurement_platform')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <a class="btn btn-primary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eproc/suppliers/create')), ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-plus"></i> <?php echo htmlspecialchars(__('eproc_supplier_create'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eproc/suppliers')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('eproc_suppliers'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eproc/categories')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('eproc_categories'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eproc/scorecards')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('eproc_scorecards'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eproc/tenders')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('eproc_tenders'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eproc/contracts')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('eproc_contracts'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eproc/qualification')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('eproc_qualification'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eproc/collaboration')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('eproc_collaboration'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eproc/calendar')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('eproc_calendar'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eproc/spend')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('eproc_spend'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eproc/portal')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('eproc_portal'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eproc/rfq-templates')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('eproc_rfq_templates'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eproc/reports')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('eproc_reports'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>

    <h2 class="h5 mb-3"><?php echo htmlspecialchars(__('eproc_board'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <?php foreach (($board ?? []) as $entity => $counts): ?>
    <div class="mb-3">
        <div class="text-muted small mb-2"><?php echo htmlspecialchars((string) $entity, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="row g-3">
            <?php foreach ((array) $counts as $st => $cnt): ?>
            <div class="col-6 col-md-3 col-xl">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted small"><?php echo htmlspecialchars((string) $st, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="fs-4 fw-semibold"><?php echo (int) $cnt; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if ((array) $counts === []): ?>
            <div class="col-12"><div class="text-muted small"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></div></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (($board ?? []) === []): ?>
    <p class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <div class="row g-3 mt-2">
        <div class="col-lg-5">
            <h2 class="h5"><?php echo htmlspecialchars(__('eproc_spend'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="border rounded p-3 mb-3">
                <div class="text-muted small"><?php echo htmlspecialchars(__('total'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="fs-4 fw-semibold"><?php echo htmlspecialchars(number_format((float) ($spend['snapshots_total'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="table-responsive border rounded">
                <table class="table mb-0 align-middle">
                    <thead><tr><th><?php echo htmlspecialchars(__('period'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('total'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach (($spend['snapshots_by_period'] ?? []) as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) ($row['period_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(number_format((float) ($row['total'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (($spend['snapshots_by_period'] ?? []) === []): ?><tr><td colspan="2" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-lg-7">
            <h2 class="h5"><?php echo htmlspecialchars(__('eproc_timeline'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group list-group-flush border rounded">
                <?php foreach (($timeline ?? []) as $ev): ?>
                    <li class="list-group-item">
                        <div class="fw-semibold"><?php echo htmlspecialchars((string) ($ev['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php if (!empty($ev['body'])): ?><div class="small"><?php echo htmlspecialchars((string) $ev['body'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                        <div class="small text-muted"><?php echo htmlspecialchars((string) ($ev['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    </li>
                <?php endforeach; ?>
                <?php if (($timeline ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
