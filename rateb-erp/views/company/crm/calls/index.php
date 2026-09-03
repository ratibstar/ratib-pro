<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
    <?php if (!empty($canCreate)): ?>
    <form method="post" class="border rounded p-3 mb-3 col-lg-8">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="row g-2">
            <div class="col-md-4"><input class="form-control" name="subject" placeholder="<?php echo htmlspecialchars(__('subject'), ENT_QUOTES, 'UTF-8'); ?>" required></div>
            <div class="col-md-3">
                <select class="form-select" name="direction">
                    <option value="outbound"><?php echo htmlspecialchars(rateb_ui('outbound'), ENT_QUOTES, 'UTF-8'); ?></option>
                    <option value="inbound"><?php echo htmlspecialchars(rateb_ui('inbound'), ENT_QUOTES, 'UTF-8'); ?></option>
                </select>
            </div>
            <div class="col-md-3"><input class="form-control" type="datetime-local" name="called_at"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100" type="submit"><?php echo htmlspecialchars(__('create'), ENT_QUOTES, 'UTF-8'); ?></button></div>
        </div>
    </form>
    <?php endif; ?>
    <div class="table-responsive"><table class="table table-striped"><thead><tr><th><?php echo htmlspecialchars(__('subject'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('date'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead>
    <tbody>
    <?php foreach (($items ?? []) as $row): ?>
        <tr><td><?php echo htmlspecialchars((string) ($row['subject'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string) ($row['called_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars(rateb_ui((string) ($row['direction'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td></tr>
    <?php endforeach; ?>
    <?php if (($items ?? []) === []): ?><tr><td colspan="3" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
    </tbody></table></div>
</div>
