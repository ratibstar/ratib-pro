<?php
$req = $request ?? [];
$items = $items ?? [];
?>
<div class="rateb-card">
    <div class="rateb-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><?php echo __('purchase_requests'); ?> #<?php echo Rateb\App\Core\View::escape($req['request_no'] ?? ''); ?></span>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo rateb_url(rateb_app_route('purchase-requests') . '/' . (int)($req['id'] ?? 0) . '/edit'); ?>" class="btn btn-sm btn-outline-primary"><?php echo __('edit'); ?></a>
            <?php if (in_array((string)($req['status'] ?? ''), ['approved', 'submitted'], true)) { ?>
            <form method="post" action="<?php echo rateb_url(rateb_app_route('purchase-requests') . '/' . (int)($req['id'] ?? 0) . '/convert-to-po'); ?>" class="d-inline">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <button type="submit" class="btn btn-sm btn-success"><?php echo __('convert_to_po'); ?></button>
            </form>
            <?php } ?>
            <?php if ((string)($req['status'] ?? '') === 'draft') { ?>
            <form method="post" action="<?php echo rateb_url(rateb_app_route('purchase-requests') . '/' . (int)($req['id'] ?? 0) . '/submit'); ?>" class="d-inline">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <button type="submit" class="btn btn-sm btn-success"><?php echo __('submit_for_approval'); ?></button>
            </form>
            <?php } ?>
        </div>
    </div>
    <div class="rateb-card-body">
        <dl class="row mb-4">
            <dt class="col-sm-3"><?php echo __('title'); ?></dt>
            <dd class="col-sm-9"><?php echo Rateb\App\Core\View::escape($req['title'] ?? ''); ?></dd>
            <dt class="col-sm-3"><?php echo __('department'); ?></dt>
            <dd class="col-sm-9"><?php echo Rateb\App\Core\View::escape($req['department'] ?? '—'); ?></dd>
            <dt class="col-sm-3"><?php echo __('priority'); ?></dt>
            <dd class="col-sm-9"><?php echo Rateb\App\Core\View::escape(__($req['priority'] ?? '')); ?></dd>
            <dt class="col-sm-3"><?php echo __('expected_date'); ?></dt>
            <dd class="col-sm-9 rateb-ltr-date"><?php echo Rateb\App\Core\View::formatDate($req['expected_date'] ?? '—'); ?></dd>
            <dt class="col-sm-3"><?php echo __('status'); ?></dt>
            <dd class="col-sm-9"><?php echo Rateb\App\Core\View::escape(__($req['status'] ?? '')); ?></dd>
            <dt class="col-sm-3"><?php echo __('currency'); ?></dt>
            <dd class="col-sm-9"><?php echo Rateb\App\Core\View::escape($req['currency'] ?? 'SAR'); ?></dd>
            <dt class="col-sm-3"><?php echo __('estimated_total'); ?></dt>
            <dd class="col-sm-9"><?php echo number_format((float) ($req['total_estimated'] ?? 0), 2); ?> <?php echo Rateb\App\Core\View::escape($req['currency'] ?? 'SAR'); ?></dd>
            <dt class="col-sm-3"><?php echo __('notes'); ?></dt>
            <dd class="col-sm-9"><?php echo nl2br(Rateb\App\Core\View::escape($req['notes'] ?? '')); ?></dd>
        </dl>
        <?php Rateb\App\Core\View::partial('procurement-items-table', ['items' => $items, 'showDeliveryCols' => false, 'order' => $req]); ?>
    </div>
</div>
