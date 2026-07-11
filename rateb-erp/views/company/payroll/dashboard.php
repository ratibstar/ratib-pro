<?php
declare(strict_types=1);
/** @var array<string, array<string, int>> $board */
/** @var list<array<string, mixed>> $timeline */
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('payroll_platform')), ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('payroll/batches')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('payroll_batches'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('payroll/cycles')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('payroll_cycles'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('payroll/payslips')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('payroll_payslips'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('payroll/salary-structures')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('payroll_salary_structures'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('payroll/loans')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('payroll_loans'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('payroll/advances')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('payroll_advances'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('payroll/overtime')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('payroll_overtime'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('payroll/reports')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('payroll_reports'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('payroll/timeline')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('payroll_timeline'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <h2 class="h5 mb-3"><?php echo htmlspecialchars(__('payroll_board'), ENT_QUOTES, 'UTF-8'); ?></h2>
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
        </div>
    </div>
    <?php endforeach; ?>
    <h2 class="h5 mt-4 mb-3"><?php echo htmlspecialchars(__('payroll_timeline'), ENT_QUOTES, 'UTF-8'); ?></h2>
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
