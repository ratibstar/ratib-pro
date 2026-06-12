<div class="row g-3">
    <?php if (!empty($companies)) { ?>
    <div class="col-12">
        <form method="get" class="row g-2">
            <div class="col-md-4">
                <select class="form-select" name="company_id" onchange="this.form.submit()">
                    <option value=""><?php echo __('all_companies'); ?></option>
                    <?php foreach ($companies as $c) { ?>
                    <option value="<?php echo (int) $c['id']; ?>"<?php echo (int)($_GET['company_id'] ?? 0) === (int)$c['id'] ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($c['name'] ?? ''); ?></option>
                    <?php } ?>
                </select>
            </div>
        </form>
    </div>
    <?php } ?>
    <div class="col-12">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo __('rfq'); ?> / <?php echo __('quotations'); ?></div>
            <div class="rateb-card-body p-0">
                <div class="table-responsive">
                    <table class="table rateb-table mb-0">
                        <thead>
                            <tr>
                                <th><?php echo __('companies'); ?></th>
                                <th><?php echo __('rfq_no'); ?></th>
                                <th><?php echo __('title'); ?></th>
                                <th><?php echo __('status'); ?></th>
                                <th><?php echo __('deadline'); ?></th>
                                <th><?php echo __('quotations'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)) { ?>
                            <tr><td colspan="6" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                            <?php } else { foreach ($items as $row) { ?>
                            <tr>
                                <td><?php echo Rateb\App\Core\View::escape($row['company_name'] ?? ''); ?></td>
                                <td><?php echo Rateb\App\Core\View::escape($row['rfq_no'] ?? ''); ?></td>
                                <td><?php echo Rateb\App\Core\View::escape($row['title'] ?? ''); ?></td>
                                <td><?php echo Rateb\App\Core\View::escape($row['status'] ?? ''); ?></td>
                                <td><?php echo Rateb\App\Core\View::escape($row['deadline'] ?? ''); ?></td>
                                <td><?php echo (int) ($row['quote_count'] ?? 0); ?></td>
                            </tr>
                            <?php } } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
