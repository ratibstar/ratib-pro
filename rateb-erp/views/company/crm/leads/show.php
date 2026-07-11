<?php
declare(strict_types=1);
/** @var array<string,mixed> $item */
/** @var list<array<string,mixed>> $timeline */
/** @var list<string> $transitions */
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><?php echo htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="text-muted"><?php echo htmlspecialchars((string) ($item['lead_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · <span class="badge text-bg-secondary"><?php echo htmlspecialchars((string) ($item['workflow_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></div>
        </div>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/leads') . '/' . (int) $item['id'] . '/edit'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('edit'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="border rounded p-3 mb-3">
                <div><strong><?php echo htmlspecialchars(__('contact'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars((string) ($item['contact_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div><strong><?php echo htmlspecialchars(__('email'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars((string) ($item['email'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div><strong><?php echo htmlspecialchars(__('phone'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars((string) ($item['phone'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="mt-2"><?php echo nl2br(htmlspecialchars((string) ($item['notes'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></div>
            </div>
            <?php if (!empty($canWorkflow) && ($transitions ?? []) !== []): ?>
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/leads') . '/' . (int) $item['id'] . '/transition'), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 mb-3">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <label class="form-label"><?php echo htmlspecialchars(__('crm_workflow_transition'), ENT_QUOTES, 'UTF-8'); ?></label>
                <div class="input-group">
                    <select class="form-select" name="to_status" required>
                        <?php foreach ($transitions as $t): ?><option value="<?php echo htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary" type="submit"><?php echo htmlspecialchars(__('apply'), ENT_QUOTES, 'UTF-8'); ?></button>
                </div>
            </form>
            <?php endif; ?>
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/leads') . '/' . (int) $item['id'] . '/notes'), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <label class="form-label"><?php echo htmlspecialchars(__('crm_add_note'), ENT_QUOTES, 'UTF-8'); ?></label>
                <textarea class="form-control mb-2" name="body" rows="3" required></textarea>
                <button class="btn btn-outline-primary btn-sm" type="submit"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
            </form>
        </div>
        <div class="col-lg-5">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_timeline'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group">
                <?php foreach (($timeline ?? []) as $ev): ?>
                <li class="list-group-item">
                    <div class="fw-semibold"><?php echo htmlspecialchars((string) ($ev['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php if (!empty($ev['body'])): ?><div class="small"><?php echo htmlspecialchars((string) $ev['body'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                    <div class="small text-muted"><?php echo htmlspecialchars((string) ($ev['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                </li>
                <?php endforeach; ?>
                <?php if (($timeline ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
