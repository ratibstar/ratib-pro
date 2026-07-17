<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Controllers;

use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Pos\Services\PosContextService;

final class PosSettingsController extends PosBaseController
{
    public function index(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/settings');

        $context = (new PosContextService())->snapshot();
        $can = static function (string $slug): bool {
            if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
                return true;
            }

            return function_exists('rateb_can') && rateb_can($slug);
        };

        $links = [];
        if ($can('pos.register')) {
            $links[] = [
                'url' => rateb_app_url('pos/register'),
                'title' => __('pos_register'),
                'hint' => __('pos_settings_link_register_hint'),
            ];
        }
        if ($can('pos.orders.view')) {
            $links[] = [
                'url' => rateb_app_url('pos/orders'),
                'title' => __('pos_orders'),
                'hint' => __('pos_settings_link_orders_hint'),
            ];
        }
        if ($can('pos.reports.view')) {
            $links[] = [
                'url' => rateb_app_url('pos/reports'),
                'title' => __('pos_reports'),
                'hint' => __('pos_settings_link_reports_hint'),
            ];
        }
        if ($can('pos.view')) {
            $links[] = [
                'url' => rateb_app_url('pos/shifts'),
                'title' => __('pos_shifts'),
                'hint' => __('pos_settings_link_shifts_hint'),
            ];
            $links[] = [
                'url' => rateb_app_url('pos/terminals'),
                'title' => __('pos_terminals'),
                'hint' => __('pos_settings_link_terminals_hint'),
            ];
            $links[] = [
                'url' => rateb_app_url('pos/cash-drawers'),
                'title' => __('pos_cash_drawers'),
                'hint' => __('pos_settings_link_drawers_hint'),
            ];
        }
        if ($can('pos.sync.manage')) {
            $links[] = [
                'url' => rateb_app_url('pos/sync'),
                'title' => __('pos_sync'),
                'hint' => __('pos_settings_link_sync_hint'),
            ];
        }
        if ($can('pos.devices.manage') || $can('pos.settings.manage')) {
            $links[] = [
                'url' => rateb_app_url('pos/devices'),
                'title' => __('pos_devices'),
                'hint' => __('pos_settings_link_devices_hint'),
            ];
        }

        $this->posView('settings/index', [
            'title' => __('pos_settings'),
            'csrf' => Csrf::token(),
            'context' => $context,
            'links' => $links,
            'flashSuccess' => SessionManager::flash('success'),
            'flashError' => SessionManager::flash('error'),
            'locale' => rateb_locale(),
        ], 'pos-pages-shell');
    }
}
