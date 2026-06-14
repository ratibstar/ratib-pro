<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo __('quotation_compare'); ?>: <?php echo Rateb\App\Core\View::escape($rfq['rfq_no'] ?? ''); ?></span>
        <a href="<?php echo rateb_app_url('rfq'); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('back_to_list'); ?></a>
    </div>
    <div class="rateb-card-body">
        <p class="mb-3"><strong><?php echo Rateb\App\Core\View::escape($rfq['title'] ?? ''); ?></strong></p>
        <?php if (empty($quotations)) { ?>
        <p class="text-muted mb-0"><?php echo __('no_records'); ?></p>
        <?php } else { ?>
        <div class="table-responsive">
            <table class="table rateb-table">
                <thead>
                    <tr>
                        <th><?php echo __('quotation_no'); ?></th>
                        <th><?php echo __('suppliers'); ?></th>
                        <th><?php echo __('amount'); ?></th>
                        <th><?php echo __('status'); ?></th>
                        <th><?php echo __('valid_until'); ?></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $best = null;
                    foreach ($quotations as $q) {
                        $amt = (float) ($q['amount'] ?? 0);
                        if ($best === null || $amt < $best) {
                            $best = $amt;
                        }
                    }
                    foreach ($quotations as $q) {
                        $amt = (float) ($q['amount'] ?? 0);
                        $isBest = $best !== null && abs($amt - $best) < 0.01;
                        ?>
                    <tr<?php echo $isBest ? ' class="table-success"' : ''; ?>>
                        <td><?php echo Rateb\App\Core\View::escape($q['quotation_no'] ?? ''); ?></td>
                        <td><?php echo Rateb\App\Core\View::escape($q['supplier_name'] ?? ''); ?></td>
                        <td><strong><?php echo number_format($amt, 2); ?></strong> <?php if ($isBest) { ?><span class="badge bg-success"><?php echo __('best_price'); ?></span><?php } ?></td>
                        <td><?php echo Rateb\App\Core\View::escape($q['status'] ?? ''); ?></td>
                        <td><?php echo Rateb\App\Core\View::escape($q['valid_until'] ?? ''); ?></td>
                        <td>
                            <form method="post" action="<?php echo rateb_app_url('quotations/' . (int) ($q['id'] ?? 0) . '/create-po'); ?>" class="d-inline">
                                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? \Rateb\App\Core\Csrf::token()); ?>">
                                <button type="submit" class="btn btn-sm btn-primary"><?php echo __('create_po_from_quote'); ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } ?>
    </div>
</div>
