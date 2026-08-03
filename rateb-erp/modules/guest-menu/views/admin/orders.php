<?php
declare(strict_types=1);

use Rateb\App\GuestMenu\Support\GuestMenuView;

/** @var string $title */
/** @var list<array<string, mixed>> $orders */
/** @var string $settingsUrl */
/** @var string $csrf */
?>
<div class="gm-admin-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="h3 mb-0"><?php echo GuestMenuView::escape($title); ?></h1>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo GuestMenuView::escape($settingsUrl); ?>">
            <?php echo __('guest_menu_settings'); ?>
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <?php if ($orders === []) { ?>
            <p class="p-4 mb-0 text-muted"><?php echo __('guest_menu_orders_empty'); ?></p>
            <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th><?php echo __('guest_menu_order_no'); ?></th>
                            <th><?php echo __('guest_menu_order_guest'); ?></th>
                            <th><?php echo __('guest_menu_total'); ?></th>
                            <th><?php echo __('status'); ?></th>
                            <th><?php echo __('created_at'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order) {
                            $items = json_decode((string) ($order['items_json'] ?? '[]'), true);
                            $itemCount = is_array($items) ? count($items) : 0;
                            $status = (string) ($order['status'] ?? 'pending');
                            $oid = (int) ($order['id'] ?? 0);
                            $guest = trim((string) ($order['guest_name'] ?? ''));
                            $table = trim((string) ($order['table_label'] ?? ''));
                            $who = $guest !== '' ? $guest : ($table !== '' ? __('guest_menu_table_short', ['table' => $table]) : '—');
                            ?>
                        <tr>
                            <td><code><?php echo GuestMenuView::escape((string) ($order['order_no'] ?? '')); ?></code></td>
                            <td><?php echo GuestMenuView::escape($who); ?> <span class="text-muted small">(<?php echo (int) $itemCount; ?>)</span></td>
                            <td><?php echo GuestMenuView::escape(number_format((float) ($order['total_amount'] ?? 0), 2)); ?> <?php echo GuestMenuView::escape((string) ($order['currency'] ?? 'SAR')); ?></td>
                            <td><span class="badge bg-<?php echo $status === 'accepted' ? 'success' : ($status === 'cancelled' ? 'secondary' : 'warning'); ?>"><?php echo GuestMenuView::escape($status); ?></span></td>
                            <td class="text-muted small"><?php echo GuestMenuView::escape((string) ($order['created_at'] ?? '')); ?></td>
                            <td class="text-end">
                                <?php if ($status === 'pending' && $oid > 0) { ?>
                                <form method="post" action="<?php echo GuestMenuView::escape(rateb_app_url('guest-menu/orders/' . $oid . '/status')); ?>" class="d-inline">
                                    <input type="hidden" name="_csrf" value="<?php echo GuestMenuView::escape($csrf); ?>">
                                    <input type="hidden" name="status" value="accepted">
                                    <button type="submit" class="btn btn-success btn-sm"><?php echo __('guest_menu_accept_order'); ?></button>
                                </form>
                                <form method="post" action="<?php echo GuestMenuView::escape(rateb_app_url('guest-menu/orders/' . $oid . '/status')); ?>" class="d-inline">
                                    <input type="hidden" name="_csrf" value="<?php echo GuestMenuView::escape($csrf); ?>">
                                    <input type="hidden" name="status" value="cancelled">
                                    <button type="submit" class="btn btn-outline-secondary btn-sm"><?php echo __('cancel'); ?></button>
                                </form>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>
        </div>
    </div>
</div>
