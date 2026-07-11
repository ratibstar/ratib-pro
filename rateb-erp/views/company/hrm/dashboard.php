<?php
declare(strict_types=1);
/** @var array<string, array<string, int>> $board */
/** @var list<array<string, mixed>> $timeline */
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('hr_platform')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <a class="btn btn-primary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('hrm/employees/create')), ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-plus"></i> <?php echo htmlspecialchars(__('hrm_employee_create'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('hrm/employees')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('hrm_employees'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('hrm/departments')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('hrm_departments'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('hrm/positions')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('hrm_positions'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('hrm/organization')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('hrm_organization'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('hrm/training')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('hrm_training'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('hrm/performance')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('hrm_performance'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('hrm/promotions')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('hrm_promotions'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('hrm/transfers')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('hrm_transfers'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('hrm/goals')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('hrm_goals'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('hrm/competencies')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('hrm_competencies'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('hrm/reports')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('hrm_reports'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('hrm/timeline')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('hrm_timeline'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>

    <h2 class="h5 mb-3"><?php echo htmlspecialchars(__('hrm_board'), ENT_QUOTES, 'UTF-8'); ?></h2>
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

    <h2 class="h5 mt-4 mb-3"><?php echo htmlspecialchars(__('hrm_timeline'), ENT_QUOTES, 'UTF-8'); ?></h2>
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
