<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="post" action="<?php echo htmlspecialchars((string) ($action ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="col-lg-6">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="mb-3"><label class="form-label"><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input class="form-control" name="name" required></div>
        <div class="mb-3"><label class="form-label"><?php echo htmlspecialchars(__('amount'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input class="form-control" type="number" step="0.01" name="amount" value="0"></div>
        <div class="mb-3"><label class="form-label"><?php echo htmlspecialchars(__('crm_pipeline'), ENT_QUOTES, 'UTF-8'); ?></label>
            <select class="form-select" name="pipeline_id">
                <option value=""><?php echo htmlspecialchars(__('default'), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php foreach (($pipelines ?? []) as $p): ?>
                <option value="<?php echo (int) $p['id']; ?>"><?php echo htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select></div>
        <button class="btn btn-primary" type="submit"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
    </form>
</div>
