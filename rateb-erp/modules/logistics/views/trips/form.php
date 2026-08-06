<?php
declare(strict_types=1);

use Rateb\App\Core\View;

\Rateb\App\Core\View::partial('crud-form', get_defined_vars());

/** @var array<string,mixed> $item */
/** @var list<string> $nextStatuses */
/** @var array<int,array<string,mixed>> $history */
$item = $item ?? [];
$nextStatuses = $nextStatuses ?? [];
$history = $history ?? [];
$isEdit = (int) ($item['id'] ?? 0) > 0;
if (!$isEdit) {
    return;
}
?>
<div class="rateb-card mt-3">
    <div class="rateb-card-header"><?php echo __('logistics_status_actions'); ?></div>
    <div class="rateb-card-body">
        <?php if ($nextStatuses === []) { ?>
            <p class="text-muted mb-0"><?php echo __('logistics_no_transitions'); ?></p>
        <?php } else { ?>
            <form method="post" action="<?php echo rateb_app_url('logistics/trips/' . (int) $item['id'] . '/transition'); ?>" class="row g-2 align-items-end">
                <input type="hidden" name="_csrf" value="<?php echo View::escape((string) ($csrf ?? '')); ?>">
                <div class="col-md-4">
                    <label class="form-label"><?php echo __('status'); ?></label>
                    <select name="to_status" class="form-select" required>
                        <?php foreach ($nextStatuses as $status) { ?>
                            <option value="<?php echo View::escape($status); ?>"><?php echo View::escape(__('logistics_status_' . $status)); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label"><?php echo __('notes'); ?></label>
                    <input type="text" name="reason" class="form-control" maxlength="255">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><?php echo __('logistics_apply_transition'); ?></button>
                </div>
            </form>
        <?php } ?>
    </div>
</div>

<?php if ($history !== []) { ?>
<div class="rateb-card mt-3">
    <div class="rateb-card-header"><?php echo __('logistics_status_history'); ?></div>
    <div class="rateb-card-body p-0">
        <table class="table table-sm mb-0">
            <thead><tr><th><?php echo __('from'); ?></th><th><?php echo __('to'); ?></th><th><?php echo __('notes'); ?></th><th><?php echo __('created_at'); ?></th></tr></thead>
            <tbody>
            <?php foreach ($history as $row) { ?>
                <tr>
                    <td><?php echo View::escape((string) ($row['from_status'] ?? '')); ?></td>
                    <td><?php echo View::escape((string) ($row['to_status'] ?? '')); ?></td>
                    <td><?php echo View::escape((string) ($row['reason'] ?? '')); ?></td>
                    <td><?php echo View::escape((string) ($row['created_at'] ?? '')); ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<?php } ?>
