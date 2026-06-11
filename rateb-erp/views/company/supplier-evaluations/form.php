<?php
/** @var array<string, mixed>|null $item */
/** @var array<int, array<string, mixed>> $suppliers */
$isEdit = !empty($item);
$action = $isEdit ? rateb_url($routePrefix . '/' . (int) $item['id']) : rateb_url($routePrefix);
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo $action; ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('suppliers'); ?></label>
                    <select class="form-select" name="supplier_id" required>
                        <option value="">—</option>
                        <?php foreach ($suppliers as $supplier) { ?>
                        <option value="<?php echo (int) $supplier['id']; ?>"<?php echo (int) ($item['supplier_id'] ?? 0) === (int) $supplier['id'] ? ' selected' : ''; ?>>
                            <?php echo Rateb\App\Core\View::escape($supplier['name']); ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('evaluation_date'); ?></label>
                    <input class="form-control" type="date" name="evaluation_date" value="<?php echo Rateb\App\Core\View::escape($item['evaluation_date'] ?? date('Y-m-d')); ?>" required>
                </div>
                <?php
                $scoreFields = ['quality_score', 'delivery_score', 'price_score', 'service_score'];
                foreach ($scoreFields as $sf) {
                    ?>
                <div class="col-md-3">
                    <label class="form-label"><?php echo __( $sf); ?> (0-10)</label>
                    <input class="form-control" type="number" name="<?php echo $sf; ?>" min="0" max="10" value="<?php echo Rateb\App\Core\View::escape((string) ($item[$sf] ?? 0)); ?>" required>
                </div>
                <?php } ?>
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('status'); ?></label>
                    <select class="form-select" name="status">
                        <?php foreach (['draft', 'published', 'archived'] as $st) { ?>
                        <option value="<?php echo $st; ?>"<?php echo ($item['status'] ?? 'published') === $st ? ' selected' : ''; ?>><?php echo __( $st); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label"><?php echo __('comments'); ?></label>
                    <textarea class="form-control" name="comments" rows="4"><?php echo Rateb\App\Core\View::escape($item['comments'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
