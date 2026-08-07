<?php
declare(strict_types=1);

namespace Rateb\App\Marketplace\Support;

use Rateb\App\Core\View;
use Rateb\App\Marketplace\MarketplaceModule;

/** Renders views from modules/marketplace/views into the Admin ERP shell. */
final class MarketplaceView
{
    public static function render(string $view, array $data = [], ?string $layout = 'main'): void
    {
        $viewFile = MarketplaceModule::viewsPath() . '/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'Marketplace view not found: ' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8');
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

        $pageContent = $content();
        extract($data, EXTR_SKIP);
        include RATEB_VIEWS_PATH . '/layouts/main.php';
    }

    public static function escape($value): string
    {
        return View::escape($value);
    }
}
