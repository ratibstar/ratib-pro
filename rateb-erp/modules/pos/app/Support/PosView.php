<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Support;

use Rateb\App\Core\View;
use Rateb\App\Pos\PosModule;

/** Renders views from modules/pos/views — keeps POS isolated from RATEB_VIEWS_PATH. */
final class PosView
{
    public static function render(string $view, array $data = [], ?string $layout = 'pos-pages-shell'): void
    {
        $viewFile = PosModule::viewsPath() . '/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'POS view not found: ' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8');
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

        $layoutFile = PosModule::viewsPath() . '/layouts/' . $layout . '.php';
        if ($layout === 'main') {
            $layoutFile = RATEB_VIEWS_PATH . '/layouts/main.php';
        }
        if (!is_file($layoutFile)) {
            echo $content();
            return;
        }

        $pageContent = $content();
        extract($data, EXTR_SKIP);
        if ($layout === 'main') {
            include RATEB_VIEWS_PATH . '/layouts/main.php';
            return;
        }
        include $layoutFile;
    }

    public static function partial(string $partial, array $data = []): void
    {
        $file = PosModule::viewsPath() . '/partials/' . str_replace('.', '/', $partial) . '.php';
        if (!is_file($file)) {
            return;
        }
        extract($data, EXTR_SKIP);
        include $file;
    }

    public static function escape($value): string
    {
        return View::escape($value);
    }
}
