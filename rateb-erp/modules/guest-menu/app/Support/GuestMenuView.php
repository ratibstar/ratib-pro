<?php
declare(strict_types=1);

namespace Rateb\App\GuestMenu\Support;

use Rateb\App\GuestMenu\GuestMenuModule;

/** Renders views from modules/guest-menu/views. */
final class GuestMenuView
{
    public static function render(string $view, array $data = [], ?string $layout = 'main'): void
    {
        $viewFile = GuestMenuModule::viewsPath() . '/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'Guest menu view not found: ' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8');

            return;
        }

        $content = static function () use ($viewFile, $data): string {
            extract($data, EXTR_SKIP);
            ob_start();
            try {
                include $viewFile;

                return (string) ob_get_clean();
            } catch (\Throwable $e) {
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                throw $e;
            }
        };

        if ($layout === null) {
            echo $content();

            return;
        }

        if ($layout === 'main') {
            $pageContent = $content();
            $asset = function_exists('rateb_asset')
                ? rateb_asset('assets/css/guest-menu-admin.css')
                : '/assets/css/guest-menu-admin.css';
            $pageContent = '<link rel="stylesheet" href="'
                . htmlspecialchars($asset, ENT_QUOTES, 'UTF-8') . '">' . $pageContent;
            extract($data, EXTR_SKIP);
            include RATEB_VIEWS_PATH . '/layouts/main.php';

            return;
        }

        if ($layout === 'public') {
            extract(array_merge($data, ['pageContent' => $content()]), EXTR_SKIP);
            include GuestMenuModule::viewsPath() . '/layouts/public.php';

            return;
        }

        echo $content();
    }

    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
