<?php
declare(strict_types=1);
/** @var array<string,mixed> $item */
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-1"><?php echo htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="text-muted mb-3"><?php echo htmlspecialchars((string) ($item['work_order_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars((string) ($item['workflow_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
    <?php if (!empty($canWorkflow) && ($transitions ?? []) !== []): ?>
    <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eam/work-orders') . '/' . (int) $item['id'] . '/transition'), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 col-lg-6">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="expected_version" value="<?php echo (int) ($item['version'] ?? 1); ?>">
        <select name="to_status" class="form-select mb-2">
            <?php foreach (($transitions ?? []) as $tr): ?>
            <option value="<?php echo htmlspecialchars($tr, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($tr, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary btn-sm" type="submit"><?php echo htmlspecialchars(__('apply'), ENT_QUOTES, 'UTF-8'); ?></button>
    </form>
    <?php endif; ?>
</div>
