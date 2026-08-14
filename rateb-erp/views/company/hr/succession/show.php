<?php
/** @var array<string, mixed> $position */
/** @var list<array<string, mixed>> $candidates */
/** @var list<array<string, mixed>> $employees */
/** @var list<string> $readiness */
/** @var string $csrf */
/** @var string $routePrefix */
/** @var bool $canManage */
$position = $position ?? [];
$candidates = $candidates ?? [];
$employees = $employees ?? [];
$readiness = $readiness ?? \Rateb\App\Services\HrSuccessionService::READINESS;
$csrf = (string) ($csrf ?? \Rateb\App\Core\Csrf::token());
$routePrefix = (string) ($routePrefix ?? rateb_app_route('hr/succession'));
$canManage = (bool) ($canManage ?? false);
$posId = (int) ($position['id'] ?? 0);
$escape = static fn ($v): string => \Rateb\App\Core\View::escape((string) $v);
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'succession']);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1"><?php echo $escape((string) ($position['title'] ?? '')); ?></h1>
        <div class="small text-muted rateb-ltr-num"><?php echo $escape((string) ($position['code'] ?? '')); ?></div>
    </div>
    <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('back'); ?></a>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="rateb-card h-100">
            <div class="rateb-card-header"><?php echo __('hr_succession_current_holder'); ?></div>
            <div class="rateb-card-body">
                <?php if ((int) ($position['current_employee_id'] ?? 0) > 0) { ?>
                    <a href="<?php echo rateb_url(rateb_app_route('hr/employees/' . (int) $position['current_employee_id'])); ?>">
                        <?php echo $escape((string) ($position['current_employee_name'] ?? '')); ?>
                    </a>
                    <div class="small text-muted rateb-ltr-num"><?php echo $escape((string) ($position['current_employee_code'] ?? '')); ?></div>
                <?php } else { ?>
                    <span class="text-muted"><?php echo __('no_records'); ?></span>
                <?php } ?>
                <?php if (trim((string) ($position['skill_gap_notes'] ?? '')) !== '') { ?>
                    <hr>
                    <div class="small"><strong><?php echo __('hr_succession_skill_gaps'); ?>:</strong>
                        <?php echo nl2br($escape((string) $position['skill_gap_notes'])); ?></div>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <?php if ($canManage) { ?>
        <div class="rateb-card h-100">
            <div class="rateb-card-header"><?php echo __('hr_succession_add_candidate'); ?></div>
            <div class="rateb-card-body">
                <form method="post" action="<?php echo rateb_url($routePrefix . '/' . $posId . '/candidates'); ?>" class="row g-2">
                    <input type="hidden" name="_csrf" value="<?php echo $escape($csrf); ?>">
                    <div class="col-12">
                        <select name="employee_id" class="form-select form-select-sm" required>
                            <option value=""><?php echo __('select'); ?></option>
                            <?php foreach ($employees as $e) {
                                echo '<option value="' . (int) ($e['id'] ?? 0) . '">' . $escape((string) ($e['name'] ?? '') . ' · ' . (string) ($e['employee_code'] ?? '')) . '</option>';
                            } ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <select name="readiness" class="form-select form-select-sm">
                            <?php foreach ($readiness as $r) {
                                echo '<option value="' . $escape($r) . '">' . $escape(__('hr_succession_ready_' . $r)) . '</option>';
                            } ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <input type="number" name="rank_order" class="form-control form-control-sm" value="1" min="1" max="99" placeholder="<?php echo $escape(__('hr_o_rank')); ?>">
                    </div>
                    <div class="col-12">
                        <textarea name="skill_gap_notes" class="form-control form-control-sm" rows="2" placeholder="<?php echo $escape(__('hr_succession_skill_gaps')); ?>"></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-sm btn-primary"><?php echo __('save'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php } ?>
    </div>
</div>

<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('hr_o_candidates'); ?></div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead><tr>
                    <th>#</th>
                    <th><?php echo __('employee'); ?></th>
                    <th><?php echo __('hr_succession_readiness'); ?></th>
                    <th><?php echo __('hr_succession_skill_gaps'); ?></th>
                </tr></thead>
                <tbody>
                <?php if ($candidates === []) { ?>
                    <tr><td colspan="4" class="text-muted"><?php echo __('no_records'); ?></td></tr>
                <?php } ?>
                <?php foreach ($candidates as $c) { ?>
                    <tr>
                        <td class="rateb-ltr-num"><?php echo (int) ($c['rank_order'] ?? 0); ?></td>
                        <td>
                            <a href="<?php echo rateb_url(rateb_app_route('hr/employees/' . (int) ($c['employee_id'] ?? 0))); ?>">
                                <?php echo $escape((string) ($c['employee_name'] ?? '')); ?>
                            </a>
                        </td>
                        <td><?php echo $escape(__('hr_succession_ready_' . (string) ($c['readiness'] ?? 'developing'))); ?></td>
                        <td><?php echo $escape((string) ($c['skill_gap_notes'] ?? '')); ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
