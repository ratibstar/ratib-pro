<?php
declare(strict_types=1);

/** @var array<string, mixed> $context */
/** @var array<string, mixed> $registerConfig */
$context = $context ?? [];
$terminal = $context['terminal'] ?? null;
$shift = $context['shift'] ?? null;
$branch = $context['branch'] ?? null;
$warehouse = $context['warehouse'] ?? null;

$termLabel = $terminal ? trim((string) ($terminal['code'] ?? '') . ' — ' . (string) ($terminal['name'] ?? '')) : '—';
$shiftLabel = $shift ? (string) ($shift['shift_no'] ?? '—') : '—';
$branchLabel = $branch ? (string) ($branch['name'] ?? '—') : '—';
$warehouseLabel = $warehouse ? (string) ($warehouse['name'] ?? '—') : '—';
?>
<div class="rateb-pos-context-bar" role="region" aria-label="<?php echo __('pos_session'); ?>">
    <span class="rateb-pos-context-item"><?php echo __('pos_context_terminal'); ?>: <?php echo \Rateb\App\Pos\Support\PosView::escape($termLabel); ?></span>
    <span class="rateb-pos-context-item"><?php echo __('pos_context_shift'); ?>: <?php echo \Rateb\App\Pos\Support\PosView::escape($shiftLabel); ?></span>
    <span class="rateb-pos-context-item"><?php echo __('pos_context_branch'); ?>: <?php echo \Rateb\App\Pos\Support\PosView::escape($branchLabel); ?></span>
    <span class="rateb-pos-context-item"><?php echo __('pos_context_warehouse'); ?>: <?php echo \Rateb\App\Pos\Support\PosView::escape($warehouseLabel); ?></span>
</div>
