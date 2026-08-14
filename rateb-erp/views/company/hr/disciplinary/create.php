<?php
/** @var int $companyId */
/** @var list<array<string, mixed>> $employees */
/** @var list<string> $actionTypes */
/** @var string $csrf */
/** @var string $routePrefix */
/** @var int $prefillEmployeeId */
$companyId = (int) ($companyId ?? 0);
$employees = $employees ?? [];
$actionTypes = $actionTypes ?? \Rateb\App\Services\HrDisciplinaryService::ACTION_TYPES;
$csrf = (string) ($csrf ?? \Rateb\App\Core\Csrf::token());
$routePrefix = (string) ($routePrefix ?? rateb_app_route('hr/disciplinary'));
$prefillEmployeeId = (int) ($prefillEmployeeId ?? 0);
$escape = static fn ($v): string => \Rateb\App\Core\View::escape((string) $v);
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'disciplinary']);
?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center">
        <span><?php echo __('hr_disciplinary_new'); ?></span>
        <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('back'); ?></a>
    </div>
    <div class="rateb-card-body">
        <?php if ($companyId < 1) { ?>
            <div class="alert alert-warning"><?php echo __('hr_select_company_hint'); ?></div>
        <?php } else { ?>
        <form method="post" action="<?php echo rateb_url(rateb_app_route('hr/disciplinary')); ?>" class="row g-3">
            <input type="hidden" name="_csrf" value="<?php echo $escape($csrf); ?>">
            <div class="col-md-6">
                <label class="form-label"><?php echo __('employee'); ?></label>
                <select name="employee_id" class="form-select" required>
                    <option value=""><?php echo __('select'); ?></option>
                    <?php foreach ($employees as $e) {
                        $eid = (int) ($e['id'] ?? 0);
                        $sel = $prefillEmployeeId === $eid ? ' selected' : '';
                        ?>
                        <option value="<?php echo $eid; ?>"<?php echo $sel; ?>>
                            <?php echo $escape((string) ($e['name'] ?? '') . ' · ' . (string) ($e['employee_code'] ?? '')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo __('type'); ?></label>
                <select name="action_type" class="form-select">
                    <?php foreach ($actionTypes as $at) { ?>
                        <option value="<?php echo $escape($at); ?>"><?php echo $escape($at); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label"><?php echo __('title'); ?></label>
                <input type="text" name="title" class="form-control" required maxlength="190">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo __('date'); ?></label>
                <input type="date" name="action_date" class="form-control" value="<?php echo $escape(date('Y-m-d')); ?>">
            </div>
            <div class="col-12">
                <label class="form-label"><?php echo __('description'); ?></label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
            </div>
        </form>
        <?php } ?>
    </div>
</div>
