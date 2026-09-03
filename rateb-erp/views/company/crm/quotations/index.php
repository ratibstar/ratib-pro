<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if (!empty($canCreate)): ?>
        <a class="btn btn-primary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/quotations/create')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('create'), ENT_QUOTES, 'UTF-8'); ?></a>
        <?php endif; ?>
    </div>
    <form class="row g-2 mb-3" method="get">
        <div class="col-md-4"><input class="form-control" name="q" value="<?php echo htmlspecialchars((string) ($q ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars(__('search'), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-2"><input class="form-control" name="status" value="<?php echo htmlspecialchars((string) ($status ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-2"><button class="btn btn-outline-primary w-100"><?php echo htmlspecialchars(__('search'), ENT_QUOTES, 'UTF-8'); ?></button></div>
    </form>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
            <tr>
                <th><?php echo htmlspecialchars(__('record_id'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?php echo htmlspecialchars(__('title'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?php echo htmlspecialchars(__('amount'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach (($items ?? []) as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string) ($row['quotation_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['total_amount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars((string) ($row['currency_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(rateb_enum_label((string) ($row['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/quotations/' . (int) ($row['id'] ?? 0))), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('view'), ENT_QUOTES, 'UTF-8'); ?></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($items ?? []) === []): ?>
                <tr><td colspan="5" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
