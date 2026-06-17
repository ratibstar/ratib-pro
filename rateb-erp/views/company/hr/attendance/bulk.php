<?php
/** @var array<int, array<string, mixed>> $employees */
/** @var array<int, array<string, mixed>> $existing */
$date = (string) ($date ?? date('Y-m-d'));
$canManage = $canManage ?? true;
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'attendance-bulk']);
?>
<form method="get" class="row g-2 align-items-end mb-3">
    <div class="col-auto">
        <label class="form-label"><?php echo __('attendance_date'); ?></label>
        <input type="date" name="date" class="form-control rateb-form-control" value="<?php echo Rateb\App\Core\View::escape($date); ?>">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary btn-sm"><?php echo __('filter'); ?></button>
    </div>
</form>
<?php if ($canManage) { ?>
<form method="post" action="<?php echo rateb_url($routePrefix); ?>">
    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
    <input type="hidden" name="attendance_date" value="<?php echo Rateb\App\Core\View::escape($date); ?>">
    <div class="rateb-card">
        <div class="rateb-card-header"><?php echo __('hr_attendance_bulk'); ?> — <?php echo Rateb\App\Core\View::escape($date); ?></div>
        <div class="rateb-card-body p-0">
            <div class="table-responsive">
                <table class="table rateb-table mb-0">
                    <thead>
                    <tr>
                        <th><?php echo __('present'); ?></th>
                        <th><?php echo __('employee_code'); ?></th>
                        <th><?php echo __('name'); ?></th>
                        <th><?php echo __('check_in'); ?></th>
                        <th><?php echo __('check_out'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($employees)) { ?>
                    <tr><td colspan="5" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                    <?php } else {
                        foreach ($employees as $emp) {
                            $eid = (int) ($emp['id'] ?? 0);
                            $row = $existing[$eid] ?? [];
                            ?>
                    <tr>
                        <td><input type="checkbox" name="present[<?php echo $eid; ?>]" value="1"<?php echo !empty($row) ? ' checked' : ''; ?>></td>
                        <td><?php echo Rateb\App\Core\View::escape((string) ($emp['employee_code'] ?? '')); ?></td>
                        <td><?php echo Rateb\App\Core\View::escape((string) ($emp['name'] ?? '')); ?></td>
                        <td><input type="text" name="check_in[<?php echo $eid; ?>]" class="form-control form-control-sm rateb-form-control rateb-ltr-num" value="<?php echo Rateb\App\Core\View::escape(substr((string) ($row['check_in'] ?? '09:00'), 0, 5)); ?>"></td>
                        <td><input type="text" name="check_out[<?php echo $eid; ?>]" class="form-control form-control-sm rateb-form-control rateb-ltr-num" value="<?php echo Rateb\App\Core\View::escape(substr((string) ($row['check_out'] ?? '17:00'), 0, 5)); ?>"></td>
                    </tr>
                    <?php }
                    } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="rateb-card-footer text-end">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> <?php echo __('save'); ?></button>
        </div>
    </div>
</form>
<?php } ?>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
