<?php
declare(strict_types=1);

/** @var array<string, mixed> $drawer */
/** @var array<int, array<string, mixed>> $events */
/** @var bool $canManage */
$drawer = $drawer ?? [];
$events = $events ?? [];
?>
<link href="<?php echo rateb_pos_asset('css/pos-module.css'); ?>" rel="stylesheet">
<div class="rateb-pos-page">
    <h1 class="h3 mb-3"><?php echo \Rateb\App\Pos\Support\PosView::escape($title ?? ''); ?></h1>
    <div class="rateb-card mb-3">
        <div class="rateb-card-body">
            <dl class="row mb-0 rateb-pos-dl">
                <dt class="col-sm-4"><?php echo __('status'); ?></dt>
                <dd class="col-sm-8"><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($drawer['status'] ?? '')); ?></dd>
                <dt class="col-sm-4"><?php echo __('pos_expected_balance'); ?></dt>
                <dd class="col-sm-8"><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($drawer['expected_balance'] ?? '0')); ?></dd>
                <dt class="col-sm-4"><?php echo __('pos_counted_balance'); ?></dt>
                <dd class="col-sm-8"><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($drawer['counted_balance'] ?? '—')); ?></dd>
                <dt class="col-sm-4"><?php echo __('pos_variance'); ?></dt>
                <dd class="col-sm-8"><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($drawer['variance'] ?? '—')); ?></dd>
            </dl>
        </div>
    </div>
    <?php if (!empty($canManage)) { ?>
    <div class="rateb-card mb-3">
        <div class="rateb-card-header"><?php echo __('pos_drawer_event'); ?></div>
        <div class="rateb-card-body">
            <form method="post" action="<?php echo rateb_app_url('pos/cash-drawers/' . (int) ($drawer['id'] ?? 0) . '/event'); ?>">
                <input type="hidden" name="_csrf" value="<?php echo \Rateb\App\Pos\Support\PosView::escape($csrf ?? ''); ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="event_type"><?php echo __('type'); ?></label>
                        <select class="form-select" id="event_type" name="event_type" required>
                            <option value="pay_in"><?php echo __('pos_pay_in'); ?></option>
                            <option value="pay_out"><?php echo __('pos_pay_out'); ?></option>
                            <option value="no_sale"><?php echo __('pos_no_sale'); ?></option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="amount"><?php echo __('amount'); ?></label>
                        <input class="form-control" type="number" step="0.01" min="0" id="amount" name="amount" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="notes"><?php echo __('notes'); ?></label>
                        <input class="form-control" type="text" id="notes" name="notes">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3"><?php echo __('save'); ?></button>
            </form>
        </div>
    </div>
    <?php } ?>
    <div class="rateb-card">
        <div class="rateb-card-header"><?php echo __('pos_drawer_events'); ?></div>
        <div class="rateb-card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                    <tr>
                        <th><?php echo __('type'); ?></th>
                        <th><?php echo __('amount'); ?></th>
                        <th><?php echo __('notes'); ?></th>
                        <th><?php echo __('created_at'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($events === []) { ?>
                    <tr><td colspan="4" class="text-muted p-3"><?php echo __('no_records'); ?></td></tr>
                    <?php } ?>
                    <?php foreach ($events as $ev) { ?>
                    <tr>
                        <td><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($ev['event_type'] ?? '')); ?></td>
                        <td><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($ev['amount'] ?? '')); ?></td>
                        <td><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($ev['notes'] ?? '')); ?></td>
                        <td><?php echo \Rateb\App\Core\View::formatDate((string) ($ev['created_at'] ?? '')); ?></td>
                    </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <a href="<?php echo rateb_app_url('pos/cash-drawers'); ?>" class="btn btn-outline-secondary mt-3"><?php echo __('back'); ?></a>
</div>
