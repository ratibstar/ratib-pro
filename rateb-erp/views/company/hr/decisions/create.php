<?php
/** @var int $companyId */
/** @var list<array<string, mixed>> $employees */
/** @var list<string> $decisionTypes */
/** @var string $csrf */
/** @var string $routePrefix */
$companyId = (int) ($companyId ?? 0);
$employees = $employees ?? [];
$decisionTypes = $decisionTypes ?? \Rateb\App\Services\HrDecisionService::TYPES;
$csrf = (string) ($csrf ?? \Rateb\App\Core\Csrf::token());
$routePrefix = (string) ($routePrefix ?? rateb_app_route('hr/decisions'));
$escape = static fn ($v): string => \Rateb\App\Core\View::escape((string) $v);
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'decisions']);
?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center">
        <span><?php echo __('hr_decision_new'); ?></span>
        <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('back'); ?></a>
    </div>
    <div class="rateb-card-body">
        <p class="text-muted small"><?php echo __('hr_decisions_hint'); ?></p>
        <?php if ($companyId < 1) { ?>
            <div class="alert alert-warning"><?php echo __('hr_select_company_hint'); ?></div>
        <?php } else { ?>
        <form method="post" action="<?php echo rateb_url(rateb_app_route('hr/decisions')); ?>" class="row g-3">
            <input type="hidden" name="_csrf" value="<?php echo $escape($csrf); ?>">
            <div class="col-md-6">
                <label class="form-label"><?php echo __('employee'); ?></label>
                <select name="employee_id" class="form-select" required>
                    <option value=""><?php echo __('select'); ?></option>
                    <?php foreach ($employees as $e) { ?>
                        <option value="<?php echo (int) ($e['id'] ?? 0); ?>">
                            <?php echo $escape((string) ($e['name'] ?? '') . ' · ' . (string) ($e['employee_code'] ?? '')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo __('type'); ?></label>
                <select name="decision_type" id="hrDecisionType" class="form-select" required>
                    <?php foreach ($decisionTypes as $dt) { ?>
                        <option value="<?php echo $escape($dt); ?>"><?php echo $escape(__('hr_decision_type_' . $dt)); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo __('date'); ?></label>
                <input type="date" name="effective_date" class="form-control" value="<?php echo $escape(date('Y-m-d')); ?>">
            </div>
            <div class="col-md-4 hr-dec-salary">
                <label class="form-label"><?php echo __('hr_decision_new_salary'); ?></label>
                <input type="number" step="0.01" name="new_salary_base" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo __('hr_decision_new_job_title'); ?></label>
                <input type="text" name="new_job_title" class="form-control" maxlength="190">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo __('department'); ?></label>
                <input type="number" name="new_department_id" class="form-control" min="0" placeholder="ID">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo __('branch'); ?></label>
                <input type="number" name="new_branch_id" class="form-control" min="0" placeholder="ID">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo __('hr_decision_deduction_days'); ?></label>
                <input type="number" step="0.5" name="deduction_days" class="form-control">
            </div>
            <div class="col-12">
                <label class="form-label"><?php echo __('reason'); ?></label>
                <textarea name="reason" class="form-control" rows="3"></textarea>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><?php echo __('submit'); ?></button>
            </div>
        </form>
        <?php } ?>
    </div>
</div>
<script>
(function () {
  var sel = document.getElementById('hrDecisionType');
  if (!sel) return;
  function sync() {
    var t = sel.value;
    var salary = document.querySelector('.hr-dec-salary');
    if (salary) {
      salary.style.display = (t === 'salary_adjustment' || t === 'salary_movement' || t === 'promotion') ? '' : 'none';
    }
  }
  sel.addEventListener('change', sync);
  sync();
})();
</script>
