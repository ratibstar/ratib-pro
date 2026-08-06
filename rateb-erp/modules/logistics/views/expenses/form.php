<?php
declare(strict_types=1);

\Rateb\App\Core\View::partial('crud-form', get_defined_vars());

$canPost = !empty($canPost);
$itemId = (int) (($item['id'] ?? 0));
if ($canPost && $itemId > 0) {
    ?>
    <div class="rateb-card mt-3">
        <div class="rateb-card-body d-flex justify-content-between align-items-center">
            <div>
                <strong><?php echo \Rateb\App\Core\View::escape(__('logistics_expense_post')); ?></strong>
                <div class="text-muted small"><?php echo \Rateb\App\Core\View::escape(__('logistics_expense_post_hint')); ?></div>
            </div>
            <form method="post" action="<?php echo rateb_app_url('logistics/expenses/' . $itemId . '/post'); ?>">
                <input type="hidden" name="_csrf" value="<?php echo \Rateb\App\Core\View::escape($csrf ?? ''); ?>">
                <button type="submit" class="btn btn-primary btn-sm"><?php echo \Rateb\App\Core\View::escape(__('logistics_expense_post')); ?></button>
            </form>
        </div>
    </div>
    <?php
}
