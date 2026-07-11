<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
    <?php if (!empty($canCreate)): ?>
    <form method="post" class="border rounded p-3 mb-3 col-lg-7">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="row g-2">
            <div class="col-md-5"><input class="form-control" name="subject" required placeholder="<?php echo htmlspecialchars(__('subject'), ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div class="col-md-4"><input class="form-control" type="datetime-local" name="due_at"></div>
            <div class="col-md-3"><button class="btn btn-primary w-100" type="submit"><?php echo htmlspecialchars(__('create'), ENT_QUOTES, 'UTF-8'); ?></button></div>
        </div>
    </form>
    <?php endif; ?>
    <div class="table-responsive"><table class="table table-striped"><thead><tr><th><?php echo htmlspecialchars(__('subject'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('due_date'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach (($items ?? []) as $row): ?>
        <tr>
            <td><?php echo htmlspecialchars((string) ($row['subject'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string) ($row['due_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td>
                <?php if (($row['status'] ?? '') === 'open'): ?>
                <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/tasks') . '/' . (int) $row['id'] . '/complete'), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <button class="btn btn-sm btn-outline-success" type="submit"><?php echo htmlspecialchars(__('done'), ENT_QUOTES, 'UTF-8'); ?></button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (($items ?? []) === []): ?><tr><td colspan="4" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
    </tbody></table></div>
</div>
