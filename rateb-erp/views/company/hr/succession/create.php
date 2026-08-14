<?php
/** @var int $companyId */
/** @var list<array<string, mixed>> $departments */
/** @var list<array<string, mixed>> $jobTitles */
/** @var list<array<string, mixed>> $employees */
/** @var string $csrf */
/** @var string $routePrefix */
$companyId = (int) ($companyId ?? 0);
$departments = $departments ?? [];
$jobTitles = $jobTitles ?? [];
$employees = $employees ?? [];
$csrf = (string) ($csrf ?? \Rateb\App\Core\Csrf::token());
$routePrefix = (string) ($routePrefix ?? rateb_app_route('hr/succession'));
$escape = static fn ($v): string => \Rateb\App\Core\View::escape((string) $v);
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'succession']);
?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between">
        <span><?php echo __('hr_succession_new_position'); ?></span>
        <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('back'); ?></a>
    </div>
    <div class="rateb-card-body">
        <?php if ($companyId < 1) { ?>
            <div class="alert alert-warning"><?php echo __('hr_select_company_hint'); ?></div>
        <?php } else { ?>
        <form method="post" action="<?php echo rateb_url($routePrefix); ?>" class="row g-3">
            <input type="hidden" name="_csrf" value="<?php echo $escape($csrf); ?>">
            <div class="col-md-8">
                <label class="form-label"><?php echo __('title'); ?></label>
                <input type="text" name="title" class="form-control" required maxlength="190">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo __('code'); ?></label>
                <input type="text" name="code" class="form-control" maxlength="40" placeholder="CP-…">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo __('department'); ?></label>
                <select name="department_id" class="form-select">
                    <option value="0">—</option>
                    <?php foreach ($departments as $d) {
                        echo '<option value="' . (int) ($d['id'] ?? 0) . '">' . $escape((string) ($d['name'] ?? '')) . '</option>';
                    } ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo __('job_title'); ?></label>
                <select name="job_title_id" class="form-select">
                    <option value="0">—</option>
                    <?php foreach ($jobTitles as $j) {
                        echo '<option value="' . (int) ($j['id'] ?? 0) . '">' . $escape((string) ($j['name'] ?? '')) . '</option>';
                    } ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo __('hr_succession_current_holder'); ?></label>
                <select name="current_employee_id" class="form-select">
                    <option value="0">—</option>
                    <?php foreach ($employees as $e) {
                        echo '<option value="' . (int) ($e['id'] ?? 0) . '">' . $escape((string) ($e['name'] ?? '') . ' · ' . (string) ($e['employee_code'] ?? '')) . '</option>';
                    } ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label"><?php echo __('hr_succession_skill_gaps'); ?></label>
                <textarea name="skill_gap_notes" class="form-control" rows="3"></textarea>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
            </div>
        </form>
        <?php } ?>
    </div>
</div>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
