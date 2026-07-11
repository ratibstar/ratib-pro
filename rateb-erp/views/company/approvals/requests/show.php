<?php
declare(strict_types=1);
/** @var array<string,mixed> $item */
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><?php echo htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="text-muted"><?php echo htmlspecialchars((string) ($item['request_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars((string) ($item['workflow_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-lg-8">
            <h2 class="h5"><?php echo htmlspecialchars(__('comments'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group mb-3">
                <?php foreach (($comments ?? []) as $c): ?>
                    <li class="list-group-item"><div><?php echo nl2br(htmlspecialchars((string) ($c['body'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></div><div class="small text-muted"><?php echo htmlspecialchars((string) ($c['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></li>
                <?php endforeach; ?>
            </ul>
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('approvals/requests') . '/' . (int) $item['id'] . '/comments'), ENT_QUOTES, 'UTF-8'); ?>" class="mb-3">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <textarea name="body" class="form-control mb-2" rows="2" required></textarea>
                <button class="btn btn-outline-primary btn-sm" type="submit"><?php echo htmlspecialchars(__('add_comment'), ENT_QUOTES, 'UTF-8'); ?></button>
            </form>
        </div>
        <div class="col-lg-4">
            <?php if (($transitions ?? []) !== []): ?>
            <div class="border rounded p-3 mb-3">
                <h2 class="h6"><?php echo htmlspecialchars(__('workflow'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('approvals/requests') . '/' . (int) $item['id'] . '/transition'), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="expected_version" value="<?php echo (int) ($item['version'] ?? 1); ?>">
                    <select name="to_status" class="form-select mb-2">
                        <?php foreach (($transitions ?? []) as $tr): ?>
                        <?php
                        $allowed = true;
                        if ($tr === 'approved' && empty($canApprove)) { $allowed = false; }
                        if ($tr === 'rejected' && empty($canReject)) { $allowed = false; }
                        if (in_array($tr, ['submitted', 'pending'], true) && empty($canSubmit) && empty($canApprove)) { $allowed = false; }
                        if (!$allowed) { continue; }
                        ?>
                        <option value="<?php echo htmlspecialchars($tr, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($tr, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="reason" class="form-control mb-2" placeholder="<?php echo htmlspecialchars(__('reason'), ENT_QUOTES, 'UTF-8'); ?>">
                    <button class="btn btn-primary btn-sm" type="submit"><?php echo htmlspecialchars(__('apply'), ENT_QUOTES, 'UTF-8'); ?></button>
                </form>
            </div>
            <?php endif; ?>
            <?php if (!empty($canDelegate)): ?>
            <div class="border rounded p-3 mb-3">
                <h2 class="h6"><?php echo htmlspecialchars(__('approval_delegate'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('approvals/requests') . '/' . (int) $item['id'] . '/delegate'), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="number" name="to_user_id" class="form-control mb-2" placeholder="User ID" required>
                    <button class="btn btn-outline-primary btn-sm" type="submit"><?php echo htmlspecialchars(__('approval_delegate'), ENT_QUOTES, 'UTF-8'); ?></button>
                </form>
            </div>
            <?php endif; ?>
            <h2 class="h6"><?php echo htmlspecialchars(__('approval_history'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group list-group-flush border rounded">
                <?php foreach (($timeline ?? []) as $ev): ?>
                    <li class="list-group-item"><div class="fw-semibold"><?php echo htmlspecialchars((string) ($ev['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div><div class="small text-muted"><?php echo htmlspecialchars((string) ($ev['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
