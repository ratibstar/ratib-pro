<?php
declare(strict_types=1);

/** @var array<string, list<array{value: int, label: string}>> $lookups */
$terminals = $lookups['pos_terminals'] ?? [];
?>
<link href="<?php echo rateb_pos_asset('css/pos-module.css'); ?>" rel="stylesheet">
<div class="rateb-pos-page">
    <div class="rateb-card">
        <div class="rateb-card-header"><?php echo \Rateb\App\Pos\Support\PosView::escape($title ?? ''); ?></div>
        <div class="rateb-card-body">
            <form method="post" action="<?php echo rateb_app_url('pos/shifts/open'); ?>">
                <input type="hidden" name="_csrf" value="<?php echo \Rateb\App\Pos\Support\PosView::escape($csrf ?? ''); ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="pos_terminal_id"><?php echo __('pos_terminals'); ?></label>
                        <select class="form-select" id="pos_terminal_id" name="terminal_id" required>
                            <option value=""><?php echo __('select'); ?></option>
                            <?php foreach ($terminals as $opt) { ?>
                            <option value="<?php echo (int) $opt['value']; ?>"><?php echo \Rateb\App\Pos\Support\PosView::escape($opt['label']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="pos_opening_float"><?php echo __('pos_opening_float'); ?></label>
                        <input class="form-control" type="number" step="0.01" min="0" id="pos_opening_float" name="opening_float" value="0">
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><?php echo __('pos_shift_open'); ?></button>
                    <a href="<?php echo rateb_app_url('pos/shifts'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
                </div>
            </form>
        </div>
    </div>
</div>
