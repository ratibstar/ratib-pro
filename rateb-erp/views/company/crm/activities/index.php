<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="mb-3 d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/calls')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('crm_calls'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/meetings')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('crm_meetings'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/tasks')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('crm_tasks'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <?php if (!empty($canCreate)): ?>
    <form method="post" class="border rounded p-3 mb-3 col-lg-8">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="row g-2">
            <div class="col-md-4"><input class="form-control" name="subject" placeholder="<?php echo htmlspecialchars(__('subject'), ENT_QUOTES, 'UTF-8'); ?>" required></div>
            <div class="col-md-3">
                <select class="form-select" name="activity_type">
                    <option value="note">note</option>
                    <option value="call">call</option>
                    <option value="meeting">meeting</option>
                    <option value="task">task</option>
                    <option value="follow_up">follow_up</option>
                    <option value="other">other</option>
                </select>
            </div>
            <div class="col-md-3"><input class="form-control" type="datetime-local" name="activity_at"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100" type="submit"><?php echo htmlspecialchars(__('create'), ENT_QUOTES, 'UTF-8'); ?></button></div>
            <div class="col-12"><textarea class="form-control" name="body" rows="2" placeholder="<?php echo htmlspecialchars(__('notes'), ENT_QUOTES, 'UTF-8'); ?>"></textarea></div>
        </div>
    </form>
    <?php endif; ?>
    <div class="table-responsive"><table class="table table-striped"><thead><tr><th><?php echo htmlspecialchars(__('subject'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('type'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('date'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead>
    <tbody>
    <?php foreach (($items ?? []) as $row): ?>
        <tr>
            <td><?php echo htmlspecialchars((string) ($row['subject'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string) ($row['activity_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string) ($row['activity_at'] ?? $row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (($items ?? []) === []): ?><tr><td colspan="3" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
    </tbody></table></div>
</div>
