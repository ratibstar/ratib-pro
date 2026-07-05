<?php
declare(strict_types=1);

$opsSection(__('pos_nav_section'), [
    ['pos/dashboard', 'pos_dashboard', 'fa-cash-register', 'pos', 'pos.view'],
    ['pos', 'pos_register', 'fa-keyboard', 'pos', 'pos.register'],
    ['pos/terminals', 'pos_terminals', 'fa-desktop', 'pos', 'pos.view'],
    ['pos/shifts', 'pos_shifts', 'fa-clock', 'pos', 'pos.view'],
    ['pos/cash-drawers', 'pos_cash_drawers', 'fa-money-bill', 'pos', 'pos.view'],
    ['pos/orders', 'pos_orders', 'fa-receipt', 'pos', 'pos.orders.view'],
    ['pos/reports', 'pos_reports', 'fa-chart-bar', 'pos', 'pos.reports.view'],
    ['pos/returns', 'pos_returns', 'fa-undo', 'pos', 'pos.returns.manage'],
    ['pos/sync', 'pos_sync', 'fa-cloud-arrow-up', 'pos', 'pos.sync.manage'],
    ['pos/settings', 'pos_settings', 'fa-sliders', 'pos', 'pos.settings.manage'],
], 'fa-cash-register');
